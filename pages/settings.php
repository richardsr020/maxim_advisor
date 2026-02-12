<?php
// settings.php - Page des paramètres
require_once __DIR__ . '/../includes/parameters.php';
require_once __DIR__ . '/../includes/budgets.php';
require_once __DIR__ . '/../includes/period.php';
require_once __DIR__ . '/../includes/flash.php';

$currentParams = getCurrentParameters();
$categories = getAllCategories();
$budgetPercentages = getBudgetPercentages($currentParams['id']);
$history = getParametersHistory(5);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'default_income' => (int)$_POST['default_income'],
            'currency' => $_POST['currency'],
            'tithing_percent' => (int)$_POST['tithing_percent'],
            'main_saving_percent' => (int)$_POST['main_saving_percent'],
            'extra_saving_percent' => (int)$_POST['extra_saving_percent'],
            'budget_percentages' => []
        ];
        
        // Récupérer les pourcentages de budget
        $total = 0;
        foreach ($categories as $category) {
            $percentage = (int)$_POST['budget_' . $category['id']];
            $data['budget_percentages'][$category['id']] = $percentage;
            $total += $percentage;
        }
        
        // Valider
        if ($total != 100) {
            $error = "Les pourcentages de budget doivent totaliser 100% (actuellement: {$total}%)";
            addFlashMessage($error, 'warning');
        } else {
            $newVersion = createParameters($data);
            $newParams = getParameters($newVersion);
            $versionLabel = $newParams['version'] ?? $newVersion;
            addFlashMessage("Paramètres mis à jour avec succès (version {$versionLabel})", 'success');

            $syncResult = synchronizeActivePeriod($newVersion);
            if ($syncResult['synced']) {
                addFlashMessage('Synchronisation des calculs terminée pour la période active.', 'success');
            } else {
                addFlashMessage('Paramètres enregistrés. Aucune période active à synchroniser.', 'info');
            }

            header('Location: ?page=settings');
            exit;
        }
        
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
        addFlashMessage($error, 'error');
    }
}
?>

<div class="settings-container">
    <h2>⚙️ Paramètres du système</h2>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <form method="POST" class="settings-form">
        <div class="settings-section">
            <h3>Revenus et épargnes</h3>
            
            <div class="form-grid">
                <div class="form-group">
                    <label>Revenu mensuel par défaut (FC)</label>
                    <input type="number" name="default_income" 
                           value="<?php echo htmlspecialchars($currentParams['default_income']); ?>" 
                           required min="0">
                </div>
                
                <div class="form-group">
                    <label>Devise</label>
                    <input type="text" name="currency" 
                           value="<?php echo htmlspecialchars($currentParams['currency']); ?>" 
                           required maxlength="3">
                </div>
                
                <div class="form-group">
                    <label>Dîme (%)</label>
                    <input type="number" name="tithing_percent" 
                           value="<?php echo htmlspecialchars($currentParams['tithing_percent']); ?>" 
                           required min="0" max="100">
                </div>
                
                <div class="form-group">
                    <label>Épargne revenu principal (%)</label>
                    <input type="number" name="main_saving_percent" 
                           value="<?php echo htmlspecialchars($currentParams['main_saving_percent']); ?>" 
                           required min="0" max="100">
                </div>
                
                <div class="form-group">
                    <label>Épargne revenu occasionnel (%)</label>
                    <input type="number" name="extra_saving_percent" 
                           value="<?php echo htmlspecialchars($currentParams['extra_saving_percent']); ?>" 
                           required min="0" max="100">
                </div>
            </div>
        </div>
        
        <div class="settings-section">
            <h3>Répartition des budgets</h3>
            <p class="form-help">Total doit être égal à 100%</p>
            
            <div class="budget-percentages">
                <?php foreach ($categories as $category): ?>
                <div class="budget-category-input">
                    <div class="category-info">
                        <span class="category-icon"><?php echo $category['icon']; ?></span>
                        <span class="category-name"><?php echo htmlspecialchars($category['name']); ?></span>
                        <?php if ($category['is_unexpected']): ?>
                        <span class="unexpected-badge">Imprévu</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="percentage-input">
                        <input type="number" 
                               name="budget_<?php echo $category['id']; ?>"
                               value="<?php echo $budgetPercentages[$category['id']] ?? 0; ?>"
                               min="0" max="100" 
                               class="budget-percentage"
                               data-category="<?php echo $category['id']; ?>">
                        <span class="percentage-suffix">%</span>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <div class="budget-total">
                    <strong>Total :</strong>
                    <span id="budget-total">0</span>%
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Enregistrer les paramètres</button>
            <button type="reset" class="btn btn-secondary">Réinitialiser</button>
        </div>
    </form>
    
    <div class="settings-history">
        <h3>Historique des paramètres</h3>
        
        <table class="history-table">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Revenu</th>
                    <th>Dîme</th>
                    <th>Épargne</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $param): ?>
                <tr <?php echo $param['is_active'] ? 'class="active"' : ''; ?>>
                    <td>v<?php echo $param['version'] ?? $param['id']; ?></td>
                    <td><?php echo formatCurrency($param['default_income']); ?></td>
                    <td><?php echo $param['tithing_percent']; ?>%</td>
                    <td><?php echo $param['main_saving_percent']; ?>%</td>
                    <td><?php echo date('d/m/Y H:i', strtotime($param['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Calcul dynamique du total des pourcentages
document.querySelectorAll('.budget-percentage').forEach(input => {
    input.addEventListener('input', updateBudgetTotal);
});

function updateBudgetTotal() {
    let total = 0;
    document.querySelectorAll('.budget-percentage').forEach(input => {
        total += parseInt(input.value) || 0;
    });
    
    document.getElementById('budget-total').textContent = total;
    
    // Changer la couleur si différent de 100
    const totalElement = document.getElementById('budget-total');
    const totalWrapper = totalElement.closest('.budget-total');
    if (total === 100) {
        totalElement.textContent = '100 OK';
        if (totalWrapper) {
            totalWrapper.classList.add('is-valid');
            totalWrapper.classList.remove('is-invalid');
        }
    } else {
        totalElement.textContent = total;
        if (totalWrapper) {
            totalWrapper.classList.add('is-invalid');
            totalWrapper.classList.remove('is-valid');
        }
    }
}

// Initialiser le total
updateBudgetTotal();
</script>
