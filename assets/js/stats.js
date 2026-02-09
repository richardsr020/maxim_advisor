// stats.js - Graphiques de statistiques (courbes)

function getCssVar(name) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
}

function buildLineDataset(label, data, color) {
    return {
        label,
        data,
        borderColor: color,
        backgroundColor: color,
        pointBackgroundColor: color,
        pointBorderColor: color,
        borderWidth: 2,
        tension: 0.35,
        pointRadius: 2,
        pointHoverRadius: 4,
        fill: false
    };
}

function showEmptyState(canvasId, message) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        return;
    }
    const wrapper = canvas.parentElement;
    if (wrapper) {
        wrapper.removeChild(canvas);
        const empty = document.createElement('p');
        empty.className = 'no-data';
        empty.textContent = message;
        wrapper.appendChild(empty);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const flowCanvas = document.getElementById('stats-flow-chart');
    const balanceCanvas = document.getElementById('stats-balance-chart');

    if (!flowCanvas && !balanceCanvas) {
        return;
    }

    fetch('/includes/dashboard.php?action=get_stats_series')
        .then(response => response.json())
        .then(data => {
            const labels = data.labels || [];
            const series = data.series || {};

            if (labels.length === 0) {
                showEmptyState('stats-flow-chart', 'Aucune donnée disponible pour la période active.');
                showEmptyState('stats-balance-chart', 'Aucune donnée disponible pour la période active.');
                return;
            }

            const income = series.income || [];
            const expense = series.expense || [];
            const tithing = series.tithing || [];
            const saving = series.saving || [];

            const incomeColor = getCssVar('--color-income') || '#16a34a';
            const expenseColor = getCssVar('--color-expense') || '#dc2626';
            const tithingColor = getCssVar('--color-tithing') || '#d97706';
            const savingColor = getCssVar('--color-saving') || '#2563eb';
            const balanceColor = getCssVar('--color-balance') || '#7c3aed';
            const textColor = getCssVar('--muted') || '#6b7280';
            const gridColor = getCssVar('--border') || 'rgba(0,0,0,0.05)';

            if (flowCanvas && typeof Chart !== 'undefined') {
                new Chart(flowCanvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            buildLineDataset('Revenus', income, incomeColor),
                            buildLineDataset('Dépenses', expense, expenseColor),
                            buildLineDataset('Dîme', tithing, tithingColor),
                            buildLineDataset('Épargne', saving, savingColor)
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: {
                                    color: textColor
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.dataset.label}: ${formatNumber(context.raw)} FC`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            },
                            y: {
                                ticks: {
                                    color: textColor,
                                    callback: function(value) {
                                        return formatNumber(value) + ' FC';
                                    }
                                },
                                grid: { color: gridColor }
                            }
                        }
                    }
                });
            }

            if (balanceCanvas && typeof Chart !== 'undefined') {
                const balance = [];
                let running = 0;
                for (let i = 0; i < labels.length; i++) {
                    running += (income[i] || 0) - (expense[i] || 0) - (tithing[i] || 0) - (saving[i] || 0);
                    balance.push(running);
                }

                new Chart(balanceCanvas, {
                    type: 'line',
                    data: {
                        labels,
                        datasets: [
                            buildLineDataset('Solde cumulé', balance, balanceColor)
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                labels: {
                                    color: textColor
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return `${context.dataset.label}: ${formatNumber(context.raw)} FC`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: { color: textColor },
                                grid: { color: gridColor }
                            },
                            y: {
                                ticks: {
                                    color: textColor,
                                    callback: function(value) {
                                        return formatNumber(value) + ' FC';
                                    }
                                },
                                grid: { color: gridColor }
                            }
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Erreur chargement statistiques:', error);
            showEmptyState('stats-flow-chart', 'Erreur de chargement des statistiques.');
            showEmptyState('stats-balance-chart', 'Erreur de chargement des statistiques.');
        });
});
