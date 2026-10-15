<?php

declare(strict_types=1);

namespace DataFixtures;

use App\Entity\FavoriteLot;
use App\Entity\HolidayAdjustment;
use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\LotProgress;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\TimeEntry;
use App\Entity\TimeOffEntitlement;
use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Entity\WeeklyMax;
use App\Enum\Type\HalfDay;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Model\Schedule\LeafProgress;
use App\Model\TimeOff\TimeOffPeriod;
use App\Model\Week;
use App\Service\HolidayManager;
use App\Service\LegalHolidays;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Symfony\Component\String\u;

/**
 * A fake software company: three projects split into lots and sub-lots, a team with part-time
 * people and two people on the Belgian holiday calendar, and six months of time entries up to yesterday, none on a
 * holiday nor on a day off. Each week a team swarms on one leaf of its project, and its people lend a hand to another
 * project some days; two weeks of each person are approved paid leave. Test accounts get no entry in the current week
 * and no time off, so that the end-to-end scenarios find their week empty.
 * Deterministic: the same seed always gives the same company, relative to today.
 */
final class DemoCompanyFixtures extends Fixture implements DependentFixtureInterface
{
    private const int WEEKS = 26;
    private const int SEED = 2026;

    /** Test account key => its fixture reference and team. */
    private const array TEST_ACCOUNTS = [
        'thomas' => [AppFixtures::DIRECTOR, ''],
        'olivier' => [AppFixtures::LEAD, 'onboarding'],
        'antoine' => [AppFixtures::PROD, 'onboarding'],
    ];

    /** People on the Belgian holiday calendar. */
    private const array BELGIANS = ['damien', 'lena'];

    /** Team => the team whose project it lends a hand to. */
    private const array HELPS = ['sca' => 'modernisation', 'modernisation' => 'sca', 'onboarding' => 'sca'];

    /** Leaves left « à estimer » although time is entered on them. */
    private const array TO_ESTIMATE = ['Tests de charge'];

    /** A running leaf behind its estimate: its progress declarations, days before today then percent, the latest last. */
    private const array PROGRESS_HISTORY = ['Relances' => [[21, 30], [7, 40], [0, 45]]];

    /** A running leaf whose progress, declared two weeks ago when a quarter of its time was entered, is used up since. */
    private const string PROGRESS_TO_REFRESH = 'Documentation';

    /** Person key => team type, technical skills, functional experiences. */
    private const array PROFILES = [
        'thomas' => ['Direction', [], ['SCA', 'Socle technique']],
        'thibaut' => ['Produit', [], ['SCA', 'Onboarding']],
        'gabriel' => ['Back', ['Symfony', 'PHP', 'PostgreSQL', 'Docker'], ['SCA', 'Socle technique']],
        'olivier' => ['Front', ['React', 'TypeScript', 'Stimulus'], ['Onboarding', 'SCA']],
        'maxime' => ['Back', ['Symfony', 'PHP', 'PostgreSQL'], ['SCA']],
        'romain' => ['Back', ['Symfony', 'PHP', 'API REST'], ['SCA', 'Onboarding']],
        'damien' => ['Back', ['Symfony', 'Docker', 'CI'], ['Socle technique']],
        'antoine' => ['Front', ['React', 'TypeScript'], ['Onboarding']],
        'hobi' => ['QA', ['Playwright', 'API REST'], ['SCA', 'Onboarding']],
        'lena' => ['Design', ['Figma'], ['Onboarding']],
    ];

    /** Person key => their manager: Thomas heads Gabriel, Thibaut heads Olivier, each lead their team. */
    private const array MANAGERS = [
        'gabriel' => 'thomas', 'olivier' => 'thibaut',
        'maxime' => 'gabriel', 'romain' => 'gabriel', 'damien' => 'gabriel', 'hobi' => 'gabriel',
        'antoine' => 'olivier', 'lena' => 'olivier',
    ];

    /**
     * The team of the running leaves, owner first: they start on the week of their first entry. Maxime is loaded at
     * 150 % by Relances and Recette, as when a planning is overtaken by the time entered.
     */
    private const array TEAMS = [
        'Relances' => [['maxime', 100], ['hobi', 50]],
        'Recette' => [['hobi', 50], ['maxime', 50]],
        'Tableaux de bord' => [['romain', 100]],
        'Alerting' => [['damien', 80], ['gabriel', 50]],
        'Documentation' => [['gabriel', 50], ['damien', 20]],
        'Tableau de bord d\'accueil' => [['lena', 100]],
    ];

