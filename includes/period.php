<?php
// period.php - Gestion des périodes financières
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/parameters.php';
require_once __DIR__ . '/budgets.php';
require_once __DIR__ . '/calculations.php';

/**
 * Récupère la période active
 */
function getActivePeriod() {
    $sql = "SELECT * FROM financial_periods WHERE is_active = 1 ORDER BY start_date DESC LIMIT 1";
    return queryOne($sql);
}

/**
 * Crée une nouvelle période financière
 */
function createPeriod($income, $parametersVersion = 1) {
    $db = getDatabase();
    
    try {
        $db->beginTransaction();
        
        // Désactiver l'ancienne période
        $db->exec("UPDATE financial_periods SET is_active = 0 WHERE is_active = 1");
        
        // Calculer les dates
        $startDate = new DateTime();
        $endDate = clone $startDate;
        $endDate->add(new DateInterval('P1M'));
        
        // Récupérer les paramètres
        $params = getParameters($parametersVersion);
        
        // Calculs obligatoires
        $tithing = (int)floor($income * (FIXED_TITHING_PERCENT / 100));
        $saving = (int)floor($income * ($params['main_saving_percent'] / 100));
        $spendable = (int)($income - $tithing - $saving);
        
        // Créer la période
        $sql = "INSERT INTO financial_periods 
                (start_date, end_date, parameters_version, initial_income, tithing_amount, saving_amount, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $parametersVersion,
            $income,
            $tithing,
            $saving
        ]);
        
        $periodId = $db->lastInsertId();
        
        // Récupérer les pourcentages de budget
        $percentages = getBudgetPercentages($parametersVersion);
        if (empty($percentages)) {
            global $DEFAULT_BUDGET_PERCENTAGES;
            $percentages = $DEFAULT_BUDGET_PERCENTAGES ?? [];
        }
        
        // Créer les budgets
        foreach ($percentages as $categoryId => $percentage) {
            $allocated = $spendable * ($percentage / 100);
            
            $sql = "INSERT INTO period_budgets (period_id, category_id, allocated_amount)
                    VALUES (?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$periodId, $categoryId, round($allocated)]);
        }
        
        // Enregistrer le revenu principal (transaction initiale)
        $sql = "INSERT INTO transactions 
                (period_id, type, category_id, amount, description, comment, date, 
                 tithing_paid, saving_paid, balance_after)
                VALUES (?, 'income_main', NULL, ?, ?, '', ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $periodId,
            $income,
            'Revenu principal',
            $startDate->format('Y-m-d'),
            $tithing,
            $saving,
            $spendable
        ]);
        
        $db->commit();
        
        return [
            'id' => $periodId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'spendable' => $spendable
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Synchronise la période active avec une version de paramètres
 */
function synchronizeActivePeriod($parametersVersion = null) {
    $period = getActivePeriod();
    if (!$period) {
        return ['synced' => false, 'reason' => 'no_active_period'];
    }

    $params = $parametersVersion ? getParameters($parametersVersion) : getCurrentParameters();

    $incomeTransactions = queryAll(
        "SELECT id, type, amount
         FROM transactions
         WHERE period_id = ? AND type IN ('income_main', 'income_extra')
         ORDER BY id ASC",
        [$period['id']]
    );

    $mainIncome = 0;
    $extraIncome = 0;
    $tithing = 0;
    $saving = 0;

    $db = getDatabase();
    $updateIncomeStmt = $db->prepare(
        "UPDATE transactions
         SET tithing_paid = ?, saving_paid = ?
         WHERE id = ?"
    );

    try {
        $db->beginTransaction();

        foreach ($incomeTransactions as $tx) {
            $amount = (int)$tx['amount'];
            $type = (string)$tx['type'];

            $txTithing = (int)floor($amount * (FIXED_TITHING_PERCENT / 100));
            if ($type === 'income_main') {
                $mainIncome += $amount;
                $txSaving = (int)floor($amount * ($params['main_saving_percent'] / 100));
            } else {
                $extraIncome += $amount;
                if (!empty($params['extra_income_to_savings_only'])) {
                    $txSaving = max($amount - $txTithing, 0);
                } else {
                    $txSaving = (int)floor($amount * ($params['extra_saving_percent'] / 100));
                }
            }

            $tithing += $txTithing;
            $saving += $txSaving;
            $updateIncomeStmt->execute([$txTithing, $txSaving, (int)$tx['id']]);
        }

        $totalIncome = $mainIncome + $extraIncome;
        $spendable = max($totalIncome - $tithing - $saving, 0);
        $allocation = calculateBudgetAllocation($spendable, $params['id']);

        $db->prepare(
            "UPDATE financial_periods
             SET parameters_version = ?, initial_income = ?, tithing_amount = ?, saving_amount = ?
             WHERE id = ?"
        )->execute([
            $params['id'],
            $mainIncome,
            $tithing,
            $saving,
            $period['id']
        ]);

        $existing = queryAll(
            "SELECT id, category_id FROM period_budgets WHERE period_id = ?",
            [$period['id']]
        );
        $existingMap = [];
        foreach ($existing as $row) {
            $existingMap[(int)$row['category_id']] = (int)$row['id'];
        }

        if (!empty($allocation)) {
            foreach ($allocation as $categoryId => $amount) {
                $allocated = (int)round($amount);
                if (isset($existingMap[$categoryId])) {
                    $db->prepare(
                        "UPDATE period_budgets SET allocated_amount = ? WHERE id = ?"
                    )->execute([$allocated, $existingMap[$categoryId]]);
                } else {
                    $db->prepare(
                        "INSERT INTO period_budgets (period_id, category_id, allocated_amount, spent_amount)
                         VALUES (?, ?, ?, 0)"
                    )->execute([$period['id'], $categoryId, $allocated]);
                }
            }

            $allocationKeys = array_keys($allocation);
            foreach ($existingMap as $categoryId => $budgetId) {
                if (!in_array($categoryId, $allocationKeys, true)) {
                    $db->prepare(
                        "UPDATE period_budgets SET allocated_amount = 0 WHERE id = ?"
                    )->execute([$budgetId]);
                }
            }
        }

        $db->commit();

        return [
            'synced' => true,
            'period_id' => $period['id'],
            'total_income' => $totalIncome,
            'spendable' => $spendable
        ];
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Vérifie si une période doit être clôturée
 */
function checkPeriodEnd() {
    $period = getActivePeriod();
    if (!$period) return false;
    
    $today = new DateTime();
    $endDate = new DateTime($period['end_date']);
    
    if ($today >= $endDate) {
        // Créer une nouvelle période avec le revenu par défaut
        $params = getCurrentParameters();
        createPeriod($params['default_income'], $params['id']);
        return true;
    }
    
    return false;
}

/**
 * Récupère toutes les périodes
 */
function getAllPeriods($limit = 12) {
    $sql = "SELECT * FROM financial_periods 
            ORDER BY start_date DESC 
            LIMIT ?";
    return queryAll($sql, [$limit]);
}
?>

<?php
// Endpoint AJAX: vérifier fin de période
if (php_sapi_name() !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__ && isset($_GET['action']) && $_GET['action'] === 'check') {
    header('Content-Type: application/json');
    $newPeriod = checkPeriodEnd();
    echo json_encode(['new_period' => (bool)$newPeriod]);
    exit;
}
