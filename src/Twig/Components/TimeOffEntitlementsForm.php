<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dto\TimeOffEntitlementsInput;
use App\Entity\User;
use App\Enum\Type\TimeOffType;
use App\Form\TimeOffEntitlementsType;
use App\Model\TimeOff\Balance;
use App\Model\TimeOff\TimeOffPeriod;
use App\Repository\UserRepository;
use App\Service\TimeOffEntitlementManager;
use App\Service\TimeOffOverview;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * The entitlements of a period, whose balances follow what the direction types before saving them in place.
 */
#[AsLiveComponent]
final class TimeOffEntitlementsForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;
    use NotifiesInPlace;

    #[LiveProp]
    public string $type = 'cp';

    #[LiveProp]
    public int $year = 0;

    /** @var list<User>|null */
    private ?array $people = null;

    /** @var array<int, Balance>|null */
    private ?array $balances = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TimeOffOverview $timeOffOverview,
        private readonly TimeOffEntitlementManager $timeOffEntitlementManager,
    ) {
    }

    #[PostMount(priority: 10)]
    #[PostHydrate]
    public function denyToAllButTheDirection(): void
    {
        $this->denyAccessUnlessGranted('ROLE_DIRECTION');
    }

    public function getPeriod(): TimeOffPeriod
    {
        return TimeOffPeriod::of(TimeOffType::from($this->type), $this->year);
    }

    /**
     * @return list<User>
     */
    public function getPeople(): array
    {
        return $this->people ??= $this->userRepository->findActiveWithManager();
    }

    /**
     * The balance of each person, by user id, as if the entitlements typed in were saved.
     *
     * @return array<int, Balance>
     */
    public function getBalances(): array
    {
        $balances = [];
        foreach ($this->savedBalances() as $userId => $balance) {
            $balances[$userId] = $balance->withEntitlement($this->halfDaysTypedFor($userId));
        }

        return $balances;
    }

    public function isDirty(): bool
    {
        return array_any($this->savedBalances(), fn (Balance $balance, int $userId): bool => $this->halfDaysTypedFor($userId) !== $balance->entitledHalfDays);
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();
        $input = $this->getForm()->getData();
        \assert($input instanceof TimeOffEntitlementsInput);

        $period = $this->getPeriod();
        $this->timeOffEntitlementManager->declare($period, $input->halfDaysOf($this->getPeople()));
        $this->notify('success', \sprintf('Les droits %s %s sont enregistrés.', $period->type->label(), $period->label()));

        $this->balances = null;
        $this->resetForm();
        $this->isValidated = false;
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(TimeOffEntitlementsType::class, TimeOffEntitlementsInput::fromBalances($this->savedBalances()));
    }

    private function halfDaysTypedFor(int $userId): int
    {
        $input = $this->getForm()->getData();
        \assert($input instanceof TimeOffEntitlementsInput);

        return (int) round(($input->days[$userId] ?? 0) * 2);
    }

    /**
     * @return array<int, Balance>
     */
    private function savedBalances(): array
    {
        return $this->balances ??= $this->timeOffOverview->balancesOver($this->getPeriod(), $this->getPeople());
    }
}
