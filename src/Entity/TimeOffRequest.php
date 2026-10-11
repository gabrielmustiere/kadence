<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Repository\TimeOffRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Only the bounds of a request are stored: its half-days are worked out on reading, on the working days of the
 * calendar in force, so that a day becoming a holiday no longer counts.
 */
#[ORM\Entity(repositoryClass: TimeOffRequestRepository::class)]
#[ORM\Index(name: 'idx_time_off_user_status', columns: ['user_id', 'status'])]
#[ORM\Index(name: 'idx_time_off_status_end', columns: ['status', 'end_day'])]
class TimeOffRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (assigned by Doctrine) */
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false)]
    private User $user;

    #[ORM\Column(length: 10, enumType: TimeOffType::class)]
    private TimeOffType $type;

    #[ORM\Column(name: 'start_day', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDay;

    #[ORM\Column(name: 'start_half', length: 10, enumType: HalfDay::class)]
    private HalfDay $startHalf;

    #[ORM\Column(name: 'end_day', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endDay;

    #[ORM\Column(name: 'end_half', length: 10, enumType: HalfDay::class)]
    private HalfDay $endHalf;

    /** @var non-empty-string|null */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $comment;

    #[ORM\Column(length: 10, enumType: TimeOffStatus::class)]
    private TimeOffStatus $status = TimeOffStatus::Pending;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** Null once approved on its own, when nobody else could decide. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'decided_by_id')]
    private ?User $decidedBy = null;

    #[ORM\Column(name: 'decided_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $decidedAt = null;

    /** @var non-empty-string|null */
    #[ORM\Column(name: 'refusal_reason', length: 500, nullable: true)]
    private ?string $refusalReason = null;

    /** Null once cancelled with the deactivation of its person. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'cancelled_by_id')]
    private ?User $cancelledBy = null;

    #[ORM\Column(name: 'cancelled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * @param non-empty-string|null $comment
     */
    public function __construct(User $user, TimeOffType $type, \DateTimeImmutable $startDay, HalfDay $startHalf, \DateTimeImmutable $endDay, HalfDay $endHalf, ?string $comment, \DateTimeImmutable $createdAt)
    {
        $this->user = $user;
        $this->type = $type;
        $this->startDay = $startDay->setTime(0, 0);
        $this->startHalf = $startHalf;
        $this->endDay = $endDay->setTime(0, 0);
        $this->endHalf = $endHalf;
        $this->comment = $comment;
        $this->createdAt = $createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): TimeOffType
    {
        return $this->type;
    }

    public function getStartDay(): \DateTimeImmutable
    {
        return $this->startDay;
    }

    public function getStartHalf(): HalfDay
    {
        return $this->startHalf;
    }

    public function getEndDay(): \DateTimeImmutable
    {
        return $this->endDay;
    }

    public function getEndHalf(): HalfDay
    {
        return $this->endHalf;
    }

    /** @return non-empty-string|null */
    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getStatus(): TimeOffStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDecidedBy(): ?User
    {
        return $this->decidedBy;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    /** @return non-empty-string|null */
    public function getRefusalReason(): ?string
    {
        return $this->refusalReason;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function approve(?User $by, \DateTimeImmutable $at): static
    {
        $this->status = TimeOffStatus::Approved;
        $this->decidedBy = $by;
        $this->decidedAt = $at;

        return $this;
    }

    /**
     * @param non-empty-string $reason
     */
    public function refuse(User $by, \DateTimeImmutable $at, string $reason): static
    {
        $this->status = TimeOffStatus::Refused;
        $this->decidedBy = $by;
        $this->decidedAt = $at;
        $this->refusalReason = $reason;

        return $this;
    }

    public function cancel(?User $by, \DateTimeImmutable $at): static
    {
        $this->status = TimeOffStatus::Cancelled;
        $this->cancelledBy = $by;
        $this->cancelledAt = $at;

        return $this;
    }
}
