<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Smoke testy veřejných rout, které NEsahají na databázi ani externí síť
 * (GitHub markdown fetcher / YouTube feed). Cíl: na každém push/PR ověřit,
 * že kernel nabootuje, DI container se zkompiluje a routing + security + Twig
 * + AssetMapper fungují.
 *
 * DB-backed stránky (`/`, `/mapa`, `/denik/{id}`, `/highline/*`) ani síťové
 * (`/wiki`, `/docs`, slackTV) tu schválně nejsou — CI běží na prázdné SQLite
 * bez schématu, viz `.github/workflows/symfony.yml`. Až bude test DB se
 * schématem (např. `doctrine:schema:create` v test env), můžou přibýt.
 */
final class PublicPagesSmokeTest extends WebTestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function publicSuccessRoutes(): iterable
    {
        yield 'about' => ['/o-projektu'];
        yield 'login' => ['/login'];
        // POZN: `/register` sem zatím nedávat — render `RegistrationForm` spouští
        // přímé deprecations (array-option validator constraints, viz roadmap.md
        // deprecation checklist). S `failOnDeprecation=true` by shodil CI. Přidat
        // až po cleanupu formulářů na named-args constraints.
    }

    #[DataProvider('publicSuccessRoutes')]
    public function testPublicRouteReturnsSuccess(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
    }

    public function testProfileRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/profile');

        self::assertResponseRedirects();
        self::assertStringContainsString(
            '/login',
            (string) $client->getResponse()->headers->get('Location'),
        );
    }
}
