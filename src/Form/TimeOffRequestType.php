<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\TimeOffRequestInput;
use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TimeOffRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => TimeOffType::class,
                'label' => 'Type',
                'choice_label' => static fn (TimeOffType $type): string => $type->label(),
                'attr' => ['data-test' => 'time-off-type'],
            ])
            ->add('startDay', DateType::class, [
                'label' => 'Du',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-test' => 'time-off-start'],
            ])
            ->add('startHalf', EnumType::class, self::halfDayOptions('time-off-start-half'))
            ->add('endDay', DateType::class, [
                'label' => 'Au',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-test' => 'time-off-end'],
            ])
            ->add('endHalf', EnumType::class, self::halfDayOptions('time-off-end-half'))
            ->add('comment', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'help' => 'Facultatif, visible de votre manager et de la direction.',
                'attr' => ['rows' => 2, 'maxlength' => 500, 'data-test' => 'time-off-comment'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeOffRequestInput::class,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function halfDayOptions(string $test): array
    {
        return [
            'class' => HalfDay::class,
            'label' => false,
            'expanded' => true,
            'choice_label' => static fn (HalfDay $half): string => ucfirst($half->label()),
            'choice_attr' => static fn (HalfDay $half): array => ['data-test' => $test . '-' . $half->value],
            'attr' => ['class' => 'flex flex-wrap gap-x-5 gap-y-2'],
        ];
    }
}
