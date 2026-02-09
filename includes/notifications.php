<?php
// notifications.php - Notifications IA
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/period.php';
require_once __DIR__ . '/budgets.php';

function sanitizeAiHtml($html) {
    $allowed = '<p><ul><ol><li><strong><em><h4><br>';
    $clean = strip_tags((string)$html, $allowed);
    return trim($clean);
}

function formatAiContent($text) {
    $trimmed = trim((string)$text);
    if ($trimmed === '') {
        return '<p>Aucune analyse disponible.</p>';
    }

    if (strpos($trimmed, '<') !== false) {
        return sanitizeAiHtml($trimmed);
    }

    $escaped = htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $escaped = nl2br($escaped);
    return '<p>' . $escaped . '</p>';
}

function notificationExists($timeframe, $rangeStart, $rangeEnd) {
    $sql = "SELECT id FROM ai_notifications WHERE timeframe = ? AND range_start = ? AND range_end = ? LIMIT 1";
    $row = queryOne($sql, [$timeframe, $rangeStart, $rangeEnd]);
    return !empty($row);
}

function createNotification($data) {
    $sql = "INSERT INTO ai_notifications 
            (period_id, timeframe, range_start, range_end, export_path, analysis_html, raw_response, is_read)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    return executeQuery($sql, [
        $data['period_id'] ?? null,
        $data['timeframe'],
        $data['range_start'],
        $data['range_end'],
        $data['export_path'],
        $data['analysis_html'],
        $data['raw_response'] ?? null,
        $data['is_read'] ?? 0
    ]);
}

function getNotifications($limit = 50, $timeframe = null) {
    if ($timeframe) {
        $sql = "SELECT * FROM ai_notifications WHERE timeframe = ? ORDER BY created_at DESC LIMIT ?";
        return queryAll($sql, [$timeframe, $limit]);
    }
    $sql = "SELECT * FROM ai_notifications ORDER BY created_at DESC LIMIT ?";
    return queryAll($sql, [$limit]);
}

function getLatestNotifications($limit = 5) {
    return getNotifications($limit);
}

function markNotificationAsRead($notificationId) {
    $sql = "UPDATE ai_notifications SET is_read = 1 WHERE id = ?";
    return executeQuery($sql, [$notificationId]);
}

function markAllNotificationsAsRead() {
    $sql = "UPDATE ai_notifications SET is_read = 1 WHERE is_read = 0";
    return executeQuery($sql);
}

function getUnreadNotificationCount() {
    $row = queryOne("SELECT COUNT(*) as count FROM ai_notifications WHERE is_read = 0");
    return (int)($row['count'] ?? 0);
}

function getOverlappingNotificationsForPeriod($startDate, $endDate) {
    $sql = "SELECT * FROM ai_notifications 
            WHERE range_end >= ? AND range_start <= ?
            ORDER BY created_at DESC";
    return queryAll($sql, [$startDate, $endDate]);
}

function buildRangeExportData($startDate, $endDate) {
    $transactions = queryAll(
        "SELECT t.*, c.name as category_name, c.color, c.icon
         FROM transactions t
         LEFT JOIN budget_categories c ON t.category_id = c.id
         WHERE date BETWEEN ? AND ?
         ORDER BY date, created_at",
        [$startDate, $endDate]
    );

    $periods = queryAll(
        "SELECT * FROM financial_periods 
         WHERE end_date >= ? AND start_date <= ?
         ORDER BY start_date",
        [$startDate, $endDate]
    );

    $summary = [
        'income_main' => 0,
        'income_extra' => 0,
        'expense' => 0,
        'tithing' => 0,
        'saving' => 0
    ];

    $daily = [];
    $byCategory = [];

    foreach ($transactions as $tx) {
        $dateKey = $tx['date'];
        if (!isset($daily[$dateKey])) {
            $daily[$dateKey] = [
                'date' => $dateKey,
                'income' => 0,
                'expense' => 0,
                'tithing' => 0,
                'saving' => 0
            ];
        }

        if ($tx['type'] === 'income_main') {
            $summary['income_main'] += (int)$tx['amount'];
            $daily[$dateKey]['income'] += (int)$tx['amount'];
        } elseif ($tx['type'] === 'income_extra') {
            $summary['income_extra'] += (int)$tx['amount'];
            $daily[$dateKey]['income'] += (int)$tx['amount'];
        } elseif ($tx['type'] === 'expense') {
            $summary['expense'] += (int)$tx['amount'];
            $daily[$dateKey]['expense'] += (int)$tx['amount'];

            $categoryId = $tx['category_id'] ?? 0;
            if (!isset($byCategory[$categoryId])) {
                $byCategory[$categoryId] = [
                    'category_id' => $categoryId,
                    'name' => $tx['category_name'],
                    'color' => $tx['color'],
                    'icon' => $tx['icon'],
                    'total' => 0,
                    'count' => 0
                ];
            }
            $byCategory[$categoryId]['total'] += (int)$tx['amount'];
            $byCategory[$categoryId]['count'] += 1;
        }

        $summary['tithing'] += (int)$tx['tithing_paid'];
        $summary['saving'] += (int)$tx['saving_paid'];
        $daily[$dateKey]['tithing'] += (int)$tx['tithing_paid'];
        $daily[$dateKey]['saving'] += (int)$tx['saving_paid'];
    }

    ksort($daily);

    return [
        'metadata' => [
            'range_start' => $startDate,
            'range_end' => $endDate,
            'export_date' => date('Y-m-d H:i:s'),
            'system_version' => APP_VERSION
        ],
        'periods' => $periods,
        'summary' => $summary,
        'daily' => array_values($daily),
        'by_category' => array_values($byCategory),
        'transactions' => $transactions
    ];
}

function exportRangeToJSON($timeframe, $startDate, $endDate) {
    $data = buildRangeExportData($startDate, $endDate);

    if (!file_exists(EXPORTS_PATH)) {
        mkdir(EXPORTS_PATH, 0755, true);
    }

    $filename = sprintf('ai_%s_%s_%s.json', $timeframe, $startDate, $endDate);
    $filepath = EXPORTS_PATH . '/' . $filename;

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($filepath, $json);

    return [
        'filepath' => $filepath,
        'filename' => $filename
    ];
}

// Endpoint AJAX: marquer une notification comme lue
if (php_sapi_name() !== 'cli' && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__ && isset($_GET['action']) && $_GET['action'] === 'mark_read') {
    header('Content-Type: application/json');
    $notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($notificationId > 0) {
        markNotificationAsRead($notificationId);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'ID invalide']);
    }
    exit;
}
