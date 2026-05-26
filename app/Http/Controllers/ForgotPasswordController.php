<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Carbon\Carbon;

use Illuminate\Validation\Rules;

class ForgotPasswordController extends Controller
{
    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $isIframe = $request->input('is_iframe') === '1';

        if ($isIframe) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return redirect()->route('password.request', [
                    'sso_error' => $validator->errors()->first(),
                    'email' => $request->input('email'),
                    'is_iframe' => '1'
                ]);
            }
        } else {
            $request->validate(['email' => 'required|email|exists:users,email']);
        }

        // Hard Limit: 3 attempts per 24 hours per email
        $hardLimitKey = 'otp_hard_limit:' . Str::lower($request->email);
        $requestCount = \Illuminate\Support\Facades\Cache::get($hardLimitKey, 0);

        if ($requestCount >= 3) {
            if ($isIframe) {
                return redirect()->route('password.request', [
                    'sso_hard_block' => '1',
                    'email' => $request->email,
                    'is_iframe' => '1'
                ]);
            }
            return back()->with('hard_block', true);
        }

        // Rate Limiting: 1 attempt per minute per email/IP to protect SMTP
        $throttleKey = Str::lower($request->email) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            if ($isIframe) {
                return redirect()->route('password.request', [
                    'sso_throttle_seconds' => $seconds,
                    'email' => $request->email,
                    'is_iframe' => '1'
                ]);
            }
            return back()->withErrors([
                'email' => "Silakan tunggu $seconds detik sebelum meminta OTP baru."
            ])->with('throttle_seconds', $seconds);
        }

        RateLimiter::hit($throttleKey, 60);
        \Illuminate\Support\Facades\Cache::put($hardLimitKey, $requestCount + 1, now()->addDay());

        // Generate 6-digit OTP
        $otp = sprintf("%06d", mt_rand(1, 999999));

        // Delete old OTPs and Insert new one
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($otp),
                'created_at' => Carbon::now()
            ]
        );

        // Send Email
        try {
            Mail::send('emails.otp', ['otp' => $otp], function($message) use($request){
                $message->to($request->email);
                $message->subject('Reset Password OTP - darkotech AI');
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error sending OTP email: ' . $e->getMessage());
            if ($isIframe) {
                return redirect()->route('password.request', [
                    'sso_error' => 'Gagal mengirim email. Pastikan konfigurasi SMTP benar.',
                    'email' => $request->email,
                    'is_iframe' => '1'
                ]);
            }
            return back()->withErrors(['email' => 'Gagal mengirim email. Pastikan konfigurasi SMTP benar.']);
        }

        return redirect()->route('password.verify', ['email' => $request->email])
                         ->with('status', 'Kami telah mengirimkan OTP ke email Anda.');
    }

    public function showVerifyOtpForm(Request $request)
    {
        if (!$request->email) {
            return redirect()->route('password.request');
        }
        return view('auth.verify-otp', ['email' => $request->email]);
    }

    public function verifyOtp(Request $request)
    {
        $isIframe = $request->input('is_iframe') === '1';

        if ($isIframe) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric|digits:6',
            ]);

            if ($validator->fails()) {
                return redirect()->route('password.verify', [
                    'sso_error' => $validator->errors()->first(),
                    'email' => $request->input('email'),
                    'is_iframe' => '1'
                ]);
            }
        } else {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric|digits:6',
            ]);
        }

        // Rate Limiting: 5 attempts per minute per IP to prevent brute-forcing
        $throttleKey = 'verify-otp|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            if ($isIframe) {
                return redirect()->route('password.verify', [
                    'sso_error' => "Terlalu banyak percobaan. Silakan coba lagi dalam $seconds detik.",
                    'email' => $request->email,
                    'is_iframe' => '1'
                ]);
            }
            return back()->withErrors([
                'otp' => "Terlalu banyak percobaan. Silakan coba lagi dalam $seconds detik."
            ]);
        }

        RateLimiter::hit($throttleKey, 60);

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset) {
            if ($isIframe) {
                return redirect()->route('password.verify', [
                    'sso_error' => 'OTP tidak ditemukan atau sudah kadaluarsa.',
                    'email' => $request->email,
                    'is_iframe' => '1'
                ]);
            }
            return back()->withErrors(['otp' => 'OTP tidak ditemukan atau sudah kadaluarsa.'])->withInput();
        }

        if (!Hash::check($request->otp, $reset->token)) {
            if ($isIframe) {
                return redirect()->route('password.verify', [
                    'sso_error' => 'OTP salah.',
                    'email' => $request->email,
                    'is_iframe' => '1'
                ]);
            }
            return back()->withErrors(['otp' => 'OTP salah.'])->withInput();
        }

        if (Carbon::parse($reset->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            if ($isIframe) {
                return redirect()->route('password.verify', [
                    'sso_error' => 'OTP sudah kadaluarsa. Silakan minta ulang.',
                    'email' => $request->email,
                    'is_iframe' => '1'
                ]);
            }
            return back()->withErrors(['otp' => 'OTP sudah kadaluarsa. Silakan minta ulang.'])->withInput();
        }

        // OTP valid, continue to reset password page
        return redirect()->route('password.reset', [
            'email' => $request->email,
            'otp' => $request->otp,
            'is_iframe' => $isIframe ? '1' : '0'
        ]);
    }

    public function showResetPasswordForm(Request $request)
    {
        if (!$request->email || !$request->otp) {
            return redirect()->route('password.request');
        }
        return view('auth.reset-password', [
            'email' => $request->email,
            'otp' => $request->otp
        ]);
    }

    public function resetPassword(Request $request)
    {
        $isIframe = $request->input('is_iframe') === '1';

        if ($isIframe) {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric|digits:6',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);

            if ($validator->fails()) {
                return redirect()->route('password.reset', [
                    'sso_error' => $validator->errors()->first(),
                    'email' => $request->input('email'),
                    'otp' => $request->input('otp'),
                    'is_iframe' => '1'
                ]);
            }
        } else {
            $request->validate([
                'email' => 'required|email|exists:users,email',
                'otp' => 'required|numeric|digits:6',
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ]);
        }

        $reset = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->otp, $reset->token)) {
            if ($isIframe) {
                return redirect()->route('password.request', [
                    'sso_error' => 'Sesi reset password tidak valid atau OTP salah.',
                    'is_iframe' => '1'
                ]);
            }
            return redirect()->route('password.request')->withErrors(['email' => 'Sesi reset password tidak valid atau OTP salah.']);
        }

        if (Carbon::parse($reset->created_at)->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            if ($isIframe) {
                return redirect()->route('password.request', [
                    'sso_error' => 'OTP sudah kadaluarsa.',
                    'is_iframe' => '1'
                ]);
            }
            return redirect()->route('password.request')->withErrors(['email' => 'OTP sudah kadaluarsa.']);
        }

        // Update User password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete used OTP
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return redirect()->route('login')->with('status', 'Password berhasil direset. Silakan login dengan password baru Anda.');
    }
}
