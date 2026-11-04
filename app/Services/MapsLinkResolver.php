<?php

namespace App\Services;

use App\Support\ResolvedLink;
use App\Support\UserFacingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MapsLinkResolver
{
    private const MAX_REDIRECTS = 5;

    // Feste Liste: Nur diese Hosts werden je angefragt, auch bei Weiterleitungen (Schutz vor SSRF).
    private const ALLOWED_HOST = '/^(maps\.app\.goo\.gl|goo\.gl|g\.co|((www|maps|consent)\.)?google\.[a-z]{2,3}(\.[a-z]{2})?)$/i';

    /** Zieht die erste URL aus einem Text, etwa aus dem Teilen-Text der Maps-App. */
    public function extractUrl(string $text): ?string
    {
        if (! preg_match('~https?://[^\s<>"]+~i', $text, $matches)) {
            return null;
        }

        return rtrim($matches[0], '.,;:!?)');
    }

    public function resolve(string $url): ResolvedLink
    {
        $current = $this->normalize($url);

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $parsed = $this->parse($current);

            if ($parsed !== null) {
                return $parsed;
            }

            $next = $this->nextUrl($current);

            if ($next === null) {
                break;
            }

            $current = $this->normalize($next);
        }

        throw new UserFacingException('Aus diesem Link konnte ich keinen Ort lesen. Gib stattdessen den Namen des Orts ein.');
    }

    private function normalize(string $url): string
    {
        $parts = parse_url($url);
        $host = $parts['host'] ?? '';
        $scheme = strtolower($parts['scheme'] ?? '');

        if (
            ! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['port'])
            || ! preg_match(self::ALLOWED_HOST, $host)
        ) {
            throw new UserFacingException('Das ist kein Google-Maps-Link. Gib stattdessen den Namen des Orts ein.');
        }

        return preg_replace('~^http://~i', 'https://', $url);
    }

    private function nextUrl(string $url): ?string
    {
        $parts = parse_url($url);

        // Die Einwilligungsseite trägt das eigentliche Ziel im Parameter "continue".
        if (str_starts_with(strtolower($parts['host']), 'consent.')) {
            parse_str($parts['query'] ?? '', $query);

            return is_string($query['continue'] ?? null) ? $query['continue'] : null;
        }

        try {
            $response = Http::withoutRedirecting()
                ->timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; erlebt)'])
                ->get($url);
        } catch (ConnectionException) {
            throw new UserFacingException('Der Link konnte gerade nicht aufgelöst werden. Gib stattdessen den Namen des Orts ein.');
        }

        if (! $response->redirect()) {
            return null;
        }

        $location = $response->header('Location');

        if ($location === '') {
            return null;
        }

        // Relative Weiterleitungen bleiben auf demselben, bereits geprüften Host.
        return str_starts_with($location, '/')
            ? 'https://'.$parts['host'].$location
            : $location;
    }

    private function parse(string $url): ?ResolvedLink
    {
        $parts = parse_url($url);
        $path = rawurldecode($parts['path'] ?? '');
        parse_str($parts['query'] ?? '', $query);

        [$latitude, $longitude] = $this->coordinates($url);

        if (preg_match('~/maps/place/([^/]+)~', $path, $matches)) {
            $name = trim(str_replace('+', ' ', $matches[1]));

            if ($name !== '' && ! str_starts_with($name, '@')) {
                return new ResolvedLink($name, $latitude, $longitude);
            }
        }

        foreach (['q', 'query'] as $key) {
            if (is_string($query[$key] ?? null) && trim($query[$key]) !== '') {
                return new ResolvedLink(trim($query[$key]), $latitude, $longitude);
            }
        }

        return null;
    }

    /** @return array{0: ?float, 1: ?float} */
    private function coordinates(string $url): array
    {
        // !3d/!4d ist die genaue Position des Orts, @lat,lng nur die Kartenmitte.
        if (preg_match('/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $matches)
            || preg_match('/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $matches)) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        return [null, null];
    }
}
