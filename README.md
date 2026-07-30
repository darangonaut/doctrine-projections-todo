# Úlohy — demo pre `laravel-doctrine-projections`

Malá todo aplikácia, ktorá existuje na jediný účel: ukázať, že
[`darangonaut/laravel-doctrine-projections`](https://github.com/darangonaut/laravel-doctrine-projections)
funguje na zelenej lúke. Balík sa ťahá z Packagistu ako každá iná
závislosť — nič tu nie je nalinkované na lokálny adresár.

**Zápis ide cez Doctrine entity, čítanie cez generované Eloquent projekcie.**

```php
// zápis — invarianty sú v entite
$list->addTask('Zaliať kvety', Priority::High);
$em->flush();

// čítanie — Eloquent so všetkým, čo naň patrí
TodoList::withCount('tasks')->orderBy('archived_at')->get();

// a toto vyhodí ReadOnlyProjection
Task::query()->update(['status' => 'done']);
```

## Spustenie

```bash
composer install
touch database/database.sqlite
php artisan migrate --seed
php artisan doctrine:projections
php artisan serve
```

Migrácia `create_todo_schema` **nebola písaná ručne** — vygeneroval ju
`php artisan doctrine:diff` z mapovania.

Žiadny `npm` krok tu nie je: šablóny ťahajú Tailwind cez play CDN, aby
demo bežalo hneď po `composer install`. Do produkcie by to nešlo, ale tu
nie je predmetom ukážky CSS build.

## Doména

Tri entity v `app/Models/Entity`, tri pravidlá, ktoré sa oplatí mať na
jednom mieste:

| Pravidlo | Kde žije |
|---|---|
| Úloha vzniká len cez `TodoList::addTask()`, ktorý jej pridelí poradie | `TodoList` |
| Zoznam sa nedá archivovať, kým má otvorené úlohy | `TodoList::archive()` |
| `status` a `completed_at` sú jeden fakt v dvoch stĺpcoch, hýbu sa spolu | `Task::complete()` / `reopen()` |

Controller ich nekontroluje — len chytí `DomainException` a zobrazí ju.
Preto platia rovnako pre formulár, seeder aj čokoľvek dopísané neskôr.

## Čo z balíka je otestované

`app/Models/Projection` je **build output**, nie zdroják — generuje sa
z mapovania a prepisuje sa pri každom behu.

| Suita | Čo dokazuje |
|---|---|
| `Unit/DomainRulesTest` | doménové pravidlá bežia **bez Laravelu a bez databázy** — entity nevedia, že nejaká existuje |
| `Feature/WritingThroughDoctrineTest` | čo zapíše Doctrine, prečíta projekcia — vrátane oboch enumov, one-to-many aj many-to-many |
| `Feature/ProjectionsRefuseWritesTest` | `save`, `update`, `delete`, `create`, `insert`, `upsert`, `touch`, `attach`/`detach`/`sync`/`toggle` — každý odmietnutý a riadok po pokuse skontrolovaný |
| `Feature/SharedConnectionTest` | Doctrine a Eloquent držia **ten istý PDO handle**, takže `DB::transaction()` vie odrolovať `flush()` |
| `Feature/ProjectionsStayInSyncTest` | `--check` a `doctrine:diff --dry` chytia entitu, ktorá sa rozišla s projekciou alebo so schémou |
| `Feature/PagesTest` | stránky, filter, formuláre a odmietnuté pravidlo ako flash hláška |

```bash
php artisan test     # 40 testov
```

### Prečo je `SharedConnectionTest` dôležitý

Testy bežia na `DB_DATABASE=:memory:`. Keby sa Doctrine zapájalo tak, ako
sa to ponúka samo — prečítať `config('database.connections.sqlite')` a
podať to `DriverManager`u — dostalo by **vlastnú prázdnu in-memory
databázu** a celý zvyšok suity by padol. `SharedPdoDriver` z balíka mu
namiesto toho podá to isté PDO, ktoré už otvoril Laravel.

Zapojenie je celé v `app/Providers/DoctrineServiceProvider.php`; balík
schválne nerobí nič z toho sám, len si vypýta `EntityManagerInterface`
z kontajnera.
