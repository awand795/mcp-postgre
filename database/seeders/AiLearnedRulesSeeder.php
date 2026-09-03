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
        ];

        foreach ($rules as $r) {
            AiLearnedRule::firstOrCreate(
                ['trigger_keywords' => $r['trigger_keywords']],
                $r
            );
        }
    }
}
