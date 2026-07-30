<?php

declare(strict_types=1);

namespace App\Models\Entity;

use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

/**
 * Status and completedAt are one fact stored in two columns, so nothing
 * outside this class may set either. Every transition goes through a named
 * method, which is also why an illegal one (completing twice, finishing a
 * task nobody started) can be refused rather than silently recorded.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tasks')]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 200)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 20, enumType: TaskStatus::class)]
    private TaskStatus $status = TaskStatus::Open;

    #[ORM\Column(type: 'smallint', enumType: Priority::class)]
    private Priority $priority;

    #[ORM\Column(name: 'due_on', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $dueOn;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: TodoList::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'list_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private TodoList $list;

    /** @var Collection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_tag')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $tags;

    /** @internal use {@see TodoList::addTask()} */
    public function __construct(
        TodoList $list,
        string $title,
        int $position,
        Priority $priority = Priority::Normal,
        ?DateTimeImmutable $dueOn = null,
    ) {
        $this->list = $list;
        $this->position = $position;
        $this->priority = $priority;
        $this->dueOn = $dueOn;
        $this->createdAt = new DateTimeImmutable;
        $this->tags = new ArrayCollection;

        $this->rename($title);
    }

    public function start(): void
    {
        if ($this->status->isFinished()) {
            throw new DomainException('Hotová úloha sa nedá rozrobiť, najprv ju otvor.');
        }

        $this->status = TaskStatus::InProgress;
    }

    public function complete(): void
    {
        if ($this->status->isFinished()) {
            throw new DomainException('Úloha je už hotová.');
        }

        $this->status = TaskStatus::Done;
        $this->completedAt = new DateTimeImmutable;
    }

    public function reopen(): void
    {
        $this->status = TaskStatus::Open;
        $this->completedAt = null;
    }

    public function rename(string $title): void
    {
        $title = trim($title);

        if ($title === '') {
            throw new DomainException('Úloha potrebuje názov.');
        }

        $this->title = $title;
    }

    public function changePriority(Priority $priority): void
    {
        $this->priority = $priority;
    }

    public function reschedule(?DateTimeImmutable $dueOn): void
    {
        $this->dueOn = $dueOn;
    }

    public function describe(?string $notes): void
    {
        $notes = $notes === null ? null : trim($notes);

        $this->notes = $notes === '' ? null : $notes;
    }

    public function tag(Tag $tag): void
    {
        if (! $this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
    }

    public function untag(Tag $tag): void
    {
        $this->tags->removeElement($tag);
    }

    public function isOverdue(?DateTimeImmutable $now = null): bool
    {
        if ($this->dueOn === null || $this->status->isFinished()) {
            return false;
        }

        return $this->dueOn < ($now ?? new DateTimeImmutable);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function priority(): Priority
    {
        return $this->priority;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function list(): TodoList
    {
        return $this->list;
    }

    /** @return Collection<int, Tag> */
    public function tags(): Collection
    {
        return $this->tags;
    }
}
