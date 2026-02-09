<?php
// chat.php - Endpoints et utilitaires pour le chat IA
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/period.php';
require_once __DIR__ . '/budgets.php';
require_once __DIR__ . '/calculations.php';
require_once __DIR__ . '/parameters.php';
require_once __DIR__ . '/alerts.php';
require_once __DIR__ . '/habits.php';
require_once __DIR__ . '/notifications.php';
require_once __DIR__ . '/ai_client.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sanitizeChatHtml($html) {
    $allowed = '<p><ul><ol><li><strong><em><h4><br>';
    $clean = strip_tags((string)$html, $allowed);
    return trim($clean);
}

function formatChatAssistantContent($text) {
    $trimmed = trim((string)$text);
    if ($trimmed === '') {
        return '<p>Aucune réponse disponible.</p>';
    }
    if (strpos($trimmed, '<') !== false) {
        return sanitizeChatHtml($trimmed);
    }
    $escaped = htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $escaped = nl2br($escaped);
    return '<p>' . $escaped . '</p>';
}

function getChatThreads($limit = 50) {
    $sql = "SELECT * FROM ai_chat_threads ORDER BY updated_at DESC LIMIT ?";
    return queryAll($sql, [$limit]);
}

function createChatThread($periodId, $title) {
    $sql = "INSERT INTO ai_chat_threads (period_id, title) VALUES (?, ?)";
    executeQuery($sql, [$periodId, $title]);
    return lastInsertId();
}

function touchThread($threadId) {
    $sql = "UPDATE ai_chat_threads SET updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    executeQuery($sql, [$threadId]);
}

function addChatMessage($threadId, $role, $content) {
    $sql = "INSERT INTO ai_chat_messages (thread_id, role, content) VALUES (?, ?, ?)";
    executeQuery($sql, [$threadId, $role, $content]);
    touchThread($threadId);
    return lastInsertId();
}

function getChatMessages($threadId, $limit = 100) {
    $sql = "SELECT * FROM ai_chat_messages WHERE thread_id = ? ORDER BY id ASC LIMIT ?";
    return queryAll($sql, [$threadId, $limit]);
}

function getThreadById($threadId) {
    $sql = "SELECT * FROM ai_chat_threads WHERE id = ?";
    return queryOne($sql, [$threadId]);
}

function resolveContextPeriodId($threadId, $threadPeriodId) {
    $activePeriod = getActivePeriod();
    if (!$activePeriod) {
        return [
            'period_id' => $threadPeriodId ? (int)$threadPeriodId : null,
            'active_period_id' => null,
            'source' => 'thread_period'
        ];
    }

    $activeId = (int)$activePeriod['id'];
    if (!$threadPeriodId || (int)$threadPeriodId !== $activeId) {
        if ($threadId > 0) {
            executeQuery("UPDATE ai_chat_threads SET period_id = ? WHERE id = ?", [$activeId, $threadId]);
        }
        return [
            'period_id' => $activeId,
            'active_period_id' => $activeId,
            'source' => 'active_period'
        ];
    }

    return [
        'period_id' => (int)$threadPeriodId,
        'active_period_id' => $activeId,
        'source' => 'thread_period'
    ];
}

