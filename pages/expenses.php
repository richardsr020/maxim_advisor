<?php
// expenses.php - Gestion des dépenses
require_once __DIR__ . '/../includes/budgets.php';
require_once __DIR__ . '/../includes/transactions.php';

$period = getActivePeriod();
$categories = getAllCategories();

// Filtrer par catégorie
$categoryFilter = $_GET['category'] ?? 'all';

// Récupérer les transactions de dépenses
$sql = "SELECT t.*, c.name as category_name, c.icon, c.color
        FROM transactions t
        JOIN budget_categories c ON t.category_id = c.id
        WHERE t.period_id = ? AND t.type = 'expense'";
        
$params = [$period['id']];

if ($categoryFilter != 'all') {
    $sql .= " AND t.category_id = ?";
    $params[] = $categoryFilter;
}

$sql .= " ORDER BY t.date DESC, t.created_at DESC";

$expenses = queryAll($sql, $params);

// Totaux
$sql = "SELECT 
            c.name,
            c.icon,
            SUM(t.amount) as total,
            COUNT(*) as count
        FROM transactions t
        JOIN budget_categories c ON t.category_id = c.id
        WHERE t.period_id = ? AND t.type = 'expense'
        GROUP BY c.id
        ORDER BY total DESC";

$categoryTotals = queryAll($sql, [$period['id']]);

// Ajouter une nouvelle dépense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    try {
        $categoryId = (int)$_POST['category_id'];
        $amount = (int)$_POST['amount'];
        $description = trim($_POST['description']);
        $comment = $_POST['comment'] ?? null;
        
        if ($amount <= 0) {
            $error = "Le montant doit être positif";
        } elseif (empty($description)) {
            $error = "La description est obligatoire";
        } else {
            $transactionId = recordExpense($categoryId, $amount, $description, $comment);
            $success = "Dépense enregistrée avec succès";
            
            // Recharger les données
            header("Location: ?page=expenses&added=" . $transactionId);
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="expenses-container">
    <h2>💰 Gestion des dépenses</h2>
    
    <div class="expenses-header">
        <div class="stats-summary">
            <h3>Résumé des dépenses</h3>
            <div class="stats-cards">
                <?php foreach ($categoryTotals as $cat): ?>
                <div class="stat-card" style="--stat-color: <?php echo $cat['color']; ?>">
                    <div class="stat-icon"><?php echo $cat['icon']; ?></div>
                    <div class="stat-details">
                        <div class="stat-name tone-category"><?php echo htmlspecialchars($cat['name']); ?></div>
                        <div class="stat-amount tone-category"><?php echo formatCurrency($cat['total']); ?></div>
                        <div class="stat-count"><?php echo $cat['count']; ?> dépenses</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="add-expense-form">
            <h3>Nouvelle dépense</h3>
            
            <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Catégorie</label>
                    <select name="category_id" required>
                        <option value="">Sélectionner une catégorie</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>">
                            <?php echo $cat['icon']; ?> <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Montant (FC)</label>
                    <input type="number" name="amount" required min="1" step="1">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" required maxlength="100">
                </div>
                
                <div class="form-group" id="comment-field" style="display: none;">
                    <label>Commentaire (obligatoire pour les imprévus)</label>
                    <textarea name="comment" rows="2" maxlength="200"></textarea>
                </div>
                
                <button type="submit" name="add_expense" class="btn btn-primary btn-block">
                    Enregistrer la dépense
                </button>
            </form>
        </div>
    </div>
    
    <div class="expenses-list">
        <div class="list-header">
            <h3>Historique des dépenses</h3>
            
            <div class="filters">
                <select id="category-filter" onchange="filterByCategory()">
                    <option value="all">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" 
                        <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo $cat['icon']; ?> <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <table class="expenses-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Catégorie</th>
                    <th>Description</th>
                    <th>Montant</th>
                    <th>Commentaire</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                <tr>
                    <td colspan="5" class="no-data">Aucune dépense enregistrée</td>
                </tr>
                <?php else: ?>
                <?php foreach ($expenses as $expense): ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($expense['date'])); ?></td>
                    <td class="category-cell">
                        <span class="category-badge" style="background-color: <?php echo $expense['color']; ?>">
                            <?php echo $expense['icon']; ?> <?php echo htmlspecialchars($expense['category_name']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($expense['description']); ?></td>
                    <td class="amount-cell expense tone-expense"><?php echo formatCurrency($expense['amount']); ?></td>
                    <td class="comment-cell">
                        <?php if (!empty($expense['comment'])): ?>
                        <span class="comment" title="<?php echo htmlspecialchars($expense['comment']); ?>">
                            📝
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Afficher/masquer le champ commentaire
document.querySelector('select[name="category_id"]').addEventListener('change', function() {
    const categoryId = this.value;
    const commentField = document.getElementById('comment-field');
    
    // Vérifier si c'est la catégorie "Imprévus" (ID 5)
    if (categoryId == '5') {
        commentField.style.display = 'block';
        commentField.querySelector('textarea').required = true;
    } else {
        commentField.style.display = 'none';
        commentField.querySelector('textarea').required = false;
    }
});

function filterByCategory() {
    const category = document.getElementById('category-filter').value;
    window.location.href = '?page=expenses&category=' + category;
}

// Scroll vers la dépense ajoutée
<?php if (isset($_GET['added'])): ?>
window.scrollTo(0, document.querySelector('.expenses-list').offsetTop);
<?php endif; ?>
</script>
