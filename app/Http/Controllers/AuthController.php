<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function showAdminLogin()
    {
        return view('auth.admin_login');
    }

    public function adminLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        if (Auth::attempt($credentials, $request->boolean('remember')) && $request->user()->isAdmin()) {
            $request->session()->regenerate();
            return redirect()->intended(route('admin.dashboard'));
        }
        Auth::logout();
        return back()->withErrors(['username' => 'Kredensial admin tidak valid.'])->onlyInput('username');
    }

    public function guest(Request $request): RedirectResponse
    {
        $request->session()->put('guest_order_id', bin2hex(random_bytes(16)));
        return redirect()->route('kiosk.home', ['guest' => 1]);
    }

    public function redirectToGoogle(): RedirectResponse
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google SSO belum dikonfigurasi.');
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $exception) {
            Log::warning('Google OAuth session state was missing or invalid.', [
                'session_id' => $request->session()->getId(),
                'host' => $request->getHost(),
            ]);

            return redirect()->route('login')->withErrors([
                'google' => 'Sesi login Google kedaluwarsa. Silakan coba lagi dari halaman login.',
            ]);
        }

        $user = User::firstOrNew(['google_id' => $googleUser->getId()]);
        $user->fill([
            'name' => $googleUser->getName() ?: 'Pengguna FHK',
            'email' => $googleUser->getEmail(),
            'email_verified_at' => now(),
            'role' => 'user',
        ]);
        if (! $user->exists) {
            $user->password = Hash::make(Str::random(48));
        }
        $user->save();
        Auth::login($user, true);
        $request->session()->regenerate();
        return redirect()->route('kiosk.home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}