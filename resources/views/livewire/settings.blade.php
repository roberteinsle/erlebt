@php
    $field = 'w-full rounded-lg border border-stone-300 bg-white px-3 py-3 text-base';
    $labels = ['ok' => 'Funktioniert', 'error' => 'Fehler', 'off' => 'Nicht eingerichtet'];
    $colors = ['ok' => 'text-green-800', 'error' => 'text-red-800', 'off' => 'text-stone-600'];
@endphp
<div>
    <h1 class="mb-6 text-2xl font-semibold">Einstellungen</h1>

    <section class="mb-8">
        <h2 class="mb-1 font-semibold">Claude-Modelle</h2>
        <p class="mb-4 text-sm text-stone-600">Die Auswahl hier hat Vorrang vor <code>MODEL_QUESTIONS</code> und <code>MODEL_REVIEW</code>.</p>

        @if ($modelsError)
            <p role="alert" class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Die Modell-Liste ist gerade nicht abrufbar, deshalb steht nur eine feste Auswahl bereit. {{ $modelsError }}
            </p>
        @endif

        <form wire:submit="save" class="space-y-4">
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Modell für die Fragen</span>
                <select wire:model="questionsModel" class="{{ $field }}">
                    @foreach ($models as $id => $name)
                        <option value="{{ $id }}" wire:key="q-{{ $id }}">{{ $name }} ({{ $id }})</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Modell für den Bewertungstext</span>
                <select wire:model="reviewModel" class="{{ $field }}">
                    @foreach ($models as $id => $name)
                        <option value="{{ $id }}" wire:key="r-{{ $id }}">{{ $name }} ({{ $id }})</option>
                    @endforeach
                </select>
            </label>
            @if ($error)
                <p role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $error }}</p>
            @endif
            @if ($saved)
                <p role="status" class="rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800">{{ $saved }}</p>
            @endif
            <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded-lg bg-stone-900 px-4 py-3 font-medium text-white disabled:opacity-50">Modelle speichern</button>
        </form>
    </section>

    <section>
        <h2 class="mb-1 font-semibold">API-Status</h2>
        <p class="mb-4 text-sm text-stone-600">Sendet je eine kleine echte Anfrage mit den gespeicherten Modellen und verursacht dadurch minimale Kosten.</p>

        <button type="button" wire:click="runChecks" wire:loading.attr="disabled"
            class="mb-4 w-full rounded-lg border border-stone-300 bg-white px-4 py-3 font-medium disabled:opacity-50">
            <span wire:loading.remove wire:target="runChecks">Jetzt prüfen</span>
            <span wire:loading wire:target="runChecks">Prüfe die APIs …</span>
        </button>

        @if ($checks)
            <ul class="space-y-3">
                @foreach ($checks as $check)
                    <li wire:key="check-{{ $check['name'] }}" class="rounded-lg border border-stone-200 bg-white p-4">
                        <span class="block font-medium break-words">{{ $check['name'] }}</span>
                        <span class="block text-sm font-medium {{ $colors[$check['state']] }}">{{ $labels[$check['state']] }}</span>
                        <span class="mt-1 block text-sm text-stone-600 break-words">{{ $check['detail'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
