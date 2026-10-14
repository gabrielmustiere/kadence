<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dto\LotInput;
use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use App\Form\LotFormFactory;
use App\Security\Voter\LotVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\Attribute\PreReRender;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * The form of a lot, checked as it is filled in: the team shows with its owner, the people offered follow the tag
 * filter, and the rules of the lot speak up on the field they concern. Saving still posts the form to the page, whose
 * controller passes in the form it rendered.
 */
#[AsLiveComponent]
final class LotForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /** The project a new lot is added to. */
    #[LiveProp]
    public ?Project $project = null;

    /** The lot a new sub-lot is added to. */
    #[LiveProp]
    public ?Lot $parent = null;

    /** The lot edited, under another field name than the form's. */
    #[LiveProp(fieldName: 'edited')]
    public ?Lot $lot = null;

    #[LiveProp]
    public string $submitLabel = 'Enregistrer';

    #[LiveProp]
    public bool $addAnother = false;

    #[LiveProp]
    public string $cancelPath = '';

    /** @var array<string, string> the tag id each team member must carry, '' for any, by category value */
    #[LiveProp(writable: true)]
    public array $tagFilter = [];

    public function __construct(
        private readonly LotFormFactory $lotFormFactory,
    ) {
    }

    #[PostMount(priority: 10)]
    #[PostHydrate]
    public function denyToThoseWhoCannotEditIt(): void
    {
        if (null !== $this->lot) {
            $this->denyAccessUnlessGranted(LotVoter::EDIT, $this->lot);

            return;
        }
        $this->denyAccessUnlessGranted('ROLE_LEAD');
    }

    /**
     * The browser only binds a model to a key the prop already has.
     */
    #[PostMount]
    public function startWithoutTagFilter(): void
    {
        foreach (TagCategory::cases() as $category) {
            $this->tagFilter[$category->value] ??= '';
        }
    }

    /**
     * The team rules (capacity, a person once) fall on the whole team: they speak up as soon as the team, the start
     * date or the owner is touched, not only once the whole form is.
     */
    #[PreReRender(priority: 10)]
    public function checkTheTeamOnceItIsTouched(): void
    {
        $name = $this->getFormName();
        foreach ($this->validatedFields as $field) {
            if (\in_array($field, [$name . '.startDate', $name . '.owner'], true) || (\is_string($field) && str_starts_with($field, $name . '.members.'))) {
                $this->validatedFields[] = $name . '.members';

                return;
            }
        }
    }

    /**
     * The team as it will be saved: the rows with a person, plus the owner.
     *
     * @return list<array{person: User, share: int<10, 100>, owner: bool}>
     */
    public function getTeam(): array
    {
        $input = $this->getFormView()->vars['data'] ?? null;
        if (!$input instanceof LotInput) {
            return [];
        }

        return array_map(static fn (array $member): array => ['person' => $member[0], 'share' => $member[1], 'owner' => $member[0] === $input->owner], $input->effectiveMembers());
    }

    /**
     * @return list<string>|null the ids of the people carrying every tag of the filter, null without filter
     */
    public function getOfferedPeople(): ?array
    {
        $required = array_map(intval(...), array_values(array_filter($this->tagFilter, static fn (string $id): bool => '' !== $id)));
        if ([] === $required) {
            return null;
        }

        $offered = array_filter($this->personChoices(), static fn (ChoiceView $choice): bool => $choice->data instanceof User && self::carriesAll($choice->data, $required));

        return array_values(array_map(static fn (ChoiceView $choice): string => $choice->value, $offered));
    }

    #[LiveAction]
    public function addMember(): void
    {
        $members = $this->formValues['members'] ?? [];
        \assert(\is_array($members));
        $members[[] === $members ? 0 : max(array_keys($members)) + 1] = ['user' => '', 'share' => '100'];
        $this->formValues['members'] = $members;
    }

    #[LiveAction]
    public function removeMember(#[LiveArg] int $index): void
    {
        $members = $this->formValues['members'] ?? [];
        \assert(\is_array($members));
        unset($members[$index]);
        $this->formValues['members'] = $members;
    }

    protected function instantiateForm(): FormInterface
    {
        $options = ['keep_empty_members' => true];

        return match (true) {
            null !== $this->lot => $this->lotFormFactory->forLot($this->lot, $options),
            null !== $this->parent => $this->lotFormFactory->forNewSubLot($this->parent, $options),
            null !== $this->project => $this->lotFormFactory->forNewLot($this->project, $options),
            default => throw new \LogicException('A lot form edits a lot, or adds one to a project or a lot.'),
        };
    }

    /**
     * @return list<ChoiceView> the people a team row offers, the same on every row
     */
    private function personChoices(): array
    {
        $view = $this->getFormView();
        foreach (isset($view['members']) ? $view['members'] : [] as $row) {
            $choices = $row['user']->vars['choices'] ?? [];

            return \is_array($choices) ? array_values(array_filter($choices, static fn (mixed $choice): bool => $choice instanceof ChoiceView)) : [];
        }

        return [];
    }

    /**
     * @param list<int> $tagIds
     */
    private static function carriesAll(User $person, array $tagIds): bool
    {
        $carried = [];
        foreach (TagCategory::cases() as $category) {
            foreach ($person->tagsOf($category) as $tag) {
                $carried[] = $tag->getId();
            }
        }

        return [] === array_diff($tagIds, $carried);
    }
}
