<?php

namespace App\Livewire;

use App\Models\Review;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Verlauf')]
class ReviewHistory extends Component
{
    public function render()
    {
        return view('livewire.review-history', [
            'reviews' => Review::query()
                ->where('user_id', auth()->id())
                ->with('latestVersion')
                ->latest('id')
                ->limit(200)
                ->get(),
        ]);
    }
}
