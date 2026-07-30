<?php

declare(strict_types=1);

namespace App\Models\Projection;

use Darangonaut\DoctrineProjections\Eloquent\ReadOnlyModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * GENERATED — do not edit.
 *
 * Source: App\Models\Entity\Tag
 * Regenerate: php artisan doctrine:projections
 *
 * Read-only projection. Writing throws ReadOnlyProjection — change
 * data through the Doctrine entity.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Collection<int, Task> $tasks
 */
class Tag extends Model
{
    use ReadOnlyModel;

    protected $table = 'tags';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }

    /** @return BelongsToMany<Task, $this> */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_tag', 'tag_id', 'task_id');
    }
}
