<?php
// index.php - Routeur principal
session_start();
define('ROOT_PATH', __DIR__);

// Auto-chargement des includes
spl_autoload_register(function ($class) {
    $file = ROOT_PATH . '/includes/' . strtolower($class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Vérifier si installation nécessaire
if (!file_exists(ROOT_PATH . '/database/maxim_advisor.sqlite') && basename($_SERVER['PHP_SELF']) !== 'install.php') {
    header('Location: install.php');
    exit;
}

// Initialiser la base de données
require_once ROOT_PATH . '/includes/database.php';
initDatabase();

// Charger les dépendances globales utilisées par les templates
require_once ROOT_PATH . '/includes/period.php';
require_once ROOT_PATH . '/includes/budgets.php';

// Gérer l'authentification
if (!isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), ['login.php', 'install.php'])) {
    header('Location: login.php');
    exit;
}

// Routeur
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$allowed_pages = [
    'dashboard' => 'pages/dashboard.php',
    'stats' => 'pages/stats.php',
    'chat' => 'pages/chat.php',
    'settings' => 'pages/settings.php',
    'expenses' => 'pages/expenses.php',
    'history' => 'pages/history.php',
    'export' => 'pages/export.php',
    'alerts' => 'pages/alerts.php'
];

// Inclure header
require_once ROOT_PATH . '/templates/header.php';

// Inclure topbar
require_once ROOT_PATH . '/templates/topbar.php';

// Contenu principal
echo '<main class="main-content">';
if (isset($allowed_pages[$page])) {
    require_once ROOT_PATH . '/' . $allowed_pages[$page];
} else {
    require_once ROOT_PATH . '/pages/dashboard.php';
}
echo '</main>';

// Inclure footer
require_once ROOT_PATH . '/templates/footer.php';
?>
