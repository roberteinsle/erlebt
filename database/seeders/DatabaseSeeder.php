<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Legt den einzigen Login an oder aktualisiert sein Passwort. Mehrfach ausführbar.
     */
    public function run(): void
    {
        $email = config('erlebt.admin.email');
        $password = config('erlebt.admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL oder ADMIN_PASSWORD fehlt, es wurde kein Login angelegt.');

            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'password' => $password],
        );
    }
}
