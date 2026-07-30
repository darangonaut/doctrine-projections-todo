<?php

declare(strict_types=1);

namespace App\Models\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * A list owns its tasks: they are created through it, numbered by it, and
 * cannot outlive it. That ownership is the reason the rules below can be
 * trusted — there is no second way in.
 */
#[ORM\Entity]
#[ORM\Table(name: 'todo_lists')]
class TodoList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 120)]
    private string $name;

    #[ORM\Column(type: 'string', length: 140, unique: true)]
    private string $slug;

    #[ORM\Column(name: 'archived_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'list', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $tasks;

    public function __construct(string $name, string $slug)
    {
        $this->rename($name);

        if (trim($slug) === '') {
            throw new DomainException('Slug nemôže byť prázdny.');
        }

        $this->slug = $slug;
        $this->createdAt = new DateTimeImmutable;
        $this->tasks = new ArrayCollection;
    }

    /**
     * The only way a Task comes into existence. It is what lets `position`
     * mean something — an entity constructed from the outside would have to
     * be told its number, and sooner or later would be told the wrong one.
     */
    public function addTask(string $title, Priority $priority = Priority::Normal, ?DateTimeImmutable $dueOn = null): Task
    {
        if ($this->isArchived()) {
            throw new DomainException('Do archivovaného zoznamu sa nedá pridávať.');
        }

        $task = new Task($this, $title, $this->nextPosition(), $priority, $dueOn);

        $this->tasks->add($task);

        return $task;
    }

    /**
     * Archiving is a statement that the list is finished, so it may not
     * hold anything unfinished. Enforcing that here rather than in a
     * controller means it holds for every caller.
     */
    public function archive(): void
    {
        if ($this->isArchived()) {
            return;
        }

        if ($this->openTasks() > 0) {
            throw new DomainException('Zoznam má nedokončené úlohy, nedá sa archivovať.');
        }

        $this->archivedAt = new DateTimeImmutable;
    }

    public function restore(): void
    {
        $this->archivedAt = null;
    }

    public function rename(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new DomainException('Názov zoznamu nemôže byť prázdny.');
        }

        $this->name = $name;
    }

    public function openTasks(): int
    {
        return $this->tasks
            ->filter(static fn (Task $task): bool => ! $task->status()->isFinished())
            ->count();
    }

    private function nextPosition(): int
    {
        $positions = $this->tasks->map(static fn (Task $task): int => $task->position());

        return $positions->isEmpty() ? 1 : max($positions->toArray()) + 1;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function slug(): string
    {
        return $this->slug;
    }

    /** @return Collection<int, Task> */
    public function tasks(): Collection
    {
        return $this->tasks;
    }
}
