<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
 *  
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *  
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *  
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Materials\Sign\UseCase\Admin\Pdf;

use BaksDev\Materials\Catalog\Repository\MaterialChoice\MaterialChoiceInterface;
use BaksDev\Materials\Catalog\Repository\MaterialModificationChoice\MaterialModificationChoiceInterface;
use BaksDev\Materials\Catalog\Repository\MaterialOfferChoice\MaterialOfferChoiceInterface;
use BaksDev\Materials\Catalog\Repository\MaterialVariationChoice\MaterialVariationChoiceInterface;
use BaksDev\Materials\Catalog\Type\Offers\ConstId\MaterialOfferConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\ConstId\MaterialVariationConst;
use BaksDev\Materials\Catalog\Type\Offers\Variation\Modification\ConstId\MaterialModificationConst;
use BaksDev\Materials\Category\Repository\CategoryChoice\CategoryMaterialChoiceInterface;
use BaksDev\Materials\Category\Type\Id\CategoryMaterialUid;
use BaksDev\Products\Product\Type\Material\MaterialUid;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileChoice\UserProfileChoiceInterface;
use BaksDev\Users\Profile\UserProfile\Repository\UserProfileTokenStorage\UserProfileTokenStorageInterface;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MaterialSignPdfForm extends AbstractType
{
    public function __construct(
        #[AutowireIterator('baks.reference.choice')] private readonly iterable $reference,
        private readonly CategoryMaterialChoiceInterface $categoryChoice,
        private readonly MaterialChoiceInterface $materialChoice,
        private readonly MaterialOfferChoiceInterface $materialOfferChoice,
        private readonly MaterialVariationChoiceInterface $materialVariationChoice,
        private readonly MaterialModificationChoiceInterface $modificationChoice,
        private readonly UserProfileChoiceInterface $userProfileChoice,
        private readonly UserProfileTokenStorageInterface $userProfileTokenStorage,
    ) {}


    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('number', TextType::class);

        $builder->add('share', CheckboxType::class);

        $builder->add('category', ChoiceType::class, [
            'choices' => $this->categoryChoice->findAll(),
            'choice_value' => function(?CategoryMaterialUid $category) {
                return $category?->getValue();
            },
            'choice_label' => function(CategoryMaterialUid $category) {
                return (is_int($category->getAttr()) ? str_repeat(' - ', $category->getAttr() - 1) : '').$category->getOptions();
            },
            'label' => false,
            'required' => false,
        ]);

        $builder->add(
            'material',
            HiddenType::class
        );

        /*$builder
            ->add('material', ChoiceType::class, [
                'choices' => $this->materialChoice->fetchAllMaterial(),
                'choice_value' => function (?MaterialUid $material) {
                    return $material?->getValue();
                },
                'choice_label' => function (MaterialUid $material) {
                    return $material->getAttr();
                },

                'choice_attr' => function (?MaterialUid $material) {
                    return $material?->getOption() ? ['data-filter' => '('.$material?->getOption().')'] : [];
                },

                'label' => false,
            ]);*/


        /** Все профили пользователя */
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function(FormEvent $event): void {

                /** @var MaterialSignPdfDTO $data */
                $data = $event->getData();
                $form = $event->getForm();

                $user = $this->userProfileTokenStorage->getUser();
                $data->setUsr($user);

                //$profile = $this->userProfileTokenStorage->getProfile();
                //$data->setProfile($profile);

                $profiles = $this->userProfileChoice->getActiveUserProfile($data->getUsr());

                $form
                    ->add('profile', ChoiceType::class, [
                        'choices' => $profiles,
                        'choice_value' => function(?UserProfileUid $profile) {
                            return $profile?->getValue();
                        },
                        'choice_label' => function(UserProfileUid $profile) {
                            return $profile->getAttr();
                        },

                        'label' => false,
                        //'required' => false,
                    ]);


                if($data->getCategory())
                {
                    $this->formMaterialModifier($event->getForm(), $data->getCategory());

                    if($data->getMaterial())
                    {
                        $this->formOfferModifier($event->getForm(), $data->getMaterial());

                        if($data->getOffer())
                        {
                            $this->formVariationModifier($event->getForm(), $data->getOffer());

                            if($data->getVariation())
                            {
                                $this->formModificationModifier($event->getForm(), $data->getVariation());
                            }
                        }
                    }
                }
            }
        );


        $builder->get('category')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $category = $event->getForm()->getData();
                $this->formMaterialModifier($event->getForm()->getParent(), $category);
            }
        );


        //        $builder->get('material')->addModelTransformer(
        //            new CallbackTransformer(
        //                function($material) {
        //                    return $material instanceof MaterialUid ? $material->getValue() : $material;
        //                },
        //                function($material) {
        //                    return $material ? new MaterialUid($material) : null;
        //                }
        //            )
        //        );


        $builder->get('material')->addModelTransformer(
            new CallbackTransformer(
                function($material) {
                    return $material instanceof MaterialUid ? $material->getValue() : $material;
                },
                function($material) {
                    return $material ? new MaterialUid($material) : null;
                }
            )
        );

        /**
         * Торговые предложения
         * @var MaterialOfferConst $offer
         */

        $builder->add(
            'offer',
            HiddenType::class
        );

        $builder->get('offer')->addModelTransformer(
            new CallbackTransformer(
                function($offer) {
                    return $offer instanceof MaterialOfferConst ? $offer->getValue() : $offer;
                },
                function($offer) {
                    return $offer ? new MaterialOfferConst($offer) : null;
                }
            )
        );


        $builder->get('material')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $material = $event->getForm()->getData();
                $this->formOfferModifier($event->getForm()->getParent(), $material);
            }
        );


        /**
         * Множественный вариант торгового предложения
         * @var MaterialVariationConst $variation
         */


        $builder->add(
            'variation',
            HiddenType::class
        );

        $builder->get('variation')->addModelTransformer(
            new CallbackTransformer(
                function($variation) {
                    return $variation instanceof MaterialVariationConst ? $variation->getValue() : $variation;
                },
                function($variation) {
                    return $variation ? new MaterialVariationConst($variation) : null;
                }
            )
        );

        $builder->get('offer')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $offer = $event->getForm()->getData();
                $this->formVariationModifier($event->getForm()->getParent(), $offer);
            }
        );


        /**
         * Модификатор множественного варианта торгового предложения
         * @var MaterialModificationConst $modification
         */

        $builder->add(
            'modification',
            HiddenType::class
        );

        $builder->get('modification')->addModelTransformer(
            new CallbackTransformer(
                function($modification) {
                    return $modification instanceof MaterialModificationConst ? $modification->getValue() : $modification;
                },
                function($modification) {
                    return $modification ? new MaterialModificationConst($modification) : null;
                }
            )
        );

        $builder->get('variation')->addEventListener(
            FormEvents::POST_SUBMIT,
            function(FormEvent $event) {
                $variation = $event->getForm()->getData();
                $this->formModificationModifier($event->getForm()->getParent(), $variation);
            }
        );


        $builder->add('files', CollectionType::class, [
            'entry_type' => MaterialSignFile\MaterialSignFileForm::class,
            'entry_options' => ['label' => false],
            'label' => false,
            'by_reference' => false,
            'allow_delete' => true,
            'allow_add' => true,
            'prototype_name' => '__pdf_file__',
        ]);

        $builder->add('purchase', CheckboxType::class, ['required' => false]);


        /* Сохранить ******************************************************/
        $builder->add(
            'material_sign_pdf',
            SubmitType::class,
            ['label' => 'Save', 'label_html' => true, 'attr' => ['class' => 'btn-primary']]
        );


    }

    public function formMaterialModifier(FormInterface $form, ?CategoryMaterialUid $category = null): void
    {
        if(null === $category)
        {
            return;
        }

        $materials = $this->materialChoice->findAll($category);

        // Если у сырья нет ТП
        if(!$materials)
        {
            return;
        }


        // Продукт
        $form
            ->add('material', ChoiceType::class, [
                'choices' => $materials,
                'choice_value' => function(?MaterialUid $material) {
                    return $material?->getValue();
                },
                'choice_label' => function(MaterialUid $material) {
                    return $material->getAttr();
                },

                'choice_attr' => function(?MaterialUid $material) {
                    return $material?->getOption() ? ['data-filter' => '('.$material?->getOption().')'] : [];
                },

                'label' => false
            ]);
    }


    public function formOfferModifier(FormInterface $form, ?MaterialUid $material = null): void
    {
        if(null === $material)
        {
            return;
        }

        $offer = $this->materialOfferChoice->findByMaterial($material);

        // Если у сырья нет ТП
        if(!$offer->valid())
        {
            return;
        }

        $currenOffer = $offer->current();
        $label = $currenOffer->getOption();
        $domain = null;

        if($currenOffer->getProperty())
        {
            /** Если торговое предложение Справочник - ищем домен переводов */
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currenOffer->getProperty())
                {
                    $domain = $reference->domain();
                }
            }
        }


        $form
            ->add('offer', ChoiceType::class, [
                'choices' => $offer,
                'choice_value' => function(?MaterialOfferConst $offer) {
                    return $offer?->getValue();
                },
                'choice_label' => function(MaterialOfferConst $offer) {
                    return $offer->getAttr();
                },

                'choice_attr' => function(?MaterialOfferConst $offer) {
                    return $offer?->getCharacteristic() ? ['data-filter' => ' ('.$offer?->getCharacteristic().')'] : [];
                },

                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
    }

    public function formVariationModifier(FormInterface $form, ?MaterialOfferConst $offer = null): void
    {

        if(null === $offer)
        {
            return;
        }

        $variations = $this->materialVariationChoice->fetchMaterialVariationByOfferConst($offer);

        // Если у сырья нет множественных вариантов
        if(!$variations->valid())
        {
            return;
        }


        $currenVariation = $variations->current();
        $label = $currenVariation->getOption();
        $domain = null;

        /** Если множественный вариант Справочник - ищем домен переводов */
        if($currenVariation->getProperty())
        {
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currenVariation->getProperty())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add('variation', ChoiceType::class, [
                'choices' => $variations,
                'choice_value' => function(?MaterialVariationConst $variation) {
                    return $variation?->getValue();
                },
                'choice_label' => function(MaterialVariationConst $variation) {
                    return $variation->getAttr();
                },
                'choice_attr' => function(?MaterialVariationConst $variation) {
                    return $variation?->getCharacteristic() ? ['data-filter' => ' ('.$variation?->getCharacteristic().')'] : [];
                },
                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
    }

    public function formModificationModifier(FormInterface $form, ?MaterialVariationConst $variation = null): void
    {
        if(null === $variation)
        {
            return;
        }

        $modifications = $this->modificationChoice->fetchMaterialModificationConstByVariationConst($variation);

        // Если у сырья нет модификаций множественных вариантов
        if(!$modifications->valid())
        {
            return;
        }

        $currenModifications = $modifications->current();
        $label = $currenModifications->getOption();
        $domain = null;

        /** Если модификация Справочник - ищем домен переводов */
        if($currenModifications->getProperty())
        {
            foreach($this->reference as $reference)
            {
                if($reference->type() === $currenModifications->getProperty())
                {
                    $domain = $reference->domain();
                }
            }
        }

        $form
            ->add('modification', ChoiceType::class, [
                'choices' => $modifications,
                'choice_value' => function(?MaterialModificationConst $modification) {
                    return $modification?->getValue();
                },
                'choice_label' => function(MaterialModificationConst $modification) {
                    return $modification->getAttr();
                },
                'choice_attr' => function(?MaterialModificationConst $modification) {
                    return $modification?->getCharacteristic() ? ['data-filter' => ' ('.$modification?->getCharacteristic().')'] : [];
                },
                'label' => $label,
                'translation_domain' => $domain,
                'placeholder' => sprintf('Выберите %s из списка...', $label),
            ]);
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MaterialSignPdfDTO::class,
            'method' => 'POST',
            'attr' => ['class' => 'w-100'],
        ]);
    }
}
