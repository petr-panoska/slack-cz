<?php

namespace App\Form;

use App\Entity\Highline;
use App\Enum\HighlineType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class HighlineForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Název',
                'attr' => ['maxlength' => 150, 'autofocus' => true],
            ])
            ->add('type', EnumType::class, [
                'label' => 'Typ',
                'class' => HighlineType::class,
                'choice_label' => fn (HighlineType $t) => $t->label(),
            ])
            ->add('height', IntegerType::class, [
                'label' => 'Výška [m]',
                'attr' => ['min' => 0],
            ])
            ->add('point1Latitude', NumberType::class, [
                'label' => 'Bod 1 — šířka',
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => -90, 'max' => 90, 'inputmode' => 'decimal'],
                'constraints' => [new NotBlank(message: 'Nastav oba kotvící body lajny.')],
            ])
            ->add('point1Longitude', NumberType::class, [
                'label' => 'Bod 1 — délka',
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => -180, 'max' => 180, 'inputmode' => 'decimal'],
                'constraints' => [new NotBlank(message: 'Nastav oba kotvící body lajny.')],
            ])
            ->add('point2Latitude', NumberType::class, [
                'label' => 'Bod 2 — šířka',
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => -90, 'max' => 90, 'inputmode' => 'decimal'],
                'constraints' => [new NotBlank(message: 'Nastav oba kotvící body lajny.')],
            ])
            ->add('point2Longitude', NumberType::class, [
                'label' => 'Bod 2 — délka',
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => -180, 'max' => 180, 'inputmode' => 'decimal'],
                'constraints' => [new NotBlank(message: 'Nastav oba kotvící body lajny.')],
            ])
            ->add('country', TextType::class, [
                'label' => 'Země',
                'required' => false,
                'attr' => ['maxlength' => 100, 'placeholder' => 'Česko'],
            ])
            ->add('region', TextType::class, [
                'label' => 'Kraj / region',
                'required' => false,
                'attr' => ['maxlength' => 100],
            ])
            ->add('area', TextType::class, [
                'label' => 'Oblast',
                'required' => false,
                'attr' => ['maxlength' => 150, 'placeholder' => 'např. Tisá'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Popis',
                'required' => false,
                'attr' => ['rows' => 5, 'maxlength' => 5000],
            ])
            ->add('pointOneInfo', TextType::class, [
                'label' => 'Kotvení — bod 1',
                'required' => false,
                'attr' => ['maxlength' => 512],
            ])
            ->add('pointTwoInfo', TextType::class, [
                'label' => 'Kotvení — bod 2',
                'required' => false,
                'attr' => ['maxlength' => 512],
            ])
            ->add('anchoring', TextType::class, [
                'label' => 'Typ kotvení',
                'required' => false,
                'attr' => ['maxlength' => 50],
            ])
            ->add('approachMinutes', IntegerType::class, [
                'label' => 'Přístup [min]',
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('tensioningMinutes', IntegerType::class, [
                'label' => 'Napínání [min]',
                'required' => false,
                'attr' => ['min' => 0],
            ])
            ->add('firstAscentBy', TextType::class, [
                'label' => 'Autor 1. napnutí',
                'required' => false,
                'attr' => ['maxlength' => 150],
            ])
            ->add('firstAscentDate', DateType::class, [
                'label' => 'Datum 1. napnutí',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('nameHistory', TextareaType::class, [
                'label' => 'Historie názvu',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 1000],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Highline::class,
        ]);
    }
}
