<?php

namespace App\Tests\Service;

use App\Service\EmailMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmailMaskerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function emailProvider(): iterable
    {
        yield 'common email' => ['jan.novak@example.com', 'ja*****@e****m'];
        yield 'unicode local part' => ['šípek@seznam.cz', 'ší*****@s****z'];
        yield 'one-character local part' => ['a@b.cz', 'a*****@b****z'];
    }

    #[DataProvider('emailProvider')]
    public function testMasksTheWholeDomain(string $email, string $expected): void
    {
        self::assertSame($expected, (new EmailMasker())->mask($email));
    }
}
