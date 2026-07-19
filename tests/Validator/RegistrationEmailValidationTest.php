<?php

namespace App\Tests\Validator;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RegistrationEmailValidationTest extends KernelTestCase
{
    public function testEmailValidationAcceptsLegacySingleLabelDomains(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);

        $violations = $validator->validate('panda098@centrum', new Email(mode: Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD));

        self::assertCount(0, $violations);
    }

    public function testEmailValidationRejectsAnAddressWithoutAtSign(): void
    {
        self::bootKernel();
        $validator = self::getContainer()->get(ValidatorInterface::class);

        $violations = $validator->validate('kokos', new Email(mode: Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD));

        self::assertCount(1, $violations);
    }
}
