<?php
// habits.php - Détection des mauvaises habitudes
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/period.php';
require_once __DIR__ . '/budgets.php';

/**
 * Analyse les habitudes sur plusieurs périodes
 */
function analyzeSpendingHabits($periodsCount = 3) {
    $periods = getAllPeriods($periodsCount);
    $habits = [];
    
    if (count($periods) < 2) {
        return $habits; // Pas assez de données
    }
    
    // 1. Vérifier les dépassements répétés
    foreach ($periods as $period) {
        $budgets = getPeriodBudgets($period['id']);
        
        foreach ($budgets as $budget) {
            if ($budget['is_over']) {
                $habitKey = "over_{$budget['category_id']}";
                $habits[$habitKey] = ($habits[$habitKey] ?? 0) + 1;
            }
        }
    }
    
    // 2. Identifier les habitudes persistantes
    $persistentHabits = [];
    foreach ($habits as $habit => $count) {
        if ($count >= 2) { // Au moins 2 périodes sur 3
            $categoryId = explode('_', $habit)[1];
            $category = getCategory($categoryId);
            $persistentHabits[] = [
                'type' => 'persistent_over',
                'category' => $category['name'],
                'periods' => $count,
                'message' => "Dépassement répété sur {$category['name']} ({$count} périodes)"
            ];
        }
    }
    
    // 3. Analyser l'évolution des dépenses
    $categoryTotals = [];
    foreach ($periods as $period) {
        $sql = "SELECT 
                    c.id,
                    c.name,
                    SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total
                FROM transactions t
                JOIN budget_categories c ON t.category_id = c.id
                WHERE t.period_id = ?
                GROUP BY c.id";
        
        $expenses = queryAll($sql, [$period['id']]);
        
        foreach ($expenses as $expense) {
            if (!isset($categoryTotals[$expense['id']])) {
                $categoryTotals[$expense['id']] = [
                    'name' => $expense['name'],
                    'totals' => []
                ];
            }
            $categoryTotals[$expense['id']]['totals'][] = $expense['total'];
        }
    }
    
    // 4. Détecter les tendances à la hausse
    foreach ($categoryTotals as $categoryId => $data) {
        if (count($data['totals']) >= 3) {
            // Vérifier si les dépenses augmentent
            $trend = calculateTrend($data['totals']);
            if ($trend > 0.1) { // Hausse de plus de 10%
                $persistentHabits[] = [
                    'type' => 'increasing_trend',
                    'category' => $data['name'],
                    'trend' => round($trend * 100),
                    'message' => "Tendance à la hausse sur {$data['name']} (+" . round($trend * 100) . "%)"
                ];
            }
        }
    }
    
    return $persistentHabits;
}

/**
 * Calcule la tendance d'une série de valeurs
 */
function calculateTrend($values) {
    $n = count($values);
    if ($n < 2) return 0;
    
    // Régression linéaire simple
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $sumX += $i;
        $sumY += $values[$i];
        $sumXY += $i * $values[$i];
        $sumX2 += $i * $i;
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    
    // Normaliser par la valeur moyenne
    $average = $sumY / $n;
    return $average > 0 ? $slope / $average : 0;
}

/**
 * Génère des recommandations basées sur les habitudes
 */
function generateRecommendations($periodId) {
    $habits = analyzeSpendingHabits();
    $recommendations = [];
    
    foreach ($habits as $habit) {
        switch ($habit['type']) {
            case 'persistent_over':
                $recommendations[] = [
                    'priority' => 'high',
                    'message' => "Réduire les dépenses en {$habit['category']} ou augmenter son budget",
                    'action' => "Revoir le budget pour {$habit['category']}"
                ];
                break;
                
            case 'increasing_trend':
                $recommendations[] = [
                    'priority' => 'medium',
                    'message' => "Les dépenses en {$habit['category']} augmentent régulièrement",
                    'action' => "Analyser les causes de l'augmentation"
                ];
                break;
        }
    }
    
    // Trier par priorité
    usort($recommendations, function($a, $b) {
        $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
        return $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
    });
    
    return $recommendations;
}
?>
