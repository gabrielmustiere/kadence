<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\TagManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TagManagerTest extends TestCase
{
    public function testSplitTrimsLabelsAndDropsBlanksAndDuplicatesIgnoringCase(): void
    {
        self::assertSame(['Symfony', 'react'], TagManager::split(' Symfony, , react ,symfony,React '));
        self::assertSame([], TagManager::split(null));
        self::assertSame([], TagManager::split(' , '));
    }

    public function testCreateTrimsTheLabelAndSavesTheTag(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with(self::isInstanceOf(Tag::class));
        $entityManager->expects($this->once())->method('flush');

        $tag = $this->tagManager($entityManager)->create(TagCategory::TeamType, '  Back ');

        self::assertSame('Back', $tag->getLabel());
        self::assertSame(TagCategory::TeamType, $tag->getCategory());
    }

    public function testResolveReusesTheTagOfTheSameLabelIgnoringCaseAndCreatesTheMissingOnesOnce(): void
    {
        $symfony = new Tag(TagCategory::TechnicalSkill, 'Symfony');
        $tagRepository = $this->createStub(TagRepository::class);
        $tagRepository->method('findByCategory')->willReturn([$symfony]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with(self::callback(
            static fn (Tag $tag): bool => 'Kubernetes' === $tag->getLabel() && TagCategory::TechnicalSkill === $tag->getCategory(),
        ));
        $entityManager->expects($this->never())->method('flush');

        $tags = $this->tagManager($entityManager, $tagRepository)->resolve(TagCategory::TechnicalSkill, [' symfony ', 'Kubernetes', 'KUBERNETES', '']);

        self::assertCount(2, $tags);
        self::assertSame($symfony, $tags[0]);
        self::assertSame('Kubernetes', $tags[1]->getLabel());
    }

    public function testResolveOfBlankLabelsCreatesNothing(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        self::assertSame([], $this->tagManager($entityManager)->resolve(TagCategory::TeamType, ['  ']));
    }

    public function testDeleteDetachesTheTagFromEachHolderBeforeRemovingIt(): void
    {
        $tag = new Tag(TagCategory::FunctionalExperience, 'Paie');
        $kept = new Tag(TagCategory::FunctionalExperience, 'Facturation');
        $holder = new User()->replaceTags([$tag, $kept]);
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findHoldersOf')->willReturn([$holder]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('remove')->with($tag);
        $entityManager->expects($this->once())->method('flush');

        $this->tagManager($entityManager, userRepository: $userRepository)->delete($tag);

        self::assertSame([$kept], $holder->tagsOf(TagCategory::FunctionalExperience));
    }

    private function tagManager(EntityManagerInterface $entityManager, ?TagRepository $tagRepository = null, ?UserRepository $userRepository = null): TagManager
    {
        return new TagManager(
            $entityManager,
            $tagRepository ?? $this->createStub(TagRepository::class),
            $userRepository ?? $this->createStub(UserRepository::class),
        );
    }
}
