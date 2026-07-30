<?php

declare(strict_types=1);

namespace App\Models\Projection;

use Carbon\CarbonImmutable;
use Darangonaut\DoctrineProjections\Eloquent\ReadOnlyModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * GENERATED — do not edit.
 *
 * Source: App\Models\Entity\TodoList
 * Regenerate: php artisan doctrine:projections
 *
 * Read-only projection. Writing throws ReadOnlyProjection — change
 * data through the Doctrine entity.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CarbonImmutable|null $archived_at
 * @property CarbonImmutable $created_at
 * @property Collection<int, Task> $tasks
 */
class TodoList extends Model
{
    use ReadOnlyModel;

    protected $table = 'todo_lists';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'archived_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'list_id')
            ->orderBy('position', 'asc');
    }
}
