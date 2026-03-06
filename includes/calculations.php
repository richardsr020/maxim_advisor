<?php
// calculations.php - Calculs financiers
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/budgets.php';

/**
 * Calcule la répartition d'un revenu principal
 */
function calculateMainIncomeDistribution($amount, $params) {
    $tithing = (int)floor($amount * (FIXED_TITHING_PERCENT / 100));
    $saving = (int)floor($amount * ($params['main_saving_percent'] / 100));
    $spendable = (int)($amount - $tithing - $saving);
    
    return [
        'tithing' => $tithing,
        'saving' => $saving,
        'spendable' => $spendable,
        'budgets' => calculateBudgetAllocation($spendable, $params['id'])
    ];
}

/**
 * Calcule la répartition d'un revenu occasionnel
 */
function calculateExtraIncomeDistribution($amount, $params, $savingsOnlyOverride = null) {
    $tithing = (int)floor($amount * (FIXED_TITHING_PERCENT / 100));
    if ($savingsOnlyOverride === null) {
        $allocateToSavingsOnly = !empty($params['extra_income_to_savings_only']);
    } else {
        $allocateToSavingsOnly = (bool)$savingsOnlyOverride;
    }

    if ($allocateToSavingsOnly) {
        $saving = (int)max($amount - $tithing, 0);
        $available = 0;
    } else {
        $saving = (int)floor($amount * ($params['extra_saving_percent'] / 100));
        $available = (int)max($amount - $tithing - $saving, 0);
    }
    
    return [
        'tithing' => $tithing,
        'saving' => $saving,
        'available' => $available,
        'allocate_to_savings_only' => $allocateToSavingsOnly
    ];
}

/**
 * Calcule la répartition des budgets
 */
function calculateBudgetAllocation($total, $parametersVersion) {
    $percentages = getBudgetPercentages($parametersVersion);
    $allocation = [];
    
    foreach ($percentages as $categoryId => $percentage) {
        $amount = floor($total * ($percentage / 100));
        $allocation[$categoryId] = $amount;
    }
    
    if (empty($allocation)) {
        return $allocation;
    }
    
    // Ajuster les arrondis
    $allocatedTotal = array_sum($allocation);
    if ($allocatedTotal != $total) {
        $difference = $total - $allocatedTotal;
        // Ajouter la différence à la première catégorie
        $firstKey = array_key_first($allocation);
        $allocation[$firstKey] += $difference;
    }
    
    return $allocation;
}

/**
 * Vérifie si un budget peut être dépensé
 */
function canSpendFromBudget($budgetId, $amount) {
    $sql = "SELECT allocated_amount, spent_amount 
            FROM period_budgets 
            WHERE id = ?";
    
    $budget = queryOne($sql, [$budgetId]);
    
    if (!$budget) return false;
    
    $available = $budget['allocated_amount'] - $budget['spent_amount'];
    return $amount <= $available;
}

/**
 * Calcule les totaux pour une période
 */
function calculatePeriodTotals($periodId) {
    $sql = "SELECT 
                SUM(CASE WHEN type = 'income_main' THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN type = 'income_extra' THEN amount ELSE 0 END) as total_extra_income,
                SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
                SUM(tithing_paid) as total_tithing,
                SUM(saving_paid) as total_saving
            FROM transactions 
            WHERE period_id = ?";
    
    $totals = queryOne($sql, [$periodId]);
    
    // Ajouter les budgets
    $sql = "SELECT 
                SUM(allocated_amount) as total_budget,
                SUM(spent_amount) as total_spent
            FROM period_budgets 
            WHERE period_id = ?";
    
    $budgets = queryOne($sql, [$periodId]);
    
    return array_merge($totals, $budgets);
}

/**
 * Alias historique pour compatibilité
 */
function calculateTotals($periodId) {
    return calculatePeriodTotals($periodId);
}

/**
 * Calcule le pourcentage d'utilisation d'un budget
 */
function calculateBudgetUsage($allocated, $spent) {
    if ($allocated <= 0) return 0;
    return min(round(($spent / $allocated) * 100, 1), 100);
}
?>
