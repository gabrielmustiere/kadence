<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VocabularyTest extends WebTestCase
{
    private const array BANNED = [
        '/\bfeuilles?\b/iu',
        '/consomm/iu',
        '/au-delà de l\'estimation/iu',
        '/type d\'équipe/iu',
        '/planning partiel/iu',
        '/date de fin projetée/iu',
    ];

    /**
     * @param list<string> $pages
     */
    #[DataProvider('roleProvider')]
    public function testNoPageSpeaksOfWhatTheLexiconBans(string $email, array $pages): void
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);
        \assert($userRepository instanceof UserRepository);
        $user = $userRepository->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $sca = $entityManager->getRepository(Project::class)->findOneBy(['title' => 'SCA']);
        self::assertInstanceOf(Project::class, $sca);
        $lot = $entityManager->getRepository(Lot::class)->findOneBy(['project' => $sca, 'title' => 'Cadrage']);
        self::assertInstanceOf(Lot::class, $lot);
        $ids = ['{sca}' => (string) $sca->getId(), '{lot}' => (string) $lot->getId(), '{person}' => (string) $user->getId()];

        foreach ($pages as $page) {
            $url = strtr($page, $ids);
            $client->request('GET', $url);

            self::assertResponseIsSuccessful($url);
            $content = html_entity_decode((string) $client->getResponse()->getContent(), \ENT_QUOTES | \ENT_HTML5);
            foreach (self::BANNED as $banned) {
                self::assertDoesNotMatchRegularExpression($banned, $content, $url);
            }
        }
    }

    /**
     * @return \Generator<string, array{string, list<string>}>
     */
    public static function roleProvider(): \Generator
    {
        $pages = ['/', '/saisie', '/roadmap', '/projets', '/projets/{sca}', '/personnes', '/personnes/{person}', '/mon-compte/mot-de-passe'];

        yield 'direction' => ['thomas.cerqueus@anyti.me', [...$pages, '/tags', '/lots/{lot}/modifier', '/conges/a-valider', '/personnes/{person}/modifier']];
        yield 'prod' => ['antoine.leroy@anyti.me', $pages];
    }
}
