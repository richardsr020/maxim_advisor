<?php
// transactions.php - Gestion des transactions
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/period.php';
require_once __DIR__ . '/parameters.php';
require_once __DIR__ . '/calculations.php';
require_once __DIR__ . '/budgets.php';

/**
 * Enregistre une transaction
 */
function recordTransaction($data) {
    $db = getDatabase();
    
    $sql = "INSERT INTO transactions 
            (period_id, type, category_id, amount, description, comment, date, 
             tithing_paid, saving_paid, balance_after)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $data['period_id'] ?? null,
        $data['type'],
        $data['category_id'] ?? null,
        $data['amount'],
        $data['description'] ?? '',
        $data['comment'] ?? '',
        $data['date'],
        $data['tithing_paid'] ?? 0,
        $data['saving_paid'] ?? 0,
        $data['balance_after']
    ]);
    
    $transactionId = $db->lastInsertId();
    
    // Mettre à jour le budget si c'est une dépense
    if ($data['type'] === 'expense' && isset($data['category_id'])) {
        updateBudgetSpent($data['period_id'], $data['category_id'], $data['amount']);
    }
    
    return $transactionId;
}

/**
 * Enregistre un revenu principal
 */
function recordMainIncome($amount, $description = 'Revenu principal') {
    $period = getActivePeriod();
    if (!$period) {
        throw new Exception("Aucune période active");
    }
    
    $params = getCurrentParameters();
    $distribution = calculateMainIncomeDistribution($amount, $params);
    
    // Mettre à jour la période
    $db = getDatabase();
    $sql = "UPDATE financial_periods 
            SET tithing_amount = tithing_amount + ?, 
                saving_amount = saving_amount + ?,
                initial_income = ?
            WHERE id = ?";
    
    $db->prepare($sql)->execute([
        $distribution['tithing'],
        $distribution['saving'],
        $amount,
        $period['id']
    ]);
    
    // Mettre à jour les budgets
    foreach ($distribution['budgets'] as $categoryId => $additional) {
        $sql = "UPDATE period_budgets 
                SET allocated_amount = allocated_amount + ?
                WHERE period_id = ? AND category_id = ?";
        $db->prepare($sql)->execute([$additional, $period['id'], $categoryId]);
    }
    
    // Enregistrer la transaction
    return recordTransaction([
        'period_id' => $period['id'],
        'type' => 'income_main',
        'amount' => $amount,
        'description' => $description,
        'date' => date('Y-m-d'),
        'tithing_paid' => $distribution['tithing'],
        'saving_paid' => $distribution['saving'],
        'balance_after' => $distribution['spendable']
    ]);
}

/**
 * Enregistre un revenu occasionnel
 */
function recordExtraIncome($amount, $description) {
    $period = getActivePeriod();
    if (!$period) {
        throw new Exception("Aucune période active");
    }
    
    $params = getCurrentParameters();
    $distribution = calculateExtraIncomeDistribution($amount, $params);
    
    // Mettre à jour les budgets (répartition proportionnelle)
    $budgets = getPeriodBudgets($period['id']);
    $totalBudget = array_sum(array_column($budgets, 'allocated_amount'));
    
    $db = getDatabase();
    foreach ($budgets as $budget) {
        $percentage = $budget['allocated_amount'] / $totalBudget;
        $additional = $distribution['available'] * $percentage;
        
        $sql = "UPDATE period_budgets 
                SET allocated_amount = allocated_amount + ?
                WHERE id = ?";
        $db->prepare($sql)->execute([round($additional), $budget['id']]);
    }
    
    // Enregistrer la dîme reportée
    if ($distribution['tithing'] > 0) {
        deferTithing($distribution['tithing'], $period['id']);
    }
    
    // Enregistrer la transaction
    return recordTransaction([
        'period_id' => $period['id'],
        'type' => 'income_extra',
        'amount' => $amount,
        'description' => $description,
        'date' => date('Y-m-d'),
        'tithing_paid' => $distribution['tithing'],
        'saving_paid' => $distribution['saving'],
        'balance_after' => calculatePeriodRemaining($period['id'])
    ]);
}

/**
 * Enregistre une dépense
 */
function recordExpense($categoryId, $amount, $description, $comment = null) {
    $period = getActivePeriod();
    if (!$period) {
        throw new Exception("Aucune période active");
    }
    
    // Vérifier si la catégorie est un imprévu
    $category = getCategory($categoryId);
    if ($category['is_unexpected'] && empty($comment)) {
        throw new Exception("Un commentaire est obligatoire pour les dépenses imprévues");
    }
    
    // Vérifier le budget disponible
    $budget = getCategoryBudget($period['id'], $categoryId);
    if (!$budget) {
        throw new Exception("Budget non trouvé");
    }
    
    $available = $budget['allocated_amount'] - $budget['spent_amount'];
    if ($amount > $available) {
        throw new Exception("Dépassement de budget: " . formatCurrency($available) . " disponible");
    }
    
    // Calculer le nouveau solde
    $newSpent = $budget['spent_amount'] + $amount;
    $remaining = $available - $amount;
    
    // Enregistrer la transaction
    return recordTransaction([
        'period_id' => $period['id'],
        'type' => 'expense',
        'category_id' => $categoryId,
        'amount' => $amount,
        'description' => $description,
        'comment' => $comment,
        'date' => date('Y-m-d'),
        'balance_after' => $remaining
    ]);
}

/**
 * Met à jour le montant dépensé d'un budget
 */
function updateBudgetSpent($periodId, $categoryId, $amount) {
    $sql = "UPDATE period_budgets 
            SET spent_amount = spent_amount + ?
            WHERE period_id = ? AND category_id = ?";
    
    return executeQuery($sql, [$amount, $periodId, $categoryId]);
}

/**
 * Calcule le solde restant d'une période
 */
function calculatePeriodRemaining($periodId) {
    $sql = "SELECT 
                SUM(pb.allocated_amount) - SUM(pb.spent_amount) as remaining
            FROM period_budgets pb
            WHERE pb.period_id = ?";
    
    $result = queryOne($sql, [$periodId]);
    return $result['remaining'] ?? 0;
}

/**
 * Enregistre une dîme reportée
 */
function deferTithing($amount, $sourcePeriodId, $targetPeriodId = null) {
    if ($amount <= 0) {
        return false;
    }
    
    $sql = "INSERT INTO deferred_tithing (amount, source_period_id, target_period_id, is_paid)
            VALUES (?, ?, ?, 0)";
    
    return executeQuery($sql, [$amount, $sourcePeriodId, $targetPeriodId]);
}
?>
