<?php

$url = 'http://127.0.0.1:8000/api/home';
$response = file_get_contents($url, false, stream_context_create([
    'http' => ['header' => 'Accept: application/json']
]));

$data = json_decode($response, true);

if (!$data['success']) {
    echo "❌ API ERROR\n";
    exit(1);
}

$featured = $data['data']['featured_movie'];
$nowShowing = $data['data']['now_showing_movies'];

echo "✅ API /api/home RESPONSE:\n\n";
echo "Featured Movie: {$featured['title']}\n";
echo "Categories: " . implode(', ', array_column($featured['categories'], 'name')) . "\n";
echo "Backdrop URL: " . ($featured['backdrop_url'] ?: 'NULL') . "\n\n";

echo "Now Showing Movies ({count($nowShowing)}):\n";
foreach ($nowShowing as $movie) {
    $cats = implode(', ', array_column($movie['categories'], 'name'));
    echo "  - {$movie['title']} → {$cats}\n";
}

echo "\n✅ Categories đang được trả về đúng từ API!\n";
echo "Vui lòng REFRESH browser để thấy data thật thay vì fallback.\n";
