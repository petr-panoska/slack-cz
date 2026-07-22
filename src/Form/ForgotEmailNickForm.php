<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class ForgotEmailNickForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('nick', TextType::class, [
            'attr' => ['autocomplete' => 'username'],
            'constraints' => [
                new NotBlank(message: 'Zadej svůj nick.'),
            ],
        ]);
    }
}
