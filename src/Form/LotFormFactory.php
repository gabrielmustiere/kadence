<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\LotInput;
use App\Entity\Lot;
use App\Entity\Project;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The lot form of each page, built alike by the controller that saves it and by the live component that renders it
 * as it is filled in.
 */
final readonly class LotFormFactory
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * @param array<string, mixed> $options more options of LotType
     */
    public function forNewLot(Project $project, array $options = []): FormInterface
    {
        return $this->formFactory->create(LotType::class, LotInput::forLotOf($project), ['with_planning' => true, ...$options]);
    }

    /**
     * @param array<string, mixed> $options more options of LotType
     */
    public function forNewSubLot(Lot $parent, array $options = []): FormInterface
    {
        $input = LotInput::forSubLotOf($parent);

        return $this->formFactory->create(LotType::class, $input, ['current_owner' => $input->owner, 'with_planning' => true, ...$options]);
    }

    /**
     * @param array<string, mixed> $options more options of LotType
     */
    public function forLot(Lot $lot, array $options = []): FormInterface
    {
        $lead = $this->authorizationChecker->isGranted('ROLE_LEAD');

        return $this->formFactory->create(LotType::class, LotInput::fromLot($lot), [
            'with_estimate' => $lot->isLeaf(),
            'with_owner' => $lot->isLeaf() && $lead,
            'with_planning' => $lot->isLeaf() && $lead,
            'current_owner' => $lot->getOwner(),
            ...$options,
        ]);
    }
}
