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
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            // Hapus semua token SSO lama milik user ini agar tidak menumpuk
            $user->tokens()->where('name', 'sso-iframe-token')->delete();

            // Generate Sanctum Personal Access Token baru
            $sanctumToken = $user->createToken('sso-iframe-token')->plainTextToken;

            // Render halaman HTML bridge agar token bisa disimpan di sessionStorage (untuk iframe HTTP)
            return response()->view('auth.sso_bridge', [
                'token'       => $sanctumToken,
                'redirect_to' => route('chatbot'),
                'user_name'   => $user->name,
            ]);
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
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
