<?php

use App\Livewire\Login;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('leitet Gaeste von allen Seiten zum Login', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/', '/bewertungen', '/bewertungen/1', '/bewertungen/1/markdown']);

it('bietet keine Registrierung an', function () {
    $this->get('/register')->assertNotFound();
});

it('meldet mit richtigem Passwort an', function () {
    $user = User::factory()->create(['password' => 'geheim-geheim']);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'geheim-geheim')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('sperrt nach fuenf falschen Versuchen', function () {
    $user = User::factory()->create(['password' => 'geheim-geheim']);
    $component = Livewire::test(Login::class)->set('email', $user->email);

    foreach (range(1, 5) as $ignored) {
        $component->set('password', 'falsch')->call('login');
    }

    $component->set('password', 'geheim-geheim')->call('login')->assertSet('error', fn ($error) => str_contains($error, 'Zu viele'));
    $this->assertGuest();
});

it('legt den Login aus der Konfiguration an und ist wiederholbar', function () {
    config(['erlebt.admin.email' => 'a@example.com', 'erlebt.admin.password' => 'pw-eins']);
    $this->seed(DatabaseSeeder::class);
    config(['erlebt.admin.password' => 'pw-zwei']);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1)
        ->and(Hash::check('pw-zwei', User::first()->password))->toBeTrue();
});

it('legt ohne Konfiguration keinen Login an', function () {
    config(['erlebt.admin.email' => null, 'erlebt.admin.password' => null]);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(0);
});

it('verbirgt fremde Bewertungen', function () {
    $owner = User::factory()->create();
    $review = Review::create(['user_id' => $owner->id, 'place_name' => 'Ort', 'questions' => [], 'stars' => 5, 'length' => 'kurz', 'tone' => 'herzlich']);

    $this->actingAs(User::factory()->create())
        ->get(route('reviews.show', $review))->assertNotFound();
    $this->get(route('reviews.markdown', $review))->assertNotFound();
});
