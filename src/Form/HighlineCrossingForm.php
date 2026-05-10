<?php

namespace App\Form;

use App\Entity\HighlineCrossing;
use App\Enum\HighlineCrossingStyle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HighlineCrossingForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('crossedAt', DateType::class, [
                'label' => 'Datum přechodu',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'html5' => true,
            ])
            ->add('style', EnumType::class, [
                'label' => 'Styl',
                'class' => HighlineCrossingStyle::class,
                'choice_label' => fn (HighlineCrossingStyle $s) => $s->label(),
                'required' => false,
                'placeholder' => '— neuvedeno —',
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'Hodnocení',
                'choices' => [
                    '★' => 1,
                    '★★' => 2,
                    '★★★' => 3,
                    '★★★★' => 4,
                    '★★★★★' => 5,
                ],
                'required' => false,
                'placeholder' => '— bez hodnocení —',
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Komentář',
                'required' => false,
                'attr' => ['rows' => 4, 'maxlength' => 2000],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HighlineCrossing::class,
        ]);
    }
}
