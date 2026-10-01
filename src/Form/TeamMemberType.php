<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\TeamMemberInput;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TeamMemberType extends AbstractType
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

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
            ->add('holidayCalendar', EnumType::class, [
                'class' => HolidayCalendar::class,
                'label' => 'Jours fériés',
                'help' => 'Le calendrier des jours fériés que suit la personne, et non sa nationalité.',
                'expanded' => true,
                'choice_label' => static fn (HolidayCalendar $calendar): string => $calendar->label(),
                'choice_attr' => static fn (HolidayCalendar $calendar): array => ['data-test' => 'member-holiday-calendar-' . $calendar->value],
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

        $this
            ->addTags($builder, 'technicalSkills', TagCategory::TechnicalSkill)
            ->addNewTags($builder, 'newTechnicalSkills', 'member-new-technical-skills', 'Nouvelles compétences techniques', 'Séparées par des virgules, par exemple « Kubernetes, Go ». Elles rejoignent la liste des tags.')
            ->addTags($builder, 'functionalExperiences', TagCategory::FunctionalExperience)
            ->addNewTags($builder, 'newFunctionalExperiences', 'member-new-functional-experiences', 'Nouvelles expériences fonctionnelles', 'Séparées par des virgules, par exemple « Paie, Facturation ». Elles rejoignent la liste des tags.')
            ->addTags($builder, 'teamType', TagCategory::TeamType)
            ->addNewTags($builder, 'newTeamType', 'member-new-team-type', 'Nouveau type d\'équipe', 'Un seul, s\'il manque à la liste ci-dessus. Il la rejoint.');

        $memberId = $builder->getData() instanceof TeamMemberInput ? $builder->getData()->id : null;
        $builder->add('manager', ChoiceType::class, [
            'label' => 'Manager',
            'help' => 'La personne à qui elle rapporte. Purement informatif : cela n\'ouvre aucun droit.',
            'required' => false,
            'placeholder' => 'Aucun',
            'choices' => array_values(array_filter(
                $this->userRepository->findActiveWithTags(),
                static fn (User $user): bool => null === $memberId || $user->getId() !== $memberId,
            )),
            'choice_value' => static fn (?User $user): ?int => $user?->getId(),
            'choice_label' => static fn (User $user): string => \sprintf('%s %s', $user->getFirstName(), $user->getLastName()),
            'attr' => ['data-test' => 'member-manager'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamMemberInput::class,
            'error_mapping' => ['teamTypeUnambiguous' => 'newTeamType'],
        ]);
    }

    private function addTags(FormBuilderInterface $builder, string $field, TagCategory $category): self
    {
        $builder->add($field, ChoiceType::class, [
            'label' => $category->allowsMany() ? $category->pluralLabel() : $category->label(),
            'choices' => $this->tagRepository->findByCategory($category),
            'choice_value' => static fn (?Tag $tag): ?int => $tag?->getId(),
            'choice_label' => static fn (Tag $tag): string => $tag->getLabel(),
            'choice_attr' => static fn (Tag $tag): array => ['data-test' => 'member-tag', 'data-label' => $tag->getLabel()],
            'multiple' => $category->allowsMany(),
            'expanded' => true,
            'required' => false,
            'placeholder' => $category->allowsMany() ? null : 'Aucun',
            'attr' => ['class' => 'flex flex-wrap gap-x-5 gap-y-2', 'data-test' => 'member-tags-' . $category->value],
        ]);

        return $this;
    }

    private function addNewTags(FormBuilderInterface $builder, string $field, string $dataTest, string $label, string $help): self
    {
        $builder->add($field, TextType::class, [
            'label' => $label,
            'required' => false,
            'help' => $help,
            'attr' => ['autocomplete' => 'off', 'data-test' => $dataTest],
        ]);

        return $this;
    }
}
