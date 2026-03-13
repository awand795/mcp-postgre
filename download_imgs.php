<?php
$images = [
    'https://erp-guidance.online/wp-content/uploads/2026/01/1-28.png',
    'https://erp-guidance.online/wp-content/uploads/2026/01/2-29.png',
    'https://erp-guidance.online/wp-content/uploads/2026/01/3-22.png'
];

$dir = __DIR__ . "/";

foreach ($images as $i => $url) {
    echo "Downloading $url...\n";
    $content = @file_get_contents($url);
    if ($content !== false) {
        $filename = $dir . "klaim_img_" . ($i + 1) . ".png";
        file_put_contents($filename, $content);
        echo "Saved to $filename\n";
    } else {
        echo "Failed to download $url\n";
    }
}
