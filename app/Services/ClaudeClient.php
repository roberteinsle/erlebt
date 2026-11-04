<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Support\UserFacingException;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClaudeClient
{
    /** Schneller Client für Einstellungen und Statusprüfung, siehe AppServiceProvider. */
    public const QUICK_CLIENT = 'claude.quick';

    /** Auswahl, falls die Modell-Liste der API gerade nicht abrufbar ist. */
    public const FALLBACK_MODELS = [
        'claude-haiku-5-5' => 'Claude Haiku 5.5',
        'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
        'claude-opus-5-5' => 'Claude Opus 5.5',
    ];

    private const MAX_TOKENS = 16000;

    // Bei einer Ablehnung wählt die API selbst ein passendes Ersatzmodell.
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    private const MODELS_CACHE_SECONDS = 3600;

    /** @param array<string, mixed>|null $jsonSchema Erzwingt eine Antwort als JSON nach diesem Schema */
    public function complete(string $model, string $system, string $user, ?array $jsonSchema = null): string
    {
        $message = $this->call(fn () => app(Client::class)->beta->messages->create(
            maxTokens: self::MAX_TOKENS,
            messages: [['role' => 'user', 'content' => $user]],
            model: $model,
            fallbacks: 'default',
            outputConfig: $jsonSchema === null
                ? null
                : ['format' => ['type' => 'json_schema', 'schema' => $jsonSchema]],
            system: $system,
            betas: [self::FALLBACK_BETA],
        ));

        if ($message->stopReason === 'refusal') {
            Log::warning('Claude hat eine Anfrage abgelehnt', ['modell' => $model, 'kategorie' => $message->stopDetails?->category]);

            throw new UserFacingException('Claude hat diese Anfrage abgelehnt. Bitte formuliere deine Angaben anders.');
        }

        if ($message->stopReason === 'max_tokens') {
            throw new UserFacingException('Die Antwort von Claude wurde abgeschnitten. Bitte versuche es noch einmal.');
        }

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        if (trim($text) === '') {
            throw new UserFacingException('Claude hat eine leere Antwort geliefert. Bitte versuche es noch einmal.');
        }

        return trim($text);
    }

    /** Minimaler echter Aufruf: prüft Schlüssel, Guthaben und Modell für wenige Tokens. */
    public function ping(string $model): void
    {
        $this->call(fn () => app(self::QUICK_CLIENT)->messages->create(
            maxTokens: 16,
            messages: [['role' => 'user', 'content' => 'Antworte nur mit: ok']],
            model: $model,
        ));
    }

    /**
     * Modelle, die strukturierte Ausgabe können; die Fragen-Erzeugung ist darauf angewiesen.
     *
     * @return array<string, string> Modell-ID => Anzeigename
     */
    public function availableModels(): array
    {
        return Cache::remember('claude-models', self::MODELS_CACHE_SECONDS, function () {
            $page = $this->call(fn () => app(self::QUICK_CLIENT)->models->list(limit: 100));
            $models = [];

            foreach ($page->getItems() as $model) {
                if ($model->capabilities?->structuredOutputs->supported ?? true) {
                    $models[$model->id] = $model->displayName;
                }
            }

            if ($models === []) {
                throw new UserFacingException('Die Claude API hat keine passenden Modelle geliefert.');
            }

            return $models;
        });
    }

    private function call(Closure $request): mixed
    {
        if (blank(config('services.anthropic.key'))) {
            throw new UserFacingException('Es ist kein Claude-API-Schlüssel hinterlegt (ANTHROPIC_API_KEY).');
        }

        try {
            return $request();
        } catch (AuthenticationException $exception) {
            $this->logFailure($exception);

            throw new UserFacingException('Claude hat den API-Schlüssel abgelehnt. Bitte prüfe ANTHROPIC_API_KEY.');
        } catch (RateLimitException $exception) {
            $this->logFailure($exception);

            throw new UserFacingException('Claude ist gerade ausgelastet. Bitte versuche es gleich noch einmal.');
        } catch (APIStatusException $exception) {
            $this->logFailure($exception);

            throw new UserFacingException(trim('Claude hat einen Fehler gemeldet (Status '.$exception->status.'). '.$this->apiMessage($exception)));
        } catch (APIConnectionException $exception) {
            Log::warning('Claude API nicht erreichbar', ['fehler' => $exception::class]);

            throw new UserFacingException('Claude antwortet gerade nicht. Bitte versuche es gleich noch einmal.');
        }
    }

    private function logFailure(APIStatusException $exception): void
    {
        Log::warning('Claude API meldet einen Fehler', [
            'status' => $exception->status,
            'typ' => $exception->type?->value,
            'meldung' => $this->apiMessage($exception),
        ]);
    }

    private function apiMessage(APIStatusException $exception): string
    {
        $message = is_array($exception->body) ? ($exception->body['error']['message'] ?? '') : '';

        return is_string($message) ? $message : '';
    }
}
