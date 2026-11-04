<?php

use App\Services\MapsLinkResolver;
use App\Support\UserFacingException;
use Illuminate\Support\Facades\Http;

$longUrl = 'https://www.google.com/maps/place/Hotel+Goldner+Anker/@50.28,12.23,17z/data=!3m1!4b1!3d50.2812!4d12.2345';

it('zieht die URL aus dem Teilen-Text', function () {
    $text = "Hotel Goldner Anker\nhttps://maps.app.goo.gl/abc123?g_st=ic.";

    expect(app(MapsLinkResolver::class)->extractUrl($text))->toBe('https://maps.app.goo.gl/abc123?g_st=ic')
        ->and(app(MapsLinkResolver::class)->extractUrl('Hotel Goldner Anker'))->toBeNull();
});

it('liest Name und genaue Koordinaten aus einer langen URL ohne Anfrage', function () use ($longUrl) {
    Http::fake();

    $link = app(MapsLinkResolver::class)->resolve($longUrl);

    expect($link->name)->toBe('Hotel Goldner Anker')
        ->and($link->latitude)->toBe(50.2812)
        ->and($link->longitude)->toBe(12.2345);
    Http::assertNothingSent();
});

it('loest einen Kurzlink ueber Weiterleitungen auf', function () use ($longUrl) {
    Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => $longUrl])]);

    expect(app(MapsLinkResolver::class)->resolve('https://maps.app.goo.gl/abc')->name)->toBe('Hotel Goldner Anker');
});

it('liest den Suchbegriff aus q', function () {
    expect(app(MapsLinkResolver::class)->resolve('https://www.google.com/maps?q=Goldner+Anker')->name)->toBe('Goldner Anker');
});

it('folgt der Einwilligungsseite', function () use ($longUrl) {
    $consent = 'https://consent.google.com/m?continue='.urlencode($longUrl);
    Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => $consent])]);

    expect(app(MapsLinkResolver::class)->resolve('https://maps.app.goo.gl/abc')->name)->toBe('Hotel Goldner Anker');
});

it('lehnt fremde und interne Hosts ab, ohne sie anzufragen', function (string $url) {
    Http::fake();

    expect(fn () => app(MapsLinkResolver::class)->resolve($url))->toThrow(UserFacingException::class);
    Http::assertNothingSent();
})->with([
    'fremd' => 'https://example.com/maps/place/x',
    'intern' => 'http://169.254.169.254/latest/meta-data',
    'localhost' => 'http://localhost:8080/up',
    'getarnt' => 'https://google.com.evil.example/maps/place/x',
    'zugangsdaten' => 'https://google.com@evil.example/maps/place/x',
    'nur-benutzer' => 'https://user@www.google.com/maps/place/x',
    'port' => 'https://www.google.com:8443/maps/place/x',
    'schema' => 'file:///etc/passwd',
]);

it('blockiert Weiterleitungen auf fremde Hosts', function () {
    Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);

    expect(fn () => app(MapsLinkResolver::class)->resolve('https://maps.app.goo.gl/abc'))->toThrow(UserFacingException::class);
    Http::assertSentCount(1);
});

it('gibt bei Weiterleitungsschleifen auf', function () {
    Http::fake(['maps.app.goo.gl/*' => Http::response('', 302, ['Location' => 'https://maps.app.goo.gl/abc'])]);

    expect(fn () => app(MapsLinkResolver::class)->resolve('https://maps.app.goo.gl/abc'))->toThrow(UserFacingException::class);
});
