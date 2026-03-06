<?php
// dashboard.php - Page principale
require_once __DIR__ . '/../includes/period.php';
require_once __DIR__ . '/../includes/budgets.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/transactions.php';
require_once __DIR__ . '/../includes/calculations.php';
require_once __DIR__ . '/../includes/flash.php';

$error = null;

// Gestion des formulaires rapides (modals)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_income'])) {
            $incomeType = $_POST['income_type'] ?? 'main';
            $amount = (int)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            
            if ($amount <= 0) {
                throw new Exception("Le montant doit être positif");
            }
            if ($description === '') {
                throw new Exception("La description est obligatoire");
            }
            
            if ($incomeType === 'main') {
                $params = getCurrentParameters();
                createPeriod($amount, $params['id']);
                addFlashMessage("Nouvelle période créée et revenu principal enregistré.", 'success');
                header('Location: ?page=dashboard');
                exit;
            } else {
                $extraIncomeToSavingsOnly = isset($_POST['extra_income_to_savings_only']);
                recordExtraIncome($amount, $description, $extraIncomeToSavingsOnly);
                addFlashMessage("Revenu occasionnel enregistré.", 'success');
                header('Location: ?page=dashboard');
                exit;
            }
        }

        if (isset($_POST['spend_saving'])) {
            $amount = (int)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            withdrawFromSaving($amount, $description);
            addFlashMessage("Retrait d'épargne enregistré.", 'success');
            header('Location: ?page=dashboard');
            exit;
        }

        if (isset($_POST['pay_tithing'])) {
            $amount = (int)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            payTithingToChurch($amount, $description);
            addFlashMessage("Versement de dîme enregistré.", 'success');
            header('Location: ?page=dashboard');
            exit;
        }
        
        if (isset($_POST['add_expense'])) {
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $amount = (int)($_POST['amount'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $comment = $_POST['comment'] ?? null;
            
            if ($categoryId <= 0) {
                throw new Exception("Catégorie invalide");
            }
            if ($amount <= 0) {
                throw new Exception("Le montant doit être positif");
            }
            if ($description === '') {
                throw new Exception("La description est obligatoire");
            }
            
            recordExpense($categoryId, $amount, $description, $comment);
            addFlashMessage("Dépense enregistrée.", 'success');
            header('Location: ?page=dashboard');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        addFlashMessage($error, 'error');
    }
}

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'income_main':
            addFlashMessage("Nouvelle période créée et revenu principal enregistré.", 'success');
            break;
        case 'income_extra':
            addFlashMessage("Revenu occasionnel enregistré.", 'success');
            break;
        case 'expense':
            addFlashMessage("Dépense enregistrée.", 'success');
            break;
    }
}

$activePeriod = getActivePeriod();

if (!$activePeriod) {
    // Aucune période active, proposer d'en créer une
    include __DIR__ . '/../templates/components/no-period.php';
    return;
}

// Récupérer les données
$budgets = getBudgetSummary($activePeriod['id']);
$notifications = getLatestNotifications(3);
$totals = calculateTotals($activePeriod['id']);

// Calculs pour l'affichage
$totalAllocated = array_sum(array_column($budgets, 'allocated_amount'));
$totalSpent = array_sum(array_column($budgets, 'spent_amount'));
$totalRemaining = $totalAllocated - $totalSpent;
$overallPercentage = $totalAllocated > 0 ? round(($totalSpent / $totalAllocated) * 100) : 0;
$globalSavingBalance = getAvailableSavingBalance();
$globalTithingBalance = getAvailableTithingBalance();
$savingIncrease = getFundIncreaseMetrics('saving', $globalSavingBalance, $activePeriod['id']);
$tithingIncrease = getFundIncreaseMetrics('tithing', $globalTithingBalance, $activePeriod['id']);
$savingDecrease = getFundDecreaseMetrics('saving', $globalSavingBalance, $activePeriod['id']);
$tithingDecrease = getFundDecreaseMetrics('tithing', $globalTithingBalance, $activePeriod['id']);
$budgetIncrease = getBudgetIncreaseMetrics($activePeriod['id'], $totalAllocated);
$budgetDecrease = getBudgetDecreaseMetrics($activePeriod['id'], $totalRemaining);
$budgetIncreaseByCategory = $budgetIncrease['by_category'] ?? [];
?>

