<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->session()->get('admin_authenticated')) {
            return redirect()->route('admin.employees.index');
        }

        return Inertia::render('Admin/Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Troppi tentativi. Riprova tra '.RateLimiter::availableIn($throttleKey).' secondi.',
            ]);
        }

        $configuredEmail = config('admin.email');
        $configuredPassword = config('admin.password');

        if (
            ! is_string($configuredEmail)
            || ! is_string($configuredPassword)
            || ! hash_equals($configuredEmail, $credentials['email'])
            || ! hash_equals($configuredPassword, $credentials['password'])
        ) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Le credenziali non sono corrette.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->put('admin_authenticated', true);

        return redirect()->intended(route('admin.employees.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_authenticated');
        $request->session()->regenerate();

        return redirect()->route('admin.login');
    }
}
