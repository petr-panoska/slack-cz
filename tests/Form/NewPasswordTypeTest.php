<?php

namespace App\Tests\Form;

use App\Form\NewPasswordType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NewPasswordTypeTest extends KernelTestCase
{
    public function testFirstPasswordFieldHasTheStrengthIndicatorController(): void
    {
        self::bootKernel();
        $form = self::getContainer()->get('form.factory')->create(NewPasswordType::class);
        $field = $form->createView()->children['plainPassword']->children['first'];

        self::assertSame('password-strength', $field->vars['attr']['data-controller']);
        self::assertSame('input->password-strength#update', $field->vars['attr']['data-action']);
        self::assertSame('new-password', $field->vars['attr']['autocomplete']);
        self::assertArrayNotHasKey('data-controller', $form->createView()->children['plainPassword']->children['second']->vars['attr']);
    }
}