function buildDatabaseOverview() {
    $summary = queryOne("SELECT 
            SUM(CASE WHEN type = 'income_main' THEN amount ELSE 0 END) as total_income,
            SUM(CASE WHEN type = 'income_extra' THEN amount ELSE 0 END) as total_extra_income,
            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
            COUNT(*) as transaction_count
        FROM transactions");
    $periods = queryOne("SELECT COUNT(*) as periods_count FROM financial_periods");
    $categories = queryOne("SELECT COUNT(*) as categories_count FROM budget_categories");
    $dates = queryOne("SELECT MIN(date) as first_date, MAX(date) as latest_date FROM transactions");

    return [
        'periods_count' => (int)($periods['periods_count'] ?? 0),
        'categories_count' => (int)($categories['categories_count'] ?? 0),
        'transaction_count' => (int)($summary['transaction_count'] ?? 0),
        'total_income' => (int)($summary['total_income'] ?? 0),
        'total_extra_income' => (int)($summary['total_extra_income'] ?? 0),
        'total_expenses' => (int)($summary['total_expenses'] ?? 0),
        'first_transaction_date' => $dates['first_date'] ?? null,
        'latest_transaction_date' => $dates['latest_date'] ?? null
    ];
}

function getRecentPeriodSummaries($limit = 6) {
    $periods = queryAll(
        "SELECT id, start_date, end_date 
         FROM financial_periods 
         ORDER BY start_date DESC 
         LIMIT ?",
        [$limit]
    );
    $summaries = [];
    foreach ($periods as $period) {
        $totals = calculatePeriodTotals($period['id']);
        $summaries[] = [
            'period_id' => $period['id'],
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
            'total_income' => (int)($totals['total_income'] ?? 0),
            'total_extra_income' => (int)($totals['total_extra_income'] ?? 0),
            'total_expenses' => (int)($totals['total_expenses'] ?? 0),
            'total_budget' => (int)($totals['total_budget'] ?? 0),
            'total_spent' => (int)($totals['total_spent'] ?? 0)
        ];
    }
    return $summaries;
}

function parseDataRequest($text) {
    if (!preg_match('/\\[\\[DATA_REQUEST\\s*([^\\]]+)\\]\\]/', (string)$text, $match)) {
        return null;
    }

    $payload = trim($match[1]);
    if ($payload === '') {
        return null;
    }

    if (strpos($payload, '{') === 0) {
        $decoded = json_decode($payload, true);
        if (is_array($decoded)) {
            $pairs = [];
            foreach ($decoded as $key => $value) {
                $pairs[strtolower((string)$key)] = $value;
            }
            if (!isset($pairs['type'])) {
                return null;
            }
            return $pairs;
        }
    }

    $pairs = [];
    preg_match_all('/(\\w+)=(".*?"|\\\'.*?\\\'|\\S+)/', $payload, $parts, PREG_SET_ORDER);
    foreach ($parts as $part) {
        $key = strtolower($part[1]);
        $value = $part[2];
        $value = trim($value, "\"'");
        $pairs[$key] = $value;
    }

    if (!isset($pairs['type'])) {
        return null;
    }

    return $pairs;
}

function isValidDateString($date) {
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function buildDataToolResponse($request) {
    $type = strtolower($request['type'] ?? '');
    $response = [
        'tool' => 'DATA_REQUEST',
        'requested_at' => date('Y-m-d H:i:s'),
        'request' => $request,
        'error' => null,
        'data' => null
    ];

    switch ($type) {
        case 'active_period':
        case 'current_period':
        case 'active':
        case 'current':
            $response['data'] = buildFinancialContext();
            return $response;

        case 'period':
            $periodId = (int)($request['period_id'] ?? $request['id'] ?? 0);
            if ($periodId <= 0) {
                $response['error'] = "period_id invalide";
                return $response;
            }
            $period = queryOne("SELECT id FROM financial_periods WHERE id = ?", [$periodId]);
            if (!$period) {
                $response['error'] = "Période introuvable";
                return $response;
            }
            $response['data'] = buildFinancialContext($periodId);
            return $response;

        case 'period_by_date':
        case 'period_on':
            $date = $request['date'] ?? $request['on'] ?? null;
            if (!$date || !isValidDateString($date)) {
                $response['error'] = "Date invalide (format attendu: YYYY-MM-DD)";
                return $response;
            }
            $period = queryOne(
                "SELECT id FROM financial_periods WHERE start_date <= ? AND end_date >= ? ORDER BY start_date DESC LIMIT 1",
                [$date, $date]
            );
            if (!$period) {
                $response['error'] = "Aucune période pour cette date";
                return $response;
            }
            $response['data'] = buildFinancialContext($period['id']);
            return $response;

        case 'range':
            $startDate = $request['start'] ?? $request['start_date'] ?? null;
            $endDate = $request['end'] ?? $request['end_date'] ?? null;
            if (!$startDate || !$endDate || !isValidDateString($startDate) || !isValidDateString($endDate)) {
                $response['error'] = "Dates invalides (format attendu: YYYY-MM-DD)";
                return $response;
            }
            if ($startDate > $endDate) {
                $response['error'] = "start_date doit être <= end_date";
                return $response;
            }
            $response['data'] = buildRangeExportData($startDate, $endDate);
            return $response;

        case 'last_days':
        case 'recent_days':
            $days = (int)($request['days'] ?? 30);
            $days = max(1, min($days, 365));
            $endDate = $request['end'] ?? $request['end_date'] ?? date('Y-m-d');
            if (!isValidDateString($endDate)) {
                $response['error'] = "Date de fin invalide (format attendu: YYYY-MM-DD)";
                return $response;
            }
            $end = new DateTime($endDate);
            $start = (clone $end)->modify('-' . ($days - 1) . ' days');
            $response['data'] = buildRangeExportData($start->format('Y-m-d'), $end->format('Y-m-d'));
            return $response;

        case 'month':
            $monthRaw = $request['month'] ?? null;
            $year = (int)($request['year'] ?? 0);
            $month = 0;
            if ($monthRaw && preg_match('/^(\\d{4})-(\\d{2})$/', $monthRaw, $m)) {
                $year = (int)$m[1];
                $month = (int)$m[2];
            } else {
                $month = (int)$monthRaw;
            }
            if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
                $response['error'] = "Mois invalide (utilisez month=YYYY-MM ou year=YYYY month=MM)";
                return $response;
            }
            $startDate = sprintf('%04d-%02d-01', $year, $month);
            $start = new DateTime($startDate);
            $end = (clone $start)->modify('last day of this month');
            $response['data'] = buildRangeExportData($start->format('Y-m-d'), $end->format('Y-m-d'));
            return $response;

        case 'year':
            $year = (int)($request['year'] ?? 0);
            if ($year < 2000 || $year > 2100) {
                $response['error'] = "Année invalide";
                return $response;
            }
            $startDate = $year . '-01-01';
            $endDate = $year . '-12-31';
            $response['data'] = buildRangeExportData($startDate, $endDate);
            return $response;

        case 'recent_periods':
            $limit = (int)($request['limit'] ?? 6);
            $limit = max(1, min($limit, 24));
            $response['data'] = getRecentPeriodSummaries($limit);
            return $response;

        case 'database_overview':
            $response['data'] = buildDatabaseOverview();
            return $response;

        default:
            $response['error'] = "Type de requête inconnu";
            return $response;
    }
}

function buildFinancialContext($periodId = null) {
    $period = $periodId ? queryOne("SELECT * FROM financial_periods WHERE id = ?", [$periodId]) : getActivePeriod();
    if (!$period) {
        return [
            'period' => null,
            'summary' => null,
            'budgets' => [],
            'recent_transactions' => [],
            'database_overview' => buildDatabaseOverview()
        ];
    }

    $parameters = getParameters($period['parameters_version']);
    $budgetPercentages = getBudgetPercentages($period['parameters_version']);
    $budgets = getBudgetSummary($period['id']);
    $totals = calculateTotals($period['id']);

    $recentTransactions = queryAll(
        "SELECT t.*, c.name as category_name, c.color, c.icon
         FROM transactions t
         LEFT JOIN budget_categories c ON t.category_id = c.id
         WHERE t.period_id = ?
         ORDER BY t.date DESC, t.created_at DESC
         LIMIT 30",
        [$period['id']]
    );

    $allTransactions = queryAll(
        "SELECT t.*, c.name as category_name, c.color, c.icon
         FROM transactions t
         LEFT JOIN budget_categories c ON t.category_id = c.id
         WHERE t.period_id = ?
         ORDER BY t.date, t.created_at",
        [$period['id']]
    );

    $categoryStats = queryAll(
        "SELECT c.id, c.name, c.icon, c.color,
                SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END) as total_expenses,
                SUM(CASE WHEN t.type = 'expense' THEN 1 ELSE 0 END) as expense_count
         FROM budget_categories c
         LEFT JOIN transactions t
           ON t.category_id = c.id AND t.period_id = ?
         GROUP BY c.id
         ORDER BY total_expenses DESC",
        [$period['id']]
    );

    $largestExpenses = queryAll(
        "SELECT t.date, t.amount, t.description, c.name as category_name
         FROM transactions t
         LEFT JOIN budget_categories c ON t.category_id = c.id
         WHERE t.period_id = ? AND t.type = 'expense'
         ORDER BY t.amount DESC
         LIMIT 10",
        [$period['id']]
    );

    $incomeSummary = queryAll(
        "SELECT type, SUM(amount) as total, COUNT(*) as count
         FROM transactions
         WHERE period_id = ? AND type IN ('income_main', 'income_extra')
         GROUP BY type",
        [$period['id']]
    );

    $alerts = getActiveAlerts($period['id'], 20);
    $habits = analyzeSpendingHabits(3);
    $recommendations = generateRecommendations($period['id']);
    $categories = getAllCategories();
    $notifications = getOverlappingNotificationsForPeriod($period['start_date'], $period['end_date']);

    $remainingBudget = (int)($totals['total_budget'] ?? 0) - (int)($totals['total_spent'] ?? 0);
    $today = new DateTime('today');
    $endDate = new DateTime($period['end_date']);
    $daysLeft = (int)$today->diff($endDate)->format('%r%a');
    $daysLeft = max($daysLeft + 1, 0);
    $dailyBudget = $daysLeft > 0 ? floor($remainingBudget / $daysLeft) : 0;

    return [
        'period' => [
            'id' => $period['id'],
            'start_date' => $period['start_date'],
            'end_date' => $period['end_date'],
            'initial_income' => $period['initial_income'],
            'tithing_amount' => $period['tithing_amount'],
            'saving_amount' => $period['saving_amount']
        ],
        'parameters' => [
            'version' => $parameters['version'],
            'default_income' => $parameters['default_income'],
            'currency' => $parameters['currency'],
            'tithing_percent' => $parameters['tithing_percent'],
            'main_saving_percent' => $parameters['main_saving_percent'],
            'extra_saving_percent' => $parameters['extra_saving_percent'],
            'budget_percentages' => $budgetPercentages
        ],
        'summary' => [
            'total_budget' => (int)($totals['total_budget'] ?? 0),
            'total_spent' => (int)($totals['total_spent'] ?? 0),
            'remaining_budget' => $remainingBudget,
            'total_income' => (int)($totals['total_income'] ?? 0),
            'total_extra_income' => (int)($totals['total_extra_income'] ?? 0),
            'total_expenses' => (int)($totals['total_expenses'] ?? 0),
            'total_tithing' => (int)($totals['total_tithing'] ?? 0),
            'total_saving' => (int)($totals['total_saving'] ?? 0),
            'days_left' => $daysLeft,
            'daily_budget' => $dailyBudget
        ],
        'budgets' => $budgets,
        'recent_transactions' => $recentTransactions,
        'all_transactions' => $allTransactions,
        'category_stats' => $categoryStats,
        'largest_expenses' => $largestExpenses,
        'income_summary' => $incomeSummary,
        'alerts_active' => $alerts,
        'notifications' => $notifications,
        'habits' => $habits,
        'recommendations' => $recommendations,
        'categories' => $categories,
        'recent_periods' => getRecentPeriodSummaries(6),
        'database_overview' => buildDatabaseOverview()
    ];
}

