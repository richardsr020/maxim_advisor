<?php
// install.php - Installation du système
session_start();
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Maxim Advisor</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="install-page">
    <div class="install-container">
        <h1>🔧 Installation de Maxim Advisor</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            installSystem();
        } else {
            displayInstallForm();
        }
        
        function displayInstallForm() {
            ?>
            <div class="install-steps">
                <div class="step">
                    <h3>Étape 1 : Vérifications système</h3>
                    <ul>
                        <li>✅ PHP 7.4+ : <?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'OK' : 'NOK'; ?></li>
                        <li>✅ SQLite3 : <?php echo extension_loaded('sqlite3') ? 'OK' : 'NOK'; ?></li>
                        <li>✅ PDO SQLite : <?php echo extension_loaded('pdo_sqlite') ? 'OK' : 'NOK'; ?></li>
                        <li>✅ Répertoire database : <?php echo is_writable(__DIR__ . '/database') ? 'OK' : 'NOK'; ?></li>
                        <li>✅ Répertoire exports : <?php echo is_writable(__DIR__ . '/exports') ? 'OK' : 'NOK'; ?></li>
                    </ul>
                </div>
                
                <div class="step">
                    <h3>Étape 2 : Configuration initiale</h3>
                    <form method="POST" class="install-form">
                        <div class="form-group">
                            <label>Revenu mensuel par défaut (FC)</label>
                            <input type="number" name="default_income" value="120000" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Mot de passe administrateur</label>
                            <input type="password" name="admin_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirmer le mot de passe</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Installer le système</button>
                    </form>
                </div>
            </div>
            <?php
        }
        
        function installSystem() {
            try {
                global $DEFAULT_BUDGET_PERCENTAGES;
                // Créer les répertoires
                $dirs = ['database', 'exports/json', 'exports/backups', 'assets/css', 'assets/js', 'assets/icons'];
                foreach ($dirs as $dir) {
                    if (!file_exists(__DIR__ . '/' . $dir)) {
                        mkdir(__DIR__ . '/' . $dir, 0755, true);
                    }
                }
                
                // Créer la base de données
                $dbPath = __DIR__ . '/database/maxim_advisor.sqlite';
                $db = new PDO('sqlite:' . $dbPath);
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Exécuter le schéma SQL
                $schema = file_get_contents(__DIR__ . '/database/schema.sql');
                $db->exec($schema);
                
                // Insérer les données initiales
                $defaultIncome = $_POST['default_income'];
                $adminPassword = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                
                // Insérer paramètres
                $stmt = $db->prepare("
                    INSERT INTO parameters (version, default_income, currency, tithing_percent, main_saving_percent, extra_saving_percent) 
                    VALUES (1, ?, 'FC', 10, 20, 50)
                ");
                $stmt->execute([$defaultIncome]);
                $parametersId = $db->lastInsertId();
                
                // Insérer pourcentages de budget par défaut
                if (!empty($DEFAULT_BUDGET_PERCENTAGES)) {
                    $stmt = $db->prepare("
                        INSERT INTO budget_percentages (parameters_version, category_id, percentage)
                        VALUES (?, ?, ?)
                    ");
                    foreach ($DEFAULT_BUDGET_PERCENTAGES as $categoryId => $percentage) {
                        $stmt->execute([$parametersId, $categoryId, $percentage]);
                    }
                }
                
                // Insérer utilisateur admin
                $stmt = $db->prepare("
                    INSERT INTO users (username, password_hash, is_admin) 
                    VALUES ('admin', ?, 1)
                ");
                $stmt->execute([$adminPassword]);
                
                echo '<div class="success-message">';
                echo '<h2>✅ Installation réussie !</h2>';
                echo '<p>Le système a été installé avec succès.</p>';
                echo '<p><a href="/login.php" class="btn btn-primary">Se connecter</a></p>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="error-message">';
                echo '<h2>❌ Erreur d\'installation</h2>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        ?>
    </div>
</body>
</html>
