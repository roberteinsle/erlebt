<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ isset($title) ? $title.' · erlebt' : 'erlebt' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-stone-50 text-stone-900 antialiased">
    <div class="mx-auto flex min-h-dvh w-full max-w-xl flex-col px-4 pb-6 pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="mb-6 flex items-center justify-between gap-4">
            <a href="{{ auth()->check() ? route('home') : route('login') }}" class="text-lg font-semibold tracking-tight">erlebt</a>
            @auth
                <nav class="flex items-center gap-4 text-sm text-stone-600">
                    <a href="{{ route('reviews.index') }}" class="py-2 hover:text-stone-900">Verlauf</a>
                    <a href="{{ route('settings') }}" class="py-2 hover:text-stone-900">Einstellungen</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="py-2 hover:text-stone-900">Abmelden</button>
                    </form>
                </nav>
            @endauth
        </header>

        <main class="flex-1">{{ $slot }}</main>

        <footer class="mt-10 text-center text-xs text-stone-500">
            <a href="{{ config('erlebt.source_url') }}" class="underline">Quellcode</a> unter AGPL-3.0
        </footer>
    </div>
</body>
</html>
