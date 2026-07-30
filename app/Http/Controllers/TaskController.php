<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity\Priority;
use App\Models\Entity\Tag as TagEntity;
use App\Models\Entity\Task as TaskEntity;
use App\Models\Entity\TodoList as TodoListEntity;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function store(Request $request, int $listId): RedirectResponse
    {
        $list = $this->em->find(TodoListEntity::class, $listId);
        abort_if($list === null, 404);

        $due = $request->string('due_on')->toString();

        try {
            // Not `new Task(...)` — the list is what numbers its tasks and
            // what knows whether it still accepts any.
            $list->addTask(
                $request->string('title')->toString(),
                Priority::tryFrom($request->integer('priority')) ?? Priority::Normal,
                $due === '' ? null : new \DateTimeImmutable($due),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->em->flush();

        return back()->with('status', 'Úloha pridaná.');
    }

    public function transition(Request $request, int $id, string $to): RedirectResponse
    {
        $task = $this->em->find(TaskEntity::class, $id);
        abort_if($task === null, 404);

        try {
            match ($to) {
                'start' => $task->start(),
                'complete' => $task->complete(),
                'reopen' => $task->reopen(),
                default => abort(404),
            };
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->em->flush();

        return back();
    }

    public function tag(Request $request, int $id): RedirectResponse
    {
        $task = $this->em->find(TaskEntity::class, $id);
        abort_if($task === null, 404);

        $name = trim($request->string('tag')->toString());

        if ($name === '') {
            return back();
        }

        $slug = Str::slug($name) ?: Str::random(8);

        $tag = $this->em->getRepository(TagEntity::class)->findOneBy(['slug' => $slug])
            ?? new TagEntity($name, $slug);

        $this->em->persist($tag);
        $task->tag($tag);
        $this->em->flush();

        return back();
    }

    public function untag(int $id, int $tagId): RedirectResponse
    {
        $task = $this->em->find(TaskEntity::class, $id);
        $tag = $this->em->find(TagEntity::class, $tagId);
        abort_if($task === null || $tag === null, 404);

        $task->untag($tag);
        $this->em->flush();

        return back();
    }

    public function destroy(int $id): RedirectResponse
    {
        $task = $this->em->find(TaskEntity::class, $id);
        abort_if($task === null, 404);

        $this->em->remove($task);
        $this->em->flush();

        return back()->with('status', 'Úloha zmazaná.');
    }
}
