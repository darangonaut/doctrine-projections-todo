<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entity\Priority;
use App\Models\Entity\Tag;
use App\Models\Entity\TaskStatus;
use App\Models\Entity\TodoList as TodoListEntity;
use App\Models\Projection\Task;
use App\Models\Projection\TodoList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The claim under test: write with Doctrine, read the same row back with
 * Eloquent. If the two sides ever ended up on different connections this
 * is the suite that would go red.
 */
final class WritingThroughDoctrineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_row_written_by_doctrine_is_visible_to_the_projection(): void
    {
        $list = new TodoListEntity('Nákup', 'nakup');
        $list->addTask('Chlieb', Priority::Low);
        $list->addTask('Mlieko', Priority::High);

        $this->em()->persist($list);
        $this->em()->flush();
        $this->forget();

        $projected = TodoList::query()->where('slug', 'nakup')->firstOrFail();

        self::assertSame('Nákup', $projected->name);
        self::assertCount(2, $projected->tasks);
        self::assertSame(['Chlieb', 'Mlieko'], $projected->tasks->pluck('title')->all());
    }

    #[Test]
    public function enums_survive_the_round_trip_in_both_backings(): void
    {
        $list = new TodoListEntity('Enumy', 'enumy');
        $task = $list->addTask('Skúška', Priority::High);
        $task->start();

        $this->em()->persist($list);
        $this->em()->flush();
        $this->forget();

        $projected = Task::query()->where('title', 'Skúška')->firstOrFail();

        // string-backed on one side, int-backed on the other — the
        // generator read both off enumType, not off the column type.
        self::assertSame(TaskStatus::InProgress, $projected->status);
        self::assertSame(Priority::High, $projected->priority);
    }

    #[Test]
    public function completing_a_task_is_visible_through_the_projection(): void
    {
        $list = new TodoListEntity('Práca', 'praca');
        $task = $list->addTask('Odoslať faktúru');

        $this->em()->persist($list);
        $this->em()->flush();

        $task->complete();
        $this->em()->flush();
        $this->forget();

        $projected = Task::query()->where('title', 'Odoslať faktúru')->firstOrFail();

        self::assertSame(TaskStatus::Done, $projected->status);
        self::assertNotNull($projected->completed_at);
    }

    #[Test]
    public function positions_are_assigned_by_the_list_not_by_the_caller(): void
    {
        $list = new TodoListEntity('Poradie', 'poradie');
        $list->addTask('prvá');
        $list->addTask('druhá');
        $list->addTask('tretia');

        $this->em()->persist($list);
        $this->em()->flush();
        $this->forget();

        $positions = Task::query()->orderBy('position')->pluck('position', 'title')->all();

        self::assertSame(['prvá' => 1, 'druhá' => 2, 'tretia' => 3], $positions);
    }

    #[Test]
    public function reopening_moves_status_and_timestamp_together_in_the_row(): void
    {
        $list = new TodoListEntity('Návrat', 'navrat');
        $list->addTask('sem a tam')->complete();

        $this->em()->persist($list);
        $this->em()->flush();
        $this->forget();

        self::assertNotNull(Task::query()->firstOrFail()->completed_at);

        $reloaded = $this->em()->getRepository(\App\Models\Entity\Task::class)->findOneBy(['title' => 'sem a tam']);
        self::assertNotNull($reloaded);
        $reloaded->reopen();
        $this->em()->flush();
        $this->forget();

        // status and completed_at are one fact in two columns; if reopening
        // moved only one of them the row would start contradicting itself.
        $projected = Task::query()->firstOrFail();
        self::assertSame(TaskStatus::Open, $projected->status);
        self::assertNull($projected->completed_at);
    }

    #[Test]
    public function many_to_many_written_by_doctrine_reads_back_through_eloquent(): void
    {
        $list = new TodoListEntity('Štítky', 'stitky');
        $task = $list->addTask('Otagovaná');
        $urgent = new Tag('urgentné', 'urgentne');
        $task->tag($urgent);

        $this->em()->persist($urgent);
        $this->em()->persist($list);
        $this->em()->flush();
        $this->forget();

        $projected = Task::query()->with('tags')->where('title', 'Otagovaná')->firstOrFail();

        self::assertSame(['urgentné'], $projected->tags->pluck('name')->all());

        // and the inverse side of the same join table
        $tag = \App\Models\Projection\Tag::query()->with('tasks')->firstOrFail();
        self::assertSame(['Otagovaná'], $tag->tasks->pluck('title')->all());
    }
}
