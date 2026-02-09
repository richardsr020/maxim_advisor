<?php
// dashboard.php - Endpoints pour le dashboard
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/period.php';
require_once __DIR__ . '/budgets.php';
require_once __DIR__ . '/transactions.php';

if (php_sapi_name() === 'cli' || realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) {
    return;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_budget_data') {
    header('Content-Type: application/json');
    
    $period = getActivePeriod();
    if (!$period) {
        echo json_encode([
            'budgets' => [],
            'daily_expenses' => []
        ]);
        exit;
    }
    
    $budgets = getBudgetSummary($period['id']);
    $budgetData = array_map(function($b) {
        return [
            'category' => $b['name'],
            'spent' => (int)$b['spent_amount'],
            'allocated' => (int)$b['allocated_amount'],
            'color' => $b['color']
        ];
    }, $budgets);
    
    $dailyRows = queryAll(
        "SELECT date, SUM(amount) as total 
         FROM transactions 
         WHERE period_id = ? AND type = 'expense'
         GROUP BY date 
         ORDER BY date",
        [$period['id']]
    );
    
    $dailyExpenses = array_map(function($row) {
        return [
            'date' => date('d/m', strtotime($row['date'])),
            'amount' => (int)$row['total']
        ];
    }, $dailyRows);
    
    echo json_encode([
        'budgets' => $budgetData,
        'daily_expenses' => $dailyExpenses
    ]);
    exit;
}

if ($action === 'get_stats_series') {
    header('Content-Type: application/json');

    $period = getActivePeriod();
    if (!$period) {
        echo json_encode([
            'labels' => [],
            'series' => [
                'income' => [],
                'expense' => [],
                'tithing' => [],
                'saving' => []
            ]
        ]);
        exit;
    }

    $rows = queryAll(
        "SELECT 
            date,
            SUM(CASE WHEN type IN ('income_main', 'income_extra') THEN amount ELSE 0 END) as income_total,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense_total,
            SUM(tithing_paid) as tithing_total,
            SUM(saving_paid) as saving_total
         FROM transactions
         WHERE period_id = ?
         GROUP BY date
         ORDER BY date",
        [$period['id']]
    );

    $labels = [];
    $income = [];
    $expense = [];
    $tithing = [];
    $saving = [];

    foreach ($rows as $row) {
        $labels[] = date('d/m', strtotime($row['date']));
        $income[] = (int)$row['income_total'];
        $expense[] = (int)$row['expense_total'];
        $tithing[] = (int)$row['tithing_total'];
        $saving[] = (int)$row['saving_total'];
    }

    echo json_encode([
        'labels' => $labels,
        'series' => [
            'income' => $income,
            'expense' => $expense,
            'tithing' => $tithing,
            'saving' => $saving
        ]
    ]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['error' => 'Action invalide']);
