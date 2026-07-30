<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Entity\Priority;
use App\Models\Entity\Tag as TagEntity;
use App\Models\Entity\TodoList as TodoListEntity;
use App\Models\Projection\Tag;
use App\Models\Projection\Task;
use App\Models\Projection\TodoList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedList(): TodoListEntity
    {
        $list = new TodoListEntity('Domácnosť', 'domacnost');
        $list->addTask('Vymeniť žiarovku', Priority::Low);
        $list->addTask('Objednať servis', Priority::High)->complete();

        $tag = new TagEntity('urgentné', 'urgentne');
        $list->tasks()->first()->tag($tag);

        $this->em()->persist($tag);
        $this->em()->persist($list);
        $this->em()->flush();
        $this->forget();

        return $list;
    }

    #[Test]
    public function the_index_lists_every_list_with_its_counts(): void
    {
        $this->seedList();

        $this->get('/')
            ->assertOk()
            ->assertSee('Domácnosť')
            ->assertSee('1 / 2 otvorených');
    }

    #[Test]
    public function a_list_page_shows_its_tasks_tags_and_enum_labels(): void
    {
        $this->seedList();

        $this->get('/zoznamy/domacnost')
            ->assertOk()
            ->assertSee('Vymeniť žiarovku')
            ->assertSee('urgentné')
            ->assertSee('Nízka priorita')
            ->assertSee('Hotové');
    }

    #[Test]
    public function the_status_filter_narrows_the_list(): void
    {
        $this->seedList();

        $this->get('/zoznamy/domacnost?stav=done')
            ->assertOk()
            ->assertSee('Objednať servis')
            ->assertDontSee('Vymeniť žiarovku');
    }

    #[Test]
    public function a_list_can_be_created_through_the_form(): void
    {
        $this->post('/zoznamy', ['name' => 'Nový zoznam'])
            ->assertRedirect('/zoznamy/novy-zoznam');

        self::assertSame(1, TodoList::query()->where('slug', 'novy-zoznam')->count());
    }

    #[Test]
    public function a_task_can_be_added_and_completed_through_the_form(): void
    {
        $list = $this->seedList();

        $this->post("/zoznamy/{$list->id()}/ulohy", ['title' => 'Zaliať kvety', 'priority' => 3])
            ->assertRedirect();

        $task = Task::query()->where('title', 'Zaliať kvety')->firstOrFail();
        self::assertSame(Priority::High, $task->priority);
        self::assertSame(3, $task->position);

        $this->patch("/ulohy/{$task->id}/complete")->assertRedirect();

        self::assertSame('done', Task::query()->find($task->id)->status->value);
    }

    #[Test]
    public function a_refused_domain_rule_comes_back_as_a_flash_message(): void
    {
        $list = $this->seedList();

        // one task is still open, so archiving must be refused
        $this->patch("/zoznamy/{$list->id()}/archivovat")
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $msg): bool => str_contains($msg, 'nedokončené'));

        $this->forget();

        self::assertNull(TodoList::query()->firstOrFail()->archived_at);
    }

    #[Test]
    public function tagging_through_the_form_reuses_an_existing_tag(): void
    {
        $this->seedList();

        $task = Task::query()->where('title', 'Objednať servis')->firstOrFail();

        $this->post("/ulohy/{$task->id}/stitok", ['tag' => 'urgentné'])->assertRedirect();
        $this->forget();

        self::assertSame(1, Tag::query()->count());
        self::assertSame(2, DB::table('task_tag')->count());
    }
}
