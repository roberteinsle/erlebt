<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
            Log::warning('Login gesperrt: zu viele Versuche', ['ip' => request()->ip()]);
            $this->error = 'Zu viele Versuche. Bitte warte '.RateLimiter::availableIn($key).' Sekunden.';

            return null;
        }

        $this->email = trim($this->email);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], remember: true)) {
            RateLimiter::hit($key, 60);
            $this->logFailure();
            $this->error = 'E-Mail oder Passwort stimmen nicht.';
            $this->password = '';

            return null;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /** Der genaue Grund steht nur im Log; die Seite verrät nicht, ob eine E-Mail existiert. */
    private function logFailure(): void
    {
        $reason = match (true) {
            User::count() === 0 => 'Es gibt noch keinen Login. ADMIN_EMAIL und ADMIN_PASSWORD prüfen und neu deployen.',
            User::where('email', $this->email)->doesntExist() => 'Diese E-Mail ist nicht angelegt. Mit ADMIN_EMAIL vergleichen.',
            default => 'Falsches Passwort. ADMIN_PASSWORD wird bei jedem Deploy übernommen.',
        };

        Log::warning('Login fehlgeschlagen: '.$reason, ['ip' => request()->ip()]);
    }

    public function render()
    {
        return view('livewire.login');
    }
}
