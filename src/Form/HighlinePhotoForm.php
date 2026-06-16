<?php

namespace App\Form;

use App\Entity\HighlinePhoto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HighlinePhotoForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fotka',
                'required' => true,
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp,image/heic,image/heif,.heic,.heif'],
            ])
            ->add('caption', TextType::class, [
                'label' => 'Popisek',
                'required' => false,
                'attr' => ['maxlength' => 255, 'placeholder' => 'volitelně'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => HighlinePhoto::class,
            'validation_groups' => ['Default', 'upload'],
        ]);
    }
}
