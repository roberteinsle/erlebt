<?php

namespace App\Livewire;

use App\Models\Review;
use App\Services\ReviewWriter;
use App\Support\UserFacingException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Bewertung')]
class ReviewShow extends Component
{
    private const PRESETS = [
        'kuerzer' => 'Mach den Text deutlich kürzer.',
        'persoenlicher' => 'Mach den Text persönlicher.',
    ];

    public Review $review;

    public string $instruction = '';

    #[Locked]
    public ?string $error = null;

    public function mount(Review $review): void
    {
        abort_unless($review->user_id === auth()->id(), 404);
        $this->review = $review;
    }

    public function revisePreset(string $preset, ReviewWriter $writer): void
    {
        abort_unless(isset(self::PRESETS[$preset]), 422);
        $this->revise(self::PRESETS[$preset], $writer);
    }

    public function reviseCustom(ReviewWriter $writer): void
    {
        $instruction = trim($this->instruction);

        if ($instruction === '' || mb_strlen($instruction) > 1000) {
            $this->error = 'Beschreibe deinen Änderungswunsch in höchstens 1000 Zeichen.';

            return;
        }

        $this->revise($instruction, $writer);
    }

    public function render()
    {
        return view('livewire.review-show', [
            'versions' => $this->review->versions()->orderByDesc('id')->get(),
        ]);
    }

    private function revise(string $instruction, ReviewWriter $writer): void
    {
        $this->error = null;
        $review = $this->review;

        try {
            $body = $writer->revise(
                $review->place_name,
                $review->questions,
                $review->stars,
                $review->length,
                $review->tone,
                $review->versions()->orderByDesc('id')->value('body'),
                $instruction,
            );
        } catch (UserFacingException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $review->versions()->create(['body' => $body, 'instruction' => $instruction, 'model' => $writer->model()]);
        $this->instruction = '';
    }
}
