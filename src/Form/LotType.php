<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\LotInput;
use App\Dto\LotMemberInput;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LotType extends AbstractType
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['autocomplete' => 'off', 'data-test' => 'lot-title'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'data-test' => 'lot-description'],
            ]);

        if (true === $options['with_estimate']) {
            $builder->add('estimateDays', IntegerType::class, [
                'label' => 'Estimation (jours)',
                'required' => false,
                'help' => 'En jours entiers. Laissez vide si ce n\'est pas encore estimé.',
                'invalid_message' => 'Indiquez un nombre entier de jours.',
                'attr' => ['min' => 1, 'data-test' => 'lot-estimate'],
            ]);
        }

        $active = true === $options['with_owner'] || true === $options['with_planning'] ? $this->userRepository->findActiveForOwnerChoice() : [];

        if (true === $options['with_owner']) {
            $currentOwner = $options['current_owner'];
            $builder->add('owner', ChoiceType::class, [
                'label' => 'Responsable',
                'required' => false,
                'placeholder' => 'À désigner',
                'choices' => self::ownerChoices($active, $currentOwner instanceof User ? $currentOwner : null),
                'choice_value' => static fn (?User $user): ?int => $user?->getId(),
                'choice_label' => static fn (User $user): string => \sprintf('%s %s', $user->getFirstName(), $user->getLastName()) . ($user->isActive() ? '' : ' — désactivée'),
                'attr' => ['data-test' => 'lot-owner'],
            ]);
        }

        if (true === $options['with_planning']) {
            $builder
                ->add('startDate', DateType::class, [
                    'label' => 'Date de début',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'attr' => ['data-test' => 'lot-start-date'],
                ])
                ->add('members', CollectionType::class, [
                    'label' => 'Équipe',
                    'entry_type' => LotMemberType::class,
                    'entry_options' => ['label' => false, 'people' => [...$active, ...self::inactiveMembers($builder->getData())]],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'prototype_data' => new LotMemberInput(),
                    'delete_empty' => static fn (?LotMemberInput $member): bool => null === $member || null === $member->user,
                    'required' => false,
                    'error_bubbling' => false,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LotInput::class,
            'with_estimate' => true,
            'with_owner' => true,
            'with_planning' => false,
            'current_owner' => null,
        ]);
        $resolver->setAllowedTypes('with_estimate', 'bool');
        $resolver->setAllowedTypes('with_owner', 'bool');
        $resolver->setAllowedTypes('with_planning', 'bool');
        $resolver->setAllowedTypes('current_owner', ['null', User::class]);
    }

    /**
     * Deactivated members stay selectable on their own row so that editing the lot does not silently drop them.
     *
     * @return list<User>
     */
    private static function inactiveMembers(mixed $data): array
    {
        if (!$data instanceof LotInput) {
            return [];
        }

        $inactive = [];
        foreach ($data->members as $member) {
            if (null !== $member->user && !$member->user->isActive()) {
                $inactive[] = $member->user;
            }
        }

        return $inactive;
    }

    /**
     * A deactivated current owner stays selectable so that editing the lot does not silently drop them;
     * no other deactivated person can be chosen.
     *
     * @param list<User> $active
     *
     * @return list<User>
     */
    private static function ownerChoices(array $active, ?User $currentOwner): array
    {
        $choices = $active;
        if (null !== $currentOwner && !$currentOwner->isActive()) {
            $choices[] = $currentOwner;
        }

        return $choices;
    }
}
