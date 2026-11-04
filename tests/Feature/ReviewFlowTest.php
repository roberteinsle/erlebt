<?php

use App\Livewire\ReviewShow;
use App\Livewire\ReviewWizard;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\FakeClaude;

const FOREIGN_REVIEW = 'FREMDER-TEXT-Wildschwein-Marker';

function fakeApis(): FakeClaude
{
    Http::fake([
        'places.googleapis.com/v1/places:searchText' => Http::response(['places' => [['id' => 'abc']]]),
        'places.googleapis.com/v1/places/abc*' => Http::response([
            'displayName' => ['text' => 'Hotel Goldner Anker'],
            'formattedAddress' => 'Walter-Rathenau-Straße 9, Bad Elster',
            'primaryType' => 'hotel',
            'rating' => 4.3,
            'userRatingCount' => 120,
            'reviews' => [['text' => ['text' => FOREIGN_REVIEW]]],
        ]),
    ]);

    return FakeClaude::install(fn (int $call, array $request) => isset($request['output_config'])
        ? json_encode(['fragen' => [
            ['frage' => 'Wie war das Zimmer?', 'warum' => 'w', 'beispiel' => 'b'],
            ['frage' => 'Wie war das Frühstück?', 'warum' => 'w', 'beispiel' => 'b'],
            ['frage' => 'Wie war der Service?', 'warum' => 'w', 'beispiel' => 'b'],
            ['frage' => 'Wie war die Lage?', 'warum' => 'w', 'beispiel' => 'b'],
        ]])
        : 'Ein erzeugter Text.');
}

