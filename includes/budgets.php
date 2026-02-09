<?php
require_once __DIR__ . '/database.php';

function getAllCategories() {
    $sql = "SELECT * FROM budget_categories ORDER BY position";
    return queryAll($sql);
}

function getCategory($categoryId) {
    $sql = "SELECT * FROM budget_categories WHERE id = ?";
    return queryOne($sql, [$categoryId]);
}

function getBudgetPercentages($parametersVersion) {
    $rows = queryAll(
        "SELECT category_id, percentage FROM budget_percentages WHERE parameters_version = ? ORDER BY category_id",
        [$parametersVersion]
    );
    
    $percentages = [];
    foreach ($rows as $row) {
        $percentages[(int)$row['category_id']] = (int)$row['percentage'];
    }
    
    if (empty($percentages)) {
        global $DEFAULT_BUDGET_PERCENTAGES;
        if (!empty($DEFAULT_BUDGET_PERCENTAGES)) {
            $db = getDatabase();
            $stmt = $db->prepare("
                INSERT OR IGNORE INTO budget_percentages (parameters_version, category_id, percentage)
                VALUES (?, ?, ?)
            ");
            foreach ($DEFAULT_BUDGET_PERCENTAGES as $categoryId => $percentage) {
                $stmt->execute([$parametersVersion, $categoryId, $percentage]);
            }
            return $DEFAULT_BUDGET_PERCENTAGES;
        }
    }
    
    return $percentages;
}

function getPeriodBudgets($periodId) {
    $query = "SELECT 
                pb.id,
                pb.period_id,
                pb.category_id,
                pb.allocated_amount,
                pb.spent_amount,
                c.name,
                c.icon,
                c.color,
                c.is_unexpected,
                (pb.allocated_amount - pb.spent_amount) as remaining,
                CASE 
                    WHEN pb.allocated_amount > 0 
                    THEN ROUND((pb.spent_amount * 100.0 / pb.allocated_amount), 1)
                    ELSE 0 
                END as percentage_used
              FROM period_budgets pb
              JOIN budget_categories c ON pb.category_id = c.id
              WHERE pb.period_id = ?
              ORDER BY c.position";
    
    $budgets = queryAll($query, [$periodId]);
    
    foreach ($budgets as &$budget) {
        $budget['status'] = getBudgetStatus($budget['percentage_used']);
        $budget['is_over'] = $budget['spent_amount'] > $budget['allocated_amount'];
    }
    
    return $budgets;
}

function getCategoryBudget($periodId, $categoryId) {
    $query = "SELECT 
                pb.id,
                pb.period_id,
                pb.category_id,
                pb.allocated_amount,
                pb.spent_amount,
                c.name,
                c.icon,
                c.color,
                c.is_unexpected,
                (pb.allocated_amount - pb.spent_amount) as remaining,
                CASE 
                    WHEN pb.allocated_amount > 0 
                    THEN ROUND((pb.spent_amount * 100.0 / pb.allocated_amount), 1)
                    ELSE 0 
                END as percentage_used
              FROM period_budgets pb
              JOIN budget_categories c ON pb.category_id = c.id
              WHERE pb.period_id = ? AND pb.category_id = ?
              LIMIT 1";
    
    $budget = queryOne($query, [$periodId, $categoryId]);
    if ($budget) {
        $budget['status'] = getBudgetStatus($budget['percentage_used']);
        $budget['is_over'] = $budget['spent_amount'] > $budget['allocated_amount'];
    }
    
    return $budget;
}

function getBudgetSummary($periodId) {
    $db = getDatabase();
    
    $query = "SELECT 
                pb.id,
                pb.category_id,
                c.name,
                c.icon,
                c.color,
                c.is_unexpected,
                pb.allocated_amount,
                pb.spent_amount,
                (pb.allocated_amount - pb.spent_amount) as remaining,
                CASE 
                    WHEN pb.allocated_amount > 0 
                    THEN ROUND((pb.spent_amount * 100.0 / pb.allocated_amount), 1)
                    ELSE 0 
                END as percentage_used
              FROM period_budgets pb
              JOIN budget_categories c ON pb.category_id = c.id
              WHERE pb.period_id = :period_id
              ORDER BY c.position";
    
    $stmt = $db->prepare($query);
    $stmt->execute([':period_id' => $periodId]);
    
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ajouter les statuts d'alerte
    foreach ($budgets as &$budget) {
        $budget['status'] = getBudgetStatus($budget['percentage_used']);
        $budget['is_over'] = $budget['spent_amount'] > $budget['allocated_amount'];
    }
    
    return $budgets;
}

function getBudgetStatus($percentage) {
    if ($percentage >= 100) return 'over';
    if ($percentage >= 90) return 'critical';
    if ($percentage >= 75) return 'warning';
    return 'normal';
}

function getCategoryProgressBar($percentage, $status) {
    $width = min($percentage, 100);
    
    $colors = [
        'normal' => '#4CAF50',
        'warning' => '#FF9800',
        'critical' => '#F44336',
        'over' => '#D32F2F'
    ];
    
    $color = $colors[$status] ?? '#4CAF50';
    
    return "
    <div class='progress-bar'>
        <div class='progress-fill' style='width: {$width}%; background-color: {$color};'></div>
    </div>";
}
