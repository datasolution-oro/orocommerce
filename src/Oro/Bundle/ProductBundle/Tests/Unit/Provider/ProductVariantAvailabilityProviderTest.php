<?php

namespace Oro\Bundle\ProductBundle\Tests\Unit\Provider;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityExtendBundle\Provider\EnumValueProvider;
use Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures\TestEnumValue;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Entity\Repository\ProductRepository;
use Oro\Bundle\ProductBundle\Event\RestrictProductVariantEvent;
use Oro\Bundle\ProductBundle\ProductVariant\Registry\ProductVariantFieldValueHandlerRegistry;
use Oro\Bundle\ProductBundle\ProductVariant\VariantFieldValueHandler\BooleanVariantFieldValueHandler;
use Oro\Bundle\ProductBundle\ProductVariant\VariantFieldValueHandler\EnumVariantFieldValueHandler;
use Oro\Bundle\ProductBundle\Provider\CustomFieldProvider;
use Oro\Bundle\ProductBundle\Provider\ProductVariantAvailabilityProvider;
use Oro\Bundle\ProductBundle\Tests\Unit\Stub\ProductProxyStub;
use Oro\Bundle\ProductBundle\Tests\Unit\Stub\ProductStub;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Component\Testing\ReflectionUtil;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 */
class ProductVariantAvailabilityProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var DoctrineHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $doctrineHelper;

    /** @var ProductRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $productRepository;

    /** @var QueryBuilder|\PHPUnit\Framework\MockObject\MockObject */
    private $qb;

    /** @var AbstractQuery|\PHPUnit\Framework\MockObject\MockObject */
    private $query;

    /** @var CustomFieldProvider|\PHPUnit\Framework\MockObject\MockObject */
    private $customFieldProvider;

    /** @var EnumVariantFieldValueHandler|\PHPUnit\Framework\MockObject\MockObject */
    private $enumHandler;

    /** @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $dispatcher;

    /** @var TranslatorInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $translator;

    /** @var AclHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $aclHelper;

    /** @var ProductVariantAvailabilityProvider */
    private $availabilityProvider;

    protected function setUp(): void
    {
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->customFieldProvider = $this->createMock(CustomFieldProvider::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->aclHelper = $this->createMock(AclHelper::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->productRepository = $this->createMock(ProductRepository::class);

        $this->doctrineHelper->expects($this->any())
            ->method('getEntityRepositoryForClass')
            ->with(Product::class)
            ->willReturn($this->productRepository);
        $this->doctrineHelper->expects($this->any())
            ->method('getSingleEntityIdentifier')
            ->willReturnCallback(function ($variantValue) {
                return $variantValue->getId();
            });

        $this->enumHandler = $this->getMockBuilder(EnumVariantFieldValueHandler::class)
            ->setConstructorArgs([
                $this->doctrineHelper,
                $this->createMock(EnumValueProvider::class),
                $this->createMock(LoggerInterface::class),
                $this->createMock(ConfigManager::class),
                $this->createMock(LocalizationHelper::class),
                $this->createMock(LocaleSettings::class)
            ])
            ->onlyMethods(['getPossibleValues'])
            ->getMock();

        $variantFieldValueHandlerRegistry = new ProductVariantFieldValueHandlerRegistry();
        $variantFieldValueHandlerRegistry->addHandler(new BooleanVariantFieldValueHandler($this->translator));
        $variantFieldValueHandlerRegistry->addHandler($this->enumHandler);

        $this->query = $this->createMock(AbstractQuery::class);
        $this->qb = $this->createMock(QueryBuilder::class);
        $this->qb->expects($this->any())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->availabilityProvider = new ProductVariantAvailabilityProvider(
            $this->doctrineHelper,
            $this->customFieldProvider,
            PropertyAccess::createPropertyAccessor(),
            $this->dispatcher,
            $variantFieldValueHandlerRegistry,
            $this->aclHelper
        );
    }

    private function getConfigurableProduct(int $id): Product
    {
        $configurableProduct = new ProductStub();
        $configurableProduct->setId($id);
        $configurableProduct->setType(Product::TYPE_CONFIGURABLE);

        return $configurableProduct;
    }

    private function getSimpleProduct(int $id): Product
    {
        $simpleProduct = new ProductStub();
        $simpleProduct->setId($id);
        $simpleProduct->setType(Product::TYPE_SIMPLE);

        return $simpleProduct;
    }

    private function getProductProxy(int $id, bool $initialized, string $type = null): ProductProxyStub
    {
        $productProxy = new ProductProxyStub();
        ReflectionUtil::setId($productProxy, $id);
        $productProxy->setInitialized($initialized);
        if (null !== $type) {
            $productProxy->setType($type);
        }

        return $productProxy;
    }

    private function setUpRepositoryResult(Product $configurableProduct, array $variantParameters, array $result): void
    {
        $this->productRepository->expects($this->once())
            ->method('getSimpleProductsByVariantFieldsQueryBuilder')
            ->with($configurableProduct, $variantParameters)
            ->willReturn($this->qb);

        $this->query->expects($this->once())
            ->method('getResult')
            ->willReturn($result);
    }

    public function testGetSimpleProductsByVariantFields()
    {
        $configurableProduct = $this->getConfigurableProduct(100);
        $variantParameters = [
            'size' => 's',
            'color' => 'red',
            'slim_fit' => true
        ];

        $expected = [
            $this->getSimpleProduct(1),
            $this->getSimpleProduct(2)
        ];

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RestrictProductVariantEvent::class), RestrictProductVariantEvent::NAME);

        $this->setUpRepositoryResult($configurableProduct, $variantParameters, $expected);

        $this->assertSame(
            $expected,
            $this->availabilityProvider->getSimpleProductsByVariantFields($configurableProduct, $variantParameters)
        );

        // Check that second call does not lead to second request and result is returned from the cache
        $this->assertSame(
            $expected,
            $this->availabilityProvider->getSimpleProductsByVariantFields($configurableProduct, $variantParameters)
        );
    }

    public function testGetSimpleProductByVariantFields()
    {
        $configurableProduct = $this->getConfigurableProduct(100);

        $product1 = $this->getSimpleProduct(1);
        $products = [$product1];

        $this->setUpRepositoryResult($configurableProduct, [], $products);

        $this->assertSame(
            $product1,
            $this->availabilityProvider->getSimpleProductByVariantFields($configurableProduct)
        );
    }

    public function testGetSimpleProductByVariantFieldsSeveralProducts()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Variant values provided don't match exactly one simple product");

        $configurableProduct = $this->getConfigurableProduct(100);
        $products = [
            $this->getSimpleProduct(1),
            $this->getSimpleProduct(2)
        ];

        $this->setUpRepositoryResult($configurableProduct, [], $products);

        $this->availabilityProvider->getSimpleProductByVariantFields($configurableProduct);
    }

    public function testGetSimpleProductByVariantFieldsNoProducts()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Variant values provided don't match exactly one simple product");

        $configurableProduct = $this->getConfigurableProduct(100);
        $products = [];

        $this->setUpRepositoryResult($configurableProduct, [], $products);

        $this->availabilityProvider->getSimpleProductByVariantFields($configurableProduct);
    }

    public function testGetSimpleProductByVariantFieldsWrongProductType()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product with type "configurable" expected, "simple" given');

        $configurableProduct = $this->getSimpleProduct(1);

        $this->productRepository->expects($this->never())
            ->method('getSimpleProductsByVariantFieldsQueryBuilder');

        $this->availabilityProvider->getSimpleProductByVariantFields($configurableProduct);
    }

    public function testGetSimpleProductIdsByVariantFieldsGroupedByConfigurable()
    {
        $configurableProductIds = [100, 101, 102];
        $queryResult = [
            ['productId' => 1, 'parentProductId' => 100],
            ['productId' => 3, 'parentProductId' => 102],
            ['productId' => 2, 'parentProductId' => 100]
        ];

        $query = $this->createMock(AbstractQuery::class);
        $qb = $this->createMock(QueryBuilder::class);
        $this->productRepository->expects($this->once())
            ->method('getSimpleProductIdsByParentProductsQueryBuilder')
            ->with($configurableProductIds)
            ->willReturn($qb);
        $qb->expects($this->once())
            ->method('select')
            ->with('p.id AS productId, IDENTITY(l.parentProduct) AS parentProductId')
            ->willReturnSelf();
        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);
        $query->expects($this->once())
            ->method('getArrayResult')
            ->willReturn($queryResult);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new RestrictProductVariantEvent($qb), RestrictProductVariantEvent::NAME);

        $this->assertSame(
            [
                100 => [1, 2],
                102 => [3]
            ],
            $this->availabilityProvider->getSimpleProductIdsByVariantFieldsGroupedByConfigurable(
                $configurableProductIds
            )
        );
    }

    public function testGetSimpleProductIdsByVariantFieldsGroupedByConfigurableWhenConfigurableProductIdsAreEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The list of configurable product IDs must not be empty.');

        $this->productRepository->expects($this->never())
            ->method('getSimpleProductIdsByParentProductsQueryBuilder');
        $this->dispatcher->expects($this->never())
            ->method('dispatch');

        $this->availabilityProvider->getSimpleProductIdsByVariantFieldsGroupedByConfigurable([]);
    }

    public function testGetVariantFieldsValuesForVariant()
    {
        $configurableProduct = $this->getConfigurableProduct(100);
        $configurableProduct->setVariantFields(['color', 'new']);

        $variantsData = [
            'color' => [
                'type' => 'enum',
                'values' => [
                    'red' => 'Red',
                    'green' => 'Green',
                    'blue' => 'Blue',
                ]
            ],
            'new' => [
                'type' => 'boolean',
            ]
        ];

        $expectedProductSku = 'product1';
        $productData = [
            $expectedProductSku => [
                'color' => 'red',
                'new' => true
            ]
        ];

        $this->customFieldProvider->expects($this->any())
            ->method('getEntityCustomFields')
            ->with(Product::class)
            ->willReturn($variantsData);

        $simpleProducts = $this->getSimpleProductsWithVariants($variantsData, $productData);
        $simpleProduct = reset($simpleProducts);

        $actualFields = $this->availabilityProvider
            ->getVariantFieldsValuesForVariant($configurableProduct, $simpleProduct);

        $this->assertEquals($productData[$expectedProductSku], $actualFields);
    }

    /**
     * @dataProvider variantFieldsAvailabilityProvider
     */
    public function testGetVariantFieldsAvailability(
        array $variantsData,
        array $productData,
        array $variantParameters,
        array $variantFields,
        array $expected
    ) {
        $configurableProduct = $this->getConfigurableProduct(100);
        $configurableProduct->setVariantFields($variantFields);

        $this->configureMocks($variantsData, $productData);

        $this->assertEquals(
            $expected,
            $this->availabilityProvider->getVariantFieldsAvailability($configurableProduct, $variantParameters)
        );
    }

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function variantFieldsAvailabilityProvider(): array
    {
        return [
            'variant 1' => [
                'variantsData' => [
                    'color' => [
                        'type' => 'enum',
                        'values' => [
                            'Red' => 'red',
                            'Green' => 'green',
                            'Blue' => 'blue',
                        ]
                    ],
                    'size' => [
                        'type' => 'enum',
                        'values' => [
                            'S' => 's',
                            'M' => 'm',
                            'L' => 'l',
                            'XL' => 'xl',
                        ]
                    ],
                    'slim_fit' => [
                        'type' => 'boolean',
                        'values' => [0 => false, 1 => true]
                    ],
                    'extended_field' => [
                        'type' => 'string',
                    ],
                ],
                'productData' => [
                    'product1' => [
                        'color' => 'Red',
                        'size' => 'S',
                        'slim_fit' => true,
                    ],
                    'product2' => [
                        'color' => 'Green',
                        'size' => 'M',
                        'slim_fit' => false,
                    ],
                ],
                'variantParameters' => [
                    'size' => 'S',
                    'color' => 'Red',
                    'slim_fit' => true
                ],
                'variantFields' => [
                    'color',
                    'size',
                    'slim_fit',
                ],
                'expected' => [
                    'size' => [
                        'S' => true,
                        'M' => false,
                        'L' => false,
                        'XL' => false,
                    ],
                    'color' => [
                        'Red' => true,
                        'Green' => false,
                        'Blue' => false,
                    ],
                    'slim_fit' => [
                        0 => false,
                        1 => true
                    ]
                ],
            ],
            'variant 2' => [
                'variantsData' => [
                    'color' => [
                        'type' => 'enum',
                        'values' => [
                            'Red' => 'red',
                            'Green' => 'green',
                            'Blue' => 'blue',
                        ]
                    ],
                    'extended_field' => [
                        'type' => 'string',
                    ],
                ],
                'productData' => [
                    'product1' => [
                        'color' => 'Red'
                    ],
                    'product2' => [
                        'color' => 'Green'
                    ],
                    'product3' => [
                        'color' => null
                    ],
                ],
                'variantParameters' => [
                    'color' => 'Green',
                ],
                'variantFields' => [
                    'color',
                ],
                'expected' => [
                    'color' => [
                        'Red' => false,
                        'Green' => true,
                        'Blue' => false,
                    ]
                ],
            ],
        ];
    }

    public function testGetSimpleProductsGroupedByConfigurableNoConfigurable()
    {
        $products = [
            $this->getSimpleProduct(1)
        ];

        $this->assertSame([], $this->availabilityProvider->getSimpleProductsGroupedByConfigurable($products));
    }

    public function testGetSimpleProductsGroupedByConfigurableNoSimple()
    {
        $products = [
            $this->getConfigurableProduct(1)
        ];

        $simpleProductResult = [];
        $this->assertGetSimpleProductsCall($simpleProductResult);

        $this->assertSame([], $this->availabilityProvider->getSimpleProductsGroupedByConfigurable($products));
    }

    public function testGetSimpleProductsGroupedByConfigurable()
    {
        $products = [
            $this->getConfigurableProduct(1),
            $this->getConfigurableProduct(2),
            $this->getConfigurableProduct(3)
        ];

        $simpleProductResult = [
            ['id' => 11],
            ['id' => 12],
            ['id' => 13]
        ];
        $this->assertGetSimpleProductsCall($simpleProductResult);

        $variantsMapping = [
            11 => [1],
            12 => [2],
            13 => [1,2]
        ];
        $this->productRepository->expects($this->once())
            ->method('getVariantsMapping')
            ->with($products)
            ->willReturn($variantsMapping);

        $expected = [
            1 => [
                $this->getSimpleProduct(11),
                $this->getSimpleProduct(13)
            ],
            2 => [
                $this->getSimpleProduct(12),
                $this->getSimpleProduct(13)
            ]
        ];
        $this->assertEquals($expected, $this->availabilityProvider->getSimpleProductsGroupedByConfigurable($products));
    }

    public function testGetSimpleProductsByConfigurable()
    {
        $simpleProductResult = [
            ['id' => 1],
            ['id' => 2]
        ];

        $this->assertGetSimpleProductsCall($simpleProductResult);

        $products = [
            $this->getConfigurableProduct(11),
            $this->getConfigurableProduct(12)
        ];

        $expected = [
            $this->getSimpleProduct(1),
            $this->getSimpleProduct(2)
        ];

        $this->assertEquals(
            $expected,
            $this->availabilityProvider->getSimpleProductsByConfigurable($products)
        );
    }

    public function testFilterConfigurableProductsAllInitialized()
    {
        $loadedConfigurableProduct = $this->getConfigurableProduct(1);
        $loadedConfigurableProxy = $this->getProductProxy(2, true, Product::TYPE_CONFIGURABLE);
        $simpleProduct = $this->getSimpleProduct(11);

        $this->productRepository->expects($this->never())
            ->method($this->anything());

        $products = [
            $loadedConfigurableProduct,
            $loadedConfigurableProxy,
            $simpleProduct
        ];

        $expected = [
            $loadedConfigurableProduct,
            $loadedConfigurableProxy
        ];

        $this->assertEquals(
            $expected,
            $this->availabilityProvider->filterConfigurableProducts($products)
        );
    }

    public function testFilterConfigurableProductsNotAllInitialized()
    {
        $loadedConfigurableProduct = $this->getConfigurableProduct(1);
        $loadedConfigurableProxy = $this->getProductProxy(2, true, Product::TYPE_CONFIGURABLE);
        $loadedSimpleProxy = $this->getProductProxy(3, true, Product::TYPE_SIMPLE);
        $notLoadedProxy1 = $this->getProductProxy(4, false);
        $notLoadedProxy2 = $this->getProductProxy(5, false);
        $simpleProduct = $this->getSimpleProduct(11);

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getArrayResult')
            ->willReturn([['id' => 5]]);
        $queryBuilder = $this->createMock(QueryBuilder::class);

        $this->productRepository->expects($this->once())
            ->method('getConfigurableProductIdsQueryBuilder')
            ->with([$notLoadedProxy1, $notLoadedProxy2])
            ->willReturn($queryBuilder);

        $this->aclHelper->expects($this->once())
            ->method('apply')
            ->with($queryBuilder)
            ->willReturn($query);

        $products = [
            $loadedConfigurableProduct,
            $loadedConfigurableProxy,
            $loadedSimpleProxy,
            $notLoadedProxy1,
            $notLoadedProxy2,
            $simpleProduct
        ];

        $expected = [
            $loadedConfigurableProduct,
            $loadedConfigurableProxy,
            $notLoadedProxy2
        ];

        $this->assertEquals(
            $expected,
            $this->availabilityProvider->filterConfigurableProducts($products)
        );
    }

    private function configureMocks(array $variantsData, array $productData): void
    {
        $this->customFieldProvider->expects($this->any())
            ->method('getEntityCustomFields')
            ->with(Product::class)
            ->willReturn($variantsData);

        $this->enumHandler->expects($this->any())
            ->method('getPossibleValues')
            ->willReturnCallback(function ($fieldName) use ($variantsData) {
                return $variantsData[$fieldName]['values'] ?? [];
            });

        $this->translator->expects($this->any())
            ->method('trans')
            ->willReturnCallback(function ($message) {
                return $message . '.trans';
            });

        $this->configureRepositoryMock($variantsData, $productData);
    }

    private function configureRepositoryMock(array $variantsData, array $productData): void
    {
        $simpleProducts = $this->getSimpleProductsWithVariants($variantsData, $productData);

        $this->productRepository
            ->expects($this->any())
            ->method('getSimpleProductsByVariantFieldsQueryBuilder')
            ->willReturnCallback(function ($configurableProduct, array $variantParameters) use ($simpleProducts) {
                $filteredProducts = array_filter(
                    $simpleProducts,
                    function (Product $simpleProduct) use ($variantParameters) {
                        foreach ($variantParameters as $name => $value) {
                            if ($simpleProduct->{$name} != $value) {
                                return false;
                            }
                        }

                        return true;
                    }
                );

                $queryAvailableSimpleProducts = $this->createMock(AbstractQuery::class);
                $queryAvailableSimpleProducts->expects($this->any())
                    ->method('getResult')
                    ->willReturn($filteredProducts);
                $qbAvailableSimpleProducts = $this->createMock(QueryBuilder::class);
                $qbAvailableSimpleProducts->expects($this->any())
                    ->method('getQuery')
                    ->willReturn($queryAvailableSimpleProducts);

                return $qbAvailableSimpleProducts;
            });
    }

    /**
     * @return Product[]
     */
    private function getSimpleProductsWithVariants(array $variantsData, array $productData): array
    {
        $products = [];
        $i = 0;
        foreach ($productData as $sku => $data) {
            $product = $this->getSimpleProduct(++$i);
            $product->setSku($sku);
            $products[] = $product;

            foreach ($data as $field => $value) {
                switch ($variantsData[$field]['type']) {
                    case 'enum':
                        $fieldValue = $value ? new TestEnumValue($value, $value) : null;
                        break;
                    case 'boolean':
                        $fieldValue = $value;
                        break;
                    default:
                        throw new \InvalidArgumentException('Unknown type');
                }

                $product->{$field} = $fieldValue;
            }
        }

        return $products;
    }

    private function assertGetSimpleProductsCall(array $simpleProductResult): void
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getArrayResult')
            ->willReturn($simpleProductResult);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $this->productRepository->expects($this->once())
            ->method('getSimpleProductIdsByParentProductsQueryBuilder')
            ->willReturn($qb);

        $event = new RestrictProductVariantEvent($qb);
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event, RestrictProductVariantEvent::NAME);

        $em = $this->createMock(EntityManager::class);
        $em->expects($this->any())
            ->method('getReference')
            ->with(Product::class)
            ->willReturnCallback(function ($class, $id) {
                return $this->getSimpleProduct($id);
            });
        $this->doctrineHelper->expects($this->once())
            ->method('getEntityManagerForClass')
            ->with(Product::class)
            ->willReturn($em);
    }
}
