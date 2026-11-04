@php
    $primary = 'w-full rounded-lg bg-stone-900 px-4 py-3 font-medium text-white disabled:opacity-50';
    $secondary = 'w-full rounded-lg border border-stone-300 bg-white px-4 py-3 font-medium disabled:opacity-50';
    $field = 'w-full rounded-lg border border-stone-300 bg-white px-3 py-3 text-base';
@endphp
<div>
    @if ($error)
        <p role="alert" class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $error }}</p>
    @endif

    @if ($step === 'link')
        <h1 class="mb-1 text-2xl font-semibold">Neue Bewertung</h1>
        <p class="mb-4 text-stone-600">Füge den Google-Maps-Link ein oder gib den Namen des Orts ein.</p>
        <form wire:submit="findPlace" class="space-y-4">
            <textarea wire:model="input" rows="3" required autofocus aria-label="Maps-Link oder Ortsname"
                placeholder="https://maps.app.goo.gl/… oder „Hotel Goldner Anker, Bad Elster“" class="{{ $field }}"></textarea>
            <button type="submit" wire:loading.attr="disabled" class="{{ $primary }}">
                <span wire:loading.remove wire:target="findPlace">Ort suchen</span>
                <span wire:loading wire:target="findPlace">Suche Ort …</span>
            </button>
        </form>
    @elseif ($step === 'confirm')
        <h1 class="mb-4 text-2xl font-semibold">Ist das der Ort?</h1>
        <div class="mb-4 rounded-lg border border-stone-200 bg-white p-4">
            <p class="font-semibold">{{ $place['name'] }}</p>
            @if ($place['address'])<p class="mt-1 text-sm text-stone-600">{{ $place['address'] }}</p>@endif
            @if ($place['rating'])
                <p class="mt-1 text-sm text-stone-600">
                    {{ number_format($place['rating'], 1, ',', '') }} Sterne
                    @if ($place['ratingCount'])· {{ number_format($place['ratingCount'], 0, ',', '.') }} Bewertungen @endif
                </p>
            @endif
        </div>
        <div class="space-y-3">
            <button type="button" wire:click="confirmPlace" wire:loading.attr="disabled" class="{{ $primary }}">
                <span wire:loading.remove wire:target="confirmPlace">Ja, weiter</span>
                <span wire:loading wire:target="confirmPlace">Bereite deine Fragen vor …</span>
            </button>
            <button type="button" wire:click="changePlace" wire:loading.attr="disabled" class="{{ $secondary }}">Anderer Ort</button>
        </div>
    @elseif ($step === 'questions')
        <h1 class="mb-1 text-2xl font-semibold">Wie war es?</h1>
        <p class="mb-4 text-stone-600">Jede Frage lässt sich überspringen. Je mehr du schreibst, desto persönlicher und genauer wird der Text.</p>
        <form wire:submit="toOptions" class="space-y-5">
            @foreach ($questions as $index => $question)
                <label class="block" wire:key="question-{{ $index }}">
                    <span class="block font-medium">{{ $question['frage'] }}</span>
                    @if ($question['warum'])<span class="mb-2 block text-sm text-stone-600">{{ $question['warum'] }}</span>@endif
                    <textarea wire:model="answers.{{ $index }}" rows="2" maxlength="2000"
                        placeholder="{{ $question['beispiel'] ? 'z. B. '.$question['beispiel'] : '' }}" class="{{ $field }}"></textarea>
                </label>
            @endforeach
            <button type="submit" class="{{ $primary }}">Weiter</button>
        </form>
    @elseif ($step === 'options')
        <h1 class="mb-4 text-2xl font-semibold">Wie soll der Text werden?</h1>
        <form wire:submit="generate" class="space-y-5">
            <fieldset>
                <legend class="mb-2 font-medium">Sterne</legend>
                <div class="grid grid-cols-5 gap-2">
                    @foreach (range(1, 5) as $value)
                        <label class="block cursor-pointer">
                            <input type="radio" wire:model="stars" value="{{ $value }}" class="peer sr-only">
                            <span class="block rounded-lg border border-stone-300 bg-white py-3 text-center font-medium peer-checked:border-stone-900 peer-checked:bg-stone-900 peer-checked:text-white peer-focus-visible:ring-2">{{ $value }} ★</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="mb-2 font-medium">Länge</legend>
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['kurz' => 'Kurz', 'mittel' => 'Mittel', 'ausfuehrlich' => 'Ausführlich'] as $value => $label)
                        <label class="block cursor-pointer">
                            <input type="radio" wire:model="length" value="{{ $value }}" class="peer sr-only">
                            <span class="block rounded-lg border border-stone-300 bg-white py-3 text-center text-sm font-medium peer-checked:border-stone-900 peer-checked:bg-stone-900 peer-checked:text-white peer-focus-visible:ring-2">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="mb-2 font-medium">Ton</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['sachlich' => 'Sachlich', 'herzlich' => 'Herzlich'] as $value => $label)
                        <label class="block cursor-pointer">
                            <input type="radio" wire:model="tone" value="{{ $value }}" class="peer sr-only">
                            <span class="block rounded-lg border border-stone-300 bg-white py-3 text-center text-sm font-medium peer-checked:border-stone-900 peer-checked:bg-stone-900 peer-checked:text-white peer-focus-visible:ring-2">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="space-y-3">
                <button type="submit" wire:loading.attr="disabled" class="{{ $primary }}">
                    <span wire:loading.remove wire:target="generate">Text erzeugen</span>
                    <span wire:loading wire:target="generate">Schreibe deinen Text …</span>
                </button>
                <button type="button" wire:click="backToQuestions" wire:loading.attr="disabled" class="{{ $secondary }}">Zurück zu den Fragen</button>
            </div>
        </form>
    @endif
</div>
