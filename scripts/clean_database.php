<?php
// scripts/clean_database.php - Nettoyage des donnees (garde uniquement les tables essentielles)

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

$keepTables = [
    'users',
    'parameters',
    'budget_categories',
    'budget_percentages',
];

function quoteIdentifier($name) {
    return '"' . str_replace('"', '""', $name) . '"';
}

$db = getDatabase();

$db->exec('PRAGMA foreign_keys = OFF');
$db->beginTransaction();

try {
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        if ($table === 'sqlite_sequence') {
            continue;
        }
        if (in_array($table, $keepTables, true)) {
            continue;
        }

        $quotedTable = quoteIdentifier($table);
        $db->exec("DELETE FROM $quotedTable");
        $db->exec("DELETE FROM sqlite_sequence WHERE name = " . $db->quote($table));
    }

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    $db->exec('PRAGMA foreign_keys = ON');
    fwrite(STDERR, "Erreur: " . $e->getMessage() . "\n");
    exit(1);
}

$db->exec('PRAGMA foreign_keys = ON');

echo "Nettoyage termine.\n";
