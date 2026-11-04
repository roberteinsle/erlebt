<?php

namespace App\Services;

use App\Support\UserFacingException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenRouterClient
{
    private const URL = 'https://openrouter.ai/api/v1/chat/completions';

    // Zwei Versuche müssen unter PHP_MAX_EXECUTION_TIME (99 s) bleiben.
    private const TIMEOUT_SECONDS = 45;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>|null  $jsonSchema
     */
    public function chat(string $model, array $messages, ?array $jsonSchema = null): string
    {
        $key = config('services.openrouter.key');

        if (blank($key)) {
            throw new UserFacingException('Es ist kein OpenRouter-Schlüssel hinterlegt (OPENROUTER_API_KEY).');
        }

        $payload = ['model' => $model, 'messages' => $messages];

        if ($jsonSchema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'antwort', 'strict' => true, 'schema' => $jsonSchema],
            ];
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withToken($key)
                ->withHeaders(['X-Title' => 'erlebt'])
                ->post(self::URL, $payload);
        } catch (ConnectionException) {
            throw new UserFacingException('Das Sprachmodell antwortet gerade nicht. Bitte versuche es gleich noch einmal.');
        }

        if ($response->failed()) {
            report(new \RuntimeException('OpenRouter: HTTP '.$response->status().' '.$response->body()));

            throw new UserFacingException('Das Sprachmodell hat einen Fehler gemeldet (Status '.$response->status().').');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new UserFacingException('Das Sprachmodell hat eine leere Antwort geliefert. Bitte versuche es noch einmal.');
        }

        return trim($content);
    }
}
