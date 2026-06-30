<?php

namespace App\Form;

use App\Entity\Line;
use App\Enum\LineType;
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

class LineForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Once a line is verified the name becomes immutable — keeping it stable
        // protects the slug (and therefore every existing inbound link) from drifting.
        $data = $builder->getData();
        $nameLocked = $data instanceof Line && $data->isVerified();
        $nameAttrs = ['maxlength' => 150];
        if ($nameLocked) {
            $nameAttrs['title'] = sprintf(
                'URL: /lajna/%s — název je pevný, aby existující odkazy nepřestaly fungovat.',
                $data->getSlug() ?? '',
            );
        } else {
            $nameAttrs['autofocus'] = true;
        }

        $builder
            ->add('name', TextType::class, [
                'label' => 'Název',
                'attr' => $nameAttrs,
                'disabled' => $nameLocked,
            ])
            ->add('type', EnumType::class, [
                'label' => 'Typ',
                'class' => LineType::class,
                'choice_label' => fn (LineType $t) => $t->label(),
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
            ->add('parkingLatitude', NumberType::class, [
                'label' => 'Parkování — šířka',
                'required' => false,
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => -90, 'max' => 90, 'inputmode' => 'decimal'],
            ])
            ->add('parkingLongitude', NumberType::class, [
                'label' => 'Parkování — délka',
                'required' => false,
                'scale' => 7,
                'html5' => true,
                'attr' => ['step' => 'any', 'min' => -180, 'max' => 180, 'inputmode' => 'decimal'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Popis',
                'required' => false,
                'attr' => ['rows' => 5, 'maxlength' => 5000],
            ])
            ->add('pointOneInfo', TextareaType::class, [
                'label' => 'Kotvení — bod 1',
                'required' => false,
                'attr' => ['maxlength' => 512, 'rows' => 3],
            ])
            ->add('pointTwoInfo', TextareaType::class, [
                'label' => 'Kotvení — bod 2',
                'required' => false,
                'attr' => ['maxlength' => 512, 'rows' => 3],
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
            ->add('firstAscentBy', TextareaType::class, [
                'label' => 'Autor 1. napnutí',
                'required' => false,
                'attr' => ['maxlength' => 150, 'rows' => 2],
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
            'data_class' => Line::class,
        ]);
    }
}
