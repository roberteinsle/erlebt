@props(['label', 'property', 'models'])

@php
    $options = collect($models)->map(fn ($name, $id) => ['id' => $id, 'name' => $name, 'label' => "{$name} ({$id})"])->values();
    $listId = $property.'-options';
@endphp

<div class="relative" x-data="{
        options: @js($options),
        value: $wire.entangle(@js($property)),
        query: '',
        open: false,
        active: 0,
        get selectedLabel() {
            return this.options.find((option) => option.id === this.value)?.label ?? this.value;
        },
        get results() {
            const terms = this.query.toLowerCase().split(/\s+/).filter(Boolean);

            return this.options.filter((option) => terms.every((term) => option.label.toLowerCase().includes(term)));
        },
        show() {
            if (this.open) return;
            this.open = true;
            this.query = '';
            this.active = Math.max(this.results.findIndex((option) => option.id === this.value), 0);
            this.$nextTick(() => this.reveal());
        },
        hide() {
            this.open = false;
            this.query = '';
        },
        choose(option) {
            if (! option) return;
            this.value = option.id;
            this.hide();
            this.$refs.input.blur();
        },
        move(step) {
            this.show();
            const count = this.results.length;
            if (count === 0) return;
            this.active = (this.active + step + count) % count;
            this.$nextTick(() => this.reveal());
        },
        reveal() {
            this.$refs.list?.children[this.active]?.scrollIntoView({ block: 'nearest' });
        },
    }" x-on:click.outside="hide()">
    <label for="{{ $property }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <input id="{{ $property }}" x-ref="input" type="text" role="combobox" autocomplete="off" autocapitalize="off" spellcheck="false"
        aria-autocomplete="list" aria-controls="{{ $listId }}"
        x-bind:aria-expanded="open"
        x-bind:aria-activedescendant="open && results[active] ? @js($listId) + '-' + active : null"
        x-bind:value="open ? query : selectedLabel"
        x-bind:placeholder="selectedLabel"
        x-on:input="query = $event.target.value; active = 0; open = true"
        x-on:focus="show()"
        x-on:click="show()"
        x-on:keydown.arrow-down.prevent="move(1)"
        x-on:keydown.arrow-up.prevent="move(-1)"
        x-on:keydown.escape="hide(); $el.blur()"
        x-on:keydown.tab="hide()"
        x-on:keydown.enter="if (open) { $event.preventDefault(); choose(results[active]); }"
        class="w-full truncate rounded-lg border border-stone-300 bg-white px-3 py-3 text-base">

    <ul id="{{ $listId }}" x-ref="list" role="listbox" aria-label="{{ $label }}" x-show="open && results.length > 0" x-cloak
        class="absolute z-10 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-stone-300 bg-white py-1 shadow-lg">
        <template x-for="(option, index) in results" x-bind:key="option.id">
            <li role="option" x-bind:id="@js($listId) + '-' + index" x-bind:aria-selected="option.id === value"
                x-on:mousedown.prevent
                x-on:click="choose(option)"
                x-on:mousemove="active = index"
                x-bind:class="index === active ? 'bg-stone-100' : ''"
                class="cursor-pointer px-3 py-3 text-sm">
                <span class="block font-medium" x-text="option.name"></span>
                <span class="block break-all text-stone-600" x-text="option.id"></span>
            </li>
        </template>
    </ul>
    <p x-show="open && results.length === 0" x-cloak role="status"
        class="absolute z-10 mt-1 w-full rounded-lg border border-stone-300 bg-white px-3 py-3 text-sm text-stone-600 shadow-lg">Kein Modell gefunden.</p>
</div>
