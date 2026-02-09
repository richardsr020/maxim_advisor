<?php
// ai_client.php - Client simple pour Gemini
require_once __DIR__ . '/config.php';

function callGemini($systemPrompt, $userPrompt, $temperature = 0.2, $maxTokens = 1200) {
    global $AI_PROVIDERS;

    $config = $AI_PROVIDERS['gemini'] ?? null;
    if (!$config) {
        throw new Exception("Configuration Gemini manquante");
    }

    $apiKey = $config['api_key'] ?? '';
    if ($apiKey === '') {
        throw new Exception("Clé API Gemini manquante (GEMINI_API_KEY)");
    }

    $apiUrl = $config['api_url'] ?? '';
    if ($apiUrl === '') {
        throw new Exception("URL API Gemini manquante");
    }

    $payload = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $userPrompt]
                ]
            ]
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemPrompt]
            ]
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens
        ]
    ];

    $url = $apiUrl . '?key=' . urlencode($apiKey);

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 60
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Erreur cURL: " . $error);
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Erreur API Gemini HTTP " . $httpCode . ": " . $response);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 60
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $error = error_get_last();
            $message = $error['message'] ?? 'Erreur HTTP inconnue';
            throw new Exception("Erreur HTTP (sans cURL): " . $message);
        }

        $statusLine = $http_response_header[0] ?? '';
        if (preg_match('/HTTP\\/\\d\\.\\d\\s+(\\d+)/', $statusLine, $matches)) {
            $httpCode = (int)$matches[1];
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new Exception("Erreur API Gemini HTTP " . $httpCode . ": " . $response);
            }
        }
    }

    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

    if ($text === '') {
        throw new Exception("Réponse IA vide ou invalide");
    }

    return $text;
}
