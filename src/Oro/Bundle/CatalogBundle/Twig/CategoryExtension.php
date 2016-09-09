<?php

namespace Oro\Bundle\CatalogBundle\Twig;

use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\CatalogBundle\JsTree\CategoryTreeHandler;

class CategoryExtension extends \Twig_Extension
{
    const NAME = 'oro_catalog_category_extension';

    /**
     * @var CategoryTreeHandler
     */
    protected $categoryTreeHandler;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param CategoryTreeHandler $categoryTreeHandler
     * @param TranslatorInterface $translator
     */
    public function __construct(CategoryTreeHandler $categoryTreeHandler, TranslatorInterface $translator)
    {
        $this->categoryTreeHandler = $categoryTreeHandler;
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('oro_category_list', [$this, 'getCategoryList']),
        ];
    }

    /**
     * @param string|null $rootLabel
     * @return array
     */
    public function getCategoryList($rootLabel = null)
    {
        $tree = $this->categoryTreeHandler->createTree();
        if ($rootLabel && array_key_exists(0, $tree)) {
            $tree[0]['text'] = $this->translator->trans($rootLabel);
        }

        return $tree;
    }
}
