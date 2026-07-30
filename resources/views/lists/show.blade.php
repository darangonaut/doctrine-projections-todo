@extends('layout')
@section('title', $list->name)

@section('content')

    @php
        $statuses = [
            '' => 'Všetky',
            'open' => 'Otvorené',
            'in_progress' => 'Rozrobené',
            'done' => 'Hotové',
        ];
    @endphp

    <div class="mb-6">
        <a href="{{ route('lists.index') }}" class="text-xs text-stone-500 hover:text-stone-800">← zoznamy</a>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight text-stone-900">{{ $list->name }}</h1>
    </div>

    <nav class="mb-6 flex gap-1 text-xs">
        @foreach ($statuses as $value => $label)
            <a href="{{ route('lists.show', [$list->slug, 'stav' => $value]) }}"
               class="rounded-md px-2.5 py-1 {{ $filter === $value ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 hover:bg-stone-200' }}">
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @unless ($list->archived_at)
        <form method="POST" action="{{ route('tasks.store', $list->id) }}" class="mb-6 flex flex-wrap gap-2">
            @csrf
            <input name="title" required maxlength="200" placeholder="Nová úloha…"
                   class="min-w-48 flex-1 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm outline-none focus:border-stone-500">
            <select name="priority" class="rounded-md border border-stone-300 bg-white px-2 py-2 text-sm">
                @foreach (\App\Models\Entity\Priority::cases() as $priority)
                    <option value="{{ $priority->value }}" @selected($priority === \App\Models\Entity\Priority::Normal)>
                        {{ $priority->label() }}
                    </option>
                @endforeach
            </select>
            <input type="date" name="due_on" class="rounded-md border border-stone-300 bg-white px-2 py-2 text-sm text-stone-600">
            <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">
                Pridať
            </button>
        </form>
    @else
        <p class="mb-6 rounded-md border border-stone-300 bg-stone-50 px-4 py-2.5 text-sm text-stone-600">
            Zoznam je archivovaný — nové úlohy odmietne entita, nie formulár.
        </p>
    @endunless

    <ul class="space-y-2">
        @forelse ($tasks as $task)
            <li class="rounded-lg border border-stone-200 bg-white px-4 py-3">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 w-6 shrink-0 text-right text-xs tabular-nums text-stone-400">{{ $task->position }}.</span>

                    <div class="flex-1">
                        <p class="font-medium {{ $task->status->isFinished() ? 'text-stone-400 line-through' : 'text-stone-900' }}">
                            {{ $task->title }}
                        </p>

                        {{-- a div, not a p: a <p> may not contain the <form>
                             below it, and the browser would close the
                             paragraph early and drop the button on its own line --}}
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500">
                            <span>{{ $task->status->label() }}</span>
                            <span>{{ $task->priority->label() }} priorita</span>

                            @if ($task->due_on)
                                <span class="{{ ! $task->status->isFinished() && $task->due_on->isPast() ? 'font-semibold text-rose-600' : '' }}">
                                    do {{ $task->due_on->format('j. n. Y') }}
                                </span>
                            @endif

                            @foreach ($task->tags as $tag)
                                <span class="inline-flex items-center gap-1 rounded bg-stone-100 px-1.5 py-0.5 text-stone-600">
                                    {{ $tag->name }}
                                    <form method="POST" action="{{ route('tasks.untag', [$task->id, $tag->id]) }}" class="inline">
                                        @csrf @method('DELETE')
                                        <button class="text-stone-400 hover:text-rose-600">×</button>
                                    </form>
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2 text-xs">
                        @if (! $task->status->isFinished())
                            @if ($task->status !== \App\Models\Entity\TaskStatus::InProgress)
                                <form method="POST" action="{{ route('tasks.transition', [$task->id, 'start']) }}">
                                    @csrf @method('PATCH')
                                    <button class="text-stone-600 hover:text-stone-900">rozrobiť</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('tasks.transition', [$task->id, 'complete']) }}">
                                @csrf @method('PATCH')
                                <button class="font-medium text-emerald-700 hover:text-emerald-900">hotovo</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('tasks.transition', [$task->id, 'reopen']) }}">
                                @csrf @method('PATCH')
                                <button class="text-stone-600 hover:text-stone-900">otvoriť</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('tasks.destroy', $task->id) }}">
                            @csrf @method('DELETE')
                            <button class="text-rose-600 hover:text-rose-800">zmazať</button>
                        </form>
                    </div>
                </div>

                <form method="POST" action="{{ route('tasks.tag', $task->id) }}" class="mt-2 flex gap-1 pl-9">
                    @csrf
                    <input name="tag" list="tags" maxlength="60" placeholder="+ štítok"
                           class="w-36 rounded border border-stone-200 px-2 py-1 text-xs outline-none focus:border-stone-400">
                </form>
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-stone-300 px-4 py-8 text-center text-sm text-stone-500">
                Nič tu nie je.
            </li>
        @endforelse
    </ul>

    <datalist id="tags">
        @foreach ($tags as $tag)
            <option value="{{ $tag->name }}"></option>
        @endforeach
    </datalist>

    <p class="mt-8 text-xs leading-relaxed text-stone-500">
        Štítky sa načítali cez <code class="text-stone-700">with('tags')</code> — jeden dotaz
        na všetky úlohy. Stav aj priorita sú enumy, ktoré generátor prečítal
        z <code class="text-stone-700">enumType</code> v mapovaní, takže tu volám
        <code class="text-stone-700">$task->status->label()</code>, nie porovnávanie reťazcov.
    </p>

@endsection
