<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\ChangePasswordInput;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (true === $options['require_current_password']) {
            $builder->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => ['autocomplete' => 'current-password', 'data-test' => 'current-password'],
            ]);
        }

        $builder->add('newPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'first_options' => [
                'label' => 'Nouveau mot de passe',
                'help' => 'Au moins 12 caractères. Une phrase de plusieurs mots fait un bon mot de passe.',
                'attr' => ['autocomplete' => 'new-password', 'data-test' => 'new-password'],
            ],
            'second_options' => [
                'label' => 'Confirmer le nouveau mot de passe',
                'attr' => ['autocomplete' => 'new-password', 'data-test' => 'new-password-confirm'],
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ChangePasswordInput::class,
            'require_current_password' => true,
            'validation_groups' => static fn (Options $options): array => true === $options['require_current_password']
                ? ['Default', ChangePasswordInput::VOLUNTARY]
                : ['Default'],
        ]);
        $resolver->setAllowedTypes('require_current_password', 'bool');
    }
}
