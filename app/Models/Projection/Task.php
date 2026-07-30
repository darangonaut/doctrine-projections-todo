<?php

declare(strict_types=1);

namespace App\Models\Projection;

use App\Models\Entity\Priority;
use App\Models\Entity\TaskStatus;
use Carbon\CarbonImmutable;
use Darangonaut\DoctrineProjections\Eloquent\ReadOnlyModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * GENERATED — do not edit.
 *
 * Source: App\Models\Entity\Task
 * Regenerate: php artisan doctrine:projections
 *
 * Read-only projection. Writing throws ReadOnlyProjection — change
 * data through the Doctrine entity.
 *
 * @property int $id
 * @property string $title
 * @property string|null $notes
 * @property TaskStatus $status
 * @property Priority $priority
 * @property CarbonImmutable|null $due_on
 * @property CarbonImmutable|null $completed_at
 * @property int $position
 * @property CarbonImmutable $created_at
 * @property int $list_id
 * @property TodoList $list
 * @property Collection<int, Tag> $tags
 */
class Task extends Model
{
    use ReadOnlyModel;

    protected $table = 'tasks';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'status' => TaskStatus::class,
            'priority' => Priority::class,
            'due_on' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'position' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TodoList, $this> */
    public function list(): BelongsTo
    {
        return $this->belongsTo(TodoList::class, 'list_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag', 'task_id', 'tag_id');
    }
}
