<?php
// scripts/migrate_schema.php - Migration vers le schema database/schema.sql

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$schemaPath = ROOT_PATH . '/database/schema.sql';
if (!file_exists($schemaPath)) {
    fwrite(STDERR, "Schema introuvable: $schemaPath\n");
    exit(1);
}

$schemaSql = file_get_contents($schemaPath);
if ($schemaSql === false) {
    fwrite(STDERR, "Impossible de lire le schema.\n");
    exit(1);
}

function quoteIdentifier($name) {
    return '"' . str_replace('"', '""', $name) . '"';
}

function splitTopLevelComma($body) {
    $parts = [];
    $current = '';
    $depth = 0;
    $len = strlen($body);

    for ($i = 0; $i < $len; $i++) {
        $ch = $body[$i];
        if ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            if ($depth > 0) {
                $depth--;
            }
        } elseif ($ch === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';
            continue;
        }
        $current .= $ch;
    }

    if (trim($current) !== '') {
        $parts[] = trim($current);
    }

    return $parts;
}

function parseCreateStatements($schemaSql) {
    $statements = [];
    if (preg_match_all(
        '/CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?([A-Za-z0-9_]+)\\s*\\((?:[^;]*?)\\)\\s*;/si',
        $schemaSql,
        $matches,
        PREG_SET_ORDER
    )) {
        foreach ($matches as $match) {
            $table = $match[1];
            $statements[$table] = trim($match[0]);
        }
    }
    return $statements;
}

function parseColumnsFromCreate($createSql) {
    $open = strpos($createSql, '(');
    $close = strrpos($createSql, ')');
    if ($open === false || $close === false || $close <= $open) {
        return [];
    }
    $body = substr($createSql, $open + 1, $close - $open - 1);
    $parts = splitTopLevelComma($body);

    $columns = [];
    foreach ($parts as $part) {
        $line = trim($part);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^(CONSTRAINT|PRIMARY\\s+KEY|FOREIGN\\s+KEY|UNIQUE|CHECK)\\b/i', $line)) {
            continue;
        }

        $tokens = preg_split('/\\s+/', $line);
        if (!$tokens || $tokens[0] === '') {
            continue;
        }
        $colName = trim($tokens[0], "\"`[]");
        $type = '';
        if (isset($tokens[1]) && !preg_match('/^(PRIMARY|NOT|UNIQUE|CHECK|DEFAULT|REFERENCES|CONSTRAINT)$/i', $tokens[1])) {
            $type = strtoupper($tokens[1]);
        }

        $notNull = stripos($line, 'NOT NULL') !== false;
        $pk = stripos($line, 'PRIMARY KEY') !== false;
        $default = null;
        if (preg_match('/\\bDEFAULT\\b/i', $line)) {
            $defaultPart = preg_split('/\\bDEFAULT\\b/i', $line, 2);
            if (isset($defaultPart[1])) {
                $candidate = trim($defaultPart[1]);
                $candidate = preg_split('/\\bNOT\\s+NULL\\b|\\bPRIMARY\\s+KEY\\b|\\bUNIQUE\\b|\\bCHECK\\b|\\bREFERENCES\\b/i', $candidate, 2)[0];
                $default = trim($candidate);
            }
        }

        $columns[$colName] = [
            'type' => $type,
            'notnull' => $notNull,
            'default' => $default,
            'pk' => $pk,
        ];
    }

    return $columns;
}

function normalizeType($type) {
    $type = strtoupper(trim($type));
    $type = preg_replace('/\\s+/', ' ', $type);
    return $type;
}

function normalizeDefault($value) {
    if ($value === null) {
        return null;
    }
    $value = trim($value);
    $value = preg_replace('/\\s+/', ' ', $value);
    return $value;
}

$targetTables = parseCreateStatements($schemaSql);
if (empty($targetTables)) {
    fwrite(STDERR, "Aucune table trouvee dans le schema.\n");
    exit(1);
}

$db = getDatabase();
$db->exec('PRAGMA foreign_keys = OFF');

$existingTables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")
    ->fetchAll(PDO::FETCH_COLUMN);
