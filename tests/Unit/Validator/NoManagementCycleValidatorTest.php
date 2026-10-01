<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Dto\TeamMemberInput;
use App\Entity\User;
use App\Validator\NoManagementCycle;
use App\Validator\NoManagementCycleValidator;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<NoManagementCycleValidator>
 */
final class NoManagementCycleValidatorTest extends ConstraintValidatorTestCase
{
    public function testAPersonCannotBeTheirOwnManager(): void
    {
        $alice = $this->user(1, 'Alice');

        $this->validator->validate($this->input($alice, $alice), new NoManagementCycle());

        $this->assertCycle('Alice Test');
    }

    public function testAPersonCannotBeManagedBySomeoneTheyManageDirectly(): void
    {
        $alice = $this->user(1, 'Alice');
        $bob = $this->user(2, 'Bob')->setManager($alice);

        $this->validator->validate($this->input($alice, $bob), new NoManagementCycle());

        $this->assertCycle('Bob Test');
    }

    public function testAPersonCannotBeManagedBySomeoneTheyManageIndirectly(): void
    {
        $alice = $this->user(1, 'Alice');
        $bob = $this->user(2, 'Bob')->setManager($alice);
        $carol = $this->user(3, 'Carol')->setManager($bob);

        $this->validator->validate($this->input($alice, $carol), new NoManagementCycle());

        $this->assertCycle('Carol Test');
    }

    public function testAChainOfManagersAboveThePersonIsValid(): void
    {
        $alice = $this->user(1, 'Alice');
        $bob = $this->user(2, 'Bob')->setManager($alice);

        $this->validator->validate($this->input($this->user(3, 'Carol'), $bob), new NoManagementCycle());

        $this->assertNoViolation();
    }

    public function testNoManagerOrANewPersonIsValid(): void
    {
        $this->validator->validate($this->input($this->user(1, 'Alice'), null), new NoManagementCycle());
        $this->assertNoViolation();

        $input = new TeamMemberInput();
        $input->manager = $this->user(1, 'Alice');
        $this->validator->validate($input, new NoManagementCycle());
        $this->assertNoViolation();
    }

    public function testAnExistingLoopAboveTheManagerDoesNotHangTheValidation(): void
    {
        $bob = $this->user(2, 'Bob');
        $carol = $this->user(3, 'Carol')->setManager($bob);
        $bob->setManager($carol);

        $this->validator->validate($this->input($this->user(1, 'Alice'), $bob), new NoManagementCycle());

        $this->assertNoViolation();
    }

    protected function createValidator(): NoManagementCycleValidator
    {
        return new NoManagementCycleValidator();
    }

    private function assertCycle(string $manager): void
    {
        $this->buildViolation(new NoManagementCycle()->message)
            ->setParameter('{{ manager }}', $manager)
            ->atPath('property.path.manager')
            ->assertRaised();
    }

    private function input(User $member, ?User $manager): TeamMemberInput
    {
        $input = new TeamMemberInput();
        $input->id = $member->getId();
        $input->manager = $manager;

        return $input;
    }

    /** @param non-empty-string $firstName */
    private function user(int $id, string $firstName): User
    {
        $user = new User()->setFirstName($firstName)->setLastName('Test')->setEmail($firstName . '@example.com');
        new \ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
