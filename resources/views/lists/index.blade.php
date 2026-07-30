@extends('layout')
@section('title', 'Zoznamy')

@section('content')

    <form method="POST" action="{{ route('lists.store') }}" class="mb-8 flex gap-2">
        @csrf
        <input name="name" required maxlength="120" placeholder="Nový zoznam…"
               class="flex-1 rounded-md border border-stone-300 bg-white px-3 py-2 text-sm outline-none focus:border-stone-500">
        <button class="rounded-md bg-stone-900 px-4 py-2 text-sm font-medium text-white hover:bg-stone-700">
            Pridať
        </button>
    </form>

    <ul class="space-y-2">
        @forelse ($lists as $list)
            <li class="flex items-center gap-4 rounded-lg border border-stone-200 bg-white px-4 py-3 {{ $list->archived_at ? 'opacity-60' : '' }}">
                <a href="{{ route('lists.show', $list->slug) }}" class="flex-1 font-medium text-stone-900 hover:underline">
                    {{ $list->name }}
                    @if ($list->archived_at)
                        <span class="ml-1 text-xs font-normal text-stone-500">archivovaný</span>
                    @endif
                </a>

                <span class="text-xs tabular-nums text-stone-500">
                    {{ $list->open_tasks_count }} / {{ $list->tasks_count }} otvorených
                </span>

                @if ($list->archived_at)
                    <form method="POST" action="{{ route('lists.restore', $list->id) }}">
                        @csrf @method('PATCH')
                        <button class="text-xs text-stone-600 hover:text-stone-900">obnoviť</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('lists.archive', $list->id) }}">
                        @csrf @method('PATCH')
                        <button class="text-xs text-stone-600 hover:text-stone-900">archivovať</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('lists.destroy', $list->id) }}">
                    @csrf @method('DELETE')
                    <button class="text-xs text-rose-600 hover:text-rose-800">zmazať</button>
                </form>
            </li>
        @empty
            <li class="rounded-lg border border-dashed border-stone-300 px-4 py-8 text-center text-sm text-stone-500">
                Zatiaľ žiadny zoznam.
            </li>
        @endforelse
    </ul>

    <p class="mt-8 text-xs leading-relaxed text-stone-500">
        Počty vyššie sú jeden <code class="text-stone-700">withCount()</code> nad projekciou —
        žiadne N+1. Archivovať sa dá len zoznam bez otvorených úloh; to pravidlo je
        v entite, nie tu.
    </p>

@endsection
