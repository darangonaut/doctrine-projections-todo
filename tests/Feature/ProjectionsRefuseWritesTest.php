<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entity\Tag as TagEntity;
use App\Models\Entity\TodoList as TodoListEntity;
use App\Models\Projection\Tag;
use App\Models\Projection\Task;
use App\Models\Projection\TodoList;
use Darangonaut\DoctrineProjections\Exceptions\ReadOnlyProjection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The lock is what makes it safe to hand projections to the whole
 * application: no matter who reaches for one, the domain stays the only
 * way in. Every path here is checked against the row afterwards, because
 * an exception that fires *after* the write would still be a data leak.
 */
final class ProjectionsRefuseWritesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $list = new TodoListEntity('Zámok', 'zamok');
        $task = $list->addTask('nedotknuteľná');
        $tag = new TagEntity('štítok', 'stitok');
        $task->tag($tag);

        $this->em()->persist($tag);
        $this->em()->persist($list);
        $this->em()->flush();
        $this->forget();
    }

    #[Test]
    public function saving_a_model_is_refused(): void
    {
        $task = Task::query()->firstOrFail();
        $task->title = 'prepísané';

        $this->expectException(ReadOnlyProjection::class);

        $task->save();
    }

    #[Test]
    public function mass_update_through_the_builder_is_refused_and_changes_nothing(): void
    {
        try {
            Task::query()->update(['title' => 'prepísané']);
            self::fail('update() mal byť odmietnutý');
        } catch (ReadOnlyProjection) {
            // expected
        }

        self::assertSame('nedotknuteľná', DB::table('tasks')->value('title'));
    }

    #[Test]
    public function deleting_is_refused_and_the_row_survives(): void
    {
        try {
            Task::query()->firstOrFail()->delete();
            self::fail('delete() mal byť odmietnutý');
        } catch (ReadOnlyProjection) {
            // expected
        }

        self::assertSame(1, DB::table('tasks')->count());
    }

    #[Test]
    public function touch_is_refused_even_though_it_writes_past_update(): void
    {
        // touch() goes through toBase()->update(), so overriding update()
        // alone would not have caught it. That is why this test exists.
        $this->expectException(ReadOnlyProjection::class);

        Task::query()->touch('created_at');
    }

    #[Test]
    public function pivot_writes_are_refused_and_the_join_table_is_unchanged(): void
    {
        $task = Task::query()->firstOrFail();
        $before = DB::table('task_tag')->count();

        foreach (['attach', 'detach', 'sync', 'toggle'] as $method) {
            try {
                $task->tags()->{$method}([999]);
                self::fail("{$method}() mal byť odmietnutý");
            } catch (ReadOnlyProjection) {
                // expected
            }
        }

        self::assertSame($before, DB::table('task_tag')->count());
    }

    #[Test]
    public function creating_through_the_model_is_refused(): void
    {
        $this->expectException(ReadOnlyProjection::class);

        TodoList::query()->create(['name' => 'nový', 'slug' => 'novy']);
    }

    #[Test]
    public function insert_and_upsert_are_refused(): void
    {
        foreach (['insert', 'upsert'] as $method) {
            try {
                $method === 'insert'
                    ? Tag::query()->insert(['name' => 'x', 'slug' => 'x'])
                    : Tag::query()->upsert([['name' => 'x', 'slug' => 'x']], ['slug']);

                self::fail("{$method}() mal byť odmietnutý");
            } catch (ReadOnlyProjection) {
                // expected
            }
        }

        self::assertSame(1, DB::table('tags')->count());
    }

    #[Test]
    public function reading_still_works_in_every_shape_the_app_uses(): void
    {
        $task = Task::query()->with('tags', 'list')->firstOrFail();

        self::assertSame('nedotknuteľná', $task->title);
        self::assertSame('Zámok', $task->list->name);
        self::assertSame(['štítok'], $task->tags->pluck('name')->all());
        self::assertSame(1, TodoList::query()->withCount('tasks')->firstOrFail()->tasks_count);
    }
}
