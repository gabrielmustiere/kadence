<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Lot;
use App\Entity\User;
use App\Exception\TimeEntryRefusedException;
use App\Model\Timesheet\TimesheetRow;
use App\Model\Timesheet\WeekGrid;
use App\Model\Week;
use App\Repository\LotRepository;
use App\Service\LeafFinder;
use App\Service\TimesheetBuilder;
use App\Service\TimesheetManager;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The week grid of the signed-in person: every read and write goes through the security user, never through a prop.
 */
#[AsLiveComponent]
final class Timesheet
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $week = '';

    /** @var list<int> leaves added through the search: kept until the component is mounted again */
    #[LiveProp]
    public array $addedLotIds = [];

    #[LiveProp(writable: true)]
    public string $query = '';

    /** The day shown on small screens, 0 for Monday. */
    #[LiveProp]
    public int $focusedDay = 0;

    public ?string $error = null;

    private ?WeekGrid $grid = null;

    public function __construct(
        private readonly TimesheetBuilder $timesheetBuilder,
        private readonly TimesheetManager $timesheetManager,
        private readonly LeafFinder $leafFinder,
        private readonly LotRepository $lotRepository,
        private readonly Security $security,
        private readonly ClockInterface $clock,
    ) {
    }

    public function mount(string $week): void
    {
        $this->week = $week;
        $today = $this->clock->now();
        $this->focusedDay = $this->weekModel()->contains($today) ? (int) $today->format('N') - 1 : 0;
    }

    public function getGrid(): WeekGrid
    {
        return $this->grid ??= $this->timesheetBuilder->build($this->user(), $this->weekModel(), $this->addedLotIds);
    }

    /**
     * @return list<Lot>
     */
    public function getSearchResults(): array
    {
        $shownIds = array_map(static fn (TimesheetRow $row): int => (int) $row->lot->getId(), $this->getGrid()->rows);

        return $this->leafFinder->search($this->query, $shownIds);
    }

    public function isSearchTooShort(): bool
    {
        return !$this->leafFinder->isSearchable($this->query);
    }

    #[LiveAction]
    public function record(#[LiveArg] int $lot, #[LiveArg] string $day, #[LiveArg] int $quarters): void
    {
        $target = $this->lotRepository->find($lot) ?? throw new NotFoundHttpException('Cette feuille n\'existe plus.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $day);
        if (false === $date || !$this->weekModel()->contains($date)) {
            throw new NotFoundHttpException('Ce jour n\'appartient pas à la semaine affichée.');
        }

        try {
            $this->timesheetManager->record($this->user(), $target, $date, $quarters);
        } catch (TimeEntryRefusedException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    #[LiveAction]
    public function addLot(#[LiveArg] int $lot): void
    {
        $id = (int) $this->leaf($lot)->getId();
        if (!\in_array($id, $this->addedLotIds, true)) {
            $this->addedLotIds[] = $id;
        }
        $this->query = '';
    }

    #[LiveAction]
    public function showDay(#[LiveArg] int $index): void
    {
        $this->focusedDay = max(0, min(4, $index));
    }

    private function leaf(int $id): Lot
    {
        return $this->lotRepository->findLeavesByIds([$id])[0] ?? throw new NotFoundHttpException('Cette feuille n\'existe pas ou est découpée en sous-lots.');
    }

    private function weekModel(): Week
    {
        try {
            return Week::fromIso($this->week);
        } catch (\InvalidArgumentException $exception) {
            throw new NotFoundHttpException($exception->getMessage(), $exception);
        }
    }

    private function user(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('La saisie est réservée aux personnes connectées.');
        }

        return $user;
    }
}
