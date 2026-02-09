// charts.js - Graphiques avec Chart.js

let budgetChart = null;
let expensesChart = null;

// Initialiser les graphiques
function initCharts() {
    // Données pour le graphique des budgets
    const budgetCtx = document.getElementById('budget-chart');
    if (budgetCtx) {
        budgetChart = new Chart(budgetCtx, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${formatNumber(value)} FC (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Graphique des dépenses par jour
    const expensesCtx = document.getElementById('expenses-chart');
    if (expensesCtx) {
        expensesChart = new Chart(expensesCtx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Dépenses par jour',
                    data: [],
                    backgroundColor: '#4361ee',
                    borderColor: '#3a0ca3',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return formatNumber(value) + ' FC';
                            }
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return formatNumber(context.raw) + ' FC';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Charger les données
    loadChartData();
}

// Charger les données pour les graphiques
function loadChartData() {
    // Récupérer les données des budgets
    fetch('/includes/dashboard.php?action=get_budget_data')
        .then(response => response.json())
        .then(data => {
            if (budgetChart && data.budgets) {
                budgetChart.data.labels = data.budgets.map(b => b.category);
                budgetChart.data.datasets[0].data = data.budgets.map(b => b.spent);
                budgetChart.data.datasets[0].backgroundColor = data.budgets.map(b => b.color);
                budgetChart.update();
            }
            
            if (expensesChart && data.daily_expenses) {
                expensesChart.data.labels = data.daily_expenses.map(d => d.date);
                expensesChart.data.datasets[0].data = data.daily_expenses.map(d => d.amount);
                expensesChart.update();
            }
        })
        .catch(error => console.error('Erreur chargement données graphiques:', error));
}

// Mettre à jour les graphiques périodiquement
function updateChartsPeriodically() {
    setInterval(loadChartData, 60000); // Toutes les minutes
}

// Exporter un graphique en image
function exportChart(chartId, filename) {
    const chart = chartId === 'budget' ? budgetChart : expensesChart;
    if (chart) {
        const link = document.createElement('a');
        link.download = filename + '.png';
        link.href = chart.toBase64Image();
        link.click();
    }
}

// Initialiser au chargement
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Chart !== 'undefined') {
        initCharts();
        updateChartsPeriodically();
    }
});