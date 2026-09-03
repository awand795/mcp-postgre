<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiLearnedRule;

class AiLearnedRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'category' => 'finance',
                'trigger_keywords' => 'hpp, cogs, harga pokok',
                'rule_description' => 'Istilah "HPP" adalah harga pokok satuan (SUM(kolom_satuan_hpp)). Sedangkan "Total HPP" adalah harga pokok dikali kuantitas (SUM(kolom_satuan_hpp * kolom_qty) atau kolom_total_hpp).',
                'sql_hint' => 'HPP: SUM(hrg_pokok) AS "HPP". Total HPP: SUM(hrg_pokok * qty) atau SUM(total_hpp) AS "Total HPP".',
                'learned_from' => 'system_seed',
                'is_active' => true,
            ],
            [
                'category' => 'finance',
                'trigger_keywords' => 'netto, net, kotor, bersih, omset, omzet, gross',
                'rule_description' => 'Istilah "Netto" adalah penjualan kotor sebelum diskon. Sedangkan "Total Netto" adalah penjualan bersih final setelah dipotong diskon.',
                'sql_hint' => 'Netto: SUM(netto) AS "Netto". Total Netto: SUM(total_netto) AS "Total Netto".',
                'learned_from' => 'system_seed',
                'is_active' => true,
            ],
            [
                'category' => 'tax',
                'trigger_keywords' => 'dpp, pajak, dasar pengenaan pajak, ppn',
                'rule_description' => 'Untuk nilai DPP (Dasar Pengenaan Pajak), periksa apakah ada kolom fisik dpp/nilai_dpp. Jika tidak ada kolom fisik, hitung dari total_netto dibagi 1.11 (PPN 11%) atau total_netto dikurangi ppn.',
                'sql_hint' => 'ROUND(SUM(total_netto / 1.11), 0) AS "DPP"',
                'learned_from' => 'system_seed',
                'is_active' => true,
            ],
            [
                'category' => 'entity_alias',
                'trigger_keywords' => 'ssi, pt ssi, star sparta, star sparta indonesia, p001',
                'rule_description' => 'PT SSI atau SSI adalah singkatan dari PT. STAR SPARTA INDONESIA (kode_perusahaan = "P001"). Memiliki 6 cabang: JKT (C001), SBY (C002), MDN (C003), SMRG (C004), BDG (C005), MKSR (C006). Jika user menanyakan data/cabang/penjualan dari SSI atau PT SSI, filter kode_perusahaan = "P001" atau join view_master_cabang WHERE kode_perusahaan = "P001". Kolom tanggal di view_penjualan_rekap/rinci adalah tgl_fak_jl dan nilai penjualan adalah total_netto.',
                'sql_hint' => 'kode_perusahaan = \'P001\'',
                'learned_from' => 'system_seed',
                'is_active' => true,
            ],
            [
                'category' => 'entity_alias',
                'trigger_keywords' => 'mkn, pt mkn, mitra kencana, mitra kencana nusantara, p003',
                'rule_description' => 'PT MKN atau MKN adalah singkatan dari PT. MITRA KENCANA NUSANTARA (kode_perusahaan = "P003"). Jika user menanyakan data/cabang/penjualan dari MKN atau PT MKN, filter kode_perusahaan = "P003".',
                'sql_hint' => 'kode_perusahaan = \'P003\'',
                'learned_from' => 'system_seed',
                'is_active' => true,
            ],
            [
                'category' => 'entity_alias',
                'trigger_keywords' => 'bpi, pt bpi, battery perkakas, battery perkakas indonesia, p011',
                'rule_description' => 'PT BPI atau BPI adalah singkatan dari PT. BATTERY PERPAKAS INDONESIA (kode_perusahaan = "P011"). Jika user menanyakan data/cabang/penjualan dari BPI atau PT BPI, filter kode_perusahaan = "P011".',
                'sql_hint' => 'kode_perusahaan = \'P011\'',
                'learned_from' => 'system_seed',
                'is_active' => true,
            ],
        ];

        foreach ($rules as $r) {
            AiLearnedRule::firstOrCreate(
                ['trigger_keywords' => $r['trigger_keywords']],
                $r
            );
        }
    }
}
