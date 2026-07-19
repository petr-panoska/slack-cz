<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationAvailabilityTest extends WebTestCase
{
    public function testRejectsAnUnknownField(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/registrace/dostupnost',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"field":"password","value":"secret"}',
        );

        self::assertResponseStatusCodeSame(400);
        self::assertJsonStringEqualsJsonString('{"message":"Neplatný požadavek."}', (string) $client->getResponse()->getContent());
    }

    public function testRejectsAnEmptyEmailWithoutQueryingUsers(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/registrace/dostupnost',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"field":"email","value":""}',
        );

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"valid":false}', (string) $client->getResponse()->getContent());
    }

    public function testRejectsAnEmailWithoutAnAtSignWithoutQueryingUsers(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/registrace/dostupnost',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"field":"email","value":"kokos"}',
        );

        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"valid":false}', (string) $client->getResponse()->getContent());
    }
}
