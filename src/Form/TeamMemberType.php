<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\TeamMemberInput;
use App\Enum\Type\Role;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamMemberInput::class,
        ]);
    }
}