$existingTables = array_flip($existingTables);

$db->beginTransaction();
try {
    foreach ($targetTables as $table => $createSql) {
        $targetColumns = parseColumnsFromCreate($createSql);
        if (!isset($existingTables[$table])) {
            echo "Creation de la table $table\n";
            $db->exec($createSql);
            continue;
        }

        $stmt = $db->query("PRAGMA table_info(" . quoteIdentifier($table) . ")");
        $currentInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $currentColumns = [];
        foreach ($currentInfo as $col) {
            $currentColumns[$col['name']] = [
                'type' => strtoupper((string) $col['type']),
                'notnull' => (bool) $col['notnull'],
                'default' => $col['dflt_value'],
                'pk' => (bool) $col['pk'],
            ];
        }

        $needsRebuild = false;
        $needsAlter = [];

        foreach ($currentColumns as $name => $meta) {
            if (!isset($targetColumns[$name])) {
                $needsRebuild = true;
                break;
            }
        }

        if (!$needsRebuild) {
            foreach ($targetColumns as $name => $meta) {
                if (!isset($currentColumns[$name])) {
                    if ($meta['pk'] || ($meta['notnull'] && $meta['default'] === null)) {
                        $needsRebuild = true;
                        break;
                    }
                    $needsAlter[$name] = $meta;
                    continue;
                }

                $current = $currentColumns[$name];
                if (normalizeType($current['type']) !== normalizeType($meta['type']) ||
                    (bool) $current['notnull'] !== (bool) $meta['notnull'] ||
                    (bool) $current['pk'] !== (bool) $meta['pk'] ||
                    normalizeDefault($current['default']) !== normalizeDefault($meta['default'])) {
                    $needsRebuild = true;
                    break;
                }
            }
        }

        if ($needsRebuild) {
            echo "Reconstruction de la table $table\n";
            $missingRequired = [];
            foreach ($targetColumns as $name => $meta) {
                if (!isset($currentColumns[$name]) && ($meta['pk'] || ($meta['notnull'] && $meta['default'] === null))) {
                    $missingRequired[] = $name;
                }
            }
            if (!empty($missingRequired)) {
                throw new RuntimeException(
                    "Colonnes NOT NULL sans DEFAULT manquantes dans $table: " . implode(', ', $missingRequired)
                );
            }

            $tmpTable = "__migrate_" . $table;
            $pattern = '/(CREATE\\s+TABLE\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?)([`"\\[]?' . preg_quote($table, '/') . '[`"\\]]?)/i';
            $tmpCreateSql = preg_replace($pattern, '$1' . $tmpTable, $createSql, 1);
            $db->exec($tmpCreateSql);

            $common = array_values(array_intersect(array_keys($targetColumns), array_keys($currentColumns)));
            if (!empty($common)) {
                $colsList = implode(', ', array_map('quoteIdentifier', $common));
                $db->exec(
                    'INSERT INTO ' . quoteIdentifier($tmpTable) . " ($colsList) SELECT $colsList FROM " . quoteIdentifier($table)
                );
            }

            $db->exec('DROP TABLE ' . quoteIdentifier($table));
            $db->exec('ALTER TABLE ' . quoteIdentifier($tmpTable) . ' RENAME TO ' . quoteIdentifier($table));
            continue;
        }

        foreach ($needsAlter as $name => $meta) {
            $definition = $name;
            if ($meta['type'] !== '') {
                $definition .= ' ' . $meta['type'];
            }
            if ($meta['default'] !== null) {
                $definition .= ' DEFAULT ' . $meta['default'];
            }
            if ($meta['notnull']) {
                $definition .= ' NOT NULL';
            }
            echo "Ajout de colonne $table.$name\n";
            $db->exec('ALTER TABLE ' . quoteIdentifier($table) . ' ADD COLUMN ' . $definition);
        }
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    $db->exec('PRAGMA foreign_keys = ON');
    fwrite(STDERR, "Erreur migration: " . $e->getMessage() . "\n");
    exit(1);
}

$db->exec('PRAGMA foreign_keys = ON');
echo "Migration terminee.\n";
