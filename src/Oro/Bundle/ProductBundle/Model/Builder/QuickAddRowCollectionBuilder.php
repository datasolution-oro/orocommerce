<?php

namespace Oro\Bundle\ProductBundle\Model\Builder;

use Box\Spout\Common\Entity\Row;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Common\Type;
use Box\Spout\Reader\Common\Creator\ReaderFactory;
use Box\Spout\Reader\ReaderInterface;
use Doctrine\ORM\EntityRepository;
use Oro\Bundle\ProductBundle\Entity\Manager\ProductManager;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;
use Oro\Bundle\ProductBundle\Form\Type\QuickAddType;
use Oro\Bundle\ProductBundle\Helper\ProductGrouper\ProductsGrouperFactory;
use Oro\Bundle\ProductBundle\Model\QuickAddRowCollection;
use Oro\Bundle\ProductBundle\Storage\ProductDataStorage;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

/**
 * Creates QuickAddRowCollection based on either request, file or text.
 */
class QuickAddRowCollectionBuilder
{
    /**
     * @var EntityRepository|ProductRepository
     */
    protected $productRepository;

    /**
     * @var ProductManager
     */
    protected $productManager;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var QuickAddRowInputParser
     */
    protected $quickAddRowInputParser;

    /**
     * @var AclHelper
     */
    private $aclHelper;

    private ?ProductsGrouperFactory $productsGrouperFactory = null;

    public function __construct(
        EntityRepository $productRepository,
        ProductManager $productManager,
        EventDispatcherInterface $eventDispatcher,
        QuickAddRowInputParser $quickAddRowInputParser,
        AclHelper $aclHelper
    ) {
        $this->productRepository = $productRepository;
        $this->productManager = $productManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->quickAddRowInputParser = $quickAddRowInputParser;
        $this->aclHelper = $aclHelper;
    }

    public function setProductsGrouperFactory(?ProductsGrouperFactory $productsGrouperFactory): void
    {
        $this->productsGrouperFactory = $productsGrouperFactory;
    }

    /**
     * @param Request $request
     * @return QuickAddRowCollection
     */
    public function buildFromRequest(Request $request)
    {
        $formData = $request->request->get(QuickAddType::NAME);

        return $this->buildFromArray($formData[QuickAddType::PRODUCTS_FIELD_NAME] ?? []);
    }

    public function buildFromArray(array $products): QuickAddRowCollection
    {
        $collection = new QuickAddRowCollection();
        $collection->setEventDispatcher($this->eventDispatcher);

        if (!$products) {
            return $collection;
        }

        if ($this->productsGrouperFactory) {
            $products = $this->productsGrouperFactory
                ->createProductsGrouper(ProductsGrouperFactory::ARRAY_PRODUCTS)
                ->process($products);
        }

        foreach ($products as $index => $product) {
            if (!array_key_exists(ProductDataStorage::PRODUCT_SKU_KEY, $product) ||
                !array_key_exists(ProductDataStorage::PRODUCT_QUANTITY_KEY, $product)
            ) {
                continue;
            }

            $quickAddRow = $this->quickAddRowInputParser->createFromRequest($product, $index);
            $quickAddRow->setValid(true);
            $collection->add($quickAddRow);
        }

        $this->mapProducts($collection);

        return $collection;
    }

    /**
     * @param UploadedFile $file
     * @return QuickAddRowCollection
     * @throws UnsupportedTypeException
     */
    public function buildFromFile(UploadedFile $file)
    {
        $lineNumber = 0;
        $collection = new QuickAddRowCollection();
        $collection->setEventDispatcher($this->eventDispatcher);

        $reader = $this->createReaderForFile($file);
        $reader->open($file->getRealPath());

        foreach ($reader->getSheetIterator() as $sheet) {
            /** @var Row $row */
            foreach ($sheet->getRowIterator() as $row) {
                $row = $row->toArray();
                if (0 === $lineNumber) {
                    $lineNumber++;
                    continue;
                }

                $collection->add(
                    $this->quickAddRowInputParser->createFromFileLine($row, $lineNumber++)
                );
            }
        }

        if ($this->productsGrouperFactory) {
            $collection = $this->productsGrouperFactory
                ->createProductsGrouper(ProductsGrouperFactory::QUICK_ADD_ROW)
                ->process($collection);
        }

        $this->mapProducts($collection);

        return $collection;
    }

    /**
     * @param string $text
     * @return QuickAddRowCollection
     */
    public function buildFromCopyPasteText($text)
    {
        $collection = new QuickAddRowCollection();
        $collection->setEventDispatcher($this->eventDispatcher);
        $lineNumber = 1;

        $text = trim($text);
        if ($text) {
            $delimiter = null;
            foreach (explode(PHP_EOL, $text) as $line) {
                $line = trim($line);
                if ($delimiter === null) {
                    $delimiter = $this->detectDelimiter($line);
                }
                $data = preg_split('/' . preg_quote($delimiter, '/') . '+/', $line);
                $collection->add(
                    $this->quickAddRowInputParser->createFromCopyPasteTextLine($data, $lineNumber++)
                );
            }

            if ($this->productsGrouperFactory) {
                $collection = $this->productsGrouperFactory
                    ->createProductsGrouper(ProductsGrouperFactory::QUICK_ADD_ROW)
                    ->process($collection);
            }
        }

        $this->mapProducts($collection);

        return $collection;
    }

    private function detectDelimiter(string $line): string
    {
        foreach (["\t", ';', ' ',  ','] as $delimiter) {
            $data = preg_split('/' . preg_quote($delimiter, '/') . '+/', $line, 2);
            if ($data[0] !== $line) {
                break;
            }
        }

        return $delimiter;
    }

    /**
     * @param string[] $skus
     * @return Product[]
     */
    private function getRestrictedProductsBySkus(array $skus)
    {
        $qb = $this->productRepository->getProductWithNamesBySkuQueryBuilder($skus);
        $restricted = $this->productManager->restrictQueryBuilder($qb, []);

        // Configurable products require additional option selection is not implemented yet
        // Thus we need to hide configurable products from the product drop-downs
        $restricted->andWhere($restricted->expr()->neq('product.type', ':configurable_type'))
            ->setParameter('configurable_type', Product::TYPE_CONFIGURABLE);

        $query = $this->aclHelper->apply($restricted);

        /** @var Product[] $products */
        $products = $query->execute();
        $productsBySku = [];

        foreach ($products as $product) {
            $productsBySku[mb_strtoupper($product->getSku())] = $product;
        }

        return $productsBySku;
    }

    /**
     * @param UploadedFile $file
     * @return ReaderInterface
     * @throws UnsupportedTypeException
     */
    private function createReaderForFile(UploadedFile $file)
    {
        switch ($file->getClientOriginalExtension()) {
            case 'csv':
                return ReaderFactory::createFromType(Type::CSV);
            case 'ods':
                return ReaderFactory::createFromType(Type::ODS);
            case 'xlsx':
                return ReaderFactory::createFromType(Type::XLSX);
        }

        throw new UnsupportedTypeException();
    }

    private function mapProducts(QuickAddRowCollection $collection)
    {
        $products = $this->getRestrictedProductsBySkus($collection->getSkus());
        $collection->mapProducts($products);
    }
}