function getRelevantThreadSummaries($query, $excludeThreadId, $limit = 5) {
    $query = strtolower($query);
    $words = preg_split('/\s+/', $query);
    $terms = [];
    foreach ($words as $word) {
        $word = trim($word);
        $length = function_exists('mb_strlen') ? mb_strlen($word) : strlen($word);
        if ($length >= 4) {
            $terms[] = $word;
        }
    }

    if (empty($terms)) {
        return [];
    }

    $where = [];
    $params = [];
    foreach ($terms as $term) {
        $where[] = "(t.title LIKE ? OR t.summary_text LIKE ?)";
        $params[] = '%' . $term . '%';
        $params[] = '%' . $term . '%';
    }
    $params[] = $excludeThreadId;
    $params[] = $limit;

    $sql = "SELECT t.id, t.title, t.summary_text, t.updated_at
            FROM ai_chat_threads t
            WHERE (" . implode(' OR ', $where) . ")
              AND t.summary_text IS NOT NULL
              AND t.id != ?
            ORDER BY t.updated_at DESC
            LIMIT ?";

    return queryAll($sql, $params);
}

function getRecentThreadSummaries($excludeThreadId, $limit = 5) {
    $sql = "SELECT id, title, summary_text, updated_at
            FROM ai_chat_threads
            WHERE summary_text IS NOT NULL AND id != ?
            ORDER BY updated_at DESC
            LIMIT ?";
    return queryAll($sql, [$excludeThreadId, $limit]);
}

