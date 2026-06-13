<?php

namespace App\Tests\Form;

use App\Entity\LonglineCrossing;
use App\Form\LonglineCrossingForm;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

/**
 * The longline style picker reuses CrossingStyle but must drop the leash-only
 * styles (swami / solo / kotník) that only apply to highline. Building the form
 * here also smoke-tests the filtered EnumType `choices`. No DB — runs on the
 * schema-less CI sqlite.
 */
final class LonglineCrossingFormTest extends KernelTestCase
{
    public function testStylePickerExcludesHighlineOnlyStyles(): void
    {
        self::bootKernel();
        $factory = self::getContainer()->get('form.factory');

        $form = $factory->create(LonglineCrossingForm::class, new LonglineCrossing());
        $choices = $form->createView()->children['style']->vars['choices'];

        $values = array_map(static fn (ChoiceView $c): string => $c->value, $choices);

        self::assertContains('os_fm', $values, 'longline keeps the walk styles');
        self::assertContains('ow', $values);
        self::assertNotContains('swami', $values, 'leash-only styles are highline-only');
        self::assertNotContains('solo', $values);
        self::assertNotContains('kotnik', $values);
    }
}
