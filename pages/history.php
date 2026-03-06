<?php
// history.php - Historique financier
require_once __DIR__ . '/../includes/period.php';
require_once __DIR__ . '/../includes/transactions.php';

// Sélection de période
$periodId = isset($_GET['period']) ? (int)$_GET['period'] : null;
if (!$periodId) {
    $active = getActivePeriod();
    $periodId = $active ? (int)$active['id'] : null;
}

// Récupérer toutes les périodes
$periods = getAllPeriods(24);

// Récupérer les opérations de la période sélectionnée
$typeFilter = $_GET['type'] ?? 'all';
$allOperations = [];
if ($periodId) {
    $transactions = queryAll(
        "SELECT 
                t.id,
                t.period_id,
                t.type as op_type,
                t.category_id,
                t.amount,
                t.description,
                t.comment,
                t.date,
                t.tithing_paid,
                t.saving_paid,
                t.balance_after,
                t.created_at,
                c.name as category_name,
                c.icon,
                c.color,
                p.start_date,
                p.end_date
         FROM transactions t
         LEFT JOIN budget_categories c ON t.category_id = c.id
         JOIN financial_periods p ON t.period_id = p.id
         WHERE t.period_id = ?
         ORDER BY t.date DESC, t.created_at DESC",
        [$periodId]
    );

    foreach ($transactions as $tx) {
        $tx['source'] = 'transaction';
        $allOperations[] = $tx;
    }

    $savingWithdrawals = queryAll(
        "SELECT 
            sw.id,
            sw.period_id,
            'saving_withdrawal' as op_type,
            NULL as category_id,
            sw.amount,
            sw.description,
            '' as comment,
            sw.date,
            0 as tithing_paid,
            0 as saving_paid,
            NULL as balance_after,
            sw.created_at,
            'Épargne' as category_name,
            '💰' as icon,
            '#2563eb' as color,
            p.start_date,
            p.end_date
         FROM saving_withdrawals sw
         JOIN financial_periods p ON sw.period_id = p.id
         WHERE sw.period_id = ?
         ORDER BY sw.date DESC, sw.created_at DESC",
        [$periodId]
    );

    foreach ($savingWithdrawals as $row) {
        $row['source'] = 'saving_withdrawal';
        $allOperations[] = $row;
    }

    $tithingPayments = queryAll(
        "SELECT 
            tp.id,
            tp.period_id,
            'tithing_payment' as op_type,
            NULL as category_id,
            tp.amount,
            tp.description,
            '' as comment,
            tp.date,
            0 as tithing_paid,
            0 as saving_paid,
            NULL as balance_after,
            tp.created_at,
            'Dîme' as category_name,
            '⛪' as icon,
            '#16a34a' as color,
            p.start_date,
            p.end_date
         FROM tithing_payments tp
         JOIN financial_periods p ON tp.period_id = p.id
         WHERE tp.period_id = ?
         ORDER BY tp.date DESC, tp.created_at DESC",
        [$periodId]
    );

    foreach ($tithingPayments as $row) {
        $row['source'] = 'tithing_payment';
        $allOperations[] = $row;
    }

    usort($allOperations, function ($a, $b) {
        $dateCmp = strcmp((string)$b['date'], (string)$a['date']);
        if ($dateCmp !== 0) {
            return $dateCmp;
        }
        $createdCmp = strcmp((string)$b['created_at'], (string)$a['created_at']);
        if ($createdCmp !== 0) {
            return $createdCmp;
        }
        return ((int)$b['id']) <=> ((int)$a['id']);
    });
}

// Calculer les totaux
$totals = [
    'income_main' => 0,
    'income_extra' => 0,
    'expense' => 0,
    'saving_withdrawal' => 0,
    'tithing_payment' => 0,
    'tithing_reserved' => 0,
    'saving_reserved' => 0
];

foreach ($allOperations as $operation) {
    $type = $operation['op_type'];
    if (isset($totals[$type])) {
        $totals[$type] += (int)$operation['amount'];
    }
    $totals['tithing_reserved'] += (int)($operation['tithing_paid'] ?? 0);
    $totals['saving_reserved'] += (int)($operation['saving_paid'] ?? 0);
}

$totals['tithing_available'] = max($totals['tithing_reserved'] - $totals['tithing_payment'], 0);
$totals['saving_available'] = max($totals['saving_reserved'] - $totals['saving_withdrawal'], 0);

// Filtrer par type
$operations = $allOperations;
if ($typeFilter !== 'all' && $periodId) {
    $operations = array_values(array_filter($allOperations, function ($operation) use ($typeFilter) {
        return $operation['op_type'] === $typeFilter;
    }));
}
?>

