<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\TeamMemberInput;
use App\Enum\Type\Role;
use App\Service\TeamManager;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Ask;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-director',
    description: 'Crée un compte direction avec un mot de passe provisoire',
)]
final readonly class CreateDirectorCommand
{
    public function __construct(
        private TeamManager $teamManager,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('E-mail de la personne'), Ask('E-mail')] string $email,
        #[Argument('Prénom'), Ask('Prénom')] string $firstName,
        #[Argument('Nom'), Ask('Nom')] string $lastName,
    ): int {
        $input = new TeamMemberInput();
        $input->email = $email;
        $input->firstName = $firstName;
        $input->lastName = $lastName;
        $input->role = Role::Direction;

        $violations = $this->validator->validate($input);
        if (\count($violations) > 0) {
            foreach ($violations as $violation) {
                $io->error(\sprintf('%s : %s', $violation->getPropertyPath(), $violation->getMessage()));
            }

            return Command::FAILURE;
        }

        [$user, $temporaryPassword] = $this->teamManager->register($input);

        $io->success(\sprintf('Compte direction créé pour %s.', $user->getEmail()));
        $io->text([
            'Mot de passe provisoire, à transmettre à la personne (il ne sera plus affiché) :',
            '',
            '    ' . $temporaryPassword,
            '',
            'Il devra être remplacé à la première connexion.',
        ]);

        return Command::SUCCESS;
    }
}
