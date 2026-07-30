<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity\TodoList as TodoListEntity;
use App\Models\Projection\Tag;
use App\Models\Projection\TodoList;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Reads go through the projections, writes through the entities. The two
 * halves of every method below are the whole point of the architecture.
 */
class TodoListController extends Controller
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function index(): View
    {
        // Eloquent, with everything Eloquent gives you: aggregate counts
        // in one query, no N+1, no hand-written SQL.
        $lists = TodoList::query()
            ->withCount([
                'tasks',
                'tasks as open_tasks_count' => fn ($q) => $q->whereIn('status', ['open', 'in_progress']),
            ])
            ->orderBy('archived_at')
            ->orderByDesc('created_at')
            ->get();

        return view('lists.index', ['lists' => $lists]);
    }

    public function show(Request $request, string $slug): View
    {
        $list = TodoList::query()->where('slug', $slug)->firstOrFail();

        $tasks = $list->tasks()
            ->with('tags')
            ->when(
                $request->string('stav')->toString() !== '',
                fn ($q) => $q->where('status', $request->string('stav')->toString()),
            )
            ->orderBy('position')
            ->get();

        return view('lists.show', [
            'list' => $list,
            'tasks' => $tasks,
            'tags' => Tag::query()->orderBy('name')->get(),
            'filter' => $request->string('stav')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = $request->string('name')->toString();

        $entity = new TodoListEntity($name, Str::slug($name) ?: Str::random(8));

        $this->em->persist($entity);
        $this->em->flush();

        return to_route('lists.show', $entity->slug());
    }

    public function archive(int $id): RedirectResponse
    {
        $list = $this->em->find(TodoListEntity::class, $id);
        abort_if($list === null, 404);

        try {
            $list->archive();
        } catch (DomainException $e) {
            // The rule lives in the entity; the controller only reports it.
            return back()->with('error', $e->getMessage());
        }

        $this->em->flush();

        return back()->with('status', 'Zoznam archivovaný.');
    }

    public function restore(int $id): RedirectResponse
    {
        $list = $this->em->find(TodoListEntity::class, $id);
        abort_if($list === null, 404);

        $list->restore();
        $this->em->flush();

        return back()->with('status', 'Zoznam obnovený.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $list = $this->em->find(TodoListEntity::class, $id);
        abort_if($list === null, 404);

        $this->em->remove($list);
        $this->em->flush();

        return to_route('lists.index')->with('status', 'Zoznam zmazaný.');
    }
}
