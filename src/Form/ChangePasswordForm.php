<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class ChangePasswordForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Aktuální heslo',
                'constraints' => [
                    new NotBlank([
                        'message' => 'Zadej své aktuální heslo.',
                    ]),
                ],
                'attr' => [
                    'autocomplete' => 'current-password',
                ],
                'mapped' => false,
            ])
            ->add('newPassword', NewPasswordType::class, [
                'label' => 'Nové heslo',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
