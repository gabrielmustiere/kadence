<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dto\TimeOffRequestInput;
use App\Entity\User;
use App\Enum\Type\TimeOffStatus;
use App\Form\TimeOffRequestType;
use App\Model\TimeOff\TimeOffPreview;
use App\Service\TimeOffBook;
use App\Service\TimeOffManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The request form of the signed-in person, with the days it counts and the balance it leaves as it is filled in.
 */
#[AsLiveComponent]
final class TimeOffRequestForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    public function __construct(
        private readonly TimeOffBook $timeOffBook,
        private readonly TimeOffManager $timeOffManager,
    ) {
    }

    public function getPreview(): ?TimeOffPreview
    {
        $input = $this->getForm()->getData();
        if (!$input instanceof TimeOffRequestInput || null === $input->type || null === $input->startDay || null === $input->startHalf || null === $input->endDay || null === $input->endHalf || $input->endDay < $input->startDay) {
            return null;
        }

        return $this->timeOffBook->preview($input->requester, $input->type, $input->startDay, $input->startHalf, $input->endDay, $input->endHalf);
    }

    #[LiveAction]
    public function save(): Response
    {
        $this->submitForm();
        $input = $this->getForm()->getData();
        \assert($input instanceof TimeOffRequestInput);

        $request = $this->timeOffManager->request($input);
        $this->addFlash('success', TimeOffStatus::Approved === $request->getStatus()
            ? 'Votre congé est posé : personne d\'autre ne pouvait le décider.'
            : 'Votre demande est envoyée : ses jours sont réservés dans votre semaine en attendant la décision.');

        return $this->redirectToRoute('app_time_off_index');
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(TimeOffRequestType::class, new TimeOffRequestInput($this->user()));
    }

    private function user(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Les congés sont réservés aux personnes connectées.');
        }

        return $user;
    }
}
