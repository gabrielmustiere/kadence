<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\HolidayAdditionInput;
use App\Entity\HolidayAdjustment;
use App\Enum\Type\HolidayCalendar;
use App\Exception\HolidayAdjustmentRefusedException;
use App\Form\HolidayAdditionType;
use App\Service\HolidayManager;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/jours-feries')]
#[IsGranted('ROLE_DIRECTION')]
final class HolidayController extends AbstractController
{
    public function __construct(
        private readonly HolidayManager $holidayManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_holiday_index', methods: ['GET', 'POST'])]
    #[Route('/{year}', name: 'app_holiday_year', requirements: ['year' => '[1-9]\d{3}'], methods: ['GET', 'POST'])]
    public function year(Request $request, ?int $year = null): Response
    {
        $year ??= (int) $this->clock->now()->format('Y');
        $input = new HolidayAdditionInput();
        $form = $this->createForm(HolidayAdditionType::class, $input, [
            'action' => $this->generateUrl('app_holiday_year', ['year' => $year]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $adjustment = $this->holidayManager->add(
                $input->calendar ?? throw new \LogicException('A validated holiday addition has a calendar.'),
                $input->day ?? throw new \LogicException('A validated holiday addition has a day.'),
                self::label($input->label),
            );
            $this->addFlash('success', \sprintf('Le %s est férié dans le calendrier %s.', $adjustment->getDay()->format('d/m/Y'), $adjustment->getCalendar()->label()));

            return $this->redirectToRoute('app_holiday_year', ['year' => $adjustment->getDay()->format('Y')]);
        }

        return $this->render('holiday/index.html.twig', [
            'year' => $year,
            'form' => $form,
            'calendars' => array_map(
                fn (HolidayCalendar $calendar): array => ['calendar' => $calendar, 'lines' => $this->holidayManager->yearOf($calendar, $year)],
                HolidayCalendar::cases(),
            ),
        ]);
    }

    #[Route('/retirer', name: 'app_holiday_remove', methods: ['POST'])]
    public function remove(Request $request): Response
    {
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('holiday-remove', $payload->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $calendar = HolidayCalendar::tryFrom($payload->getString('calendar')) ?? throw $this->createNotFoundException('Ce calendrier n\'existe pas.');
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $payload->getString('day'));
        if (false === $day) {
            throw $this->createNotFoundException('Ce jour n\'existe pas.');
        }

        try {
            $this->holidayManager->remove($calendar, $day);
            $this->addFlash('success', \sprintf('Le %s est retiré du calendrier %s : on peut de nouveau y saisir.', $day->format('d/m/Y'), $calendar->label()));
        } catch (HolidayAdjustmentRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_holiday_year', ['year' => $day->format('Y')]);
    }

    #[Route('/ajustements/{id}/annuler', name: 'app_holiday_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Request $request, HolidayAdjustment $adjustment): Response
    {
        if (!$this->isCsrfTokenValid('holiday-adjustment-' . $adjustment->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $day = $adjustment->getDay();
        $calendar = $adjustment->getCalendar();
        $this->holidayManager->cancel($adjustment);
        $this->addFlash('success', \sprintf('L\'ajustement du %s est annulé : le calendrier %s suit de nouveau la loi ce jour-là.', $day->format('d/m/Y'), $calendar->label()));

        return $this->redirectToRoute('app_holiday_year', ['year' => $day->format('Y')]);
    }

    /**
     * @return non-empty-string
     */
    private static function label(?string $label): string
    {
        if (null === $label || '' === $label) {
            throw new \LogicException('A validated holiday addition has a label.');
        }

        return $label;
    }
}
