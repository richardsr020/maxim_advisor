<header class="topbar">
    <div class="brand">
        <div class="brand-mark">Mx</div>
        <div class="brand-text">
            <div class="brand-name"><?php echo APP_NAME; ?></div>
            <div class="brand-sub">Gestion financière personnelle</div>
        </div>
    </div>
    
    <nav class="topbar-links">
        <a href="/?page=dashboard" class="nav-link <?php echo ($page ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="/?page=expenses" class="nav-link <?php echo ($page ?? '') == 'expenses' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Dépenses</span>
        </a>
        <a href="/?page=history" class="nav-link <?php echo ($page ?? '') == 'history' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Historique</span>
        </a>
        <a href="/?page=alerts" class="nav-link <?php echo ($page ?? '') == 'alerts' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Notifications IA</span>
            <?php
            require_once __DIR__ . '/../includes/notifications.php';
            $unread = getUnreadNotificationCount();
            if ($unread > 0) {
                echo '<span class="nav-badge">' . (int)$unread . '</span>';
            }
            ?>
        </a>
        <a href="/?page=export" class="nav-link <?php echo ($page ?? '') == 'export' ? 'active' : ''; ?>">
            <i class="fas fa-file-export"></i>
            <span>Export</span>
        </a>
        <a href="/?page=settings" class="nav-link <?php echo ($page ?? '') == 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Paramètres</span>
        </a>
    </nav>
    
    <div class="topbar-user">
        <?php if ($period = getActivePeriod()): ?>
        <div class="period-pill">
            <i class="fas fa-calendar-alt"></i>
            <span>
                <?php echo date('d/m', strtotime($period['start_date'])); ?> 
                - 
                <?php echo date('d/m', strtotime($period['end_date'])); ?>
            </span>
        </div>
        <?php endif; ?>
        
        <div class="user-pill">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?></span>
        </div>
        <button class="theme-toggle" type="button" onclick="toggleTheme()" title="Basculer le thème" aria-label="Basculer le thème">
            <i class="fas fa-moon"></i>
        </button>
        <a href="/logout.php" class="logout-link" title="Déconnexion">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<nav class="bottom-nav">
    <a href="/?page=dashboard" class="<?php echo ($page ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Accueil</span>
    </a>
    <a href="/?page=expenses" class="<?php echo ($page ?? '') == 'expenses' ? 'active' : ''; ?>">
        <i class="fas fa-money-bill-wave"></i>
        <span>Dépenses</span>
    </a>
    <a href="/?page=history" class="<?php echo ($page ?? '') == 'history' ? 'active' : ''; ?>">
        <i class="fas fa-history"></i>
        <span>Historique</span>
    </a>
    <a href="/?page=alerts" class="<?php echo ($page ?? '') == 'alerts' ? 'active' : ''; ?>">
        <i class="fas fa-bell"></i>
        <span>Notifications</span>
    </a>
    <a href="/?page=settings" class="<?php echo ($page ?? '') == 'settings' ? 'active' : ''; ?>">
        <i class="fas fa-cog"></i>
        <span>Réglages</span>
    </a>
</nav>
