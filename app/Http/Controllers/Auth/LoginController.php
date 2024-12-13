<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class LoginController extends Controller
{
    protected $redirectTo = '/sepa';

    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('sepa.index');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        Log::info('Login attempt', ['email' => $request->email]);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            Log::error('User not found', ['email' => $request->email]);
            return back()->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])->withInput($request->only('email'));
        }

        // Attempt authentication
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            Log::info('Login successful', ['user_id' => Auth::id()]);
            
            $request->session()->regenerate();
            
            Log::info('Session regenerated', [
                'session_id' => $request->session()->getId(),
                'user_id' => Auth::id()
            ]);

            return redirect()->intended(route('sepa.index'));
        }

        Log::error('Login failed - invalid credentials', ['email' => $request->email]);

        return back()
            ->withErrors([
                'email' => 'The provided credentials do not match our records.',
            ])
            ->withInput($request->only('email'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
} 