<nav class="sidebar">
    <div class="sidebar-header">
        <h1><i class="fas fa-chart-line"></i> <?php echo APP_NAME; ?></h1>
        <p class="version">v<?php echo APP_VERSION; ?></p>
    </div>
    
    <div class="sidebar-menu">
        <a href="/?page=dashboard" class="menu-item <?php echo ($page ?? 'dashboard') == 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i> Dashboard
        </a>
        
        <a href="/?page=expenses" class="menu-item <?php echo ($page ?? '') == 'expenses' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i> Dépenses
        </a>
        
        <a href="/?page=history" class="menu-item <?php echo ($page ?? '') == 'history' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Historique
        </a>
        
        <a href="/?page=alerts" class="menu-item <?php echo ($page ?? '') == 'alerts' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i> Notifications IA
            <?php
            require_once __DIR__ . '/../includes/notifications.php';
            $unread = getUnreadNotificationCount();
            if ($unread > 0) {
                echo '<span class="badge">' . $unread . '</span>';
            }
            ?>
        </a>
        
        <a href="/?page=export" class="menu-item <?php echo ($page ?? '') == 'export' ? 'active' : ''; ?>">
            <i class="fas fa-file-export"></i> Export
        </a>
        
        <a href="/?page=settings" class="menu-item <?php echo ($page ?? '') == 'settings' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Paramètres
        </a>
    </div>
    
    <div class="sidebar-footer">
        <?php if ($period = getActivePeriod()): ?>
        <div class="current-period">
            <small>Période active :</small>
            <strong>
                <?php echo date('d/m', strtotime($period['start_date'])); ?> 
                - 
                <?php echo date('d/m', strtotime($period['end_date'])); ?>
            </strong>
        </div>
        <?php endif; ?>
        
        <div class="user-info">
            <i class="fas fa-user"></i>
            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?></span>
            <a href="/logout.php" class="logout-btn" title="Déconnexion">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</nav>
