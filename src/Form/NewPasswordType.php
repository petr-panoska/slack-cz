<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
// use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
// use Symfony\Component\Validator\Constraints\PasswordStrength;

class NewPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'first_options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'data-controller' => 'password-strength',
                        'data-action' => 'input->password-strength#update',
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Zadej heslo.',
                        ]),
                        new Length([
                            'min' => 6,
                            'minMessage' => 'Heslo musí mít aspoň {{ limit }} znaků.',
                            // max length allowed by Symfony for security reasons
                            'max' => 4096,
                        ]),
                        // new PasswordStrength(),
                        // new NotCompromisedPassword(),
                    ],
                    'label' => 'Heslo',
                ],
                'second_options' => [
                    'label' => 'Heslo znovu',
                ],
                'invalid_message' => 'Hesla se neshodují.',
                // Instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
