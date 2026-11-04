<div>
    <h1 class="mb-4 text-2xl font-semibold">Anmelden</h1>
    <form wire:submit="login" class="space-y-4">
        <label class="block">
            <span class="mb-1 block text-sm font-medium">E-Mail</span>
            <input wire:model="email" type="email" autocomplete="username" required autofocus
                class="w-full rounded-lg border border-stone-300 bg-white px-3 py-3 text-base">
        </label>
        <label class="block">
            <span class="mb-1 block text-sm font-medium">Passwort</span>
            <input wire:model="password" type="password" autocomplete="current-password" required
                class="w-full rounded-lg border border-stone-300 bg-white px-3 py-3 text-base">
        </label>
        @if ($error)
            <p role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ $error }}</p>
        @endif
        <button type="submit" wire:loading.attr="disabled"
            class="w-full rounded-lg bg-stone-900 px-4 py-3 font-medium text-white disabled:opacity-50">Anmelden</button>
    </form>
</div>
