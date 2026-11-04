<?php

namespace App\Support;

/** Bereitet ungeprüfte Bewertungen Dritter für einen klar abgegrenzten Daten-Abschnitt im Prompt auf. */
final class ForeignReviews
{
    /** @param list<string> $reviews */
    public static function format(array $reviews): string
    {
        // Spitze Klammern entfernen, damit fremder Text den Daten-Abschnitt nicht verlassen kann.
        return collect($reviews)
            ->map(fn (string $text) => '- '.str_replace(['<', '>'], ' ', $text))
            ->implode("\n");
    }
}
