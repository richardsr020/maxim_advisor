<?php
// database.php - Connexion et fonctions de base de données
require_once __DIR__ . '/config.php';

/**
 * Obtient la connexion à la base de données
 */
function getDatabase() {
    static $db = null;
    
    if ($db === null) {
        try {
            $db = new PDO('sqlite:' . DB_PATH);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->exec('PRAGMA foreign_keys = ON');
        } catch (PDOException $e) {
            die('Erreur de connexion à la base de données: ' . $e->getMessage());
        }
    }
    
    return $db;
}

/**
 * Initialise la base de données si nécessaire
 */
function initDatabase() {
    $db = getDatabase();
    
    // Vérifier si les tables existent
    $tables = ['users', 'parameters', 'budget_categories', 'financial_periods'];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$stmt->fetch()) {
            // Tables manquantes, exécuter le schéma
            $schema = file_get_contents(ROOT_PATH . '/database/schema.sql');
            $db->exec($schema);
            break;
        }
    }

    // Ajouter les tables récentes si la base existe déjà
    $db->exec("CREATE TABLE IF NOT EXISTS ai_notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        period_id INTEGER,
        timeframe TEXT NOT NULL CHECK (timeframe IN ('week', 'month', 'year')),
        range_start DATE NOT NULL,
        range_end DATE NOT NULL,
        export_path TEXT NOT NULL,
        analysis_html TEXT NOT NULL,
        raw_response TEXT,
        is_read BOOLEAN DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES financial_periods(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS ai_chat_threads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        period_id INTEGER,
        title TEXT NOT NULL,
        summary_text TEXT,
        summary_updated_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (period_id) REFERENCES financial_periods(id) ON DELETE SET NULL
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS ai_chat_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        thread_id INTEGER NOT NULL,
        role TEXT NOT NULL CHECK (role IN ('user', 'assistant')),
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (thread_id) REFERENCES ai_chat_threads(id) ON DELETE CASCADE
    )");

    ensureColumn($db, 'ai_chat_threads', 'summary_text', 'TEXT');
    ensureColumn($db, 'ai_chat_threads', 'summary_updated_at', 'DATETIME');

    // Colonnes paramètres pour compatibilité
    ensureColumn($db, 'parameters', 'version', 'INTEGER DEFAULT 1');
    ensureColumn($db, 'parameters', 'default_income', 'INTEGER DEFAULT 120000');
    ensureColumn($db, 'parameters', 'currency', "TEXT DEFAULT 'FC'");
    ensureColumn($db, 'parameters', 'tithing_percent', 'INTEGER DEFAULT 10');
    ensureColumn($db, 'parameters', 'main_saving_percent', 'INTEGER DEFAULT 20');
    ensureColumn($db, 'parameters', 'extra_saving_percent', 'INTEGER DEFAULT 50');
    ensureColumn($db, 'parameters', 'is_active', 'BOOLEAN DEFAULT 1');
}

function ensureColumn(PDO $db, $table, $column, $definition) {
    $stmt = $db->query("PRAGMA table_info($table)");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array($column, $columns, true)) {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }
}

/**
 * Exécute une requête et retourne tous les résultats
 */
function queryAll($sql, $params = []) {
    $db = getDatabase();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Exécute une requête et retourne un seul résultat
 */
function queryOne($sql, $params = []) {
    $db = getDatabase();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Exécute une requête d'insertion/mise à jour
 */
function executeQuery($sql, $params = []) {
    $db = getDatabase();
    $stmt = $db->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Retourne le dernier ID inséré
 */
function lastInsertId() {
    $db = getDatabase();
    return $db->lastInsertId();
}
?>
