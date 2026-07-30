<?php

declare(strict_types=1);

namespace Tests;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function em(): EntityManagerInterface
    {
        return $this->app->make(EntityManagerInterface::class);
    }

    /**
     * Drops everything Doctrine has in memory, so the next read is forced
     * to go to the database rather than to the identity map. Without this
     * a test could pass on a stale in-memory object.
     */
    protected function forget(): void
    {
        $this->em()->clear();
    }
}
