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
function recordExtraIncome($amount, $description, $savingsOnlyOverride = null) {
    $period = getActivePeriod();
    if (!$period) {
        throw new Exception("Aucune période active");
    }
    
    $params = getCurrentParameters();
    $distribution = calculateExtraIncomeDistribution($amount, $params, $savingsOnlyOverride);
    $budgetAdjustments = [];
    
    // Mettre à jour les budgets (répartition proportionnelle) sauf si le mode "tout en épargne" est actif
    $allocateToSavingsOnly = !empty($distribution['allocate_to_savings_only']);
    if (!$allocateToSavingsOnly && (int)$distribution['available'] > 0) {
        $budgets = getPeriodBudgets($period['id']);
        $totalBudget = array_sum(array_column($budgets, 'allocated_amount'));

        if ($totalBudget > 0) {
            $db = getDatabase();
            $rawAdjustments = [];
            $roundedTotal = 0;
            foreach ($budgets as $budget) {
                $percentage = $budget['allocated_amount'] / $totalBudget;
                $additional = (int)round($distribution['available'] * $percentage);
                $rawAdjustments[] = [
                    'id' => (int)$budget['id'],
                    'category_id' => (int)$budget['category_id'],
                    'amount' => $additional
                ];
                $roundedTotal += $additional;
            }

            $difference = (int)$distribution['available'] - $roundedTotal;
            if (!empty($rawAdjustments) && $difference !== 0) {
                $rawAdjustments[0]['amount'] += $difference;
            }

            foreach ($rawAdjustments as $adjustment) {
                $additional = (int)$adjustment['amount'];
                if ($additional <= 0) {
                    continue;
                }

                $sql = "UPDATE period_budgets 
                        SET allocated_amount = allocated_amount + ?
                        WHERE id = ?";
                $db->prepare($sql)->execute([$additional, $adjustment['id']]);
                $budgetAdjustments[$adjustment['category_id']] = ($budgetAdjustments[$adjustment['category_id']] ?? 0) + $additional;
            }
        }
    }
    
    // Enregistrer la dîme reportée
    if ($distribution['tithing'] > 0) {
        deferTithing($distribution['tithing'], $period['id']);
    }
    
    // Enregistrer la transaction
    $transactionId = recordTransaction([
        'period_id' => $period['id'],
        'type' => 'income_extra',
        'amount' => $amount,
        'description' => $description,
        'date' => date('Y-m-d'),
        'tithing_paid' => $distribution['tithing'],
        'saving_paid' => $distribution['saving'],
        'balance_after' => calculatePeriodRemaining($period['id'])
    ]);

    if (!empty($budgetAdjustments)) {
        recordBudgetAdjustments($period['id'], $transactionId, $budgetAdjustments);
    }

    return $transactionId;
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

function recordBudgetAdjustments($periodId, $sourceTransactionId, $adjustments) {
    if (empty($adjustments)) {
        return;
    }

    $db = getDatabase();
    $stmt = $db->prepare(
        "INSERT INTO budget_adjustments (period_id, source_transaction_id, category_id, amount)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($adjustments as $categoryId => $amount) {
        $amount = (int)$amount;
        if ($amount <= 0) {
            continue;
        }
        $stmt->execute([(int)$periodId, (int)$sourceTransactionId, (int)$categoryId, $amount]);
    }
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

function getTotalSavedAmount() {
    $row = queryOne("SELECT COALESCE(SUM(saving_paid), 0) as total FROM transactions");
    return (int)($row['total'] ?? 0);
}

function getTotalSavingWithdrawn() {
    $row = queryOne("SELECT COALESCE(SUM(amount), 0) as total FROM saving_withdrawals");
    return (int)($row['total'] ?? 0);
}

function getAvailableSavingBalance() {
    return max(getTotalSavedAmount() - getTotalSavingWithdrawn(), 0);
}

function getTotalTithingReserved() {
    $row = queryOne("SELECT COALESCE(SUM(tithing_paid), 0) as total FROM transactions");
    return (int)($row['total'] ?? 0);
}

function getTotalTithingPaid() {
    $row = queryOne("SELECT COALESCE(SUM(amount), 0) as total FROM tithing_payments");
    return (int)($row['total'] ?? 0);
}

function getAvailableTithingBalance() {
    return max(getTotalTithingReserved() - getTotalTithingPaid(), 0);
}

function getLatestFundCredit($fundType, $periodId = null) {
    $column = $fundType === 'tithing' ? 'tithing_paid' : 'saving_paid';
    $sql = "SELECT period_id, date, created_at, {$column} as amount
            FROM transactions
            WHERE {$column} > 0";
    $params = [];

    if ($periodId !== null) {
        $sql .= " AND period_id = ?";
        $params[] = $periodId;
    }

    $sql .= " ORDER BY date DESC, created_at DESC LIMIT 1";
    $row = queryOne($sql, $params);
    if (!$row) {
        return null;
    }

    $row['amount'] = (int)$row['amount'];
    return $row;
}

function getFundIncreaseMetrics($fundType, $currentBalance, $periodId = null) {
    $credit = getLatestFundCredit($fundType, $periodId);
    if (!$credit) {
        return [
            'amount' => 0,
            'percentage' => 0,
            'date' => null
        ];
    }

    $amount = (int)$credit['amount'];
    $previousBalance = max((int)$currentBalance - $amount, 0);
    $percentage = $previousBalance > 0
        ? round(($amount / $previousBalance) * 100, 1)
        : ($amount > 0 ? 100 : 0);

    return [
        'amount' => $amount,
        'percentage' => $percentage,
        'date' => $credit['date']
    ];
}

function getLatestFundDebit($fundType, $periodId = null) {
    if ($fundType === 'saving') {
        $table = 'saving_withdrawals';
    } elseif ($fundType === 'tithing') {
        $table = 'tithing_payments';
    } else {
        return null;
    }

    $sql = "SELECT period_id, date, created_at, amount
            FROM {$table}
            WHERE amount > 0";
    $params = [];

    if ($periodId !== null) {
        $sql .= " AND period_id = ?";
        $params[] = $periodId;
    }

    $sql .= " ORDER BY date DESC, created_at DESC, id DESC LIMIT 1";
    $row = queryOne($sql, $params);
    if (!$row) {
        return null;
    }

    $row['amount'] = (int)$row['amount'];
    return $row;
}

function getFundDecreaseMetrics($fundType, $currentBalance, $periodId = null) {
    $debit = getLatestFundDebit($fundType, $periodId);
    if (!$debit) {
        return [
            'amount' => 0,
            'percentage' => 0,
            'date' => null
        ];
    }

    $amount = (int)$debit['amount'];
    $previousBalance = max((int)$currentBalance + $amount, 0);
    $percentage = $previousBalance > 0
        ? round(($amount / $previousBalance) * 100, 1)
        : 0;

    return [
        'amount' => $amount,
        'percentage' => $percentage,
        'date' => $debit['date']
    ];
}

function getLatestBudgetIncrease($periodId) {
    $latest = queryOne(
        "SELECT source_transaction_id, SUM(amount) as total_amount, MAX(created_at) as created_at
         FROM budget_adjustments
         WHERE period_id = ?
         GROUP BY source_transaction_id
         ORDER BY MAX(created_at) DESC, source_transaction_id DESC
         LIMIT 1",
        [$periodId]
    );

    if (!$latest) {
        return null;
    }

    $sourceTransactionId = isset($latest['source_transaction_id']) ? (int)$latest['source_transaction_id'] : null;
    $rows = [];
    if ($sourceTransactionId) {
        $rows = queryAll(
            "SELECT category_id, SUM(amount) as amount
             FROM budget_adjustments
             WHERE period_id = ? AND source_transaction_id = ?
             GROUP BY category_id",
            [$periodId, $sourceTransactionId]
        );
    } else {
        $rows = queryAll(
            "SELECT category_id, SUM(amount) as amount
             FROM budget_adjustments
             WHERE period_id = ? AND source_transaction_id IS NULL
             GROUP BY category_id",
            [$periodId]
        );
    }

    $byCategory = [];
    foreach ($rows as $row) {
        $byCategory[(int)$row['category_id']] = (int)$row['amount'];
    }

    $date = null;
    if ($sourceTransactionId) {
        $tx = queryOne("SELECT date FROM transactions WHERE id = ?", [$sourceTransactionId]);
        $date = $tx['date'] ?? null;
    }

    return [
        'amount' => (int)($latest['total_amount'] ?? 0),
        'date' => $date,
        'by_category' => $byCategory
    ];
}

function getBudgetIncreaseMetrics($periodId, $currentAllocatedAmount) {
    $increase = getLatestBudgetIncrease($periodId);
    if (!$increase) {
        return [
            'amount' => 0,
            'percentage' => 0,
            'date' => null,
            'by_category' => []
        ];
    }

    $amount = (int)$increase['amount'];
    $previousAllocated = max((int)$currentAllocatedAmount - $amount, 0);
    $percentage = $previousAllocated > 0
        ? round(($amount / $previousAllocated) * 100, 1)
        : ($amount > 0 ? 100 : 0);

    return [
        'amount' => $amount,
        'percentage' => $percentage,
        'date' => $increase['date'],
        'by_category' => $increase['by_category']
    ];
}

function getLatestBudgetExpense($periodId) {
    $row = queryOne(
        "SELECT amount, date
         FROM transactions
         WHERE period_id = ? AND type = 'expense'
         ORDER BY date DESC, created_at DESC, id DESC
         LIMIT 1",
        [$periodId]
    );
    if (!$row) {
        return null;
    }

    return [
        'amount' => (int)$row['amount'],
        'date' => $row['date']
    ];
}

function getBudgetDecreaseMetrics($periodId, $currentRemainingAmount) {
    $expense = getLatestBudgetExpense($periodId);
    if (!$expense) {
        return [
            'amount' => 0,
            'percentage' => 0,
            'date' => null
        ];
    }

    $amount = (int)$expense['amount'];
    $previousRemaining = max((int)$currentRemainingAmount + $amount, 0);
    $percentage = $previousRemaining > 0
        ? round(($amount / $previousRemaining) * 100, 1)
        : 0;

    return [
        'amount' => $amount,
        'percentage' => $percentage,
        'date' => $expense['date']
    ];
}

function withdrawFromSaving($amount, $description, $periodId = null, $date = null) {
    $amount = (int)$amount;
    $description = trim((string)$description);
    if ($amount <= 0) {
        throw new Exception("Le montant du retrait d'épargne doit être positif");
    }
    if ($description === '') {
        throw new Exception("La description du projet est obligatoire");
    }

    $available = getAvailableSavingBalance();
    if ($amount > $available) {
        throw new Exception("Épargne insuffisante: " . formatCurrency($available) . " disponible");
    }

    if ($periodId === null) {
        $period = getActivePeriod();
        $periodId = $period['id'] ?? null;
    }
    $date = $date ?: date('Y-m-d');

    $sql = "INSERT INTO saving_withdrawals (period_id, amount, description, date)
            VALUES (?, ?, ?, ?)";
    return executeQuery($sql, [$periodId, $amount, $description, $date]);
}

function payTithingToChurch($amount, $description, $periodId = null, $date = null) {
    $amount = (int)$amount;
    $description = trim((string)$description);
    if ($amount <= 0) {
        throw new Exception("Le montant de dîme à verser doit être positif");
    }
    if ($description === '') {
        throw new Exception("La description du versement est obligatoire");
    }

    $available = getAvailableTithingBalance();
    if ($amount > $available) {
        throw new Exception("Dîme disponible insuffisante: " . formatCurrency($available));
    }

    if ($periodId === null) {
        $period = getActivePeriod();
        $periodId = $period['id'] ?? null;
    }
    $date = $date ?: date('Y-m-d');

    $sql = "INSERT INTO tithing_payments (period_id, amount, description, date)
            VALUES (?, ?, ?, ?)";
    return executeQuery($sql, [$periodId, $amount, $description, $date]);
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
