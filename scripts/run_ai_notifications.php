<?php
// scripts/run_ai_notifications.php - Génère les notifications IA périodiques

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Ce script doit être exécuté en CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notifications.php';
require_once __DIR__ . '/../includes/ai_client.php';

$options = getopt('', ['timeframe::', 'dry-run', 'force']);
$timeframeOption = $options['timeframe'] ?? null;
$dryRun = isset($options['dry-run']);
$force = isset($options['force']);

$timeframes = ['week', 'month', 'year'];
if ($timeframeOption) {
    if (!in_array($timeframeOption, $timeframes, true)) {
        fwrite(STDERR, "Timeframe invalide. Utilisez week, month ou year.\n");
        exit(1);
    }
    $timeframes = [$timeframeOption];
}

$systemPromptPath = __DIR__ . '/../prompts/ai_system_prompt.txt';
if (!file_exists($systemPromptPath)) {
    fwrite(STDERR, "Prompt système introuvable: {$systemPromptPath}\n");
    exit(1);
}

$systemPrompt = file_get_contents($systemPromptPath);

function getRangeForTimeframe($timeframe) {
    $yesterday = new DateTime('yesterday');

    if ($timeframe === 'week') {
        $end = clone $yesterday;
        $start = clone $yesterday;
        $start->modify('monday this week');
        return [$start, $end];
    }

    if ($timeframe === 'month') {
        $end = new DateTime('first day of this month');
        $end->modify('-1 day');
        $start = new DateTime($end->format('Y-m-01'));
        return [$start, $end];
    }

    $end = new DateTime('last day of december last year');
    $start = new DateTime('first day of january last year');
    return [$start, $end];
}

$today = new DateTime('today');

foreach ($timeframes as $timeframe) {
    if (!$force) {
        if ($timeframe === 'week' && $today->format('N') !== '1') {
            continue;
        }
        if ($timeframe === 'month' && $today->format('j') !== '1') {
            continue;
        }
        if ($timeframe === 'year' && !($today->format('n') === '1' && $today->format('j') === '1')) {
            continue;
        }
    }

    [$startDate, $endDate] = getRangeForTimeframe($timeframe);
    $rangeStart = $startDate->format('Y-m-d');
    $rangeEnd = $endDate->format('Y-m-d');

    if (notificationExists($timeframe, $rangeStart, $rangeEnd)) {
        echo "Notification déjà générée pour {$timeframe} {$rangeStart} → {$rangeEnd}\n";
        continue;
    }

    echo "Préparation export {$timeframe} {$rangeStart} → {$rangeEnd}\n";
    $export = exportRangeToJSON($timeframe, $rangeStart, $rangeEnd);

    $exportPayload = file_get_contents($export['filepath']);
    $userPrompt = "Analyse la période {$rangeStart} au {$rangeEnd}. " .
        "Voici les données JSON:\n\n" . $exportPayload;

    if ($dryRun) {
        echo "Dry-run: export créé {$export['filename']}\n";
        continue;
    }

    echo "Appel IA...\n";
    $rawResponse = callGemini($systemPrompt, $userPrompt);
    $analysisHtml = formatAiContent($rawResponse);

    createNotification([
        'timeframe' => $timeframe,
        'range_start' => $rangeStart,
        'range_end' => $rangeEnd,
        'export_path' => $export['filepath'],
        'analysis_html' => $analysisHtml,
        'raw_response' => $rawResponse,
        'is_read' => 0
    ]);

    echo "Notification IA enregistrée pour {$timeframe} {$rangeStart} → {$rangeEnd}\n";
}
