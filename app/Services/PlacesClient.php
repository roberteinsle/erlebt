<?php

namespace App\Services;

use App\Support\Place;
use App\Support\UserFacingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PlacesClient
{
    private const BASE_URL = 'https://places.googleapis.com/v1';

    private const SEARCH_RADIUS_METERS = 500.0;

    public function isConfigured(): bool
    {
        return filled(config('services.google_places.key'));
    }

    public function find(string $query, ?float $latitude = null, ?float $longitude = null): Place
    {
        return $this->details($this->searchId($query, $latitude, $longitude));
    }

    /** Günstigste Abfrage (nur IDs): prüft Schlüssel und API-Freischaltung. */
    public function ping(): void
    {
        $this->request('places.id', fn (PendingRequest $http) => $http->post(self::BASE_URL.'/places:searchText', [
            'textQuery' => 'Berlin',
            'languageCode' => 'de',
            'pageSize' => 1,
        ]));
    }

    private function searchId(string $query, ?float $latitude, ?float $longitude): string
    {
        $body = ['textQuery' => $query, 'languageCode' => 'de', 'pageSize' => 1];

        if ($latitude !== null && $longitude !== null) {
            $body['locationBias'] = ['circle' => [
                'center' => compact('latitude', 'longitude'),
                'radius' => self::SEARCH_RADIUS_METERS,
            ]];
        }

        $response = $this->request('places.id', fn (PendingRequest $http) => $http->post(self::BASE_URL.'/places:searchText', $body));
        $id = $response['places'][0]['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new UserFacingException('Diesen Ort habe ich bei Google nicht gefunden. Versuche es mit Name und Stadt.');
        }

        return $id;
    }

    private function details(string $placeId): Place
    {
        $data = $this->request(
            'displayName,formattedAddress,primaryType,rating,userRatingCount,reviews',
            fn (PendingRequest $http) => $http->get(self::BASE_URL.'/places/'.rawurlencode($placeId), ['languageCode' => 'de']),
        );

        $reviews = collect($data['reviews'] ?? [])
            ->map(fn ($review) => $review['text']['text'] ?? $review['originalText']['text'] ?? null)
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->values()
            ->all();

        return new Place(
            name: $data['displayName']['text'] ?? 'Unbekannter Ort',
            placeId: $placeId,
            address: $data['formattedAddress'] ?? null,
            type: $data['primaryType'] ?? null,
            rating: isset($data['rating']) ? (float) $data['rating'] : null,
            ratingCount: isset($data['userRatingCount']) ? (int) $data['userRatingCount'] : null,
            reviews: $reviews,
        );
    }

    private function request(string $fieldMask, callable $send): array
    {
        $http = Http::timeout(10)->withHeaders([
            'X-Goog-Api-Key' => (string) config('services.google_places.key'),
            'X-Goog-FieldMask' => $fieldMask,
        ]);

        try {
            $response = $send($http);
        } catch (ConnectionException) {
            throw new UserFacingException('Google ist gerade nicht erreichbar. Bitte versuche es gleich noch einmal.');
        }

        if ($response->failed()) {
            report(new \RuntimeException('Places API: HTTP '.$response->status().' '.$response->body()));

            throw new UserFacingException(trim('Die Abfrage bei Google ist fehlgeschlagen (Status '.$response->status().'). '.$response->json('error.message')));
        }

        return $response->json() ?? [];
    }
}