<div class="dashboard-container">
    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- En-tête de période -->
    <div class="period-header">
        <div class="period-info">
            <h2>Période active</h2>
            <p class="period-dates">
                <?php echo date('d M', strtotime($activePeriod['start_date'])); ?>
                - 
                <?php echo date('d M', strtotime($activePeriod['end_date'])); ?>
            </p>
        </div>
        
        <div class="period-summary">
            <div class="summary-card">
                <span class="summary-label tone-balance">Solde disponible</span>
                <span class="summary-amount tone-balance"><?php echo formatCurrency($totalRemaining); ?></span>
                <span class="summary-percentage">(<?php echo $overallPercentage; ?>% utilisé)</span>
            </div>
            
            <div class="summary-actions">
                <button class="btn btn-primary" onclick="showAddIncomeModal()">
                    + Revenu
                </button>
                <button class="btn btn-secondary" onclick="showAddExpenseModal()">
                    + Dépense
                </button>
                <a class="btn btn-secondary" href="/?page=stats">
                    📈 Statistiques
                </a>
            </div>
        </div>
    </div>
    
    <!-- Section fonds obligatoires -->
    <div class="mandatory-funds">
        <div class="fund-card tithing">
            <button class="fund-action-btn" type="button" onclick="showModal('pay-tithing-modal')" title="Verser la dîme">
                ↗
            </button>
            <div class="fund-icon">⛪</div>
            <div class="fund-details">
                <h3 class="tone-budget">Dîme disponible</h3>
                <p class="fund-amount tone-tithing"><?php echo formatCurrency($globalTithingBalance); ?></p>
                <p class="fund-status status-paid">Total cumulé - versements</p>
                <?php if (($tithingIncrease['amount'] ?? 0) > 0): ?>
                <p class="fund-delta fund-delta-positive">
                    +<?php echo formatCurrency($tithingIncrease['amount']); ?>
                    (<?php echo $tithingIncrease['percentage']; ?>%)
                </p>
                <?php endif; ?>
                <?php if (($tithingDecrease['amount'] ?? 0) > 0): ?>
                <p class="fund-delta fund-delta-negative">
                    -<?php echo formatCurrency($tithingDecrease['amount']); ?>
                    (<?php echo $tithingDecrease['percentage']; ?>%)
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="fund-card saving">
            <button class="fund-action-btn" type="button" onclick="showModal('spend-saving-modal')" title="Utiliser l'épargne">
                ↗
            </button>
            <div class="fund-icon">💰</div>
            <div class="fund-details">
                <h3 class="tone-budget">Épargne (globale)</h3>
                <p class="fund-amount tone-saving"><?php echo formatCurrency($globalSavingBalance); ?></p>
                <p class="fund-status status-blocked">Somme des épargnes - retraits</p>
                <?php if (($savingIncrease['amount'] ?? 0) > 0): ?>
                <p class="fund-delta fund-delta-positive">
                    +<?php echo formatCurrency($savingIncrease['amount']); ?>
                    (<?php echo $savingIncrease['percentage']; ?>%)
                </p>
                <?php endif; ?>
                <?php if (($savingDecrease['amount'] ?? 0) > 0): ?>
                <p class="fund-delta fund-delta-negative">
                    -<?php echo formatCurrency($savingDecrease['amount']); ?>
                    (<?php echo $savingDecrease['percentage']; ?>%)
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="fund-card budget">
            <div class="fund-icon">📊</div>
            <div class="fund-details">
                <h3 class="tone-budget">Budgets totaux</h3>
                <p class="fund-amount tone-budget"><?php echo formatCurrency($totalAllocated); ?></p>
                <p class="fund-status status-<?php echo getBudgetStatus($overallPercentage); ?>">
                    <?php echo $overallPercentage; ?>% utilisé
                </p>
                <?php if (($budgetIncrease['amount'] ?? 0) > 0): ?>
                <p class="fund-delta fund-delta-positive">
                    +<?php echo formatCurrency($budgetIncrease['amount']); ?>
                    (<?php echo $budgetIncrease['percentage']; ?>%)
                </p>
                <?php endif; ?>
                <?php if (($budgetDecrease['amount'] ?? 0) > 0): ?>
                <p class="fund-delta fund-delta-negative">
                    -<?php echo formatCurrency($budgetDecrease['amount']); ?>
                    (<?php echo $budgetDecrease['percentage']; ?>%)
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Tableau des budgets détaillés -->
    <div class="budgets-detail">
        <h3>Budgets détaillés</h3>
        
        <table class="budgets-table">
            <thead>
                <tr>
                    <th>Catégorie</th>
                    <th>Alloué</th>
                    <th>Dépensé</th>
                    <th>Restant</th>
                    <th>Utilisation</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($budgets as $budget): ?>
                <tr class="category-<?php echo $budget['status']; ?>">
                    <td class="category-name">
                        <span class="category-icon"><?php echo $budget['icon']; ?></span>
                        <?php echo $budget['name']; ?>
                        <?php if ($budget['is_unexpected']): ?>
                        <span class="unexpected-badge">Imprévu</span>
                        <?php endif; ?>
                    </td>
                    <td class="amount allocated">
                        <?php echo formatCurrency($budget['allocated_amount']); ?>
                        <?php $categoryBoost = (int)($budgetIncreaseByCategory[$budget['category_id']] ?? 0); ?>
                        <?php if ($categoryBoost > 0): ?>
                        <span class="budget-surplus">+<?php echo formatCurrency($categoryBoost); ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="amount spent"><?php echo formatCurrency($budget['spent_amount']); ?></td>
                    <td class="amount remaining <?php echo $budget['is_over'] ? 'negative' : ''; ?>">
                        <?php echo formatCurrency($budget['remaining']); ?>
                    </td>
                    <td class="usage">
                        <?php echo getCategoryProgressBar($budget['percentage_used'], $budget['status']); ?>
                        <span class="percentage"><?php echo $budget['percentage_used']; ?>%</span>
                        <?php if ($budget['status'] == 'over'): ?>
                        <span class="status-badge over">DÉPASSÉ</span>
                        <?php elseif ($budget['status'] == 'critical'): ?>
                        <span class="status-badge critical">CRITIQUE</span>
                        <?php elseif ($budget['status'] == 'warning'): ?>
                        <span class="status-badge warning">ATTENTION</span>
                        <?php endif; ?>
                    </td>
                    <td class="actions">
                        <button class="btn-small" 
                                onclick="addExpenseToCategory(<?php echo $budget['category_id']; ?>)">
                            Dépenser
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Notifications IA -->
    <?php if (!empty($notifications)): ?>
    <div class="notifications-section">
        <h3>Notifications IA</h3>
        <div class="notifications-list">
            <?php
            $labels = [
                'week' => 'Hebdomadaire',
                'month' => 'Mensuel',
                'year' => 'Annuel'
            ];
            ?>
            <?php foreach ($notifications as $notification): ?>
            <div class="ai-notification notification-<?php echo $notification['timeframe']; ?>" data-notification-id="<?php echo $notification['id']; ?>">
                <div class="notification-header">
                    <span class="notification-badge">
                        <?php echo $labels[$notification['timeframe']] ?? $notification['timeframe']; ?>
                    </span>
                    <span class="notification-range">
                        <?php echo date('d/m', strtotime($notification['range_start'])); ?>
                        →
                        <?php echo date('d/m', strtotime($notification['range_end'])); ?>
                    </span>
                    <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                </div>
                <div class="notification-content">
                    <?php echo $notification['analysis_html']; ?>
                </div>
                <?php if (!$notification['is_read']): ?>
                <button class="notification-dismiss" onclick="dismissNotification(<?php echo $notification['id']; ?>)">
                    ✓ Lu
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
</div>

<!-- Modals (chargés globalement dans templates/footer.php) -->

<script src="assets/js/dashboard.js"></script>
