<?php

declare(strict_types=1);

namespace App\Models\Entity;

enum TaskStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Otvorené',
            self::InProgress => 'Rozrobené',
            self::Done => 'Hotové',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::Done;
    }
}