<div class="history-container">
    <h2>📜 Historique financier</h2>
    
    <div class="history-controls">
        <div class="period-selector">
            <label>Période :</label>
            <select onchange="changePeriod(this.value)">
                <?php foreach ($periods as $p): ?>
                <option value="<?php echo $p['id']; ?>" 
                    <?php echo $p['id'] == $periodId ? 'selected' : ''; ?>>
                    <?php echo date('d/m/Y', strtotime($p['start_date'])); ?> 
                    - 
                    <?php echo date('d/m/Y', strtotime($p['end_date'])); ?>
                    <?php echo $p['is_active'] ? ' (Actif)' : ''; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="type-filters">
            <a href="?page=history&period=<?php echo $periodId; ?>&type=all" 
               class="filter-btn <?php echo $typeFilter == 'all' ? 'active' : ''; ?>">
               Tous
            </a>
            <a href="?page=history&period=<?php echo $periodId; ?>&type=income_main" 
               class="filter-btn <?php echo $typeFilter == 'income_main' ? 'active' : ''; ?>">
               Revenus principaux
            </a>
            <a href="?page=history&period=<?php echo $periodId; ?>&type=income_extra" 
               class="filter-btn <?php echo $typeFilter == 'income_extra' ? 'active' : ''; ?>">
               Revenus occasionnels
            </a>
            <a href="?page=history&period=<?php echo $periodId; ?>&type=expense" 
               class="filter-btn <?php echo $typeFilter == 'expense' ? 'active' : ''; ?>">
               Dépenses
            </a>
            <a href="?page=history&period=<?php echo $periodId; ?>&type=saving_withdrawal" 
               class="filter-btn <?php echo $typeFilter == 'saving_withdrawal' ? 'active' : ''; ?>">
               Retraits épargne
            </a>
            <a href="?page=history&period=<?php echo $periodId; ?>&type=tithing_payment" 
               class="filter-btn <?php echo $typeFilter == 'tithing_payment' ? 'active' : ''; ?>">
               Versements dîme
            </a>
        </div>
    </div>
    
    <?php if ($periodId): ?>
    <div class="period-summary-card">
        <h3>Résumé de la période</h3>
        <div class="summary-stats">
            <div class="summary-stat">
                <span class="stat-label tone-income">Revenus totaux</span>
                <span class="stat-amount income tone-income">
                    <?php echo formatCurrency($totals['income_main'] + $totals['income_extra']); ?>
                </span>
            </div>
            <div class="summary-stat">
                <span class="stat-label tone-expense">Dépenses totales</span>
                <span class="stat-amount expense tone-expense">
                    <?php echo formatCurrency($totals['expense']); ?>
                </span>
            </div>
            <div class="summary-stat">
                <span class="stat-label tone-tithing">Dîme (réservée / versée / dispo)</span>
                <span class="stat-amount tithing tone-tithing">
                    <?php echo formatCurrency($totals['tithing_reserved']); ?>
                    / <?php echo formatCurrency($totals['tithing_payment']); ?>
                    / <?php echo formatCurrency($totals['tithing_available']); ?>
                </span>
            </div>
            <div class="summary-stat">
                <span class="stat-label tone-saving">Épargne (réservée / retirée / dispo)</span>
                <span class="stat-amount saving tone-saving">
                    <?php echo formatCurrency($totals['saving_reserved']); ?>
                    / <?php echo formatCurrency($totals['saving_withdrawal']); ?>
                    / <?php echo formatCurrency($totals['saving_available']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="transactions-list">
        <h3>Opérations (<?php echo count($operations); ?>)</h3>
        
        <table class="transactions-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Catégorie</th>
                    <th>Description</th>
                    <th>Montant</th>
                    <th>Dîme</th>
                    <th>Épargne</th>
                    <th>Solde après</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($operations)): ?>
                <tr>
                    <td colspan="8" class="no-data">Aucune opération dans cette période</td>
                </tr>
                <?php else: ?>
                <?php foreach ($operations as $operation): ?>
                <tr class="type-<?php echo $operation['op_type']; ?>">
                    <td><?php echo date('d/m/Y', strtotime($operation['date'])); ?></td>
                    <td class="type-cell">
                        <?php
                        $typeLabels = [
                            'income_main' => '💼 Principal',
                            'income_extra' => '💰 Occasionnel',
                            'expense' => '💸 Dépense',
                            'saving_withdrawal' => '🎯 Retrait épargne',
                            'tithing_payment' => '⛪ Versement dîme'
                        ];
                        echo $typeLabels[$operation['op_type']] ?? $operation['op_type'];
                        ?>
                    </td>
                    <td class="category-cell">
                        <?php if (!empty($operation['category_name'])): ?>
                        <span class="category-badge" style="background-color: <?php echo $operation['color']; ?>20; color: <?php echo $operation['color']; ?>">
                            <?php echo $operation['icon']; ?> <?php echo htmlspecialchars($operation['category_name']); ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($operation['description']); ?>
                        <?php if (!empty($operation['comment'])): ?>
                        <br><small class="comment">📝 <?php echo htmlspecialchars($operation['comment']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="amount-cell 
                        <?php echo in_array($operation['op_type'], ['expense', 'saving_withdrawal', 'tithing_payment'], true) ? 'expense' : 'income'; ?>">
                        <?php echo formatCurrency($operation['amount']); ?>
                    </td>
                    <td class="amount-cell tithing">
                        <?php if ((int)$operation['tithing_paid'] > 0): ?>
                            <?php echo formatCurrency($operation['tithing_paid']); ?>
                        <?php elseif ($operation['op_type'] === 'tithing_payment'): ?>
                            Versée
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="amount-cell saving">
                        <?php if ((int)$operation['saving_paid'] > 0): ?>
                            <?php echo formatCurrency($operation['saving_paid']); ?>
                        <?php elseif ($operation['op_type'] === 'saving_withdrawal'): ?>
                            Retrait
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="amount-cell balance">
                        <?php echo $operation['balance_after'] !== null ? formatCurrency($operation['balance_after']) : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="no-period">
        <p>Aucune période disponible. Créez une nouvelle période pour commencer.</p>
    </div>
    <?php endif; ?>
</div>

<script>
function changePeriod(periodId) {
    window.location.href = '?page=history&period=' + periodId + '&type=<?php echo $typeFilter; ?>';
}
</script>
