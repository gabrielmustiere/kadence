<?php

declare(strict_types=1);

namespace DataFixtures;

use App\Entity\HolidayAdjustment;
use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Entity\WeeklyMax;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Model\Week;
use App\Service\HolidayManager;
use App\Service\LegalHolidays;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function Symfony\Component\String\u;

/**
 * A fake software company for the dev environment: four projects split into lots and sub-lots, a team with part-time
 * people and two people on the Belgian holiday calendar, and six months of time entries up to yesterday, none on a
 * holiday. Test accounts get no entry in the current week, so that the end-to-end scenarios find it empty.
 * Deterministic: the same seed always gives the same company, relative to today.
 */
#[When(env: 'dev')]
final class DemoCompanyFixtures extends Fixture implements DependentFixtureInterface
{
    private const int WEEKS = 26;
    private const int SEED = 2026;

    /** People on the Belgian holiday calendar. */
    private const array BELGIANS = ['hugo', 'ines'];

    /** Leaves left « à estimer » although time is entered on them. */
    private const array TO_ESTIMATE = ['Notifications'];

    /**
     * Person key => first name, last name, role, team, weekly quarters, day off (ISO day) or null, first week, weekly
     * quarters after a change and the week of that change (or null).
     *
     * @return array<string, array{non-empty-string, non-empty-string, Role, string, int<1, 20>, int|null, int, array{int<1, 20>, int}|null}>
     */
    private static function people(): array
    {
        return [
            'helene' => ['Hélène', 'Garnier', Role::Direction, '', 20, null, 0, null],
            'julien' => ['Julien', 'Moreau', Role::Lead, 'atlas', 20, null, 0, null],
            'camille' => ['Camille', 'Roux', Role::Prod, 'atlas', 20, null, 0, null],
            'thomas' => ['Thomas', 'Girard', Role::Prod, 'atlas', 20, null, 0, null],
            'lea' => ['Léa', 'Fontaine', Role::Prod, 'atlas', 16, 3, 0, null],
            'sophie' => ['Sophie', 'Lefèvre', Role::Lead, 'nova', 20, null, 0, null],
            'hugo' => ['Hugo', 'Chevalier', Role::Prod, 'nova', 20, null, 0, null],
            'emma' => ['Emma', 'Rousseau', Role::Prod, 'nova', 18, null, 0, null],
            'chloe' => ['Chloé', 'Mercier', Role::Prod, 'nova', 20, null, 0, null],
            'karim' => ['Karim', 'Benali', Role::Lead, 'orion', 20, null, 0, null],
            'nathan' => ['Nathan', 'Blanc', Role::Prod, 'orion', 20, null, 0, null],
            'lucas' => ['Lucas', 'Faure', Role::Prod, 'orion', 20, null, 0, [16, 13]],
            'ines' => ['Inès', 'Lambert', Role::Prod, 'orion', 20, null, 0, null],
            'maxime' => ['Maxime', 'Dupuis', Role::Prod, 'vega', 20, null, 18, null],
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
            'atlas' => ['Atlas — Plateforme de paie', 'Nouveau moteur de paie et écrans de gestion des variables.', [
                'Cadrage' => [0, 2],
                'Moteur de calcul' => ['subLots' => ['Cotisations' => [2, 14], 'Absences' => [6, 16], 'Régularisations' => [14, 26]]],
                'Écrans' => ['subLots' => ['Saisie des variables' => [10, 20], 'Bulletins' => [16, 26]]],
                'Recette' => [21, 26],
            ]],
            'nova' => ['Nova — Application mobile', 'Application mobile des salariés, utilisable hors ligne.', [
                'Maquettes' => [0, 4],
                'Authentification' => [3, 7],
                'Synchronisation hors ligne' => ['subLots' => ['File d\'attente' => [6, 15], 'Résolution des conflits' => [12, 22]]],
                'Notifications' => [18, 26],
                'Publication sur les stores' => [23, 26],
            ]],
            'orion' => ['Orion — Refonte de l\'API', 'API publique versionnée pour les intégrateurs.', [
                'Audit de l\'existant' => [0, 3],
                'Versionnage' => [3, 9],
                'Endpoints' => ['subLots' => ['Clients' => [8, 15], 'Factures' => [13, 21], 'Webhooks' => [18, 26]]],
                'Documentation' => [20, 26],
                'Migration des clients' => null,
            ]],
            'vega' => ['Vega — Portail partenaires', 'Portail documentaire et tableau de bord pour les partenaires.', [
                'Découverte' => [0, 3],
                'Espace documentaire' => ['subLots' => ['Arborescence' => [3, 12], 'Recherche' => [10, 20]]],
                'Tableau de bord partenaire' => [17, 26],
                'Accès et droits' => [8, 14],
            ]],
        ];
    }

