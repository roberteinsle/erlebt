@php
    $current = $versions->first();
    $secondary = 'rounded-lg border border-stone-300 bg-white px-4 py-3 text-sm font-medium disabled:opacity-50';
@endphp
<div>
    <h1 class="text-2xl font-semibold">{{ $review->place_name }}</h1>
    <p class="mb-4 text-sm text-stone-600">
        <span aria-label="{{ $review->stars }} von 5 Sternen">{{ str_repeat('★', $review->stars) }}{{ str_repeat('☆', 5 - $review->stars) }}</span>
        @if ($review->place_address) · {{ $review->place_address }} @endif
    </p>

    @if ($error)
        <p role="alert" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $error }}</p>
    @endif

    <div wire:key="version-{{ $current->id }}" x-data="{ copied: false, async copy() {
            const area = this.$refs.text;
            try { await navigator.clipboard.writeText(area.value); }
            catch { area.focus(); area.setSelectionRange(0, area.value.length); document.execCommand('copy'); }
            this.copied = true; setTimeout(() => this.copied = false, 2000);
        } }">
        <textarea x-ref="text" readonly rows="10" aria-label="Bewertungstext"
            class="mb-3 w-full rounded-lg border border-stone-200 bg-white p-4 text-base leading-relaxed">{{ $current->body }}</textarea>
        <button type="button" x-on:click="copy()" class="mb-3 w-full rounded-lg bg-stone-900 px-4 py-3 font-medium text-white">
            <span x-show="!copied">In die Zwischenablage kopieren</span>
            <span x-show="copied" x-cloak>Kopiert</span>
        </button>
    </div>

    <p class="mb-6 text-sm text-stone-600">Bitte lies den Text vor dem Veröffentlichen noch einmal selbst.</p>

    <h2 class="mb-2 font-semibold">Überarbeiten</h2>
    <div class="mb-3 grid grid-cols-2 gap-2">
        <button type="button" wire:click="revisePreset('kuerzer')" wire:loading.attr="disabled" class="{{ $secondary }}">Kürzer</button>
        <button type="button" wire:click="revisePreset('persoenlicher')" wire:loading.attr="disabled" class="{{ $secondary }}">Persönlicher</button>
    </div>
    <form wire:submit="reviseCustom" class="mb-2 space-y-2">
        <textarea wire:model="instruction" rows="2" maxlength="1000" aria-label="Eigener Änderungswunsch"
            placeholder="Eigener Wunsch, z. B. „Den Hund nicht erwähnen“"
            class="w-full rounded-lg border border-stone-300 bg-white px-3 py-3 text-base"></textarea>
        <button type="submit" wire:loading.attr="disabled" class="{{ $secondary }} w-full">Wunsch umsetzen</button>
    </form>
    <p wire:loading wire:target="revisePreset,reviseCustom" class="text-sm text-stone-600">Überarbeite den Text …</p>

    <div class="mt-6 flex flex-wrap items-center gap-4 text-sm">
        <a href="{{ route('reviews.markdown', $review) }}" class="underline">Als Markdown herunterladen</a>
        <a href="{{ route('home') }}" class="underline">Neue Bewertung</a>
    </div>

    @if ($versions->count() > 1)
        <details class="mt-6">
            <summary class="cursor-pointer font-semibold">Frühere Versionen ({{ $versions->count() - 1 }})</summary>
            <ol class="mt-3 space-y-3">
                @foreach ($versions->skip(1) as $version)
                    <li wire:key="old-{{ $version->id }}" class="rounded-lg border border-stone-200 bg-white p-3 text-sm whitespace-pre-line">{{ $version->body }}</li>
                @endforeach
            </ol>
        </details>
    @endif
</div>
