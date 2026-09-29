<?php

declare(strict_types=1);

namespace App\Model;

final readonly class Week
{
    private function __construct(
        public \DateTimeImmutable $monday,
    ) {
    }

    public static function containing(\DateTimeImmutable $day): self
    {
        return new self($day->setTime(0, 0)->modify('monday this week'));
    }

    /**
     * @throws \InvalidArgumentException when the value is not an existing ISO week such as « 2026-W40 »
     */
    public static function fromIso(string $iso): self
    {
        if (1 !== preg_match('/^(\d{4})-W(\d{2})$/', $iso, $matches)) {
            throw new \InvalidArgumentException(\sprintf('« %s » is not an ISO week.', $iso));
        }

        $week = new self(new \DateTimeImmutable('today')->setISODate((int) $matches[1], (int) $matches[2]));
        if ($week->iso() !== $iso) {
            throw new \InvalidArgumentException(\sprintf('« %s » is not an existing ISO week.', $iso));
        }

        return $week;
    }

    public function iso(): string
    {
        return $this->monday->format('o-\WW');
    }

    public function previous(): self
    {
        return new self($this->monday->modify('-7 days'));
    }

    public function next(): self
    {
        return new self($this->monday->modify('+7 days'));
    }

    public function friday(): \DateTimeImmutable
    {
        return $this->monday->modify('+4 days');
    }

    /**
     * @return list<\DateTimeImmutable> Monday to Friday
     */
    public function days(): array
    {
        return array_map(fn (int $offset): \DateTimeImmutable => $this->monday->modify(\sprintf('+%d days', $offset)), range(0, 4));
    }

    public function contains(\DateTimeImmutable $day): bool
    {
        $date = $day->format('Y-m-d');

        return $date >= $this->monday->format('Y-m-d') && $date <= $this->friday()->format('Y-m-d');
    }

    public function equals(self $other): bool
    {
        return $this->iso() === $other->iso();
    }
}
