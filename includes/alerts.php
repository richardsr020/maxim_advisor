<?php
// alerts.php - Système d'alertes
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/calculations.php';
require_once __DIR__ . '/budgets.php';

/**
 * Vérifie les alertes de budget
 */
function checkBudgetAlerts($periodId, $categoryId) {
    $budget = getCategoryBudget($periodId, $categoryId);
    $category = getCategory($categoryId);
    
    if (!$budget || !$category) return;
    
    $percentage = calculateBudgetUsage($budget['allocated_amount'], $budget['spent_amount']);
    
    // Alerte 75%
    if ($percentage >= WARNING_THRESHOLD && $percentage < CRITICAL_THRESHOLD) {
        createAlert($periodId, 'threshold', 'warning',
            "{$category['name']} à {$percentage}% - Attention budget"
        );
    }
    
    // Alerte 90%
    if ($percentage >= CRITICAL_THRESHOLD && $percentage < BLOCK_THRESHOLD) {
        createAlert($periodId, 'threshold', 'danger',
            "{$category['name']} à {$percentage}% - Critique!"
        );
    }
    
    // Alerte 100%
    if ($percentage >= BLOCK_THRESHOLD) {
        createAlert($periodId, 'threshold', 'danger',
            "{$category['name']} à {$percentage}% - DÉPASSEMENT DE BUDGET!"
        );
    }
}

/**
 * Détecte les mauvaises habitudes
 */
function checkBadHabits($periodId, $categoryId, $amount) {
    // 1. Ménage > Communication
    $householdBudget = getCategoryBudget($periodId, 4); // Ménage
    $communicationBudget = getCategoryBudget($periodId, 3); // Communication
    
    if ($householdBudget && $communicationBudget) {
        $householdPercent = calculateBudgetUsage($householdBudget['allocated_amount'], $householdBudget['spent_amount']);
        $commPercent = calculateBudgetUsage($communicationBudget['allocated_amount'], $communicationBudget['spent_amount']);
        
        if ($householdPercent > $commPercent + 10) { // 10% d'écart
            createAlert($periodId, 'habit', 'warning',
                "Ménage ({$householdPercent}%) > Communication ({$commPercent}%) - Vérifiez vos priorités"
            );
        }
    }
    
    // 2. Utilisation trop précoce des imprévus
    $unexpectedBudget = getCategoryBudget($periodId, 5); // Imprévus
    if ($unexpectedBudget && $categoryId == 5) {
        $period = queryOne("SELECT start_date, end_date FROM financial_periods WHERE id = ?", [$periodId]);
        
        $startDate = new DateTime($period['start_date']);
        $endDate = new DateTime($period['end_date']);
        $today = new DateTime();
        
        $totalDays = $endDate->diff($startDate)->days;
        $daysPassed = $today->diff($startDate)->days;
        
        $percentTime = ($daysPassed / $totalDays) * 100;
        $percentUsed = calculateBudgetUsage($unexpectedBudget['allocated_amount'], $unexpectedBudget['spent_amount']);
        
        if ($percentUsed > 50 && $percentTime < 50) {
            createAlert($periodId, 'habit', 'danger',
                "Imprévus utilisé à {$percentUsed}% alors que seulement {$percentTime}% de la période est écoulée"
            );
        }
    }
    
    // 3. Dépenses importantes en début de période
    if ($categoryId != 5) { // Pas les imprévus
        $period = queryOne("SELECT start_date FROM financial_periods WHERE id = ?", [$periodId]);
        $startDate = new DateTime($period['start_date']);
        $today = new DateTime();
        
        $daysSinceStart = $today->diff($startDate)->days;
        
        if ($daysSinceStart <= 3 && $amount > 10000) { // > 10,000 FC dans les 3 premiers jours
            createAlert($periodId, 'habit', 'warning',
                "Dépense importante ({$amount} FC) en début de période - Soyez prudent"
            );
        }
    }
}

/**
 * Crée une alerte
 */
function createAlert($periodId, $type, $level, $message) {
    $sql = "INSERT INTO alerts (period_id, type, level, message) 
            VALUES (?, ?, ?, ?)";
    
    return executeQuery($sql, [$periodId, $type, $level, $message]);
}

/**
 * Récupère les alertes actives
 */
function getActiveAlerts($periodId, $limit = 10) {
    $sql = "SELECT * FROM alerts 
            WHERE period_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    return queryAll($sql, [$periodId, $limit]);
}

/**
 * Marque une alerte comme lue
 */
function markAlertAsRead($alertId) {
    $sql = "UPDATE alerts SET is_read = 1 WHERE id = ?";
    return executeQuery($sql, [$alertId]);
}

/**
 * Récupère les statistiques d'alerte
 */
function getAlertStats($periodId) {
    $sql = "SELECT 
                level,
                COUNT(*) as count
            FROM alerts 
            WHERE period_id = ? AND is_read = 0
            GROUP BY level";
    
    return queryAll($sql, [$periodId]);
}
?>

<?php
// Endpoint AJAX: marquer une alerte comme lue
if (php_sapi_name() !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__ && isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    header('Content-Type: application/json');
    $alertId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($alertId > 0) {
        markAlertAsRead($alertId);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ID invalide']);
    }
    exit;
}