    /** @var array<string, User> */
    private array $people = [];

    /** @var array<string, list<array{Lot, int, int}>> leaves by team, with their active weeks */
    private array $leavesByTeam = [];

    /** @var array<int, int> quarters entered by lot id */
    private array $consumed = [];

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
        $this->loadProjects($manager);
        $this->loadReplacementDays($manager, $firstWeek, $today);
        $manager->flush();

        $support = $this->getReference(ProjectFixtures::SUPPORT, Lot::class);
        foreach ($this->people as $key => $person) {
            $this->loadTimeEntries($manager, $key, $person, $firstWeek, $today, $support);
        }
        $this->estimate();
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class, ProjectFixtures::class];
    }

    private function loadPeople(ObjectManager $manager, Week $firstWeek): void
    {
        foreach (self::people() as $key => [$firstName, $lastName, $role, , $quarters, , , $change]) {
            $email = \sprintf('%s.%s@example.com', $key, u($lastName)->ascii()->lower()->toString());
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

        $this->people['louis'] = $this->getReference(AppFixtures::LEAD, User::class);
        $this->people['paula'] = $this->getReference(AppFixtures::PROD, User::class);
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
            if ($team === (self::people()[$key][3] ?? 'vega')) {
                $members[] = $person;
            }
        }

        return $members;
    }

    private function loadTimeEntries(ObjectManager $manager, string $key, User $person, Week $firstWeek, \DateTimeImmutable $today, Lot $support): void
    {
        [, , $role, $team, $quarters, $dayOff, $firstActiveWeek, $change] = self::people()[$key] ?? ['', '', Role::Prod, 'vega', 20, null, 0, null];
        if (Role::Direction === $role) {
            return;
        }

        $isTestAccount = \in_array($key, ['louis', 'paula'], true);
        $currentMonday = Week::containing($today)->monday;
        $holidays = [mt_rand(4, 10), mt_rand(15, 22)];

        for ($weekIndex = $firstActiveWeek; $weekIndex <= self::WEEKS; ++$weekIndex) {
            $week = $this->week($firstWeek, $weekIndex);
            if (\in_array($weekIndex, $holidays, true) || ($isTestAccount && $week->monday >= $currentMonday)) {
                continue;
            }

            $weeklyQuarters = null !== $change && $weekIndex >= $change[1] ? $change[0] : $quarters;
            $leaves = $this->activeLeaves($team, $weekIndex);
            $publicHolidays = $this->holidayManager->holidaysOf($person, $week);
            foreach ($week->days() as $day) {
                if ($day >= $today) {
                    break;
                }
                if (isset($publicHolidays[$day->format('Y-m-d')])) {
                    continue;
                }

                $capacity = $this->dayCapacity((int) $day->format('N'), $weeklyQuarters, $dayOff);
                foreach ($this->split($capacity, $leaves, $support) as [$leaf, $dayQuarters]) {
                    $manager->persist(new TimeEntry($person, $leaf, $day, $dayQuarters));
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
    private function split(int $capacity, array $leaves, Lot $support): array
    {
        if (0 === $capacity) {
            return [];
        }

        $parts = [];
        if ($capacity >= 2 && mt_rand(1, 100) <= 18) {
            $parts[] = [$support, 1];
            --$capacity;
        }
        if ([] === $leaves) {
            $parts[] = [$support, $capacity];

            return self::merge($parts);
        }

        $main = $leaves[mt_rand(0, \count($leaves) - 1)];
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
     * Finished leaves are estimated around what they consumed (some overran), running ones above it; the initial
     * estimate sometimes differs, as if it had been revised since.
     */
    private function estimate(): void
    {
        foreach ($this->leavesByTeam as $leaves) {
            foreach ($leaves as [$leaf, , $lastWeek]) {
                if (\in_array($leaf->getTitle(), self::TO_ESTIMATE, true)) {
                    continue;
                }

                $consumedDays = ($this->consumed[(int) $leaf->getId()] ?? 0) / 4;
                $factor = $lastWeek < self::WEEKS ? [0.8, 0.9, 1.0, 1.1, 1.2][mt_rand(0, 4)] : 1.3 + mt_rand(0, 7) / 10;
                $estimate = max(1, (int) round($consumedDays * $factor));
                $initial = mt_rand(1, 100) <= 30 ? max(1, (int) round($estimate * 0.8)) : $estimate;
                $leaf->setEstimateDays($estimate)->setInitialEstimateDays($initial);
            }
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
