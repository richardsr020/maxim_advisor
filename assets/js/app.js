// app.js - Fonctions JavaScript globales

const themeStorageKey = 'maxim_theme';

function getPreferredTheme() {
    const savedTheme = localStorage.getItem(themeStorageKey);
    if (savedTheme) {
        return savedTheme;
    }
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    updateThemeToggle(theme);
}

function updateThemeToggle(theme) {
    const toggle = document.querySelector('.theme-toggle');
    if (!toggle) {
        return;
    }
    toggle.setAttribute('aria-pressed', theme === 'dark');
    const icon = toggle.querySelector('i');
    if (!icon) {
        return;
    }
    if (theme === 'dark') {
        icon.classList.remove('fa-moon');
        icon.classList.add('fa-sun');
    } else {
        icon.classList.remove('fa-sun');
        icon.classList.add('fa-moon');
    }
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || getPreferredTheme();
    const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
    localStorage.setItem(themeStorageKey, nextTheme);
    applyTheme(nextTheme);
}

// Gestion des modals
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Fermer modal avec ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = 'auto';
    }
});

// Fermer modal en cliquant à l'extérieur
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

// Fonctions globales pour le dashboard
function showAddIncomeModal() {
    showModal('add-income-modal');
}

function showAddExpenseModal() {
    showModal('add-expense-modal');
}

function addExpenseToCategory(categoryId) {
    const modal = document.getElementById('add-expense-modal');
    const select = modal.querySelector('select[name="category_id"]');
    if (select) {
        select.value = categoryId;
        select.dispatchEvent(new Event('change'));
    }
    showAddExpenseModal();
}

// Formater les nombres
function formatNumber(number) {
    return new Intl.NumberFormat('fr-FR').format(number);
}

// Vérifier la connexion internet
function checkConnection() {
    if (!navigator.onLine) {
        showToast('Vous êtes hors ligne. Certaines fonctionnalités peuvent être limitées.', 'warning');
    }
}

// Afficher des notifications toast
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Animation CSS pour les toasts
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Détection de changement de période
function checkPeriodChange() {
    fetch('/includes/period.php?action=check')
        .then(response => response.json())
        .then(data => {
            if (data.new_period) {
                showToast('Nouvelle période financière créée !', 'success');
                setTimeout(() => location.reload(), 2000);
            }
        })
        .catch(error => console.error('Erreur vérification période:', error));
}

// Vérifier toutes les 5 minutes
setInterval(checkPeriodChange, 300000);

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    applyTheme(getPreferredTheme());
    checkConnection();
    
    // Gestion du formulaire de dépense dans le modal
    const expenseCategory = document.getElementById('expense-category');
    const commentField = document.getElementById('expense-comment-field');
    
    if (expenseCategory && commentField) {
        expenseCategory.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const isUnexpected = selectedOption.dataset.unexpected === '1';
            
            if (isUnexpected) {
                commentField.style.display = 'block';
                commentField.querySelector('textarea').required = true;
            } else {
                commentField.style.display = 'none';
                commentField.querySelector('textarea').required = false;
            }
        });
    }
    
    // Auto-sauvegarde des formulaires longs
    const longForms = document.querySelectorAll('form');
    longForms.forEach(form => {
        form.addEventListener('input', function() {
            localStorage.setItem('form_autosave_' + form.id, new FormData(form));
        });
        
        // Restaurer les données sauvegardées
        const savedData = localStorage.getItem('form_autosave_' + form.id);
        if (savedData && !form.querySelector('[name="submitted"]')) {
            // Restaurer les données (simplifié)
        }
    });
});
