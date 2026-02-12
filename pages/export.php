<?php
// export.php - Export des données
require_once __DIR__ . '/../includes/export.php';
require_once __DIR__ . '/../includes/period.php';
require_once __DIR__ . '/../includes/flash.php';

$periods = getAllPeriods();
$exports = getExportHistory();

// Exporter une période
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_period'])) {
    try {
        $periodId = (int)$_POST['period_id'];
        $result = exportPeriodToJSON($periodId);
        $success = "Export réussi: " . $result['filename'] . " (" . round($result['size']/1024, 2) . " KB)";
        addFlashMessage($success, 'success');
        $exports = getExportHistory(); // Recharger la liste
    } catch (Exception $e) {
        $error = "Erreur d'export: " . $e->getMessage();
        addFlashMessage($error, 'error');
    }
}

// Exporter une année
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_year'])) {
    try {
        $year = (int)$_POST['year'];
        $result = exportYearToJSON($year);
        $success = "Export annuel réussi: " . $result['filename'] . " (" . round($result['size']/1024, 2) . " KB)";
        addFlashMessage($success, 'success');
        $exports = getExportHistory();
    } catch (Exception $e) {
        $error = "Erreur d'export: " . $e->getMessage();
        addFlashMessage($error, 'error');
    }
}

// Télécharger un fichier
if (isset($_GET['download'])) {
    $filepath = $_GET['download'];
    if (file_exists($filepath)) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        readfile($filepath);
        exit;
    }
}

// Aperçu JSON
if (isset($_GET['preview'])) {
    $filepath = $_GET['preview'];
    if (file_exists($filepath)) {
        header('Content-Type: application/json; charset=utf-8');
        readfile($filepath);
        exit;
    }
}
?>

<div class="export-container">
    <h2>📤 Export des données</h2>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="export-options">
        <div class="export-card">
            <h3>📅 Exporter une période</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Sélectionner une période</label>
                    <select name="period_id" required>
                        <option value="">Choisir une période</option>
                        <?php foreach ($periods as $period): ?>
                        <option value="<?php echo $period['id']; ?>">
                            <?php echo date('d/m/Y', strtotime($period['start_date'])); ?> 
                            - 
                            <?php echo date('d/m/Y', strtotime($period['end_date'])); ?>
                            <?php echo $period['is_active'] ? ' (Actif)' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" name="export_period" class="btn btn-primary">
                    📥 Exporter en JSON
                </button>
            </form>
            
            <div class="export-info">
                <p><strong>Contenu de l'export :</strong></p>
                <ul>
                    <li>Paramètres utilisés</li>
                    <li>Budgets et dépenses</li>
                    <li>Transactions complètes</li>
                    <li>Alertes et statistiques</li>
                    <li>Analyse des habitudes</li>
                </ul>
            </div>
        </div>
        
        <div class="export-card">
            <h3>📊 Exporter une année</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Sélectionner une année</label>
                    <select name="year" required>
                        <option value="">Choisir une année</option>
                        <?php
                        // Générer les 5 dernières années
                        $currentYear = date('Y');
                        for ($i = 0; $i < 5; $i++): 
                            $year = $currentYear - $i;
                        ?>
                        <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <button type="submit" name="export_year" class="btn btn-primary">
                    📈 Exporter année complète
                </button>
            </form>
            
            <div class="export-info">
                <p><strong>Contenu de l'export annuel :</strong></p>
                <ul>
                    <li>Résumés de toutes les périodes</li>
                    <li>Totaux annuels</li>
                    <li>Tendances sur l'année</li>
                    <li>Statistiques comparatives</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="exports-history">
        <h3>Historique des exports</h3>
        
        <?php if (empty($exports)): ?>
        <p class="no-data">Aucun export réalisé</p>
        <?php else: ?>
        <table class="exports-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Fichier</th>
                    <th>Taille</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exports as $export): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($export['created_at'])); ?></td>
                    <td>
                        <span class="export-type <?php echo $export['export_type']; ?>">
                            <?php echo $export['export_type'] == 'period' ? '📅 Période' : '📊 Année'; ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(basename($export['file_path'])); ?></td>
                    <td>
                        <?php 
                        if (file_exists($export['file_path'])) {
                            echo round(filesize($export['file_path']) / 1024, 2) . ' KB';
                        } else {
                            echo '<span class="file-missing">Fichier manquant</span>';
                        }
                        ?>
                    </td>
                    <td class="actions-cell">
                        <?php if (file_exists($export['file_path'])): ?>
                        <a href="?page=export&download=<?php echo urlencode($export['file_path']); ?>" 
                           class="btn btn-small" download>
                           ⬇️ Télécharger
                        </a>
                        <button class="btn btn-small btn-secondary" 
                                onclick="previewExport('<?php echo $export['file_path']; ?>')">
                           👁️ Aperçu
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<div id="preview-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Aperçu JSON</h3>
            <button class="close-modal" onclick="closePreview()">×</button>
        </div>
        <div class="modal-body">
            <pre id="json-preview"></pre>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closePreview()">Fermer</button>
        </div>
    </div>
</div>

<script>
function previewExport(filepath) {
    fetch('?page=export&preview=' + encodeURIComponent(filepath))
        .then(response => response.json())
        .then(data => {
            document.getElementById('json-preview').textContent = 
                JSON.stringify(data, null, 2);
            document.getElementById('preview-modal').style.display = 'flex';
        })
        .catch(error => {
            alert('Erreur de chargement: ' + error.message);
        });
}

function closePreview() {
    document.getElementById('preview-modal').style.display = 'none';
}

// Fermer la modal en cliquant à l'extérieur
document.getElementById('preview-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePreview();
    }
});
</script>
