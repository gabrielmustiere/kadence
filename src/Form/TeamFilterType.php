<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\TeamListFilter;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * The filter of the team list, sent by GET with bare parameter names (?competence=…&manager=…) so that its URL can be
 * shared.
 */
final class TeamFilterType extends AbstractType
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this
            ->addTag($builder, 'technicalSkill', TagCategory::TechnicalSkill, 'Toutes')
            ->addTag($builder, 'functionalExperience', TagCategory::FunctionalExperience, 'Toutes')
            ->addTag($builder, 'teamType', TagCategory::TeamType, 'Tous');

        $builder->add('manager', ChoiceType::class, [
            'label' => 'Manager',
            'required' => false,
            'placeholder' => 'Tous',
            'choices' => $this->userRepository->findManagers(),
            'choice_value' => static fn (?User $user): ?int => $user?->getId(),
            'choice_label' => static fn (User $user): string => \sprintf('%s %s', $user->getFirstName(), $user->getLastName()),
            'attr' => ['data-test' => 'team-filter-manager'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TeamListFilter::class,
            'method' => 'GET',
            'csrf_protection' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return '';
    }

    private function addTag(FormBuilderInterface $builder, string $property, TagCategory $category, string $placeholder): self
    {
        $builder->add($category->value, ChoiceType::class, [
            'label' => $category->label(),
            'property_path' => $property,
            'required' => false,
            'placeholder' => $placeholder,
            'choices' => $this->tagRepository->findByCategory($category),
            'choice_value' => static fn (?Tag $tag): ?int => $tag?->getId(),
            'choice_label' => static fn (Tag $tag): string => $tag->getLabel(),
            'attr' => ['data-test' => 'team-filter-' . $category->value],
        ]);

        return $this;
    }
}
