<?php

declare(strict_types=1);

namespace App\Models\Entity;

enum Priority: int
{
    case Low = 1;
    case Normal = 2;
    case High = 3;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Nízka',
            self::Normal => 'Bežná',
            self::High => 'Vysoká',
        };
    }
}
