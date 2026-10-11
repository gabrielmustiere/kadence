<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Model\Roadmap\RoadmapAbsence;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Schedule\ScheduleData;
use App\Model\Schedule\ScheduleResult;

final readonly class TeamAbsences
{
    /**
     * The approved time off of the members of the team of each planned leaf, over the days it reaches within the
     * window.
     *
     * @return array<int, list<RoadmapAbsence>> by lot id
     */
    public function byLeaf(ScheduleData $data, ScheduleResult $result, RoadmapWindow $window): array
    {
        $absences = [];
        foreach ($data->plans as $lotId => $plan) {
            $schedule = $result->get($lotId);
            [$start, $lastDay] = null === $schedule || !$schedule->isPlanned() ? [null, null] : RoadmapBuilder::reach($data->leaves[$lotId], $schedule);
            if (null === $start || null === $lastDay) {
                continue;
            }

            $from = max($start, $window->firstDay());
            $to = min($lastDay, $window->lastDay());
            $leafAbsences = [];
            foreach ($plan->members as $member) {
                $person = $data->people[$member->userId] ?? null;
                if (null !== $person) {
                    $leafAbsences = [...$leafAbsences, ...self::ofMember($data, $person, $from, $to, $window)];
                }
            }
            if ([] !== $leafAbsences) {
                $absences[$lotId] = $leafAbsences;
            }
        }

        return $absences;
    }

    /**
     * @return list<RoadmapAbsence>
     */
    private static function ofMember(ScheduleData $data, User $person, \DateTimeImmutable $from, \DateTimeImmutable $to, RoadmapWindow $window): array
    {
        $absences = [];
        foreach ($data->capacity->timeOffSpans((int) $person->getId(), $from, $to) as [$first, $last, $half]) {
            $bar = $window->bar($first, $last);
            if (null !== $bar) {
                $absences[] = new RoadmapAbsence($person, $first, $last, $half, $bar);
            }
        }

        return $absences;
    }
}
