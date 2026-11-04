<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReviewMarkdownController extends Controller
{
    public function __invoke(Request $request, Review $review)
    {
        abort_unless($review->user_id === $request->user()->id, 404);

        $stars = str_repeat('★', $review->stars).str_repeat('☆', 5 - $review->stars);
        $markdown = "# {$review->place_name}\n\n"
            .($review->place_address ? "{$review->place_address}\n\n" : '')
            ."{$stars}\n\n"
            .$review->versions()->orderByDesc('id')->value('body')."\n";

        $filename = 'bewertung-'.(Str::slug($review->place_name) ?: $review->id).'.md';

        return response()->streamDownload(fn () => print ($markdown), $filename, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
        ]);
    }
}