    /**
     * Time off ahead, outside the teams the end-to-end scenarios plan: person key, weeks after the current one, first
     * and last ISO day of that week with their half-day, type and status.
     */
    private const array TIME_OFF = [
        ['romain', 1, 3, HalfDay::Afternoon, 3, HalfDay::Afternoon, TimeOffType::Rtt, TimeOffStatus::Approved],
        ['hobi', 3, 1, HalfDay::Morning, 5, HalfDay::Afternoon, TimeOffType::PaidLeave, TimeOffStatus::Approved],
        ['damien', 2, 4, HalfDay::Morning, 4, HalfDay::Afternoon, TimeOffType::Rtt, TimeOffStatus::Pending],
        ['lena', 2, 1, HalfDay::Morning, 1, HalfDay::Afternoon, TimeOffType::Rtt, TimeOffStatus::Pending],
        ['maxime', 5, 1, HalfDay::Morning, 3, HalfDay::Afternoon, TimeOffType::PaidLeave, TimeOffStatus::Refused],
        ['gabriel', 4, 1, HalfDay::Morning, 5, HalfDay::Afternoon, TimeOffType::PaidLeave, TimeOffStatus::Cancelled],
        ['thibaut', 6, 1, HalfDay::Morning, 5, HalfDay::Afternoon, TimeOffType::FamilyEvent, TimeOffStatus::Pending],
    ];

    /** The entitlements declared to everyone, in half-days, for the current and the previous period. */
    private const array ENTITLEMENTS = ['cp' => 50, 'rtt' => 20];

    /** A leaf planned two weeks ago on which nobody has entered time yet: title, estimate in days, team (owner first). */
    private const array LATE_START = ['Migration des clients', 15, [['romain', 50]]];

    /**
     * Person key => first name, last name, role, team, weekly quarters, day off (ISO day) or null, first week, weekly
     * quarters after a change and the week of that change (or null).
     *
     * @return array<string, array{non-empty-string, non-empty-string, Role, string, int<1, 20>, int|null, int, array{int<1, 20>, int}|null}>
     */
    private static function people(): array
    {
        return [
            'thibaut' => ['Thibaut', 'Fleuriot', Role::Direction, '', 20, null, 0, null],
            'gabriel' => ['Gabriel', 'Mustiere', Role::Lead, 'modernisation', 20, null, 0, null],
            'maxime' => ['Maxime', 'Roux', Role::Prod, 'sca', 20, null, 0, null],
            'romain' => ['Romain', 'Garnier', Role::Prod, 'sca', 18, null, 0, null],
            'hobi' => ['Hobi', 'Lambert', Role::Prod, 'sca', 20, null, 10, null],
            'damien' => ['Damien', 'Faure', Role::Prod, 'modernisation', 20, null, 0, [16, 13]],
            'lena' => ['Lena', 'Fontaine', Role::Prod, 'onboarding', 16, 3, 0, null],
        ];
    }

    /**
     * Team => project title, description, then its lots: title => [first week, last week] for a leaf, or
     * title => ['subLots' => [sub-lot title => [first week, last week]]] for a split lot. A null range means no time
     * entered yet.
     *
     * @return array<string, array{non-empty-string, string, array<non-empty-string, array{int, int}|array{subLots: array<non-empty-string, array{int, int}|null>}|null>}>
     */
    private static function projects(): array
    {
        return [
            'sca' => ['SCA', 'Évolutions du produit SCA : facturation, exports et reprise des clients existants.', [
                'Cadrage' => [0, 2],
                'Module de facturation' => ['subLots' => ['Modèle de données' => [2, 10], 'Calcul des échéances' => [6, 16], 'Relances' => [14, 26]]],
                'Exports' => ['subLots' => ['Exports comptables' => [10, 20], 'Tableaux de bord' => [16, 26]]],
                'Recette' => [21, 26],
                'Migration des clients' => null,
            ]],
            'modernisation' => ['Modernisation technique', 'Montées de version, intégration continue et observabilité de la plateforme.', [
                'Audit de l\'existant' => [0, 3],
                'Montée de version' => ['subLots' => ['PHP 8.5' => [3, 9], 'Symfony 8' => [8, 16]]],
                'Intégration continue' => [6, 14],
                'Observabilité' => ['subLots' => ['Logs centralisés' => [14, 22], 'Alerting' => [18, 26]]],
                'Documentation' => [20, 26],
                'Tests de charge' => [22, 26],
            ]],
            'onboarding' => ['Onboarding', 'Parcours d\'arrivée des nouveaux clients, de l\'inscription au premier usage.', [
                'Maquettes' => [0, 4],
                'Inscription' => [3, 9],
                'Parcours guidé' => ['subLots' => ['Assistant de configuration' => [8, 16], 'Import des données' => [12, 22]]],
                'Tableau de bord d\'accueil' => [17, 26],
            ]],
        ];
    }

