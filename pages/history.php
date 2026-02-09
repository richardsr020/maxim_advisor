<?php
// history.php - Historique financier
require_once __DIR__ . '/../includes/period.php';
require_once __DIR__ . '/../includes/transactions.php';

// Sélection de période
$periodId = $_GET['period'] ?? null;
if (!$periodId) {
    $active = getActivePeriod();
    $periodId = $active ? $active['id'] : null;
}

// Récupérer toutes les périodes
$periods = getAllPeriods(24);

// Récupérer les transactions de la période sélectionnée
$transactions = [];
if ($periodId) {
    $sql = "SELECT 
                t.*,
                c.name as category_name,
                c.icon,
                c.color,
                p.start_date,
                p.end_date
            FROM transactions t
            LEFT JOIN budget_categories c ON t.category_id = c.id
            JOIN financial_periods p ON t.period_id = p.id
            WHERE t.period_id = ?
            ORDER BY t.date DESC, t.created_at DESC";
    
    $transactions = queryAll($sql, [$periodId]);
}

// Filtrer par type
$typeFilter = $_GET['type'] ?? 'all';
if ($typeFilter != 'all' && $periodId) {
    $transactions = array_filter($transactions, function($t) use ($typeFilter) {
        return $t['type'] == $typeFilter;
    });
}

// Calculer les totaux
$totals = [
    'income_main' => 0,
    'income_extra' => 0,
    'expense' => 0,
    'tithing' => 0,
    'saving' => 0
];

foreach ($transactions as $t) {
    $totals[$t['type']] += $t['amount'];
    $totals['tithing'] += $t['tithing_paid'];
    $totals['saving'] += $t['saving_paid'];
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
                <span class="stat-label tone-tithing">Dîme payée</span>
                <span class="stat-amount tithing tone-tithing">
                    <?php echo formatCurrency($totals['tithing']); ?>
                </span>
            </div>
            <div class="summary-stat">
                <span class="stat-label tone-saving">Épargne accumulée</span>
                <span class="stat-amount saving tone-saving">
                    <?php echo formatCurrency($totals['saving']); ?>
                </span>
            </div>
        </div>
    </div>
    
    <div class="transactions-list">
        <h3>Transactions (<?php echo count($transactions); ?>)</h3>
        
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
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="8" class="no-data">Aucune transaction dans cette période</td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $t): ?>
                <tr class="type-<?php echo $t['type']; ?>">
                    <td><?php echo date('d/m/Y', strtotime($t['date'])); ?></td>
                    <td class="type-cell">
                        <?php
                        $typeLabels = [
                            'income_main' => '💼 Principal',
                            'income_extra' => '💰 Occasionnel',
                            'expense' => '💸 Dépense'
                        ];
                        echo $typeLabels[$t['type']] ?? $t['type'];
                        ?>
                    </td>
                    <td class="category-cell">
                        <?php if ($t['category_name']): ?>
                        <span class="category-badge" style="background-color: <?php echo $t['color']; ?>20; color: <?php echo $t['color']; ?>">
                            <?php echo $t['icon']; ?> <?php echo htmlspecialchars($t['category_name']); ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($t['description']); ?>
                        <?php if (!empty($t['comment'])): ?>
                        <br><small class="comment">📝 <?php echo htmlspecialchars($t['comment']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="amount-cell 
                        <?php echo $t['type'] == 'expense' ? 'expense' : 'income'; ?>">
                        <?php echo formatCurrency($t['amount']); ?>
                    </td>
                    <td class="amount-cell tithing">
                        <?php echo $t['tithing_paid'] > 0 ? formatCurrency($t['tithing_paid']) : '-'; ?>
                    </td>
                    <td class="amount-cell saving">
                        <?php echo $t['saving_paid'] > 0 ? formatCurrency($t['saving_paid']) : '-'; ?>
                    </td>
                    <td class="amount-cell balance">
                        <?php echo formatCurrency($t['balance_after']); ?>
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
