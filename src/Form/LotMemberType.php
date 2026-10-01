<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\LotMemberInput;
use App\Entity\LotMember;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class LotMemberType extends AbstractType
{
    private const array CATEGORIES = [TagCategory::TeamType, TagCategory::TechnicalSkill, TagCategory::FunctionalExperience];

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
                'choice_label' => static fn (User $user): string => \sprintf('%s %s', $user->getFirstName(), $user->getLastName()) . ($user->isActive() ? '' : ' — désactivée') . self::tagSummary($user),
                'choice_attr' => static fn (User $user): array => ['data-tags' => implode(' ', array_map(static fn (Tag $tag): int => (int) $tag->getId(), self::tags($user)))],
                'attr' => ['data-test' => 'lot-member-user', 'data-tag-filter-target' => 'person'],
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

    /**
     * « — Back · Symfony, Docker · Paie »: the team type, then the technical skills, then the functional experiences.
     */
    private static function tagSummary(User $user): string
    {
        $groups = [];
        foreach (self::CATEGORIES as $category) {
            $labels = array_map(static fn (Tag $tag): string => $tag->getLabel(), $user->tagsOf($category));
            if ([] !== $labels) {
                $groups[] = implode(', ', $labels);
            }
        }

        return [] === $groups ? '' : ' — ' . implode(' · ', $groups);
    }

    /**
     * @return list<Tag>
     */
    private static function tags(User $user): array
    {
        return array_merge(...array_map(static fn (TagCategory $category): array => $user->tagsOf($category), self::CATEGORIES));
    }
}
