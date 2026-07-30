<!DOCTYPE html>
<html lang="sk" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Úlohy')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full bg-stone-100 text-stone-800 antialiased">
<div class="mx-auto max-w-3xl px-5 py-10">

    <header class="mb-8 flex items-baseline justify-between gap-4">
        <a href="{{ route('lists.index') }}" class="text-xl font-semibold tracking-tight text-stone-900">Úlohy</a>
        <p class="text-xs text-stone-500">
            zápis cez Doctrine · čítanie cez generované Eloquent projekcie
        </p>
    </header>

    @if (session('status'))
        <p class="mb-5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
            {{ session('status') }}
        </p>
    @endif

    @if (session('error'))
        <p class="mb-5 rounded-md border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800">
            <strong class="font-semibold">Doména odmietla:</strong> {{ session('error') }}
        </p>
    @endif

    @yield('content')

</div>
</body>
</html>
