#!/bin/bash

# ================================
# maxim_advisor - init structure
# ================================

echo "📁 Initialisation de l'arborescence maxim_advisor..."

# Sécurité : vérifier qu'on est bien dans le dossier racine
if [ ! -f "README.md" ] && [ ! -f "index.php" ]; then
  echo "⚠️  Attention : assure-toi d'être dans le dossier racine maxim_advisor"
  echo "❌ Script annulé"
  exit 1
fi

# ---------- Dossiers ----------
mkdir -p database
mkdir -p exports/json
mkdir -p exports/backups

mkdir -p pages
mkdir -p includes

mkdir -p assets/css
mkdir -p assets/js
mkdir -p assets/icons

mkdir -p templates/components

# ---------- Fichiers racine ----------
touch index.php
touch install.php
touch .htaccess
touch README.md

# ---------- Base de données ----------
touch database/maxim_advisor.sqlite

# ---------- Pages ----------
touch pages/dashboard.php
touch pages/settings.php
touch pages/expenses.php
touch pages/history.php
touch pages/export.php
touch pages/alerts.php

# ---------- Includes ----------
touch includes/config.php
touch includes/auth.php
touch includes/database.php
touch includes/period.php
touch includes/calculations.php
touch includes/transactions.php
touch includes/budgets.php
touch includes/alerts.php
touch includes/habits.php
touch includes/export.php
touch includes/helpers.php

# ---------- CSS ----------
touch assets/css/reset.css
touch assets/css/layout.css
touch assets/css/components.css
touch assets/css/dark-mode.css

# ---------- JavaScript ----------
touch assets/js/app.js
touch assets/js/dashboard.js
touch assets/js/charts.js
touch assets/js/validation.js
touch assets/js/alerts.js

# ---------- Templates ----------
touch templates/header.php
touch templates/footer.php
touch templates/sidebar.php

# ---------- Template components ----------
touch templates/components/budget-card.php
touch templates/components/alert-banner.php
touch templates/components/period-summary.php

echo "✅ Arborescence maxim_advisor créée avec succès"
