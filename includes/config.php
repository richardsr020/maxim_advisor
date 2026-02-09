<?php
// config.php - Configuration globale
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

define('APP_NAME', 'Maxim Advisor');
define('APP_VERSION', '1.0.0');
define('CURRENCY', 'FC');
define('DEFAULT_INCOME', 120000);

// Chemins
define('DB_PATH', ROOT_PATH . '/database/maxim_advisor.sqlite');
define('EXPORTS_PATH', ROOT_PATH . '/exports/json');

// Constantes financières
define('TITHING_PERCENT', 10);
define('MAIN_SAVING_PERCENT', 20);
define('EXTRA_SAVING_PERCENT', 50);

// Configuration IA (Gemini)
$AI_PROVIDERS = [
    'gemini' => [
        'api_key' => 'AIzaSyAsCbbZz24BEvYYML-VZTlAgbjvmjk-XHo',
        'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
    ],
    'google_oauth' => [
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => '',
    ],
];

// Pourcentages de budget par défaut
$DEFAULT_BUDGET_PERCENTAGES = [
    1 => 40, // Nourriture
    2 => 20, // Transport
    3 => 10, // Communication
    4 => 10, // Ménage
    5 => 20  // Imprévus
];

// Seuils d'alerte
define('WARNING_THRESHOLD', 75);
define('CRITICAL_THRESHOLD', 90);
define('BLOCK_THRESHOLD', 100);

// Fonction de formatage de devise
function formatCurrency($amount) {
    return number_format($amount, 0, ',', ' ') . ' ' . CURRENCY;
}

// Fonction pour la date relative
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'à l\'instant';
    if ($diff < 3600) return floor($diff/60) . ' min';
    if ($diff < 86400) return floor($diff/3600) . ' h';
    if ($diff < 2592000) return floor($diff/86400) . ' j';
    return date('d/m/Y', $time);
}
?>
