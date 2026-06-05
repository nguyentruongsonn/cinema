<?php

// Test Home API Response
$url = 'http://127.0.0.1:8000/api/home';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response:\n";

if ($response) {
    $data = json_decode($response, true);

    if (isset($data['data'])) {
        // Check hero movie
        if (isset($data['data']['hero_movie'])) {
            $hero = $data['data']['hero_movie'];
            echo "\n=== HERO MOVIE ===\n";
            echo "Title: {$hero['title']}\n";
            echo "Categories: ";
            if (!empty($hero['categories'])) {
                echo implode(', ', array_column($hero['categories'], 'name')) . "\n";
            } else {
                echo "[EMPTY - THIS IS THE ISSUE]\n";
            }
            echo "Has backdrop_url: " . (isset($hero['backdrop_url']) ? 'YES' : 'NO') . "\n";
            echo "Has backdrops: " . (isset($hero['backdrops']) ? 'YES' : 'NO') . "\n";
            if (isset($hero['backdrops']) && !empty($hero['backdrops'])) {
                echo "Backdrops count: " . count($hero['backdrops']) . "\n";
            }
        }

        // Check now showing
        if (isset($data['data']['now_showing'])) {
            echo "\n=== NOW SHOWING (" . count($data['data']['now_showing']) . " movies) ===\n";
            foreach ($data['data']['now_showing'] as $idx => $movie) {
                echo "\n" . ($idx + 1) . ". {$movie['title']}\n";
                echo "   Categories: ";
                if (!empty($movie['categories'])) {
                    echo implode(', ', array_column($movie['categories'], 'name')) . "\n";
                } else {
                    echo "[EMPTY]\n";
                }
                echo "   Has backdrop_url: " . (isset($movie['backdrop_url']) ? 'YES' : 'NO') . "\n";
            }
        }

        // Check upcoming
        if (isset($data['data']['upcoming'])) {
            echo "\n=== UPCOMING (" . count($data['data']['upcoming']) . " movies) ===\n";
            foreach ($data['data']['upcoming'] as $idx => $movie) {
                echo ($idx + 1) . ". {$movie['title']}\n";
            }
        }
    }

    echo "\n=== FULL JSON ===\n";
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    echo "Failed to fetch data\n";
}
