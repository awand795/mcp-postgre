<?php

namespace App\Services;

use App\Models\AiApiKey;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ApiKeyResolver — Pemilih dan Rotator API Key
 *
 * Konsep:
 * - Limit bukan dari admin, tapi dari provider (429 / quota habis).
 * - Saat 429 → flag key sebagai limit_reached=true di DB.
 * - Saat request berhasil → auto-reset limit_reached=false (berguna untuk free tier harian).
 * - Rotate hanya dari key yang di-assign admin ke user tersebut (user_ai_keys, is_enabled=true).
 */
class ApiKeyResolver
{
    /**
     * Ambil semua key milik user untuk provider tertentu, sudah disort prioritas:
     *   1. limit_reached = false dulu (key yang masih bisa dipakai)
     *   2. usage_count ASC (key paling jarang dipakai lebih diprioritaskan => load balancing)
     *
     * Hanya key yang:
     *   - Di-assign admin ke user ini (ada di user_ai_keys dengan is_enabled=true)
     *   - Key itu sendiri is_active=true di ai_api_keys
     *   - Provider sama dengan model yang dipilih user
     */
    public static function getKeysForProvider(User $user, int $providerId): Collection
    {
        return $user->aiKeys()
            ->where('ai_api_keys.provider_id', $providerId)
            ->where('ai_api_keys.is_active', true)
            ->wherePivot('is_enabled', true)
            ->orderByRaw('ai_api_keys.limit_reached ASC')
            ->orderBy('ai_api_keys.usage_count', 'ASC')
            ->with('provider')
            ->get();
    }

    /**
     * Dari collection key yang sudah disort, ambil key pertama yang belum limit.
     * Return null kalau semua sudah limit_reached.
     */
    public static function pickAvailable(Collection $keys): ?AiApiKey
    {
        return $keys->first(fn($k) => !$k->limit_reached);
    }

    /**
     * Tandai key ini sebagai limit_reached=true.
     * Dipanggil saat provider return 429 atau quota error.
     * Idempotent — tidak melakukan apa-apa jika sudah ter-flag.
     */
    public static function markLimitReached(AiApiKey $key): void
    {
        if (!$key->limit_reached) {
            $key->update(['limit_reached' => true]);
            $key->limit_reached = true;
            Log::warning("[ApiKeyResolver] Key ID={$key->id} ({$key->key_name}) => limit_reached=true (quota habis dari provider).");
        }
    }

    /**
     * Auto-reset limit_reached=false jika request berhasil.
     * Berguna untuk API gratis (Gemini Free Tier, Groq Free, dll) yang quota-nya reset harian.
     * Keesokan harinya user kirim pesan => request berhasil => flag otomatis bersih tanpa intervensi admin.
     * Idempotent — tidak melakukan apa-apa jika memang belum ter-flag.
     */
    public static function autoResetIfNeeded(AiApiKey $key): void
    {
        if ($key->limit_reached) {
            $key->update(['limit_reached' => false]);
            $key->limit_reached = false;
            Log::info("[ApiKeyResolver] Key ID={$key->id} ({$key->key_name}) => limit_reached auto-reset ke false (quota sudah pulih dari provider).");
        }
    }

    /**
     * Dari collection key, cari key berikutnya setelah key yang baru kena limit.
     * Skip key saat ini (sudah di-mark limit) dan semua yang sudah limit_reached.
     *
     * @param Collection $keys      Semua key user untuk provider ini (in-memory, sudah ter-update limit_reached)
     * @param int        $currentId ID key yang baru saja kena 429
     * @return AiApiKey|null        Key berikutnya yang bisa dipakai, atau null jika semua habis
     */
    public static function pickNextAvailable(Collection $keys, int $currentId): ?AiApiKey
    {
        return $keys->first(fn($k) => $k->id !== $currentId && !$k->limit_reached);
    }
}
