<?php
// parameters.php - Gestion des paramètres
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

/**
 * Récupère les paramètres courants
 */
function getCurrentParameters() {
    $sql = "SELECT * FROM parameters WHERE is_active = 1 LIMIT 1";
    $params = queryOne($sql);
    
    if (!$params) {
        // Créer des paramètres par défaut
        $params = createDefaultParameters();
    }
    
    return $params;
}

/**
 * Récupère des paramètres spécifiques
 */
function getParameters($version) {
    $sql = "SELECT * FROM parameters WHERE id = ?";
    $params = queryOne($sql, [$version]);
    
    if (!$params) {
        return getCurrentParameters();
    }
    
    return $params;
}

/**
 * Crée de nouveaux paramètres
 */
function createParameters($data) {
    $db = getDatabase();
    
    try {
        $db->beginTransaction();
        
        // Désactiver les anciens paramètres
        $db->exec("UPDATE parameters SET is_active = 0 WHERE is_active = 1");
        
        // Insérer les nouveaux paramètres
        $sql = "INSERT INTO parameters 
                (default_income, currency, tithing_percent, main_saving_percent, extra_saving_percent, is_active)
                VALUES (?, ?, ?, ?, ?, 1)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['default_income'],
            $data['currency'],
            $data['tithing_percent'],
            $data['main_saving_percent'],
            $data['extra_saving_percent']
        ]);
        
        $parametersId = $db->lastInsertId();
        
        // Insérer les pourcentages de budget
        foreach ($data['budget_percentages'] as $categoryId => $percentage) {
            $sql = "INSERT INTO budget_percentages (parameters_version, category_id, percentage)
                    VALUES (?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$parametersId, $categoryId, $percentage]);
        }
        
        $db->commit();
        
        return $parametersId;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Crée les paramètres par défaut
 */
function createDefaultParameters() {
    global $DEFAULT_BUDGET_PERCENTAGES;
    
    $data = [
        'default_income' => DEFAULT_INCOME,
        'currency' => CURRENCY,
        'tithing_percent' => TITHING_PERCENT,
        'main_saving_percent' => MAIN_SAVING_PERCENT,
        'extra_saving_percent' => EXTRA_SAVING_PERCENT,
        'budget_percentages' => $DEFAULT_BUDGET_PERCENTAGES
    ];
    
    return createParameters($data);
}

/**
 * Récupère l'historique des paramètres
 */
function getParametersHistory($limit = 10) {
    $sql = "SELECT * FROM parameters 
            ORDER BY created_at DESC 
            LIMIT ?";
    
    return queryAll($sql, [$limit]);
}

/**
 * Vérifie si les pourcentages totalisent 100%
 */
function validateBudgetPercentages($percentages) {
    $total = array_sum($percentages);
    return $total == 100;
}
?>
