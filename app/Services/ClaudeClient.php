<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Support\UserFacingException;

class ClaudeClient
{
    private const MAX_TOKENS = 16000;

    // Bei einer Ablehnung wählt die API selbst ein passendes Ersatzmodell.
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /** @param array<string, mixed>|null $jsonSchema Erzwingt eine Antwort als JSON nach diesem Schema */
    public function complete(string $model, string $system, string $user, ?array $jsonSchema = null): string
    {
        if (blank(config('services.anthropic.key'))) {
            throw new UserFacingException('Es ist kein Claude-API-Schlüssel hinterlegt (ANTHROPIC_API_KEY).');
        }

        try {
            $message = app(Client::class)->beta->messages->create(
                maxTokens: self::MAX_TOKENS,
                messages: [['role' => 'user', 'content' => $user]],
                model: $model,
                fallbacks: 'default',
                outputConfig: $jsonSchema === null
                    ? null
                    : ['format' => ['type' => 'json_schema', 'schema' => $jsonSchema]],
                system: $system,
                betas: [self::FALLBACK_BETA],
            );
        } catch (AuthenticationException $exception) {
            report($exception);

            throw new UserFacingException('Claude hat den API-Schlüssel abgelehnt. Bitte prüfe ANTHROPIC_API_KEY.');
        } catch (RateLimitException) {
            throw new UserFacingException('Claude ist gerade ausgelastet. Bitte versuche es gleich noch einmal.');
        } catch (APIStatusException $exception) {
            report($exception);

            throw new UserFacingException('Claude hat einen Fehler gemeldet (Status '.$exception->status.').');
        } catch (APIConnectionException) {
            throw new UserFacingException('Claude antwortet gerade nicht. Bitte versuche es gleich noch einmal.');
        }

        if ($message->stopReason === 'refusal') {
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
}
