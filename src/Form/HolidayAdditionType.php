<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\HolidayAdditionInput;
use App\Enum\Type\HolidayCalendar;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HolidayAdditionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('calendar', EnumType::class, [
                'class' => HolidayCalendar::class,
                'label' => 'Calendrier',
                'expanded' => true,
                'choice_label' => static fn (HolidayCalendar $calendar): string => $calendar->label(),
                'choice_attr' => static fn (HolidayCalendar $calendar): array => ['data-test' => 'holiday-calendar-' . $calendar->value],
                'attr' => ['class' => 'flex flex-wrap gap-x-5 gap-y-2'],
            ])
            ->add('day', DateType::class, [
                'label' => 'Jour',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-test' => 'holiday-day'],
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
                'help' => 'Affiché dans la grille de saisie, par exemple « Remplacement du 1er novembre ».',
                'attr' => ['autocomplete' => 'off', 'maxlength' => 100, 'data-test' => 'holiday-label'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HolidayAdditionInput::class,
        ]);
    }
}
