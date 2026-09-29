<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\TeamMemberInput;
use App\Enum\Type\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TeamMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['autocomplete' => 'off', 'data-test' => 'member-first-name'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr' => ['autocomplete' => 'off', 'data-test' => 'member-last-name'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-mail',
                'help' => 'Sert d\'identifiant de connexion.',
                'attr' => ['autocomplete' => 'off', 'data-test' => 'member-email'],
            ])
            ->add('role', EnumType::class, [
                'class' => Role::class,
                'label' => 'Rôle',
                'expanded' => true,
                'choice_label' => static fn (Role $role): string => $role->label(),
                'choice_attr' => static fn (Role $role): array => ['data-test' => 'member-role-' . $role->value],
            ])
            ->add('weeklyMaxDays', NumberType::class, [
                'label' => 'Maximum de saisie par semaine (jours)',
                'help' => '5 pour un temps plein, 4,5 pour un 90 %… au quart de journée près.',
                'html5' => true,
                'scale' => 2,
                'invalid_message' => 'Indiquez un nombre de jours.',
                'attr' => ['min' => 0.25, 'max' => 5, 'step' => 0.25, 'data-test' => 'member-weekly-max'],
            ])
            ->add('weeklyMaxFrom', DateType::class, [
                'label' => 'À partir de la semaine du',
                'help' => 'La valeur s\'applique dès le lundi de la semaine qui contient cette date.',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['data-test' => 'member-weekly-max-from'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamMemberInput::class,
        ]);
    }
}
