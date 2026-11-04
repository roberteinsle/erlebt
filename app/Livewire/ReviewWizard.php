<?php

namespace App\Livewire;

use App\Models\Review;
use App\Services\MapsLinkResolver;
use App\Services\PlacesClient;
use App\Services\QuestionGenerator;
use App\Services\ReviewWriter;
use App\Support\Place;
use App\Support\UserFacingException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Neue Bewertung')]
class ReviewWizard extends Component
{
    #[Locked]
    public string $step = 'link';

    public string $input = '';

    #[Locked]
    public ?array $place = null;

    #[Locked]
    public array $questions = [];

    public array $answers = [];

    public int $stars = 0;

    public string $length = 'mittel';

    public string $tone = 'herzlich';

    #[Locked]
    public ?string $error = null;

    public function findPlace(MapsLinkResolver $resolver, PlacesClient $places): void
    {
        $this->error = null;
        $input = trim($this->input);

        if ($input === '' || mb_strlen($input) > 2000) {
            $this->error = 'Füge einen Google-Maps-Link ein oder gib den Namen des Orts ein.';

            return;
        }

        if (! $this->consumeDailyRun()) {
            $this->error = 'Das Tageslimit von '.config('erlebt.max_runs_per_day').' Durchläufen ist erreicht.';

            return;
        }

        try {
            $url = $resolver->extractUrl($input);
            $link = $url !== null ? $resolver->resolve($url) : null;
            $query = $link->name ?? $input;

            $place = $places->isConfigured()
                ? $places->find($query, $link?->latitude, $link?->longitude)
                : new Place(name: $query);
        } catch (UserFacingException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $this->place = $place->toArray();
        $this->step = 'confirm';
    }

    public function changePlace(): void
    {
        $this->place = null;
        $this->error = null;
        $this->step = 'link';
    }

    public function confirmPlace(QuestionGenerator $generator): void
    {
        abort_if($this->place === null, 400);

        $this->questions = $generator->generate(Place::fromArray($this->place));
        $this->answers = array_fill(0, count($this->questions), '');
        $this->error = null;
        $this->step = 'questions';
    }

    public function toOptions(): void
    {
        if ($this->answeredQuestions() === []) {
            $this->error = 'Ohne mindestens eine Antwort müsste ich den Text erfinden. Beantworte bitte wenigstens eine Frage.';

            return;
        }

        $this->error = null;
        $this->step = 'options';
    }

    public function backToQuestions(): void
    {
        $this->error = null;
        $this->step = 'questions';
    }

    public function generate(ReviewWriter $writer)
    {
        $answered = $this->answeredQuestions();
        abort_if($this->place === null || $answered === [], 400);

        if ($this->stars < 1 || $this->stars > 5) {
            $this->error = 'Bitte wähle eine Sternezahl.';

            return null;
        }

        if (! isset(ReviewWriter::LENGTHS[$this->length], ReviewWriter::TONES[$this->tone])) {
            abort(422);
        }

        try {
            $body = $writer->write($this->place['name'], $answered, $this->stars, $this->length, $this->tone, $this->place['reviews']);
        } catch (UserFacingException $exception) {
            $this->error = $exception->getMessage();

            return null;
        }

        $review = DB::transaction(function () use ($answered, $body, $writer) {
            $review = Review::create([
                'user_id' => auth()->id(),
                'place_id' => $this->place['placeId'],
                'place_name' => $this->place['name'],
                'place_address' => $this->place['address'],
                'questions' => $answered,
                'stars' => $this->stars,
                'length' => $this->length,
                'tone' => $this->tone,
            ]);
            $review->versions()->create(['body' => $body, 'model' => $writer->model()]);

            return $review;
        });

        return redirect()->route('reviews.show', $review);
    }

    public function render()
    {
        return view('livewire.review-wizard');
    }

    /** @return list<array{frage: string, antwort: string}> */
    private function answeredQuestions(): array
    {
        $answered = [];

        foreach ($this->questions as $index => $question) {
            $answer = trim((string) ($this->answers[$index] ?? ''));

            if ($answer !== '') {
                $answered[] = ['frage' => $question['frage'], 'antwort' => $answer];
            }
        }

        return $answered;
    }

    private function consumeDailyRun(): bool
    {
        $key = 'daily-runs:'.now()->toDateString();

        if (RateLimiter::tooManyAttempts($key, config('erlebt.max_runs_per_day'))) {
            return false;
        }

        RateLimiter::hit($key, 86400);

        return true;
    }
}
