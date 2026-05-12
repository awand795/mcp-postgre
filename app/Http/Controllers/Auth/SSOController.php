<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SSOController extends Controller
{
    /**
     * Step 1: Handshake from ERP Backend
     * ERP sends user data, Chatbot returns a One-Time-Token (OTT)
     */
    public function generateToken(Request $request)
    {
        // 1. Validate fixed API Key for security (Server-to-Server)
        $expectedKey = config('app.sso_api_key');
        $providedKey = $request->header('X-SSO-KEY') ?: $request->bearerToken();

        if (!$expectedKey || $providedKey !== $expectedKey) {
            return response()->json(['error' => 'Unauthorized SSO Handshake'], 401);
        }

        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
            'erp_user_id' => 'nullable|string',
        ]);

        $email = strtolower($request->email);

        // 2. Auto-Register or Find User
        $user = User::where('email', $email)->first();

        if (!$user) {
            // NEW USER: We do NOT assign a role or database access automatically.
            // They will be registered but "Locked" until Admin configures them.
            
            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
                'role' => null, // NO ROLE: Admin must manually assign one
                'is_admin' => false,
                'is_super_admin' => false,
                'analysis_scope_limited' => true, 
                'max_tokens' => 4096, // Give very small limit initially
            ]);

            Log::info("[SSO] Auto-registered user (PENDING ADMIN APPROVAL): {$email}");
        } else {
            // Update name if changed in ERP
            $user->update(['name' => $request->name]);
        }

        // 3. Generate One-Time-Token (OTT)
        $token = 'ott_' . Str::random(64);
        
        // Store in cache for 60 seconds
        Cache::put('sso_token_' . $token, $user->id, 60);

        return response()->json([
            'token' => $token,
            'expires_in' => 60,
            'login_url' => route('sso.login', ['token' => $token])
        ]);
    }

    /**
     * Step 2: Redirect from ERP Iframe
     * Browser hits this with the token, we perform Auto-Login
     */
    public function loginWithToken(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return abort(400, 'Token missing');
        }

        $userId = Cache::pull('sso_token_' . $token);

        if (!$userId) {
            return abort(403, 'SSO Token expired or invalid');
        }

        $user = User::find($userId);

        if (!$user) {
            return abort(404, 'User not found');
        }

        // Perform login
        Auth::login($user);

        Log::info("[SSO] User logged in via SSO: {$user->email}");

        // Redirect to main chatbot page
        return redirect()->route('chatbot');
    }
}
