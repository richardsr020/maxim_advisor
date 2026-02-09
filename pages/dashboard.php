<?php
// dashboard.php - Page principale
require_once __DIR__ . '/../includes/period.php';
require_once __DIR__ . '/../includes/budgets.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/transactions.php';
require_once __DIR__ . '/../includes/calculations.php';

$success = null;
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
                header('Location: ?page=dashboard&success=income_main');
                exit;
            } else {
                recordExtraIncome($amount, $description);
                header('Location: ?page=dashboard&success=income_extra');
                exit;
            }
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
            header('Location: ?page=dashboard&success=expense');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'income_main':
            $success = "Nouvelle période créée et revenu principal enregistré";
            break;
        case 'income_extra':
            $success = "Revenu occasionnel enregistré";
            break;
        case 'expense':
            $success = "Dépense enregistrée";
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
?>

<div class="dashboard-container">
    <?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
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
            <div class="fund-icon">⛪</div>
            <div class="fund-details">
                <h3 class="tone-tithing">Dîme</h3>
                <p class="fund-amount tone-tithing"><?php echo formatCurrency($activePeriod['tithing_amount']); ?></p>
                <p class="fund-status status-paid">✓ Payée</p>
            </div>
        </div>
        
        <div class="fund-card saving">
            <div class="fund-icon">💰</div>
            <div class="fund-details">
                <h3 class="tone-saving">Épargne</h3>
                <p class="fund-amount tone-saving"><?php echo formatCurrency($activePeriod['saving_amount']); ?></p>
                <p class="fund-status status-blocked">✓ Bloquée</p>
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
                    <td class="amount allocated"><?php echo formatCurrency($budget['allocated_amount']); ?></td>
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

<!-- Modals -->
<?php include __DIR__ . '/../templates/modals/add-income.php'; ?>
<?php include __DIR__ . '/../templates/modals/add-expense.php'; ?>

<script src="/assets/js/dashboard.js"></script>
