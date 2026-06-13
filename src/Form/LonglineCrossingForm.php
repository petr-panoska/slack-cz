<?php

namespace App\Form;

use App\Entity\LonglineCrossing;
use App\Enum\CrossingStyle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LonglineCrossingForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('crossedAt', DateType::class, [
                'label' => 'Datum',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'html5' => true,
            ])
            ->add('length', IntegerType::class, [
                'label' => 'Délka (m)',
                'attr' => ['min' => 1, 'inputmode' => 'numeric'],
            ])
            ->add('place', TextType::class, [
                'label' => 'Místo',
                'attr' => ['maxlength' => 120, 'placeholder' => 'např. Liberec, Bělidlo'],
            ])
            ->add('style', EnumType::class, [
                'label' => 'Styl',
                'class' => CrossingStyle::class,
                // Same enum as highline crossings, minus the leash-only styles
                // (swami / solo / kotník) that don't apply to a longline.
                'choices' => array_filter(
                    CrossingStyle::cases(),
                    static fn (CrossingStyle $s): bool => $s->appliesToLongline(),
                ),
                'choice_label' => fn (CrossingStyle $s) => $s->label(),
                'required' => false,
                'placeholder' => '— neuvedeno —',
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
            'data_class' => LonglineCrossing::class,
        ]);
    }
}
