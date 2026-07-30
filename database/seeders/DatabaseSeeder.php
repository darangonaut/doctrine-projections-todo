<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Entity\Priority;
use App\Models\Entity\Tag;
use App\Models\Entity\TodoList;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\Seeder;

/**
 * Seeding goes through the entities like everything else that writes, so
 * the demo data cannot be in a state the domain would refuse to produce.
 */
class DatabaseSeeder extends Seeder
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function run(): void
    {
        $home = new TodoList('Domácnosť', 'domacnost');
        $work = new TodoList('Balík v0.2', 'balik-v02');
        $done = new TodoList('Sťahovanie', 'stahovanie');

        $urgent = new Tag('urgentné', 'urgentne');
        $cheap = new Tag('lacné', 'lacne');

        $home->addTask('Vymeniť žiarovku v kuchyni', Priority::Low);
        $home->addTask('Objednať servis kotla', Priority::High, new DateTimeImmutable('+3 days'))->tag($urgent);
        $home->addTask('Zaliať kvety', Priority::Normal)->tag($cheap);

        $work->addTask('Odoslať balík na Packagist', Priority::High)->complete();
        $work->addTask('Napísať upgrade guide', Priority::Normal, new DateTimeImmutable('-2 days'))->tag($urgent);
        $work->addTask('Doplniť Filament recipe', Priority::Low)->start();

        // Archiving only works because every task in it is finished.
        $done->addTask('Zrušiť starú adresu', Priority::Normal)->complete();
        $done->addTask('Prehlásiť elektrinu', Priority::High)->complete();
        $done->archive();

        foreach ([$urgent, $cheap, $home, $work, $done] as $entity) {
            $this->em->persist($entity);
        }

        $this->em->flush();
    }
}
