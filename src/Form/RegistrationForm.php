<?php

namespace App\Form;

use App\Entity\User;
use App\Form\NewPasswordType;
use App\UserEmoji;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

class RegistrationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(message: 'Zadej e-mail.'),
                    new Email(mode: Email::VALIDATION_MODE_HTML5_ALLOW_NO_TLD, message: 'Zadej platný e-mail.'),
                ],
            ])
            ->add('nick', TextType::class, [
                'label' => 'Nick',
                'constraints' => [
                    new NotBlank(message: 'Zadej nick.'),
                    new Length(max: 30, maxMessage: 'Maximálně {{ limit }} znaků.'),
                ],
            ])
            ->add('agreeTerms', CheckboxType::class, [
                // Label (s odkazem na /ochrana-osobnich-udaju) se skládá v
                // register.html.twig přes form_row(..., {label_html: true}) — potřebuje
                // Twig path(), ne natvrdo napsané URL tady.
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Pro registraci musíš souhlasit se zpracováním osobních údajů.'),
                ],
            ])
            ->add('password', NewPasswordType::class, [
                'mapped' => false
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
