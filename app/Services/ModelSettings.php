<?php

namespace App\Services;

use App\Models\Setting;

/** Modellwahl aus den Einstellungen; ohne Auswahl gilt die Konfiguration (MODEL_QUESTIONS, MODEL_REVIEW). */
class ModelSettings
{
    private const QUESTIONS = 'model_questions';

    private const REVIEW = 'model_review';

    public function questions(): string
    {
        return Setting::find(self::QUESTIONS)?->value ?? config('erlebt.models.questions');
    }

    public function review(): string
    {
        return Setting::find(self::REVIEW)?->value ?? config('erlebt.models.review');
    }

    public function save(string $questions, string $review): void
    {
        Setting::updateOrCreate(['key' => self::QUESTIONS], ['value' => $questions]);
        Setting::updateOrCreate(['key' => self::REVIEW], ['value' => $review]);
    }
}
