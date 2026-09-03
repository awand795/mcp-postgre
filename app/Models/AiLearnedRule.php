<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLearnedRule extends Model
{
    protected $table = 'ai_learned_rules';

    protected $fillable = [
        'category',
        'trigger_keywords',
        'rule_description',
        'sql_hint',
        'is_active',
        'learned_from',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Cari aturan bisnis yang relevan dengan pertanyaan user.
     * Menggunakan pencarian kata kunci cerdas sehingga hanya aturan yang cocok
     * yang dimuat ke prompt (menghemat token hingga 98%).
     */
    public static function findMatchingRules(string $userMessage): array
    {
        if (empty(trim($userMessage))) {
            return [];
        }

        $activeRules = static::where('is_active', true)->get();
        $matched = [];

        foreach ($activeRules as $rule) {
            $keywords = array_map('trim', explode(',', strtolower($rule->trigger_keywords)));
            $isMatch = false;

            foreach ($keywords as $kw) {
                if ($kw !== '' && (
                    stripos($userMessage, $kw) !== false ||
                    preg_match('/\b' . preg_quote($kw, '/') . '\b/i', $userMessage)
                )) {
                    $isMatch = true;
                    break;
                }
            }

            if ($isMatch) {
                $matched[] = $rule;
            }
        }

        return $matched;
    }
}
