<?php
// export.php - Fonctions d'export
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/parameters.php';
require_once __DIR__ . '/budgets.php';
require_once __DIR__ . '/habits.php';
require_once __DIR__ . '/calculations.php';
require_once __DIR__ . '/notifications.php';

function normalizeExportText($value) {
    return trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/**
 * Exporte une période en JSON
 */
function exportPeriodToJSON($periodId) {
    // Récupérer les données de la période
    $period = queryOne("SELECT * FROM financial_periods WHERE id = ?", [$periodId]);
    if (!$period) {
        throw new Exception("Période non trouvée");
    }
    
    // Récupérer les paramètres
    $parameters = getParameters($period['parameters_version']);
    
    // Récupérer les budgets
    $budgets = getPeriodBudgets($periodId);
    
    // Récupérer les transactions
    $transactions = queryAll("
        SELECT t.*, c.name as category_name
        FROM transactions t
        LEFT JOIN budget_categories c ON t.category_id = c.id
        WHERE t.period_id = ?
        ORDER BY t.date, t.created_at
    ", [$periodId]);
    
    // Récupérer les notifications IA liées à la période
    $notifications = getOverlappingNotificationsForPeriod($period['start_date'], $period['end_date']);
    
    // Calculer les totaux
    $totals = calculatePeriodTotals($periodId);
    
    // Construire l'objet JSON
    $exportData = [
        'metadata' => [
            'export_date' => date('Y-m-d H:i:s'),
            'period_id' => $periodId,
            'period_start' => $period['start_date'],
            'period_end' => $period['end_date'],
            'system_version' => APP_VERSION
        ],
        'parameters' => [
            'version' => $parameters['version'],
            'default_income' => $parameters['default_income'],
            'currency' => $parameters['currency'],
            'tithing_percent' => $parameters['tithing_percent'],
            'main_saving_percent' => $parameters['main_saving_percent'],
            'extra_saving_percent' => $parameters['extra_saving_percent']
        ],
        'budgets' => array_map(function($budget) {
            return [
                'category' => $budget['name'],
                'allocated' => $budget['allocated_amount'],
                'spent' => $budget['spent_amount'],
                'remaining' => $budget['remaining'],
                'percentage_used' => $budget['percentage_used'],
                'status' => $budget['status']
            ];
        }, $budgets),
        'transactions' => array_map(function($transaction) {
            return [
                'date' => $transaction['date'],
                'type' => $transaction['type'],
                'category' => $transaction['category_name'],
                'amount' => $transaction['amount'],
                'description' => normalizeExportText($transaction['description']),
                'comment' => normalizeExportText($transaction['comment']),
                'tithing_paid' => $transaction['tithing_paid'],
                'saving_paid' => $transaction['saving_paid'],
                'balance_after' => $transaction['balance_after']
            ];
        }, $transactions),
        'notifications' => array_map(function($notification) {
            return [
                'timeframe' => $notification['timeframe'],
                'range_start' => $notification['range_start'],
                'range_end' => $notification['range_end'],
                'analysis_html' => normalizeExportText($notification['analysis_html']),
                'created_at' => $notification['created_at'],
                'is_read' => (bool)$notification['is_read']
            ];
        }, $notifications),
        'summary' => [
            'total_income' => $totals['total_income'] + $totals['total_extra_income'],
            'main_income' => $totals['total_income'],
            'extra_income' => $totals['total_extra_income'],
            'total_expenses' => $totals['total_expenses'],
            'total_tithing' => $totals['total_tithing'],
            'total_saving' => $totals['total_saving'],
            'total_budget' => $totals['total_budget'],
            'total_spent' => $totals['total_spent'],
            'remaining_budget' => $totals['total_budget'] - $totals['total_spent'],
            'saving_rate' => $totals['total_income'] > 0 ? 
                round(($totals['total_saving'] / $totals['total_income']) * 100, 1) : 0
        ],
        'analysis' => [
            'habits' => array_map(function($habit) {
                if (isset($habit['message'])) {
                    $habit['message'] = normalizeExportText($habit['message']);
                }
                if (isset($habit['category'])) {
                    $habit['category'] = normalizeExportText($habit['category']);
                }
                return $habit;
            }, analyzeSpendingHabits(3)),
            'recommendations' => array_map(function($rec) {
                if (isset($rec['message'])) {
                    $rec['message'] = normalizeExportText($rec['message']);
                }
                if (isset($rec['action'])) {
                    $rec['action'] = normalizeExportText($rec['action']);
                }
                return $rec;
            }, generateRecommendations($periodId))
        ]
    ];
    
    // Créer le répertoire d'export si nécessaire
    if (!file_exists(EXPORTS_PATH)) {
        mkdir(EXPORTS_PATH, 0755, true);
    }
    
    // Générer le nom de fichier
    $filename = sprintf('period_%d_%s.json', 
        $periodId, 
        date('Y-m-d_His')
    );
    
    $filepath = EXPORTS_PATH . '/' . $filename;
    
    // Écrire le fichier JSON
    $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filepath, $json);
    
    // Enregistrer dans l'historique
    $sql = "INSERT INTO export_history (period_id, export_type, file_path) 
            VALUES (?, 'period', ?)";
    executeQuery($sql, [$periodId, $filepath]);
    
    return [
        'filepath' => $filepath,
        'filename' => $filename,
        'size' => filesize($filepath)
    ];
}

/**
 * Exporte une année complète en JSON
 */
function exportYearToJSON($year) {
    // Récupérer toutes les périodes de l'année
    $sql = "SELECT id FROM financial_periods 
            WHERE strftime('%Y', start_date) = ? 
            ORDER BY start_date";
    
    $periods = queryAll($sql, [$year]);
    
    if (empty($periods)) {
        throw new Exception("Aucune période trouvée pour l'année $year");
    }
    
    $yearData = [
        'metadata' => [
            'export_date' => date('Y-m-d H:i:s'),
            'year' => $year,
            'period_count' => count($periods),
            'system_version' => APP_VERSION
        ],
        'periods' => []
    ];
    
    // Exporter chaque période
    foreach ($periods as $period) {
        try {
            $periodExport = exportPeriodToJSON($period['id']);
            $periodData = json_decode(file_get_contents($periodExport['filepath']), true);
            
            // Ne garder que le résumé pour l'export annuel
            $yearData['periods'][] = [
                'period_id' => $period['id'],
                'start_date' => $periodData['metadata']['period_start'],
                'end_date' => $periodData['metadata']['period_end'],
                'summary' => $periodData['summary']
            ];
            
        } catch (Exception $e) {
            // Continuer avec les autres périodes
            error_log("Erreur export période {$period['id']}: " . $e->getMessage());
        }
    }
    
    // Calculer les totaux annuels
    $annualTotals = [
        'total_income' => 0,
        'total_expenses' => 0,
        'total_tithing' => 0,
        'total_saving' => 0,
        'average_saving_rate' => 0
    ];
    
    $savingRates = [];
    
    foreach ($yearData['periods'] as $period) {
        $summary = $period['summary'];
        $annualTotals['total_income'] += $summary['total_income'];
        $annualTotals['total_expenses'] += $summary['total_expenses'];
        $annualTotals['total_tithing'] += $summary['total_tithing'];
        $annualTotals['total_saving'] += $summary['total_saving'];
        $savingRates[] = $summary['saving_rate'];
    }
    
    if (!empty($savingRates)) {
        $annualTotals['average_saving_rate'] = array_sum($savingRates) / count($savingRates);
    }
    
    $yearData['annual_summary'] = $annualTotals;
    
    // Sauvegarder le fichier annuel
    $filename = sprintf('year_%s_%s.json', $year, date('Y-m-d_His'));
    $filepath = EXPORTS_PATH . '/' . $filename;
    
    $json = json_encode($yearData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filepath, $json);
    
    // Enregistrer dans l'historique
    $sql = "INSERT INTO export_history (export_type, file_path) 
            VALUES ('year', ?)";
    executeQuery($sql, [$filepath]);
    
    return [
        'filepath' => $filepath,
        'filename' => $filename,
        'size' => filesize($filepath),
        'periods' => count($periods)
    ];
}

/**
 * Liste les exports disponibles
 */
function getExportHistory($limit = 20) {
    $sql = "SELECT * FROM export_history 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    return queryAll($sql, [$limit]);
}
?>
