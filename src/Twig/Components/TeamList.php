<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dto\TeamListFilter;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use App\Exception\LastActiveDirectorException;
use App\Exception\ManagerWithActiveReportsException;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\TeamManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The people, filtered as soon as a filter changes: the filters live in the URL (?competence=…&manager=…) so that it can
 * be shared, and stay as they are when a member is deactivated or reactivated in place. Each role reads what the page of
 * a person shows it: the leads the tags, the direction the manager, the deactivated people and the management actions.
 */
#[AsLiveComponent]
final class TeamList extends AbstractController
{
    use DefaultActionTrait;
    use NotifiesInPlace;

    #[LiveProp(writable: true, url: true)]
    public ?int $competence = null;

    #[LiveProp(writable: true, url: true)]
    public ?int $experience = null;

    #[LiveProp(writable: true, url: true)]
    public ?int $equipe = null;

    #[LiveProp(writable: true, url: true)]
    public ?int $manager = null;

    /** @var array<string, list<Tag>>|null by category value */
    private ?array $tags = null;

    /** @var list<User>|null */
    private ?array $managers = null;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TagRepository $tagRepository,
        private readonly TeamManager $teamManager,
    ) {
    }

    /**
     * @return array<string, list<Tag>> the tags to filter on, by category value
     */
    public function getTags(): array
    {
        if (null === $this->tags) {
            $this->tags = [];
            foreach (TagCategory::cases() as $category) {
                $this->tags[$category->value] = $this->tagRepository->findByCategory($category);
            }
        }

        return $this->tags;
    }

    /**
     * @return list<User>
     */
    public function getManagers(): array
    {
        if (!$this->isGranted('ROLE_DIRECTION')) {
            return [];
        }

        return $this->managers ??= $this->userRepository->findManagers();
    }

    /**
     * @return list<User>
     */
    public function getMembers(): array
    {
        return $this->userRepository->findForTeamList($this->filter());
    }

    public function isFiltered(): bool
    {
        $filter = $this->filter();

        return [] !== $filter->tags() || null !== $filter->manager;
    }

    #[LiveAction]
    public function clear(): void
    {
        $this->competence = $this->experience = $this->equipe = $this->manager = null;
    }

    #[LiveAction]
    public function deactivate(#[LiveArg] int $member): void
    {
        $this->denyAccessUnlessGranted('ROLE_DIRECTION');
        $user = $this->member($member);
        try {
            $this->teamManager->deactivate($user);
            $this->notify('success', \sprintf('Le compte de %s %s est désactivé.', $user->getFirstName(), $user->getLastName()));
        } catch (LastActiveDirectorException|ManagerWithActiveReportsException $exception) {
            $this->notify('error', $exception->getMessage());
        }
    }

    #[LiveAction]
    public function reactivate(#[LiveArg] int $member): void
    {
        $this->denyAccessUnlessGranted('ROLE_DIRECTION');
        $user = $this->member($member);
        $this->teamManager->reactivate($user);
        $this->notify('success', \sprintf('Le compte de %s %s est réactivé.', $user->getFirstName(), $user->getLastName()));
    }

    /**
     * The filter the props ask for, within what the viewer may read: an id that names no tag of its category, or no
     * manager, filters nothing, and neither does a filter the viewer's role does not show, even forged in the URL.
     */
    private function filter(): TeamListFilter
    {
        $filter = new TeamListFilter();
        if ($this->isGranted('ROLE_LEAD')) {
            $filter->technicalSkill = $this->tagOf(TagCategory::TechnicalSkill, $this->competence);
            $filter->functionalExperience = $this->tagOf(TagCategory::FunctionalExperience, $this->experience);
            $filter->teamType = $this->tagOf(TagCategory::TeamType, $this->equipe);
        }
        $filter->manager = array_find($this->getManagers(), fn (User $manager): bool => $manager->getId() === $this->manager);
        $filter->activeOnly = !$this->isGranted('ROLE_DIRECTION');

        return $filter;
    }

    private function tagOf(TagCategory $category, ?int $id): ?Tag
    {
        return array_find($this->getTags()[$category->value], static fn (Tag $tag): bool => $tag->getId() === $id);
    }

    private function member(int $id): User
    {
        return $this->userRepository->find($id) ?? throw $this->createNotFoundException('Cette personne n\'existe pas.');
    }
}
