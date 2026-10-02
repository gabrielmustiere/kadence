<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\TeamListFilter;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function loadUserByIdentifier(string $identifier): ?User
    {
        return $this->findOneByEmail($identifier);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => mb_strtolower($email)]);
    }

    /**
     * @return list<User>
     */
    public function findAllForTeamList(): array
    {
        return $this->findBy([], ['active' => 'DESC', 'lastName' => 'ASC', 'firstName' => 'ASC']);
    }

    /**
     * The people offered as owner, team member or manager, with their tags shown beside each team member.
     *
     * @return list<User>
     */
    public function findActiveWithTags(): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->addSelect('t')
            ->leftJoin('u.tags', 't')
            ->andWhere('u.active = true')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * The team list with each person's tags and manager, restricted to the people carrying every tag of the filter and
     * reporting directly to its manager.
     *
     * @return list<User>
     */
    public function findForTeamList(TeamListFilter $filter): array
    {
        $queryBuilder = $this->createQueryBuilder('u')
            ->addSelect('t', 'm')
            ->leftJoin('u.tags', 't')
            ->leftJoin('u.manager', 'm')
            ->orderBy('u.active', 'DESC')
            ->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC');

        // MEMBER OF filters in a subquery: filtering on the fetch-joined alias would load only the filtered tags.
        foreach ($filter->tags() as $index => $tag) {
            $queryBuilder->andWhere(\sprintf(':tag%d MEMBER OF u.tags', $index))->setParameter('tag' . $index, $tag);
        }
        if (null !== $filter->manager) {
            $queryBuilder->andWhere('u.manager = :manager')->setParameter('manager', $filter->manager);
        }

        /** @var list<User> $users */
        $users = $queryBuilder->getQuery()->getResult();

        return $users;
    }

    /**
     * @return list<User> the people, active or not, who are the direct manager of someone
     */
    public function findManagers(): array
    {
        /** @var list<User> $managers */
        $managers = $this->createQueryBuilder('m')
            ->andWhere('EXISTS (SELECT r.id FROM App\Entity\User r WHERE r.manager = m)')
            ->orderBy('m.lastName', 'ASC')
            ->addOrderBy('m.firstName', 'ASC')
            ->getQuery()
            ->getResult();

        return $managers;
    }

    /**
     * @return list<User> the people, active or not, whose direct manager is this person
     */
    public function findReportsOf(User $manager): array
    {
        return $this->findBy(['manager' => $manager], ['lastName' => 'ASC', 'firstName' => 'ASC']);
    }

    /**
     * @return list<User> the people, active or not, carrying the tag
     */
    public function findHoldersOf(Tag $tag): array
    {
        /** @var list<User> $holders */
        $holders = $this->createQueryBuilder('u')
            ->andWhere(':tag MEMBER OF u.tags')
            ->setParameter('tag', $tag)
            ->getQuery()
            ->getResult();

        return $holders;
    }

    public function countActiveDirectors(): int
    {
        return $this->count(['role' => Role::Direction, 'active' => true]);
    }
}