    /** @var array<string, User> */
    private array $people = [];

    /** @var array<string, array<string, Tag>> by category value, then label */
    private array $tags = [];

    /** @var array<string, list<array{Lot, int, int}>> leaves by team, with their active weeks */
    private array $leavesByTeam = [];

    /** @var array<string, array<int, Lot|null>> the leaf each team swarms on, by week index */
    private array $focus = [];

    /** @var array<int, int> quarters entered by lot id */
    private array $consumed = [];

    /** @var array<string, Lot> every leaf by title */
    private array $leaves = [];

    /** @var array<int, TimeEntry> the last entry made on each lot, by lot id */
    private array $lastEntries = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ClockInterface $clock,
        private readonly LegalHolidays $legalHolidays,
        private readonly HolidayManager $holidayManager,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        mt_srand(self::SEED);
        $today = $this->clock->now()->setTime(0, 0);
        $firstWeek = Week::containing($today);
        for ($i = 0; $i < self::WEEKS; ++$i) {
            $firstWeek = $firstWeek->previous();
        }

        $this->loadPeople($manager, $firstWeek);
        $this->loadProfiles($manager);
        $this->loadProjects($manager);
        $this->loadReplacementDays($manager, $firstWeek, $today);
        $manager->flush();

        $support = $this->getReference(ProjectFixtures::SUPPORT, Lot::class);
        foreach ($this->people as $key => $person) {
            $this->loadTimeEntries($manager, $key, $person, $firstWeek, $today, $support);
        }
        $this->estimate();
        $this->plan($firstWeek, $today);
        $this->declareProgress($manager, $today);
        $this->loadFavorites($manager, $support);
        $this->loadTimeOff($manager, $today);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, ProjectFixtures::class];
    }

    private function loadPeople(ObjectManager $manager, Week $firstWeek): void
    {
        foreach (self::people() as $key => [$firstName, $lastName, $role, , $quarters, , , $change]) {
            $email = \sprintf('%s.%s@anyti.me', $key, u($lastName)->ascii()->lower()->toString());
            /** @var non-empty-string $email */
            $user = new User()->setEmail($email)->setFirstName($firstName)->setLastName($lastName)->setRole($role)
                ->setHolidayCalendar(\in_array($key, self::BELGIANS, true) ? HolidayCalendar::Belgium : HolidayCalendar::France);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
            $manager->persist($user);
            $this->people[$key] = $user;

            if ($quarters < 20) {
                $manager->persist(new WeeklyMax($user, $firstWeek->monday, $quarters));
            }
            if (null !== $change) {
                $manager->persist(new WeeklyMax($user, $this->week($firstWeek, $change[1])->monday, $change[0]));
            }
        }

        foreach (self::TEST_ACCOUNTS as $key => [$reference]) {
            $this->people[$key] = $this->getReference($reference, User::class);
        }
    }

    private function loadProfiles(ObjectManager $manager): void
    {
        foreach (self::PROFILES as $key => [$teamType, $skills, $experiences]) {
            $tags = [$this->tag($manager, TagCategory::TeamType, $teamType)];
            foreach ($skills as $label) {
                $tags[] = $this->tag($manager, TagCategory::TechnicalSkill, $label);
            }
            foreach ($experiences as $label) {
                $tags[] = $this->tag($manager, TagCategory::FunctionalExperience, $label);
            }
            $this->people[$key]->replaceTags($tags);
        }

        foreach (self::MANAGERS as $key => $managerKey) {
            $this->people[$key]->setManager($this->people[$managerKey]);
        }
    }

    /**
     * @param non-empty-string $label
     */
    private function tag(ObjectManager $manager, TagCategory $category, string $label): Tag
    {
        if (!isset($this->tags[$category->value][$label])) {
            $this->tags[$category->value][$label] = new Tag($category, $label);
            $manager->persist($this->tags[$category->value][$label]);
        }

        return $this->tags[$category->value][$label];
    }

    /**
     * A Belgian legal holiday falling on a weekend is replaced by the next working day that is not a holiday.
     */
    private function loadReplacementDays(ObjectManager $manager, Week $firstWeek, \DateTimeImmutable $today): void
    {
        $legalHolidays = [];
        for ($year = (int) $firstWeek->monday->format('Y'); $year <= (int) $today->format('Y'); ++$year) {
            $legalHolidays += $this->legalHolidays->forYear(HolidayCalendar::Belgium, $year);
        }

        foreach (array_keys($legalHolidays) as $day) {
            $holiday = new \DateTimeImmutable($day);
            if ($holiday < $firstWeek->monday || $holiday >= $today || (int) $holiday->format('N') < 6) {
                continue;
            }

            $replacement = $holiday->modify('next monday');
            while (isset($legalHolidays[$replacement->format('Y-m-d')])) {
                $replacement = $replacement->modify('+1 weekday');
            }
            $manager->persist(HolidayAdjustment::added(HolidayCalendar::Belgium, $replacement, \sprintf('Remplacement du %s', $holiday->format('d/m'))));
        }
    }

    private function loadProjects(ObjectManager $manager): void
    {
        foreach (self::projects() as $team => [$title, $description, $lots]) {
            $project = new Project()->setTitle($title)->setDescription($description);
            $manager->persist($project);
            $members = $this->members($team);

            foreach ($lots as $lotTitle => $content) {
                $lot = new Lot($project)->setTitle($lotTitle);
                $manager->persist($lot);

                if (null === $content || !isset($content['subLots'])) {
                    $this->addLeaf($team, $lot->setOwner($members[mt_rand(0, \count($members) - 1)]), $content);
                    continue;
                }

                foreach ($content['subLots'] as $subLotTitle => $weeks) {
                    $subLot = new Lot($project, $lot)->setTitle($subLotTitle)->setOwner($members[mt_rand(0, \count($members) - 1)]);
                    $manager->persist($subLot);
                    $this->addLeaf($team, $subLot, $weeks);
                }
            }
        }
    }

    /**
     * @param array{int, int}|null $weeks
     */
    private function addLeaf(string $team, Lot $leaf, ?array $weeks): void
    {
        $this->leaves[(string) $leaf->getTitle()] = $leaf;
        if (null !== $weeks) {
            $this->leavesByTeam[$team][] = [$leaf, $weeks[0], $weeks[1]];
        }
    }

    /**
     * @return list<User>
     */
    private function members(string $team): array
    {
        $members = [];
        foreach ($this->people as $key => $person) {
            if ($team === (self::people()[$key][3] ?? self::TEST_ACCOUNTS[$key][1] ?? '')) {
                $members[] = $person;
            }
        }

        return $members;
    }

    /**
     * The test accounts pin their current leaf and the support.
     */
    private function loadFavorites(ObjectManager $manager, Lot $support): void
    {
        $favorites = [
            'antoine' => [$this->leaves['Tableau de bord d\'accueil'], $support],
            'olivier' => [$this->leaves['Tableau de bord d\'accueil'], $support],
        ];
        foreach ($favorites as $key => $leaves) {
            foreach ($leaves as $leaf) {
                $manager->persist(new FavoriteLot($this->people[$key], $leaf));
            }
        }
    }

    private function loadTimeEntries(ObjectManager $manager, string $key, User $person, Week $firstWeek, \DateTimeImmutable $today, Lot $support): void
    {
        [, , , $team, $quarters, $dayOff, $firstActiveWeek, $change] = self::people()[$key] ?? ['', '', Role::Prod, self::TEST_ACCOUNTS[$key][1] ?? '', 20, null, 0, null];
        if (Role::Direction === $person->getRole()) {
            return;
        }

        $isTestAccount = isset(self::TEST_ACCOUNTS[$key]);
        $currentMonday = Week::containing($today)->monday;
        $holidays = [mt_rand(4, 10), mt_rand(15, 22)];

        for ($weekIndex = $firstActiveWeek; $weekIndex <= self::WEEKS; ++$weekIndex) {
            $week = $this->week($firstWeek, $weekIndex);
            if (\in_array($weekIndex, $holidays, true) && !$isTestAccount) {
                $manager->persist($this->timeOff($person, $week->monday, HalfDay::Morning, $week->friday(), HalfDay::Afternoon, TimeOffType::PaidLeave, TimeOffStatus::Approved));
            }
            if (\in_array($weekIndex, $holidays, true) || ($isTestAccount && $week->monday >= $currentMonday)) {
                continue;
            }

            $weeklyQuarters = null !== $change && $weekIndex >= $change[1] ? $change[0] : $quarters;
            $leaves = $this->activeLeaves($team, $weekIndex);
            $focus = $this->focus($team, $weekIndex);
            $helped = $this->focus(self::HELPS[$team] ?? '', $weekIndex);
            $publicHolidays = $this->holidayManager->holidaysOf($person, $week);
            foreach ($week->days() as $day) {
                if ($day >= $today) {
                    break;
                }
                if (isset($publicHolidays[$day->format('Y-m-d')])) {
                    continue;
                }

                $capacity = $this->dayCapacity((int) $day->format('N'), $weeklyQuarters, $dayOff);
                foreach ($this->split($capacity, $leaves, $focus, $helped, $support) as [$leaf, $dayQuarters]) {
                    $entry = new TimeEntry($person, $leaf, $day, $dayQuarters);
                    $manager->persist($entry);
                    $this->lastEntries[(int) $leaf->getId()] = $entry;
                    $this->consumed[(int) $leaf->getId()] = ($this->consumed[(int) $leaf->getId()] ?? 0) + $dayQuarters;
                }
            }
        }
    }

    /**
     * A full day, except a part-time day off (four-day weeks) or a Friday afternoon off (4.5 days); now and then a
     * forgotten or partly entered day.
     */
    private function dayCapacity(int $isoDay, int $weeklyQuarters, ?int $dayOff): int
    {
        if ($isoDay === $dayOff || (16 === $weeklyQuarters && null === $dayOff && 5 === $isoDay)) {
            return 0;
        }
        if (18 === $weeklyQuarters && 5 === $isoDay) {
            return 2;
        }

        $draw = mt_rand(1, 100);

        return match (true) {
            $draw <= 3 => 0,
            $draw <= 7 => mt_rand(2, 3),
            default => 4,
        };
    }

    /**
     * @param list<Lot> $leaves
     *
     * @return list<array{Lot, int<1, 4>}>
     */
    private function split(int $capacity, array $leaves, ?Lot $focus, ?Lot $helped, Lot $support): array
    {
        if (0 === $capacity) {
            return [];
        }

        $parts = [];
        if ($capacity >= 2 && mt_rand(1, 100) <= 18) {
            $parts[] = [$support, 1];
            --$capacity;
        }
        if (null !== $helped && $capacity >= 2 && mt_rand(1, 100) <= 40) {
            $lent = mt_rand(1, $capacity - 1);
            $parts[] = [$helped, $lent];
            $capacity -= $lent;
        }
        if ([] === $leaves) {
            $parts[] = [$support, $capacity];

            return self::merge($parts);
        }

        $main = null !== $focus && mt_rand(1, 100) <= 70 ? $focus : $leaves[mt_rand(0, \count($leaves) - 1)];
        if ($capacity >= 2 && \count($leaves) > 1 && mt_rand(1, 100) <= 35) {
            $other = $leaves[mt_rand(0, \count($leaves) - 1)];
            $first = mt_rand(1, $capacity - 1);
            $parts[] = [$main, $first];
            $parts[] = [$other, $capacity - $first];
        } else {
            $parts[] = [$main, $capacity];
        }

        return self::merge($parts);
    }

    /**
     * @param list<array{Lot, int}> $parts
     *
     * @return list<array{Lot, int<1, 4>}>
     */
    private static function merge(array $parts): array
    {
        $merged = [];
        foreach ($parts as [$leaf, $quarters]) {
            $id = spl_object_id($leaf);
            $merged[$id] = [$leaf, ($merged[$id][1] ?? 0) + $quarters];
        }

        return array_values(array_map(static fn (array $part): array => [$part[0], max(1, min(4, $part[1]))], $merged));
    }

    private function focus(string $team, int $weekIndex): ?Lot
    {
        if (!\array_key_exists($weekIndex, $this->focus[$team] ?? [])) {
            $leaves = $this->activeLeaves($team, $weekIndex);
            $this->focus[$team][$weekIndex] = [] === $leaves ? null : $leaves[mt_rand(0, \count($leaves) - 1)];
        }

        return $this->focus[$team][$weekIndex];
    }

    /**
     * @return list<Lot>
     */
    private function activeLeaves(string $team, int $weekIndex): array
    {
        return array_values(array_map(
            static fn (array $leaf): Lot => $leaf[0],
            array_filter($this->leavesByTeam[$team] ?? [], static fn (array $leaf): bool => $weekIndex >= $leaf[1] && $weekIndex <= $leaf[2]),
        ));
    }

    /**
     * Finished leaves either consumed exactly their estimate or overran it, running ones are estimated above what they
     * consumed; the initial estimate sometimes differs, as if it had been revised since.
     */
    private function estimate(): void
    {
        foreach ($this->leavesByTeam as $leaves) {
            foreach ($leaves as [$leaf, , $lastWeek]) {
                if (\in_array($leaf->getTitle(), self::TO_ESTIMATE, true)) {
                    continue;
                }

                $consumed = $this->consumed[(int) $leaf->getId()] ?? 0;
                $finished = $lastWeek < self::WEEKS;
                $factor = $finished ? [0.8, 0.9, 1.0, 1.1, 1.2][mt_rand(0, 4)] : 1.3 + mt_rand(0, 7) / 10;
                $estimate = match (true) {
                    $finished && $factor >= 1.0 => intdiv($this->trimToWholeDays($leaf, $consumed), 4),
                    $finished => (int) floor($consumed / 4 * $factor),
                    default => (int) round($consumed / 4 * $factor),
                };
                $estimate = max(1, $estimate);
                $initial = mt_rand(1, 100) <= 30 ? max(1, (int) round($estimate * 0.8)) : $estimate;
                $leaf->setEstimateDays($estimate)->setInitialEstimateDays($initial);
            }
        }
    }

    /**
     * Takes the part of a day beyond whole days off the last entry of a leaf, so that it consumed exactly its estimate.
     *
     * @return int the quarters consumed afterwards
     */
    private function trimToWholeDays(Lot $leaf, int $consumed): int
    {
        $excess = $consumed % 4;
        $last = $this->lastEntries[(int) $leaf->getId()] ?? null;
        $trimmed = null === $last ? 0 : $last->getQuarters() - $excess;
        if (0 === $excess || null === $last || $trimmed < 1 || $trimmed > 4) {
            return $consumed;
        }

        $last->setQuarters($trimmed);

        return $consumed - $excess;
    }

    /**
     * Every leaf with time entered starts on the week of its first entry; finished leaves keep their owner alone.
     */
    private function plan(Week $firstWeek, \DateTimeImmutable $today): void
    {
        foreach ($this->leavesByTeam as $leaves) {
            foreach ($leaves as [$leaf, $firstActiveWeek]) {
                $leaf->setStartDate($this->week($firstWeek, $firstActiveWeek)->monday);
                $team = self::TEAMS[(string) $leaf->getTitle()] ?? null;
                $owner = $leaf->getOwner();
                if (null !== $team) {
                    $this->staff($leaf, $team);
                } elseif (null !== $owner) {
                    new LotMember($leaf, $owner, 100);
                }
            }
        }

        [$title, $estimateDays, $team] = self::LATE_START;
        $late = $this->leaves[$title]->setEstimateDays($estimateDays)->setStartDate(Week::containing($today)->monday->modify('-2 weeks'));
        $this->staff($late, $team);
    }

    /**
     * Besides the leaves of PROGRESS_HISTORY and PROGRESS_TO_REFRESH, the first finished leaf a day or more over its
     * estimate gets an end again at 90 %, and the first one that consumed exactly its estimate is declared complete.
     * The time entered is read before the last entries were trimmed to whole days: a leaf that consumed exactly its
     * estimate is declared on its estimate.
     */
    private function declareProgress(ObjectManager $manager, \DateTimeImmutable $today): void
    {
        $declare = function (Lot $leaf, int $daysAgo, int $percent, int $entered) use ($manager, $today): void {
            $percent = max(1, min(100, $percent));
            $entered = max(0, $entered);
            $remaining = LeafProgress::anchoredRemaining($percent, $entered, ($leaf->getEstimateDays() ?? 0) * 4);
            $author = $leaf->getOwner() ?? $this->people['thomas'];
            $manager->persist(new LotProgress($leaf, $author, $today->modify(\sprintf('-%d days', $daysAgo)), $percent, $entered, $remaining));
        };
        $consumed = fn (Lot $leaf): int => $this->consumed[(int) $leaf->getId()] ?? 0;

        foreach (self::PROGRESS_HISTORY as $title => $declarations) {
            $leaf = $this->leaves[$title];
            foreach ($declarations as [$daysAgo, $percent]) {
                $declare($leaf, $daysAgo, $percent, intdiv($consumed($leaf) * (100 - 2 * $daysAgo), 100));
            }
        }
        $toRefresh = $this->leaves[self::PROGRESS_TO_REFRESH];
        $declare($toRefresh, 14, 50, intdiv($consumed($toRefresh), 4));

        $overrun = null;
        $complete = null;
        foreach ($this->leavesByTeam as $leaves) {
            foreach ($leaves as [$leaf, , $lastWeek]) {
                $estimate = $leaf->getEstimateDays();
                if ($lastWeek >= self::WEEKS || null === $estimate || \in_array($leaf->getTitle(), self::TO_ESTIMATE, true)) {
                    continue;
                }
                $overrun ??= $consumed($leaf) >= ($estimate + 1) * 4 ? $leaf : null;
                $complete ??= intdiv($consumed($leaf), 4) === $estimate ? $leaf : null;
            }
        }
        if (null !== $overrun) {
            $declare($overrun, 0, 90, $consumed($overrun));
        }
        if (null !== $complete) {
            $declare($complete, 0, 100, (int) $complete->getEstimateDays() * 4);
        }
    }

    private function loadTimeOff(ObjectManager $manager, \DateTimeImmutable $today): void
    {
        foreach ($this->people as $person) {
            foreach (self::ENTITLEMENTS as $type => $halfDays) {
                $current = TimeOffPeriod::containing(TimeOffType::from($type), $today);
                foreach ([$current->previous(), $current] as $period) {
                    $manager->persist(new TimeOffEntitlement($person, $period->type, $period->year, $halfDays));
                }
            }
        }

        $currentWeek = Week::containing($today);
        foreach (self::TIME_OFF as [$key, $weeksAhead, $firstDay, $firstHalf, $lastDay, $lastHalf, $type, $status]) {
            $monday = $currentWeek->monday->modify(\sprintf('+%d weeks', $weeksAhead));
            $manager->persist($this->timeOff($this->people[$key], $monday->modify(\sprintf('+%d days', $firstDay - 1)), $firstHalf, $monday->modify(\sprintf('+%d days', $lastDay - 1)), $lastHalf, $type, $status));
        }
    }

    private function timeOff(User $person, \DateTimeImmutable $start, HalfDay $startHalf, \DateTimeImmutable $end, HalfDay $endHalf, TimeOffType $type, TimeOffStatus $status): TimeOffRequest
    {
        $requestedAt = $start->modify('-3 weeks')->setTime(9, 0);
        $request = new TimeOffRequest($person, $type, $start, $startHalf, $end, $endHalf, null, $requestedAt);
        $manager = $person->getManager() ?? $this->people['thomas'];
        match ($status) {
            TimeOffStatus::Pending => null,
            TimeOffStatus::Approved => $request->approve($manager, $requestedAt->modify('+1 day')),
            TimeOffStatus::Refused => $request->refuse($manager, $requestedAt->modify('+1 day'), 'Mise en production prévue cette semaine-là : décalez d\'une semaine.'),
            TimeOffStatus::Cancelled => $request->cancel($person, $requestedAt->modify('+2 days')),
        };

        return $request;
    }

    /**
     * @param list<array{string, int<10, 100>}> $team person key and share, owner first
     */
    private function staff(Lot $leaf, array $team): void
    {
        $leaf->setOwner($this->people[$team[0][0]]);
        foreach ($team as [$key, $share]) {
            new LotMember($leaf, $this->people[$key], $share);
        }
    }

    private function week(Week $firstWeek, int $index): Week
    {
        $week = $firstWeek;
        for ($i = 0; $i < $index; ++$i) {
            $week = $week->next();
        }

        return $week;
    }
}
