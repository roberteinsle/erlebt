<?php

namespace App\Services;

/**
 * Schreibt den Bewertungstext. Bewusst ohne Parameter für fremde Bewertungen:
 * Der Text entsteht ausschließlich aus den Antworten der bewertenden Person.
 */
class ReviewWriter
{
    public const LENGTHS = [
        'kurz' => '2 bis 3 Sätze',
        'mittel' => '4 bis 6 Sätze',
        'ausfuehrlich' => '2 bis 3 kurze Absätze',
    ];

    public const TONES = [
        'sachlich' => 'sachlich und klar',
        'herzlich' => 'herzlich und warm',
    ];

    private const RULES = <<<'PROMPT'
    Du formulierst eine echte Google-Bewertung für eine Person, die den Ort selbst erlebt hat.

    Regeln:
    - Verwende nur Fakten aus den Antworten. Die Fragen dienen nur dazu, die Antworten zu verstehen;
      was nur in einer Frage steht, gilt nicht als erlebt, solange die Antwort es nicht bestätigt.
    - Erfinde keine Namen, Preise, Gerichte, Zeiten oder sonstigen Details.
    - Schreibe natürlich, in der Ich- oder Wir-Form, passend zu den Antworten, ohne Werbesprache.
    - Die Sternezahl bestimmt die Grundstimmung, nenne sie aber nicht im Text.
    - Gib nur den fertigen Text aus, ohne Überschrift, Anführungszeichen oder Kommentar.
    PROMPT;

    public function __construct(private OpenRouterClient $openRouter) {}

    /** @param list<array{frage: string, antwort: string}> $answers */
    public function write(string $placeName, array $answers, int $stars, string $length, string $tone): string
    {
        return $this->send([
            ['role' => 'system', 'content' => self::RULES],
            ['role' => 'user', 'content' => $this->brief($placeName, $answers, $stars, $length, $tone)],
        ]);
    }

    /** @param list<array{frage: string, antwort: string}> $answers */
    public function revise(string $placeName, array $answers, int $stars, string $length, string $tone, string $current, string $instruction): string
    {
        return $this->send([
            ['role' => 'system', 'content' => self::RULES."\n- Ein Änderungswunsch darf keine neuen Fakten erfinden, außer die Person nennt sie im Wunsch selbst."],
            ['role' => 'user', 'content' => $this->brief($placeName, $answers, $stars, $length, $tone)
                ."\n\nBisheriger Text:\n".$current
                ."\n\nÄnderungswunsch:\n".$instruction],
        ]);
    }

    public function model(): string
    {
        return config('erlebt.models.review');
    }

    private function send(array $messages): string
    {
        return $this->openRouter->chat($this->model(), $messages);
    }

    /** @param list<array{frage: string, antwort: string}> $answers */
    private function brief(string $placeName, array $answers, int $stars, string $length, string $tone): string
    {
        $pairs = collect($answers)
            ->map(fn (array $pair) => 'Frage: '.$pair['frage']."\nAntwort: ".$pair['antwort'])
            ->implode("\n\n");

        return "Ort: {$placeName}\nSterne: {$stars} von 5\nLänge: ".self::LENGTHS[$length]
            ."\nTon: ".self::TONES[$tone]."\n\n".$pairs;
    }
}
