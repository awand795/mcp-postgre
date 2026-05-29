<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SSOController extends Controller
{
    /**
     * Step 1: Handshake dari ERP Backend (Server-to-Server)
     * ERP kirim data user → Chatbot balas dengan One-Time-Token (OTT)
     */
    public function generateToken(Request $request)
    {
        // 1. Validasi SSO API Key
        $expectedKey = config('app.sso_api_key');
        $providedKey = $request->header('X-SSO-KEY') ?: $request->bearerToken();

        if (!$expectedKey || $providedKey !== $expectedKey) {
            return response()->json(['error' => __('Unauthorized SSO Handshake')], 401);
        }

        $request->validate([
            'email'        => 'required|email',
            'name'         => 'required|string',
            'erp_user_id'  => 'nullable|string|max:255',
        ]);

        $email = strtolower($request->email);

        // 2. Auto-Register atau temukan user
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name'                   => $request->name,
                'email'                  => $email,
                'erp_user_id'            => $request->erp_user_id,
                'password'               => Hash::make(Str::random(32)),
                'role'                   => null,
                'is_admin'               => false,
                'is_super_admin'         => false,
                'analysis_scope_limited' => true,
                'max_tokens'             => 4096,
            ]);
            Log::info("[SSO] Auto-registered user (PENDING ADMIN APPROVAL): {$email}");
        } else {
            $user->update([
                'name'        => $request->name,
                'erp_user_id' => $request->erp_user_id ?? $user->erp_user_id,
            ]);
        }

        // 3. Generate One-Time-Token (OTT) — berlaku 60 detik
        $ott = 'ott_' . Str::random(64);
        Cache::put('sso_token_' . $ott, $user->id, 60);

        return response()->json([
            'token'      => $ott,
            'expires_in' => 60,
            'login_url'  => route('sso.login', ['token' => $ott]),
        ]);
    }

    /**
     * Step 2: Browser (iframe) hit endpoint ini dengan OTT
     *
     * Karena HTTP + cross-domain, kita TIDAK bisa andalkan cookie session.
     * Solusi: generate Sanctum Personal Access Token, lalu kirim ke parent
     * ERP via window.postMessage. Iframe JS akan simpan token di memory
     * dan inject ke setiap fetch request sebagai Authorization: Bearer.
     */
    public function loginWithToken(Request $request)
    {
        $ott = $request->query('token');

        if (!$ott) {
            return $this->ssoErrorPage(__('Token tidak ditemukan.'));
        }

        $userId = Cache::pull('sso_token_' . $ott);

        if (!$userId) {
            return $this->ssoErrorPage(__('SSO Token sudah kadaluarsa atau tidak valid. Silakan muat ulang halaman ERP.'));
        }

        $user = User::find($userId);

        if (!$user) {
            return $this->ssoErrorPage(__('User tidak ditemukan.'));
        }

        // Hapus token SSO lama milik user ini yang sudah tidak aktif (> 24 jam) agar tidak menumpuk,
        // namun tidak menghapus token aktif di tab lain yang sedang terbuka.
        $user->tokens()
            ->where('name', 'sso-iframe-token')
            ->where(function($query) {
                $query->where('last_used_at', '<', now()->subHours(24))
                      ->orWhere(function($q) {
                          $q->whereNull('last_used_at')
                            ->where('created_at', '<', now()->subHours(24));
                      });
            })
            ->delete();

        // Generate Sanctum Personal Access Token baru
        $sanctumToken = $user->createToken('sso-iframe-token')->plainTextToken;

        // Login session biasa sebagai fallback (aman jika cookies aktif/diakses langsung di browser/Postman)
        \Illuminate\Support\Facades\Auth::login($user);

        Log::info("[SSO] Iframe token generated and session started for: {$user->email}");

        // Render halaman HTML kecil yang:
        // 1. Kirim token ke parent ERP via postMessage
        // 2. Redirect ke /chatbot dengan token di JS memory
        return response()->view('auth.sso_bridge', [
            'token'       => $sanctumToken,
            'redirect_to' => route('chatbot', $request->query()),
            'user_name'   => $user->name,
        ]);
    }

    /**
     * Cek apakah Sanctum token masih valid (untuk refresh check dari iframe)
     */
    public function checkToken(Request $request)
    {
        // Guard sanctum sudah handle validasi di middleware
        return response()->json([
            'valid' => true,
            'user'  => [
                'name'  => $request->user()->name,
                'email' => $request->user()->email,
            ],
        ]);
    }

    /**
     * Render halaman error SSO yang rapi
     */
    private function ssoErrorPage(string $message)
    {
        return response()->view('auth.sso_error', ['message' => $message], 403);
    }
}