function updateThreadSummary($threadId) {
    $summaryPromptPath = __DIR__ . '/../prompts/ai_chat_summary.txt';
    if (!file_exists($summaryPromptPath)) {
        return;
    }

    $thread = getThreadById($threadId);
    $summaryUpdatedAt = $thread['summary_updated_at'] ?? null;
    if ($summaryUpdatedAt) {
        $last = new DateTime($summaryUpdatedAt);
        $diffMinutes = (time() - $last->getTimestamp()) / 60;
        if ($diffMinutes < 10) {
            return;
        }
    }

    $systemPrompt = file_get_contents($summaryPromptPath);
    $messages = getChatMessages($threadId, 40);

    if (empty($messages)) {
        return;
    }

    if (count($messages) < 4 && !empty($thread['summary_text'])) {
        return;
    }

    $lines = [];
    foreach ($messages as $message) {
        $role = $message['role'] === 'assistant' ? 'Assistant' : 'Utilisateur';
        $lines[] = $role . ": " . $message['content'];
    }

    $userPrompt = "Résumé de la discussion suivante:\n\n" . implode("\n", $lines);

    try {
        $summaryText = callGemini($systemPrompt, $userPrompt, 0.2, 400);
    } catch (Exception $e) {
        return;
    }

    $summaryText = trim($summaryText);
    if ($summaryText === '') {
        return;
    }

    $sql = "UPDATE ai_chat_threads SET summary_text = ?, summary_updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    executeQuery($sql, [$summaryText, $threadId]);
}

