<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;

final class ErrorPageTest extends KernelTestCase
{
    #[DataProvider('statusProvider')]
    public function testTheErrorPageSaysWhatHappenedInFrench(int $statusCode, string $heading): void
    {
        $twig = self::getContainer()->get('twig');
        \assert($twig instanceof Environment);

        $crawler = new Crawler($twig->render('@Twig/Exception/error.html.twig', ['status_code' => $statusCode, 'status_text' => 'Status']));

        self::assertSame($heading, $crawler->filter('h1')->text());
        self::assertSame('Erreur ' . $statusCode, $crawler->filter('[data-test="error-page"] .eyebrow')->text());
        self::assertSame('/', $crawler->filter('[data-test="error-page"] a')->attr('href'));
    }

    /**
     * @return \Generator<string, array{int, string}>
     */
    public static function statusProvider(): \Generator
    {
        yield 'forbidden' => [403, 'Accès refusé'];
        yield 'not found' => [404, 'Page introuvable'];
        yield 'server error' => [500, 'Une erreur est survenue'];
    }
}
