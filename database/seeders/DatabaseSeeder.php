<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Legt den Login an oder aktualisiert sein Passwort. Läuft bei jedem Deploy, die Variablen sind maßgeblich.
     */
    public function run(): void
    {
        $email = config('erlebt.admin.email');
        $password = config('erlebt.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL oder ADMIN_PASSWORD fehlt, der Login wurde nicht angelegt oder geändert.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'password' => $password],
        );

        $this->command?->info('Login aus ADMIN_EMAIL und ADMIN_PASSWORD übernommen.');
    }
}
