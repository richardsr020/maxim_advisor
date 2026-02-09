// dashboard.js - Fonctions spécifiques au dashboard

function dismissNotification(notificationId) {
    fetch('/includes/notifications.php?action=mark_read&id=' + encodeURIComponent(notificationId))
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                const el = document.querySelector(`.ai-notification[data-notification-id="${notificationId}"]`);
                if (el) {
                    el.remove();
                }
            } else if (typeof showToast === 'function') {
                showToast('Impossible de marquer la notification comme lue', 'error');
            }
        })
        .catch(() => {
            if (typeof showToast === 'function') {
                showToast('Erreur réseau lors de la mise à jour', 'error');
            }
        });
}
