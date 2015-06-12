<?php

namespace OroB2B\Bundle\RFPAdminBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\CurrencyBundle\Form\Type\PriceType;

use OroB2B\Bundle\ProductBundle\Form\Type\ProductUnitSelectionType;
use OroB2B\Bundle\RFPAdminBundle\Entity\RequestProductItem;

class RequestProductItemType extends AbstractType
{
    const NAME = 'orob2b_rfp_admin_request_product_item';

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param TranslatorInterface $translator
     */
    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('quantity', 'integer', [
                'required' => true,
                'label' => 'orob2b.rfpadmin.requestproductitem.quantity.label'
            ])
            ->add('price', PriceType::NAME, [
                'required' => true,
                'label' => 'orob2b.rfpadmin.requestproductitem.price.label'
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'preSetData']);
        $builder->addEventListener(FormEvents::PRE_SUBMIT, [$this, 'preSubmit']);
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => 'OroB2B\Bundle\RFPAdminBundle\Entity\RequestProductItem',
            'intention' => 'rfp_admin_request_product_item',
            'extra_fields_message' => 'This form should not contain extra fields: "{{ extra_fields }}"'
        ]);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @param FormEvent $event
     */
    public function preSetData(FormEvent $event)
    {
        /** @var $requestProductItem RequestProductItem */
        $requestProductItem = $event->getData();
        $form = $event->getForm();
        $choices = null;

        $productUnitOptons = [
            'compact' => false,
            'disabled' => false,
            'label' => 'orob2b.product.productunit.entity_label',
            'required' => true
        ];

        if ($requestProductItem && null !== $requestProductItem->getId()) {
            $product = $requestProductItem->getRequestProduct()->getProduct();
            if ($product) {
                $choices = [];
                foreach ($product->getUnitPrecisions() as $unitPrecision) {
                    $choices[] = $unitPrecision->getUnit();
                }
            }
            $productUnit = $requestProductItem->getProductUnit();
            if (!$productUnit || ($product && !in_array($productUnit->getCode(), $choices))) {
                // ProductUnit was removed
                $productUnitOptons['empty_value'] =  $this->translator->trans(
                    'orob2b.rfpadmin.message.requestproductitem.unit.removed',
                    ['{title}' => $requestProductItem->getProductUnitCode()]
                );
            }
        }
        $productUnitOptons['choices'] = $choices;
        $form->add(
            'productUnit',
            ProductUnitSelectionType::NAME,
            $productUnitOptons
        );
    }

    /**
     * @param FormEvent $event
     */
    public function preSubmit(FormEvent $event)
    {
        $event->getForm()->add(
            'productUnit',
            ProductUnitSelectionType::NAME,
            [
                'compact' => false,
                'disabled' => false,
                'label' => 'orob2b.product.productunit.entity_label'
            ]
        );
    }
}
