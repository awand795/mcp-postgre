<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('chatbot');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $isIframe = $request->input('is_iframe') === '1';

        if ($isIframe) {
            // Validasi manual agar tidak memicu auto-redirect Laravel yang menggunakan session
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);

            if ($validator->fails()) {
                return redirect()->route('login', [
                    'sso_error' => $validator->errors()->first(),
                    'email' => $request->input('email'),
                    'is_iframe' => '1'
                ]);
            }
            $credentials = $validator->validated();
        } else {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required'],
            ]);
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Hapus semua token SSO lama milik user ini agar tidak menumpuk
            $user->tokens()->where('name', 'sso-iframe-token')->delete();

            // Generate Sanctum Personal Access Token baru
            $sanctumToken = $user->createToken('sso-iframe-token')->plainTextToken;

            return response()->view('auth.sso_bridge', [
                'token'       => $sanctumToken,
                'redirect_to' => route('chatbot', $request->query()),
                'user_name'   => $user->name,
            ]);
        }

        if ($isIframe) {
            return redirect()->route('login', [
                'sso_error' => __('Email atau password salah.'),
                'email' => $request->input('email'),
                'is_iframe' => '1'
            ]);
        }

        return back()->withErrors([
            'email' => __('Email atau password salah.'),
        ])->onlyInput('email');
    }


    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
