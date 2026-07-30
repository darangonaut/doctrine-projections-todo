<?php

declare(strict_types=1);

namespace App\Models\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use DomainException;

#[ORM\Entity]
#[ORM\Table(name: 'tags')]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 60)]
    private string $name;

    #[ORM\Column(type: 'string', length: 80, unique: true)]
    private string $slug;

    /**
     * The inverse side. Tasks own the relation, so tagging happens through
     * Task::tag() and this collection only ever reads.
     *
     * @var Collection<int, Task>
     */
    #[ORM\ManyToMany(targetEntity: Task::class, mappedBy: 'tags')]
    private Collection $tasks;

    public function __construct(string $name, string $slug)
    {
        $name = trim($name);

        if ($name === '' || trim($slug) === '') {
            throw new DomainException('Štítok potrebuje názov aj slug.');
        }

        $this->name = $name;
        $this->slug = $slug;
        $this->tasks = new ArrayCollection;
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
