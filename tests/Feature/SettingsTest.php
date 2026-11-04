<?php

use App\Livewire\Login;
use App\Livewire\Settings;
use App\Models\User;
use App\Services\ModelSettings;
use App\Services\QuestionGenerator;
use App\Support\Place;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Support\FakeClaude;

function modelsPage(): array
{
    $support = fn (bool $supported) => ['supported' => $supported];
    $capabilities = fn (bool $structured) => array_fill_keys(['batch', 'citations', 'code_execution', 'image_input', 'pdf_input'], $support(true))
        + ['structured_outputs' => $support($structured)];

    return ['status' => 200, 'body' => [
        'data' => [
            ['id' => 'claude-neu', 'type' => 'model', 'display_name' => 'Claude Neu', 'created_at' => '2026-01-01T00:00:00Z', 'capabilities' => $capabilities(true)],
            ['id' => 'claude-alt', 'type' => 'model', 'display_name' => 'Claude Alt', 'created_at' => '2024-01-01T00:00:00Z', 'capabilities' => $capabilities(false)],
        ],
        'has_more' => false, 'first_id' => 'claude-neu', 'last_id' => 'claude-alt',
    ]];
}

function loggedInAdmin(): void
{
    test()->actingAs(User::factory()->create());
}

it('ist nur mit Login erreichbar', function () {
    $this->get('/einstellungen')->assertRedirect(route('login'));
});

it('zeigt die Modelle der API, die strukturierte Ausgabe können', function () {
    loggedInAdmin();
    FakeClaude::install(modelsPage());

    $component = Livewire::test(Settings::class);

    expect($component->get('models'))->toHaveKey('claude-neu')->not->toHaveKey('claude-alt')
        ->toHaveKey('claude-haiku-5-5')
        ->and($component->get('modelsError'))->toBeNull();
});

it('bietet durchsuchbare Auswahlfelder an', function () {
    loggedInAdmin();
    FakeClaude::install(modelsPage());

    Livewire::test(Settings::class)
        ->assertSeeHtml('role="combobox"')
        ->assertSee('Modell für die Fragen')
        ->assertSee('claude-neu');
});

it('fällt auf eine feste Liste zurück, wenn die API nicht antwortet', function () {
    loggedInAdmin();
    FakeClaude::install(FakeClaude::error(401, 'authentication_error'));

    $component = Livewire::test(Settings::class);

    expect($component->get('models'))->toHaveKey('claude-opus-5-5')
        ->and($component->get('modelsError'))->toContain('ANTHROPIC_API_KEY');
    expect(Cache::has('claude-models'))->toBeFalse();
});

it('speichert die Auswahl und nutzt sie für neue Anfragen', function () {
    loggedInAdmin();
    FakeClaude::install(modelsPage());

    Livewire::test(Settings::class)
        ->set('questionsModel', 'claude-neu')
        ->set('reviewModel', 'claude-neu')
        ->call('save')
        ->assertSet('saved', fn ($saved) => str_contains($saved, 'Gespeichert'));

    expect(app(ModelSettings::class)->review())->toBe('claude-neu');

    $claude = FakeClaude::install('kein json');
    app(QuestionGenerator::class)->generate(new Place('Ort'));

    expect($claude->requests[0]['model'])->toBe('claude-neu');
});

it('lehnt Modelle außerhalb der Liste ab', function () {
    loggedInAdmin();
    FakeClaude::install(modelsPage());

    Livewire::test(Settings::class)
        ->set('questionsModel', 'ausgedacht')
        ->call('save')
        ->assertSet('error', fn ($error) => str_contains($error, 'Liste'));

    expect(app(ModelSettings::class)->questions())->toBe(config('erlebt.models.questions'));
});

it('prüft alle APIs und zeigt Erfolge', function () {
    loggedInAdmin();
    FakeClaude::install(modelsPage());
    $component = Livewire::test(Settings::class);
    $claude = FakeClaude::install('ok');
    Http::fake(['places.googleapis.com/*' => Http::response(['places' => [['id' => 'x']]])]);

    $checks = collect($component->call('runChecks')->get('checks'));

    expect($checks->pluck('state')->unique()->all())->toBe(['ok'])
        ->and($checks)->toHaveCount(4)
        ->and(collect($claude->requests)->pluck('model')->all())->toBe(['claude-haiku-5-5', 'claude-sonnet-5-5'])
        ->and($claude->requests[0]['max_tokens'])->toBe(16);
    Http::assertSent(fn ($request) => $request->hasHeader('X-Goog-FieldMask', 'places.id'));
});

it('zeigt Fehler jeder API mit dem Grund', function () {
    loggedInAdmin();
    FakeClaude::install(modelsPage());
    $component = Livewire::test(Settings::class);
    FakeClaude::install(['status' => 400, 'body' => ['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Your credit balance is too low']]]);
    Http::fake(['places.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

    $component->call('runChecks')
        ->assertSee('Your credit balance is too low')
        ->assertSee('API key not valid');

    $states = collect($component->get('checks'))->pluck('state', 'name');

    expect($states['Datenbank'])->toBe('ok')
        ->and($states['Google Places'])->toBe('error')
        ->and($states['Claude API (claude-sonnet-5-5)'])->toBe('error');
});

it('zeigt Google ohne Schlüssel als nicht eingerichtet', function () {
    loggedInAdmin();
    FakeClaude::install(modelsPage());
    $component = Livewire::test(Settings::class);
    FakeClaude::install('ok');
    config(['services.google_places.key' => null]);
    Http::fake();

    expect(collect($component->call('runChecks')->get('checks'))->pluck('state', 'name')['Google Places'])->toBe('off');
    Http::assertNothingSent();
});

it('schreibt den Grund eines fehlgeschlagenen Logins ins Log, nie das Passwort', function (string $email, string $reason) {
    User::factory()->create(['email' => 'robert@example.com', 'password' => 'richtig-richtig']);
    Log::spy();

    Livewire::test(Login::class)->set('email', $email)->set('password', 'GEHEIM-FALSCH')->call('login')
        ->assertSet('error', 'E-Mail oder Passwort stimmen nicht.');

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => str_contains($message, $reason)
        && ! str_contains($message.json_encode($context), 'GEHEIM-FALSCH'));
})->with([
    'falsches Passwort' => ['robert@example.com', 'Falsches Passwort'],
    'unbekannte E-Mail' => ['fremd@example.com', 'nicht angelegt'],
]);

it('meldet im Log, wenn es noch keinen Login gibt', function () {
    Log::spy();

    Livewire::test(Login::class)->set('email', 'a@example.com')->set('password', 'x')->call('login');

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message) => str_contains($message, 'noch keinen Login'));
});

it('ignoriert Leerzeichen um die E-Mail', function () {
    $user = User::factory()->create(['email' => 'robert@example.com', 'password' => 'richtig-richtig']);

    Livewire::test(Login::class)->set('email', ' robert@example.com ')->set('password', 'richtig-richtig')->call('login');

    $this->assertAuthenticatedAs($user);
});
