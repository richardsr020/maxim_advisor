<?php
// alerts.php - Notifications IA
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/period.php';

$period = getActivePeriod();

if (!$period) {
    echo '<div class="no-period"><p>Aucune période active</p></div>';
    return;
}

if (isset($_GET['mark_read'])) {
    markNotificationAsRead((int)$_GET['mark_read']);
}

if (isset($_GET['mark_all_read'])) {
    markAllNotificationsAsRead();
}

$notifications = getNotifications(50);
$unreadCount = getUnreadNotificationCount();

$labels = [
    'week' => 'Hebdomadaire',
    'month' => 'Mensuel',
    'year' => 'Annuel'
];
?>

<div class="alerts-container">
    <h2>🔔 Notifications IA</h2>

    <div class="alerts-stats">
        <h3>Suivi des notifications</h3>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">🧠</div>
                <div class="stat-details">
                    <div class="stat-count"><?php echo count($notifications); ?></div>
                    <div class="stat-label">Messages IA</div>
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-icon">📌</div>
                <div class="stat-details">
                    <div class="stat-count"><?php echo $unreadCount; ?></div>
                    <div class="stat-label">Non lus</div>
                </div>
            </div>
        </div>

        <?php if ($unreadCount > 0): ?>
        <div class="actions-bar">
            <a href="?page=alerts&mark_all_read=1" class="btn btn-secondary">
                ✅ Marquer toutes comme lues
            </a>
        </div>
        <?php endif; ?>
    </div>

    <div class="alerts-content notifications-content">
        <div class="alerts-list">
            <h3>Dernières analyses</h3>

            <?php if (empty($notifications)): ?>
            <div class="no-alerts">
                <p>🕒 Aucune notification IA disponible pour le moment.</p>
            </div>
            <?php else: ?>
            <div class="notifications-items">
                <?php foreach ($notifications as $notification): ?>
                <div class="ai-notification notification-<?php echo $notification['timeframe']; ?> <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="notification-header">
                        <span class="notification-badge">
                            <?php echo $labels[$notification['timeframe']] ?? $notification['timeframe']; ?>
                        </span>
                        <span class="notification-range">
                            <?php echo date('d/m/Y', strtotime($notification['range_start'])); ?>
                            →
                            <?php echo date('d/m/Y', strtotime($notification['range_end'])); ?>
                        </span>
                        <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                    </div>
                    <div class="notification-content">
                        <?php echo $notification['analysis_html']; ?>
                    </div>
                    <div class="notification-actions">
                        <?php if (!$notification['is_read']): ?>
                        <a href="?page=alerts&mark_read=<?php echo $notification['id']; ?>" class="btn btn-small">
                            ✓ Lu
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.alert-item.unread {
    border-left: 4px solid;
}
.alert-item.level-danger.unread {
    border-left-color: #F44336;
}
.alert-item.level-warning.unread {
    border-left-color: #FF9800;
}
.alert-item.read {
    opacity: 0.7;
}
</style>
