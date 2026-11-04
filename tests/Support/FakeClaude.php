<?php

namespace Tests\Support;

use Anthropic\Client;
use Closure;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Ersetzt den HTTP-Transport des SDK, damit Tests nie die echte API aufrufen. */
class FakeClaude implements ClientInterface
{
    /** @var list<array<string, mixed>> Gesendete Request-Bodies */
    public array $requests = [];

    /** @var list<array<string, list<string>>> Header je Request */
    public array $headers = [];

    private function __construct(private string|array|Closure $reply) {}

    /** @param string|array{status: int, body: array<string, mixed>}|Closure(int, array<string, mixed>): string $reply */
    public static function install(string|array|Closure $reply): self
    {
        $fake = new self($reply);

        app()->instance(Client::class, new Client(
            apiKey: 'test',
            requestOptions: ['transporter' => $fake, 'maxRetries' => 0],
        ));

        return $fake;
    }

    public static function error(int $status, string $type): array
    {
        return ['status' => $status, 'body' => ['type' => 'error', 'error' => ['type' => $type, 'message' => 'test']]];
    }

    public static function refusal(): array
    {
        return ['status' => 200, 'body' => self::message([], 'refusal')];
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $this->requests[] = $body;
        $this->headers[] = array_change_key_case($request->getHeaders());

        $reply = $this->reply instanceof Closure
            ? ($this->reply)(count($this->requests), $body)
            : $this->reply;

        if (is_string($reply)) {
            $reply = ['status' => 200, 'body' => self::message([['type' => 'text', 'text' => $reply]], 'end_turn')];
        }

        return new Response($reply['status'], ['Content-Type' => 'application/json'], json_encode($reply['body']));
    }

    private static function message(array $content, string $stopReason): array
    {
        return [
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'test',
            'content' => $content,
            'stop_reason' => $stopReason,
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ];
    }
}
