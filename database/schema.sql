-- database/schema.sql
PRAGMA foreign_keys = ON;

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    is_admin BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des paramètres (versionnés)
CREATE TABLE IF NOT EXISTS parameters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version INTEGER NOT NULL DEFAULT 1,
    default_income INTEGER NOT NULL DEFAULT 120000,
    currency TEXT NOT NULL DEFAULT 'FC',
    tithing_percent INTEGER NOT NULL DEFAULT 10,
    main_saving_percent INTEGER NOT NULL DEFAULT 20,
    extra_saving_percent INTEGER NOT NULL DEFAULT 50,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT 1
);

-- Table des catégories de budget
CREATE TABLE IF NOT EXISTS budget_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    color TEXT NOT NULL,
    icon TEXT NOT NULL,
    is_unexpected BOOLEAN DEFAULT 0,
    position INTEGER NOT NULL
);

-- Table des pourcentages de budget
CREATE TABLE IF NOT EXISTS budget_percentages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parameters_version INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    percentage INTEGER NOT NULL,
    FOREIGN KEY (parameters_version) REFERENCES parameters(id),
    FOREIGN KEY (category_id) REFERENCES budget_categories(id),
    UNIQUE(parameters_version, category_id)
);

-- Table des périodes financières
CREATE TABLE IF NOT EXISTS financial_periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    parameters_version INTEGER NOT NULL,
    initial_income INTEGER NOT NULL,
    tithing_amount INTEGER NOT NULL DEFAULT 0,
    saving_amount INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table des budgets par période
CREATE TABLE IF NOT EXISTS period_budgets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    allocated_amount INTEGER NOT NULL,
    spent_amount INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (period_id) REFERENCES financial_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id)
);

-- Table des transactions
CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK (type IN ('income_main', 'income_extra', 'expense')),
    category_id INTEGER,
    amount INTEGER NOT NULL,
    description TEXT,
    comment TEXT,
    date DATE NOT NULL,
    tithing_paid INTEGER DEFAULT 0,
    saving_paid INTEGER DEFAULT 0,
    balance_after INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES financial_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES budget_categories(id)
);

-- Table des alertes
CREATE TABLE IF NOT EXISTS alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id INTEGER NOT NULL,
    type TEXT NOT NULL CHECK (type IN ('threshold', 'habit', 'system')),
    level TEXT NOT NULL CHECK (level IN ('warning', 'danger', 'info')),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES financial_periods(id) ON DELETE CASCADE
);

-- Table des dîmes reportées
CREATE TABLE IF NOT EXISTS deferred_tithing (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    amount INTEGER NOT NULL,
    source_period_id INTEGER NOT NULL,
    target_period_id INTEGER,
    is_paid BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_period_id) REFERENCES financial_periods(id),
    FOREIGN KEY (target_period_id) REFERENCES financial_periods(id)
);

-- Table d'historique d'export
CREATE TABLE IF NOT EXISTS export_history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id INTEGER,
    export_type TEXT NOT NULL CHECK (export_type IN ('period', 'year')),
    file_path TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES financial_periods(id) ON DELETE SET NULL
);

-- Table des notifications IA
CREATE TABLE IF NOT EXISTS ai_notifications (
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
);

-- Table des discussions IA (threads)
CREATE TABLE IF NOT EXISTS ai_chat_threads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id INTEGER,
    title TEXT NOT NULL,
    summary_text TEXT,
    summary_updated_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES financial_periods(id) ON DELETE SET NULL
);

-- Table des messages IA
CREATE TABLE IF NOT EXISTS ai_chat_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    thread_id INTEGER NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('user', 'assistant')),
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES ai_chat_threads(id) ON DELETE CASCADE
);

-- Insertion des catégories par défaut
INSERT INTO budget_categories (name, color, icon, is_unexpected, position) VALUES
('Nourriture', '#4CAF50', '🍎', 0, 1),
('Transport', '#2196F3', '🚗', 0, 2),
('Communication', '#9C27B0', '📱', 0, 3),
('Ménage', '#FF9800', '🏠', 0, 4),
('Imprévus', '#F44336', '⚠️', 1, 5);