function wizardAtQuestions()
{
    return Livewire::test(ReviewWizard::class)
        ->set('input', 'Hotel Goldner Anker')
        ->call('findPlace')
        ->assertSet('step', 'confirm')
        ->call('confirmPlace')
        ->assertSet('step', 'questions');
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('fuehrt vom Ort bis zum gespeicherten Text', function () {
    fakeApis();

    $component = wizardAtQuestions()
        ->set('answers.0', 'Grosses Zimmer mit Balkon')
        ->call('toOptions')
        ->assertSet('step', 'options')
        ->set('stars', 4)
        ->set('length', 'kurz')
        ->set('tone', 'sachlich')
        ->call('generate');

    $review = Review::sole();
    $component->assertRedirect(route('reviews.show', $review));

    expect($review->place_name)->toBe('Hotel Goldner Anker')
        ->and($review->place_id)->toBe('abc')
        ->and($review->questions)->toBe([['frage' => 'Wie war das Zimmer?', 'antwort' => 'Grosses Zimmer mit Balkon']])
        ->and($review->versions()->sole()->body)->toBe('Ein erzeugter Text.');
});

it('schickt fremde Bewertungen nur an den Fragen-Aufruf und speichert sie nie', function () {
    $claude = fakeApis();

    wizardAtQuestions()->set('answers.0', 'Gut')->call('toOptions')->set('stars', 5)->call('generate');

    [$questionCall, $writeCall] = $claude->requests;

    expect(json_encode($questionCall))->toContain(FOREIGN_REVIEW)
        ->and(json_encode($writeCall))->not->toContain(FOREIGN_REVIEW)
        ->and(json_encode(Review::sole()->toArray()))->not->toContain(FOREIGN_REVIEW);
});

it('nutzt die konfigurierten Modelle und den Fallback', function () {
    $claude = fakeApis();

    wizardAtQuestions()->set('answers.0', 'Gut')->call('toOptions')->set('stars', 5)->call('generate');

    expect($claude->requests[0]['model'])->toBe('claude-haiku-5-5')
        ->and($claude->requests[1]['model'])->toBe('claude-sonnet-5-5')
        ->and($claude->requests[1]['fallbacks'])->toBe('default')
        ->and($claude->headers[1]['anthropic-beta'] ?? [])->toContain('server-side-fallback-2026-07-01')
        ->and($claude->requests[1]['system'])->toContain('nur Fakten aus den Antworten');
});

it('zeigt eine Ablehnung und speichert nichts', function () {
    fakeApis();
    $component = wizardAtQuestions()->set('answers.0', 'Gut')->call('toOptions')->set('stars', 5);
    FakeClaude::install(FakeClaude::refusal());

    $component->call('generate')->assertSet('error', fn ($error) => str_contains($error, 'abgelehnt'));

    expect(Review::count())->toBe(0);
});

it('meldet einen ungültigen Schlüssel verständlich', function () {
    fakeApis();
    $component = wizardAtQuestions()->set('answers.0', 'Gut')->call('toOptions')->set('stars', 5);
    FakeClaude::install(FakeClaude::error(401, 'authentication_error'));

    $component->call('generate')->assertSet('error', fn ($error) => str_contains($error, 'ANTHROPIC_API_KEY'));
});

it('verlangt einen Schlüssel', function () {
    fakeApis();
    $component = wizardAtQuestions()->set('answers.0', 'Gut')->call('toOptions')->set('stars', 5);
    config(['services.anthropic.key' => null]);

    $component->call('generate')->assertSet('error', fn ($error) => str_contains($error, 'ANTHROPIC_API_KEY'));
});

it('entfernt fremde Bewertungen nach den Fragen aus dem Zustand', function () {
    fakeApis();

    expect(wizardAtQuestions()->get('place')['reviews'])->toBe([]);
});

it('verlangt mindestens eine Antwort', function () {
    fakeApis();

    wizardAtQuestions()
        ->set('answers.0', '   ')
        ->call('toOptions')
        ->assertSet('step', 'questions')
        ->assertSet('error', fn ($error) => str_contains($error, 'mindestens eine Antwort'));
});

it('verlangt eine Sternezahl', function () {
    fakeApis();

    wizardAtQuestions()->set('answers.0', 'Gut')->call('toOptions')->call('generate')
        ->assertSet('error', 'Bitte wähle eine Sternezahl.');

    expect(Review::count())->toBe(0);
});

it('schuetzt den Zustand vor Manipulation', function () {
    fakeApis();

    expect(fn () => Livewire::test(ReviewWizard::class)->set('step', 'options'))->toThrow(Exception::class);
});

it('haelt das Tageslimit ein', function () {
    config(['erlebt.max_runs_per_day' => 1]);
    fakeApis();

    $component = Livewire::test(ReviewWizard::class)->set('input', 'Ort')->call('findPlace')->assertSet('step', 'confirm');
    $component->call('changePlace')->set('input', 'Ort')->call('findPlace')
        ->assertSet('step', 'link')
        ->assertSet('error', fn ($error) => str_contains($error, 'Tageslimit'));
});

it('funktioniert ohne Google-Schlüssel mit dem eingegebenen Namen', function () {
    config(['services.google_places.key' => null]);
    Http::fake();

    Livewire::test(ReviewWizard::class)->set('input', 'Café Muster')->call('findPlace')
        ->assertSet('step', 'confirm')
        ->assertSet('place.name', 'Café Muster');
    Http::assertNothingSent();
});

it('zeigt verstaendliche Fehler bei einem unbekannten Link', function () {
    Http::fake();

    Livewire::test(ReviewWizard::class)->set('input', 'https://example.com/x')->call('findPlace')
        ->assertSet('step', 'link')
        ->assertSet('error', fn ($error) => str_contains($error, 'kein Google-Maps-Link'));
});

function storedReview(User $user): Review
{
    $review = Review::create([
        'user_id' => $user->id, 'place_name' => 'Hotel Goldner Anker', 'place_address' => 'Bad Elster',
        'questions' => [['frage' => 'Zimmer?', 'antwort' => 'Gross']], 'stars' => 4, 'length' => 'mittel', 'tone' => 'herzlich',
    ]);
    $review->versions()->create(['body' => 'Erste Fassung.', 'model' => 'm']);

    return $review;
}

it('ueberarbeitet und behaelt alte Versionen', function () {
    $review = storedReview($this->user);
    $claude = FakeClaude::install('Kurze Fassung.');

    Livewire::test(ReviewShow::class, ['review' => $review])
        ->call('revisePreset', 'kuerzer')
        ->assertSee('Kurze Fassung.')
        ->assertSee('Erste Fassung.');

    expect($review->versions()->count())->toBe(2)
        ->and($review->latestVersion()->first()->instruction)->toBe('Mach den Text deutlich kürzer.');
    expect(json_encode($claude->requests[0]))->toContain('Erste Fassung.');
});

it('setzt freie Änderungswünsche um und lehnt leere ab', function () {
    $review = storedReview($this->user);
    FakeClaude::install('Neu.');

    Livewire::test(ReviewShow::class, ['review' => $review])
        ->call('reviseCustom')
        ->assertSet('error', fn ($error) => str_contains($error, 'Änderungswunsch'))
        ->set('instruction', 'Ohne Hund')
        ->call('reviseCustom')
        ->assertSet('instruction', '');

    expect($review->versions()->count())->toBe(2);
});

it('behaelt den Text, wenn die Ueberarbeitung scheitert', function () {
    $review = storedReview($this->user);
    FakeClaude::install(FakeClaude::error(500, 'api_error'));

    Livewire::test(ReviewShow::class, ['review' => $review])
        ->call('revisePreset', 'kuerzer')
        ->assertSet('error', fn ($error) => str_contains($error, 'Fehler'));

    expect($review->versions()->count())->toBe(1);
});

it('exportiert die neueste Fassung als Markdown', function () {
    $review = storedReview($this->user);
    $review->versions()->create(['body' => 'Zweite Fassung.', 'model' => 'm']);

    $response = $this->get(route('reviews.markdown', $review))->assertOk();
    $response->assertDownload('bewertung-hotel-goldner-anker.md');

    expect($response->streamedContent())
        ->toContain('# Hotel Goldner Anker')
        ->toContain('★★★★☆')
        ->toContain('Zweite Fassung.')
        ->not->toContain('Erste Fassung.');
});

it('zeigt den Verlauf', function () {
    storedReview($this->user);

    $this->get(route('reviews.index'))->assertOk()->assertSee('Hotel Goldner Anker')->assertSee('Erste Fassung.');
});

it('escaped Modellausgaben', function () {
    $review = storedReview($this->user);
    $review->versions()->create(['body' => '<script>alert(1)</script>', 'model' => 'm']);

    $this->get(route('reviews.show', $review))->assertOk()->assertDontSee('<script>alert(1)</script>', false);
});
