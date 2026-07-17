<?php

namespace App\Form;

use App\Entity\User;
use App\UserEmoji;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;

class UserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $maxLen = static fn (int $max): Length => new Length(
            max: $max,
            maxMessage: 'Maximálně {{ limit }} znaků.',
        );

        $phoneFormatMessage = 'Telefon zadej jako 9 nebo 12 číslic, volitelně s předvolbou + nebo 00 (např. 123456789, +420123456789).';

        $builder
            ->add('emoji', ChoiceType::class, [
                'label' => 'Emoji',
                'choices' => UserEmoji::choices(),
                'expanded' => true,
                'choice_attr' => static fn (string $emoji): array => [
                    'class' => 'emoji-picker__input',
                    'data-emoji-picker-target' => 'input',
                    'data-action' => 'change->emoji-picker#select',
                ],
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Jméno',
                'required' => false,
                'constraints' => [$maxLen(30)],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Příjmení',
                'required' => false,
                'constraints' => [$maxLen(30)],
            ])
            ->add('city', TextType::class, [
                'label' => 'Město',
                'required' => false,
                'constraints' => [$maxLen(50)],
            ])
            ->add('birthYear', IntegerType::class, [
                'label' => 'Ročník',
                'required' => false,
                'constraints' => [new Range(
                    min: 1900,
                    max: (int) date('Y'),
                    notInRangeMessage: 'Zadej ročník mezi {{ min }} a {{ max }}.',
                )],
            ])
            ->add('phone', TelType::class, [
                'label' => 'Telefon',
                'required' => false,
                'help' => 'Telefon je vidět jen přihlášeným uživatelům, ne veřejně.',
                'attr' => [
                    'pattern' => '\d{9}|(\+|00)?\d{12}',
                    'title' => $phoneFormatMessage,
                ],
                'constraints' => [
                    new Regex(
                        pattern: '/^(\d{9}|(\+|00)?\d{12})$/',
                        message: $phoneFormatMessage,
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
