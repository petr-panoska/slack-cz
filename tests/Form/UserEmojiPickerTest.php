<?php

namespace App\Tests\Form;

use App\Entity\User;
use App\Form\UserForm;
use App\UserEmoji;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

final class UserEmojiPickerTest extends KernelTestCase
{
    public function testProfileOffersTheCanonicalAnimalPalette(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('form.factory');

        $form = $factory->create(UserForm::class, new User());
        $choices = $form->createView()->children['emoji']->vars['choices'];
        $values = array_map(static fn (ChoiceView $choice): string => $choice->value, $choices);

        self::assertSame(UserEmoji::VALUES, $values);
    }

    public function testRandomEmojiComesFromTheCanonicalPalette(): void
    {
        self::assertContains(UserEmoji::random(), UserEmoji::VALUES);
    }
}