function buildChatPrompt($threadId, $userMessage) {
    $thread = getThreadById($threadId);
    $contextMeta = resolveContextPeriodId($threadId, $thread['period_id'] ?? null);
    $context = buildFinancialContext($contextMeta['period_id'] ?? null);
    $messages = getChatMessages($threadId, 20);
    $userName = $_SESSION['username'] ?? 'Richard';

    $history = [];
    foreach ($messages as $message) {
        $role = $message['role'] === 'assistant' ? 'Assistant' : 'Utilisateur';
        $history[] = $role . ": " . $message['content'];
    }

    $relevantSummaries = getRelevantThreadSummaries($userMessage, $threadId, 4);
    $recentSummaries = getRecentThreadSummaries($threadId, 4);

    $payload = [
        'user_name' => $userName,
        'context' => $context,
        'context_meta' => $contextMeta,
        'history' => $history,
        'relevant_summaries' => $relevantSummaries,
        'recent_summaries' => $recentSummaries,
        'question' => $userMessage
    ];

    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

if (php_sapi_name() === 'cli' || realpath($_SERVER['SCRIPT_FILENAME']) !== __FILE__) {
    return;
}

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

if ($action === 'threads') {
    echo json_encode(['threads' => getChatThreads(50)]);
    exit;
}

if ($action === 'create_thread' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $period = getActivePeriod();
    $title = 'Discussion ' . date('d/m/Y H:i');
    $threadId = createChatThread($period['id'] ?? null, $title);
    echo json_encode(['thread_id' => $threadId]);
    exit;
}

if ($action === 'messages') {
    $threadId = (int)($_GET['thread_id'] ?? 0);
    if ($threadId <= 0) {
        echo json_encode(['messages' => []]);
        exit;
    }
    $messages = getChatMessages($threadId, 200);
    $formatted = array_map(function($message) {
        if ($message['role'] === 'assistant') {
            $message['content_html'] = formatChatAssistantContent($message['content']);
        } else {
            $message['content_html'] = '<p>' . htmlspecialchars($message['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</p>';
        }
        return $message;
    }, $messages);
    echo json_encode(['messages' => $formatted]);
    exit;
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $threadId = (int)($input['thread_id'] ?? 0);
    $message = trim((string)($input['message'] ?? ''));

    if ($threadId <= 0 || $message === '') {
        echo json_encode(['error' => 'Message invalide']);
        exit;
    }

    addChatMessage($threadId, 'user', $message);

    $systemPromptPath = __DIR__ . '/../prompts/ai_accountant_system.txt';
    $systemPrompt = file_exists($systemPromptPath) ? file_get_contents($systemPromptPath) : '';
    $userPrompt = "Données JSON:\n" . buildChatPrompt($threadId, $message);

    try {
        $rawResponse = callGemini($systemPrompt, $userPrompt);
        $assistantContent = $rawResponse;

        $toolRequest = parseDataRequest($assistantContent);
        if ($toolRequest) {
            $toolResponse = buildDataToolResponse($toolRequest);
            $toolPayload = json_encode($toolResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $followUpPrompt = $userPrompt . "\n\nDonnées demandées (outil DATA_REQUEST):\n" . $toolPayload;
            $assistantContent = callGemini($systemPrompt, $followUpPrompt, 0.2);
        }
    } catch (Exception $e) {
        $assistantContent = "Désolé, une erreur est survenue lors de l'appel IA: " . $e->getMessage();
    }

    addChatMessage($threadId, 'assistant', $assistantContent);
    updateThreadSummary($threadId);

    $responseHtml = formatChatAssistantContent($assistantContent);

    echo json_encode([
        'success' => true,
        'assistant' => [
            'content' => $assistantContent,
            'content_html' => $responseHtml
        ]
    ]);
    exit;
}

echo json_encode(['error' => 'Action invalide']);
