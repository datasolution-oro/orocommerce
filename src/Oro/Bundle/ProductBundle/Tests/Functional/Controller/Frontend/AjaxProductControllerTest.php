<?php

namespace Oro\Bundle\ProductBundle\Tests\Functional\Controller\Frontend;

use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadCustomerUserData;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Manager\UserProductFiltersSidebarStateManager;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadFrontendProductData;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class AjaxProductControllerTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient(
            [],
            $this->generateBasicAuthHeader(LoadCustomerUserData::AUTH_USER, LoadCustomerUserData::AUTH_PW)
        );

        $this->loadFixtures([
            LoadFrontendProductData::class,
        ]);
    }

    /**
     * @dataProvider productNamesBySkusDataProvider
     */
    public function testProductNamesBySkus(array $skus, array $expectedData)
    {
        $this->client->request(
            'POST',
            $this->getUrl('oro_product_frontend_ajax_names_by_skus'),
            ['skus' => $skus]
        );
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals($expectedData, $data);
    }

    public function productNamesBySkusDataProvider()
    {
        return [
            'restricted' => [
                'skus'         => [
                    'not a sku',
                    LoadProductData::PRODUCT_1,
                    LoadProductData::PRODUCT_2,
                    LoadProductData::PRODUCT_3,
                    LoadProductData::PRODUCT_4,
                ],
                'expectedData' => [
                    LoadProductData::PRODUCT_1 => ['name' => 'product-1.names.default'],
                    LoadProductData::PRODUCT_2 => ['name' => 'product-2.names.default'],
                    LoadProductData::PRODUCT_3 => ['name' => 'product-3.names.default'],
                ],
            ],
            'allowed'    => [
                'skus'         => [
                    'not a sku',
                    LoadProductData::PRODUCT_1,
                    LoadProductData::PRODUCT_2,
                ],
                'expectedData' => [
                    LoadProductData::PRODUCT_1 => ['name' => 'product-1.names.default'],
                    LoadProductData::PRODUCT_2 => ['name' => 'product-2.names.default'],
                ],
            ],
        ];
    }

    public function testProductImagesById()
    {
        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_1);
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_product_frontend_ajax_images_by_id',
                [
                    'id' => $product->getId(),
                    'filters' => ['product_gallery_popup'],
                ]
            )
        );
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);

        $data = json_decode($result->getContent(), true);

        $this->assertNotEmpty($data);
        $this->assertArrayHasKey(0, $data);
        $this->assertArrayHasKey('isInitial', $data[0]);
        $this->assertArrayHasKey('product_gallery_popup', $data[0]);
        $this->assertStringMatchesFormat(
            '/media/cache/attachment/%s/product_gallery_popup/%s/%d/product-1.jpg.webp',
            $data[0]['product_gallery_popup'][0]['srcset']
        );
        $this->assertStringMatchesFormat(
            '/media/cache/attachment/%s/product_gallery_popup/%s/%d/product-1.jpg',
            $data[0]['product_gallery_popup'][1]['srcset']
        );
    }

    public function testProductImagesByIdWhenProductHasNoImages()
    {
        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_4);
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_product_frontend_ajax_images_by_id',
                [
                    'id' => $product->getId(),
                    'filters' => ['product_gallery_popup'],
                ]
            )
        );
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);

        $data = json_decode($result->getContent(), true);
        $this->assertSame([], $data);
    }

    public function testProductImagesByIdWhenProductIsMissing()
    {
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_product_frontend_ajax_images_by_id',
                [
                    'id' => 123456,
                    'filters' => ['product_gallery_popup'],
                ]
            )
        );
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals([], $data);
    }

    public function testProductImagesByIdWhenFiltersNamesAreMissing()
    {
        /** @var Product $product */
        $product = $this->getReference(LoadProductData::PRODUCT_1);
        $this->client->request(
            'GET',
            $this->getUrl(
                'oro_product_frontend_ajax_images_by_id',
                [
                    'id' => $product->getId(),
                ]
            )
        );
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals([], $data);
    }

    /**
     * @dataProvider getProductFiltersSidebarStateDataProvider
     */
    public function testSetProductFiltersSidebarStateAction(int|string $isSidebarExpanded, bool $expectedResult): void
    {
        $this->ajaxRequest(
            'POST',
            $this->getUrl('oro_product_frontend_ajax_set_product_filters_sidebar_state'),
            [
                'sidebarExpanded' => $isSidebarExpanded,
            ]
        );
        $result = $this->client->getResponse();
        self::assertJsonResponseStatusCodeEquals($result, 200);

        $userProductFiltersSidebarStateManager = $this->getContainer()
            ->get(UserProductFiltersSidebarStateManager::class);

        self::assertEquals(
            $expectedResult,
            $userProductFiltersSidebarStateManager->isProductFiltersSidebarExpanded()
        );
    }

    /**
     * @dataProvider getProductFiltersSidebarStateDataProvider
     */
    public function testSetProductFiltersSidebarStateActionAnon(
        int|string $isSidebarExpanded,
        bool $expectedResult
    ): void {
        $this->initClient();

        $this->ajaxRequest(
            'POST',
            $this->getUrl('oro_product_frontend_ajax_set_product_filters_sidebar_state'),
            [
                'sidebarExpanded' => $isSidebarExpanded,
            ]
        );
        $result = $this->client->getResponse();
        self::assertJsonResponseStatusCodeEquals($result, 200);

        $this->ensureSessionIsAvailable();

        $userProductFiltersSidebarStateManager = $this->getContainer()
            ->get(UserProductFiltersSidebarStateManager::class);

        self::assertEquals(
            $expectedResult,
            $userProductFiltersSidebarStateManager->isProductFiltersSidebarExpanded()
        );
    }

    public function getProductFiltersSidebarStateDataProvider(): array
    {
        return [
            [
                'isSidebarExpanded' => '',
                'expectedResult' => false,
            ],
            [
                'isSidebarExpanded' => 0,
                'expectedResult' => false,
            ],
            [
                'isSidebarExpanded' => 1,
                'expectedResult' => true,
            ],
            [
                'isSidebarExpanded' => '0',
                'expectedResult' => false,
            ],
            [
                'isSidebarExpanded' => '1',
                'expectedResult' => true,
            ],
        ];
    }
}
