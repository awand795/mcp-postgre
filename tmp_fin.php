<?php
$json = json_decode(file_get_contents(__DIR__ . '/config/erp_guidance.json'), true);
foreach ($json['guides'] as $index => $item) {
    if ($item['category'] == 'Finance') {
        echo "Found {$item['title']} at index $index\n";
    }
}
