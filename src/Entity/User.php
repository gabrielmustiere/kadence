<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Type\HolidayCalendar;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    /** @var non-empty-string|null */
    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /** @var non-empty-string|null */
    #[ORM\Column(name: 'first_name', length: 100)]
    private ?string $firstName = null;

    /** @var non-empty-string|null */
    #[ORM\Column(name: 'last_name', length: 100)]
    private ?string $lastName = null;

    #[ORM\Column(length: 20, enumType: Role::class)]
    private Role $role = Role::Prod;

    #[ORM\Column(name: 'holiday_calendar', length: 2, enumType: HolidayCalendar::class, options: ['default' => 'fr'])]
    private HolidayCalendar $holidayCalendar = HolidayCalendar::France;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(name: 'must_change_password')]
    private bool $mustChangePassword = false;

    #[ORM\Column]
    private ?string $password = null;

    /** @var Collection<int, Tag> at most one tag of a category that does not allow many */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'user_tag')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    #[ORM\InverseJoinColumn(name: 'tag_id', nullable: false)]
    private Collection $tags;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'manager_id')]
    private ?User $manager = null;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /** @param non-empty-string $email */
    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower($email);

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /** @param non-empty-string $firstName */
    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /** @param non-empty-string $lastName */
    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getHolidayCalendar(): HolidayCalendar
    {
        return $this->holidayCalendar;
    }

    public function setHolidayCalendar(HolidayCalendar $holidayCalendar): static
    {
        $this->holidayCalendar = $holidayCalendar;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @return list<Tag> in alphabetical order
     */
    public function tagsOf(TagCategory $category): array
    {
        $tags = array_values(array_filter($this->tags->toArray(), static fn (Tag $tag): bool => $tag->getCategory() === $category));
        usort($tags, Tag::compare(...));

        return $tags;
    }

    public function teamType(): ?Tag
    {
        return $this->tagsOf(TagCategory::TeamType)[0] ?? null;
    }

    /**
     * @param iterable<Tag> $tags
     */
    public function replaceTags(iterable $tags): static
    {
        $kept = [];
        foreach ($tags as $tag) {
            $kept[spl_object_id($tag)] = $tag;
        }

        foreach (TagCategory::cases() as $category) {
            $count = \count(array_filter($kept, static fn (Tag $tag): bool => $tag->getCategory() === $category));
            if (!$category->allowsMany() && $count > 1) {
                throw new \LogicException(\sprintf('A person carries at most one tag of the category « %s ».', $category->label()));
            }
        }

        foreach ($this->tags as $tag) {
            if (!isset($kept[spl_object_id($tag)])) {
                $this->tags->removeElement($tag);
            }
        }
        foreach ($kept as $tag) {
            if (!$this->tags->contains($tag)) {
                $this->tags->add($tag);
            }
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function getManager(): ?User
    {
        return $this->manager;
    }

    public function setManager(?User $manager): static
    {
        $this->manager = $manager;

        return $this;
    }

    public function mustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email ?? throw new \LogicException('User email is not set.');
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        return [$this->role->securityRole()];
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = null !== $this->password ? hash('crc32c', $this->password) : null;
        // The user is refreshed from the database on every request: its relations would only weigh the session down with
        // Doctrine collections and proxies.
        unset($data["\0" . self::class . "\0tags"], $data["\0" . self::class . "\0manager"]);

        return $data;
    }
}
