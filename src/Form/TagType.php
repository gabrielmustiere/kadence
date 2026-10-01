<?php

declare(strict_types=1);

namespace App\Form;

use App\Dto\TagInput;
use App\Enum\Type\TagCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TagType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (true === $options['with_category']) {
            $builder->add('category', EnumType::class, [
                'class' => TagCategory::class,
                'label' => 'Catégorie',
                'expanded' => true,
                'choice_label' => static fn (TagCategory $category): string => $category->label(),
                'choice_attr' => static fn (TagCategory $category): array => ['data-test' => 'tag-category-' . $category->value],
                'attr' => ['class' => 'flex flex-wrap gap-x-5 gap-y-2'],
            ]);
        }

        $builder->add('label', TextType::class, [
            'label' => 'Libellé',
            'attr' => ['autocomplete' => 'off', 'maxlength' => 60, 'data-test' => 'tag-label'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TagInput::class,
            'with_category' => true,
        ]);
        $resolver->setAllowedTypes('with_category', 'bool');
    }
}
