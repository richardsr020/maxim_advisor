# Maxim Advisor

Système de gestion financière personnelle intelligent.

## Installation

1. Télécharger tous les fichiers sur votre serveur
2. Accéder à `install.php` dans votre navigateur
3. Suivre les instructions d'installation
4. Se connecter avec les identifiants admin

## Structure

maxim_advisor/
├── index.php # Routeur principal
├── install.php # Installation
├── login.php # Connexion
├── logout.php # Déconnexion
├── database/ # Base de données SQLite
├── includes/ # Logique métier PHP
├── pages/ # Pages de l'application
├── templates/ # Templates HTML
├── assets/ CSS/JS/Images
└── exports/ # Exports JSON


## Fonctionnalités

- Gestion des périodes financières (mois glissant)
- Répartition automatique des revenus
- Suivi des dépenses par catégories
- Alertes intelligentes
- Détection de mauvaises habitudes
- Export des données en JSON
- Interface responsive

## Technologies

- PHP 7.4+ (pur, sans framework)
- SQLite 3
- HTML5 / CSS3 / JavaScript
- Chart.js pour les graphiques

## Licence

Projet privé - Usage personnel uniquement