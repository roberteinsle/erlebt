<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Anmelden')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public ?string $error = null;

    public function login()
    {
        $key = 'login:'.Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->error = 'Zu viele Versuche. Bitte warte '.RateLimiter::availableIn($key).' Sekunden.';

            return null;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], remember: true)) {
            RateLimiter::hit($key, 60);
            $this->error = 'E-Mail oder Passwort stimmen nicht.';
            $this->password = '';

            return null;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function render()
    {
        return view('livewire.login');
    }
}
