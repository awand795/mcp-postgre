<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TrackUserOnline
{
    /**
     * Handle an incoming request.
     * Mencatat timestamp aktivitas terakhir user ke Cache/Redis.
     * User dianggap Online jika ada aktivitas dalam 5 menit terakhir.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $userId = Auth::id();
            // TTL 5 menit untuk status online instan
            Cache::put('user-is-online-' . $userId, true, now()->addMinutes(5));
            // TTL 7 hari untuk riwayat waktu terakhir terlihat
            Cache::put('user-last-seen-' . $userId, now()->toISOString(), now()->addDays(7));
        }

        return $next($request);
    }
}
