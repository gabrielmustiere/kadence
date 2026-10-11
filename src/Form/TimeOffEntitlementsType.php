<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\TimeOffEntitlementsInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One field of entitlements per person, keyed by their user id.
 */
final class TimeOffEntitlementsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('days', CollectionType::class, [
            'label' => false,
            'entry_type' => NumberType::class,
            'entry_options' => [
                'label' => false,
                'html5' => true,
                'scale' => 1,
                'attr' => ['step' => 0.5, 'min' => 0, 'class' => 'w-24 text-right tabular-nums', 'data-test' => 'time-off-entitlement'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeOffEntitlementsInput::class,
        ]);
    }
}
