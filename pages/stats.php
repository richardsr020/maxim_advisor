<?php
// stats.php - Page des statistiques
require_once __DIR__ . '/../includes/period.php';

$activePeriod = getActivePeriod();

if (!$activePeriod) {
    include __DIR__ . '/../templates/components/no-period.php';
    return;
}
?>

<div class="stats-container">
    <div class="stats-header">
        <h2>📈 Statistiques</h2>
        <p class="stat-subtitle">Courbes basées sur les transactions de la période active.</p>
    </div>

    <div class="stats-charts">
        <div class="stats-card">
            <h3>Flux journaliers</h3>
            <canvas id="stats-flow-chart"></canvas>
        </div>
        <div class="stats-card">
            <h3>Solde cumulé</h3>
            <canvas id="stats-balance-chart"></canvas>
        </div>
    </div>
</div>
