<div>
    <h1 class="mb-4 text-2xl font-semibold">Verlauf</h1>
    @forelse ($reviews as $review)
        <a wire:key="review-{{ $review->id }}" href="{{ route('reviews.show', $review) }}"
            class="mb-3 block rounded-lg border border-stone-200 bg-white p-4 hover:border-stone-400">
            <span class="flex items-baseline justify-between gap-3">
                <span class="font-semibold">{{ $review->place_name }}</span>
                <span class="shrink-0 text-sm text-stone-600">{{ $review->created_at->format('d.m.Y') }}</span>
            </span>
            <span class="mt-1 block text-sm text-stone-600">{{ str_repeat('★', $review->stars) }}{{ str_repeat('☆', 5 - $review->stars) }}</span>
            <span class="mt-1 line-clamp-2 block text-sm">{{ $review->latestVersion?->body }}</span>
        </a>
    @empty
        <p class="text-stone-600">Noch keine Bewertungen. <a href="{{ route('home') }}" class="underline">Erste Bewertung schreiben</a></p>
    @endforelse
</div>
