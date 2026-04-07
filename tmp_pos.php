<?php
$json = json_decode(file_get_contents(__DIR__ . '/config/erp_guidance.json'), true);
foreach ($json['guides'] as $index => $item) {
    if (in_array($item['title'], ['Cetak SP Piutang (Surat Teguran)', 'Cetak Konfirmasi Piutang', 'Cetak Tagihan Piutang'])) {
        echo "Found {$item['title']} at index $index\n";
    }
}
