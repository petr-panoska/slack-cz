<?php

namespace App\Form;

use App\Entity\LineCrossing;
use App\Enum\CrossingStyle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LineCrossingForm extends AbstractType
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
                'class' => CrossingStyle::class,
                'choice_label' => fn (CrossingStyle $s) => $s->label(),
                'required' => false,
                'placeholder' => '— neuvedeno —',
            ])
            // Rendered as a click-to-set star widget (rating-picker Stimulus controller,
            // see crossing/form.html.twig) — HiddenType so form_widget() emits a plain
            // <input type="hidden">, not a <select>.
            ->add('rating', HiddenType::class, [
                'required' => false,
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
            'data_class' => LineCrossing::class,
        ]);
    }
}
