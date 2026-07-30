<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Entity\Priority;
use App\Models\Entity\Tag;
use App\Models\Entity\TaskStatus;
use App\Models\Entity\TodoList;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Note what this file does not extend: not Laravel's TestCase, not
 * RefreshDatabase, not anything. The entities are plain objects with no
 * idea a database exists, so the rules can be tested without booting the
 * framework — which is the practical payoff of persistence ignorance, and
 * the reason this suite runs in milliseconds.
 */
final class DomainRulesTest extends TestCase
{
    #[Test]
    public function a_list_numbers_its_own_tasks(): void
    {
        $list = new TodoList('Poradie', 'poradie');

        self::assertSame(1, $list->addTask('prvá')->position());
        self::assertSame(2, $list->addTask('druhá')->position());
        self::assertSame(3, $list->addTask('tretia')->position());
    }

    #[Test]
    public function open_tasks_are_counted_across_both_unfinished_states(): void
    {
        $list = new TodoList('Počty', 'pocty');
        $list->addTask('otvorená');
        $list->addTask('rozrobená')->start();
        $list->addTask('hotová')->complete();

        self::assertSame(2, $list->openTasks());
    }

    #[Test]
    public function a_list_with_open_tasks_refuses_to_archive(): void
    {
        $list = new TodoList('Rozrobené', 'rozrobene');
        $list->addTask('ešte nie je hotová');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('nedokončené úlohy');

        $list->archive();
    }

    #[Test]
    public function a_list_archives_once_everything_is_done(): void
    {
        $list = new TodoList('Hotové', 'hotove');
        $list->addTask('jediná')->complete();

        $list->archive();

        self::assertTrue($list->isArchived());
    }

    #[Test]
    public function an_archived_list_refuses_new_tasks(): void
    {
        $list = new TodoList('Zavreté', 'zavrete');
        $list->archive();

        $this->expectException(DomainException::class);

        $list->addTask('neskoro');
    }

    #[Test]
    public function a_task_cannot_be_completed_twice(): void
    {
        $task = (new TodoList('X', 'x'))->addTask('raz');
        $task->complete();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('už hotová');

        $task->complete();
    }

    #[Test]
    public function a_finished_task_must_be_reopened_before_it_can_be_started(): void
    {
        $task = (new TodoList('X', 'x'))->addTask('raz');
        $task->complete();

        try {
            $task->start();
            self::fail('start() na hotovej úlohe mala vyhodiť výnimku');
        } catch (DomainException) {
            // expected
        }

        $task->reopen();
        $task->start();

        self::assertSame(TaskStatus::InProgress, $task->status());
    }

    #[Test]
    public function an_empty_or_whitespace_title_is_refused(): void
    {
        $this->expectException(DomainException::class);

        (new TodoList('X', 'x'))->addTask('   ');
    }

    #[Test]
    public function titles_are_trimmed_rather_than_stored_as_typed(): void
    {
        $task = (new TodoList('X', 'x'))->addTask('  Zaliať kvety  ');

        self::assertSame('Zaliať kvety', $task->title());
    }

    #[Test]
    public function overdue_means_past_due_and_still_unfinished(): void
    {
        $list = new TodoList('Termíny', 'terminy');
        $yesterday = new DateTimeImmutable('-1 day');

        $late = $list->addTask('meškajúca', Priority::Normal, $yesterday);
        $soon = $list->addTask('budúca', Priority::Normal, new DateTimeImmutable('+1 day'));
        $undated = $list->addTask('bez termínu');

        self::assertTrue($late->isOverdue());
        self::assertFalse($soon->isOverdue());
        self::assertFalse($undated->isOverdue());

        // finishing it stops it being late, rather than leaving it red forever
        $late->complete();
        self::assertFalse($late->isOverdue());
    }

    #[Test]
    public function tagging_the_same_tag_twice_does_not_duplicate_it(): void
    {
        $task = (new TodoList('X', 'x'))->addTask('raz');
        $tag = new Tag('urgentné', 'urgentne');

        $task->tag($tag);
        $task->tag($tag);

        self::assertCount(1, $task->tags());

        $task->untag($tag);

        self::assertCount(0, $task->tags());
    }

    #[Test]
    public function whitespace_only_notes_become_null_rather_than_an_empty_string(): void
    {
        $task = (new TodoList('X', 'x'))->addTask('raz');

        $task->describe('  poznámka  ');
        self::assertSame('poznámka', $task->notes());

        // '' and null mean the same thing to a reader, so only one of them
        // is allowed to reach the column.
        $task->describe('   ');
        self::assertNull($task->notes());

        $task->describe(null);
        self::assertNull($task->notes());
    }
}
