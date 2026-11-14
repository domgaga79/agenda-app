<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin() { return view('auth.login'); }

    public function login(Request $request)
    {
        $request->merge(['email'=>mb_strtolower(trim((string)$request->input('email')))]);
        $key='agenda-login:'.hash('sha256',$request->input('email').'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key,5)) throw ValidationException::withMessages(['email'=>'Muitas tentativas. Aguarde '.RateLimiter::availableIn($key).' segundos.']);
        $credentials = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        if (!Auth::attempt($credentials + ['active'=>true], $request->boolean('remember'))) {
            RateLimiter::hit($key,60);
            return back()->withErrors(['email' => 'E-mail ou senha inválidos.'])->onlyInput('email');
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        if (!$request->user()->active || !$request->user()->company?->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return back()->withErrors(['email' => 'Seu acesso está desativado.']);
        }
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function showChangePassword() { return view('auth.change-password'); }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required','current_password'],
            'password' => ['required','confirmed','different:current_password','max:255', Password::min(12)->letters()->numbers()],
        ]);
        $user=$request->user();
        $user->password=Hash::make($data['password']);
        $user->must_change_password=false;
        $user->remember_token=Str::random(60);
        $user->save();
        $request->session()->regenerate();
        return redirect()->route('dashboard')->with('success', 'Senha atualizada com sucesso.');
    }
}
