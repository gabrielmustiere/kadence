<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\LotMemberInput;
use App\Entity\LotMember;
use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LotMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<User> $people */
        $people = $options['people'];

        $builder
            ->add('user', ChoiceType::class, [
                'label' => 'Personne',
                'placeholder' => 'Choisir une personne',
                'choices' => $people,
                'choice_value' => static fn (?User $user): ?int => $user?->getId(),
                'choice_label' => static fn (User $user): string => \sprintf('%s %s', $user->getFirstName(), $user->getLastName()) . ($user->isActive() ? '' : ' — désactivée'),
                'attr' => ['data-test' => 'lot-member-user'],
            ])
            ->add('share', ChoiceType::class, [
                'label' => 'Part',
                'choices' => array_combine(array_map(static fn (int $share): string => $share . ' %', LotMember::SHARES), LotMember::SHARES),
                'attr' => ['data-test' => 'lot-member-share'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotMemberInput::class,
        ]);
        $resolver->setRequired('people');
        $resolver->setAllowedTypes('people', User::class . '[]');
    }
}
