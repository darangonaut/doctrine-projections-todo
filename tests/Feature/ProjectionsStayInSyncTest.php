<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The failure this guards against is a deploy where someone edits an
 * entity and forgets to regenerate: the projection then quietly lacks the
 * new column and reads come back short. Running `--check` in the suite
 * turns that into a red build instead of a bug in production.
 */
final class ProjectionsStayInSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_committed_projections_match_the_current_mapping(): void
    {
        $this->artisan('doctrine:projections --check')->assertSuccessful();
    }

    #[Test]
    public function the_migrated_schema_leaves_the_mapping_nothing_to_say(): void
    {
        // A migration missing from the repo shows up here as leftover DDL:
        // the mapping wants something the migrated database does not have.
        self::assertSame(0, Artisan::call('doctrine:diff', ['--dry' => true]));

        $ddl = preg_grep(
            '/^\s*(CREATE|ALTER|DROP)\b/i',
            explode("\n", Artisan::output()),
        );

        self::assertSame([], array_values($ddl), 'Chýba migrácia — mapovanie a schéma sa rozišli.');
    }
}
