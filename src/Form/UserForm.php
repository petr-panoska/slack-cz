<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Range;

class UserForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $maxLen = static fn (int $max): Length => new Length(
            max: $max,
            maxMessage: 'Maximálně {{ limit }} znaků.',
        );

        $builder
            ->add('nick', TextType::class, [
                'label' => 'Nick',
                'required' => false,
                'constraints' => [$maxLen(30)],
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
                'constraints' => [$maxLen(30)],
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
