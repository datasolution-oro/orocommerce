<?php

namespace Oro\Bundle\WebCatalogBundle\Tests\Functional\Layout\DataProvider;

use Oro\Bundle\CatalogBundle\Tests\Functional\DataFixtures\LoadCategoryData;
use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadCustomerUserData;
use Oro\Bundle\SearchBundle\Tests\Functional\SearchExtensionTrait;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\WebCatalogBundle\Tests\Functional\DataFixtures\LoadContentNodesData;
use Oro\Bundle\WebCatalogBundle\Tests\Functional\DataFixtures\LoadWebCatalogCategoryVariantsData;

class WebCatalogBreadcrumbProviderTest extends WebTestCase
{
    use SearchExtensionTrait;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->initClient(
            [],
            $this->generateBasicAuthHeader(LoadCustomerUserData::AUTH_USER, LoadCustomerUserData::AUTH_PW)
        );
        $this->client->useHashNavigation(false);

        $this->loadFixtures(
            [
                LoadWebCatalogCategoryVariantsData::class
            ]
        );

        $this->getContainer()->get('oro_website_search.indexer')->reindex();
    }

    /**
     * @dataProvider getSlugs
     * @param $reference string
     * @param $expectedCount int
     * @param $expectedBreadcrumbs array
     */
    public function testBreadcrumbs($reference, $expectedCount, $expectedBreadcrumbs)
    {
        $crawler = $this->client->request('GET', '/'.$reference);
        $result = $this->client->getResponse();
        $this->assertHtmlResponseStatusCodeEquals($result, 200);

        static::assertStringContainsString(
            $reference,
            $crawler->filter('title')->html()
        );

        static::assertStringContainsString(
            $reference,
            $crawler->filter('h1.category-title')->html()
        );

        $breadcrumbs = [];
        /** @var \DOMElement $item */
        foreach ($crawler->filter('.breadcrumbs__item a') as $key => $item) {
            $this->assertEquals($expectedBreadcrumbs[$key], $item->textContent);
            $breadcrumbs[] = trim($item->textContent);
        }

        $this->assertCount($expectedCount, $breadcrumbs);
    }

    public function testGetItemsByProducWithBaseUrl()
    {
        //Emulate subfolder request
        $crawler = $this->client->request(
            'GET',
            '/custom/base/url/app.php/' . LoadContentNodesData::CATALOG_1_ROOT_SUBNODE_1_1,
            [],
            [],
            [
                'SCRIPT_NAME' => '/custom/base/url/app.php',
                'SCRIPT_FILENAME' => 'app.php'
            ]
        );

        $breadcrumbUrls = [];

        /** @var \DOMElement $item */
        foreach ($crawler->filter('.breadcrumbs__item a') as $key => $item) {
            $breadcrumbUrls[] = $item->getAttribute('href');
        }

        static::assertCount(4, $breadcrumbUrls);
        static::assertStringContainsString('/custom/base/url/app.php/', $breadcrumbUrls[0]);
        static::assertStringContainsString('/custom/base/url/app.php/', $breadcrumbUrls[1]);
        static::assertStringContainsString('/custom/base/url/app.php/', $breadcrumbUrls[2]);
        static::assertStringContainsString('/custom/base/url/app.php/', $breadcrumbUrls[3]);
    }

    /**
     * @return array
     */
    public function getSlugs()
    {
        return [
            [
                LoadContentNodesData::CATALOG_1_ROOT,
                2,
                [
                    'All Products',
                    LoadCategoryData::FIRST_LEVEL,
                ]
            ],
            [
                LoadContentNodesData::CATALOG_1_ROOT_SUBNODE_1,
                3,
                [
                    'All Products',
                    LoadCategoryData::FIRST_LEVEL,
                    LoadCategoryData::SECOND_LEVEL1,
                ]
            ],
            [
                LoadContentNodesData::CATALOG_1_ROOT_SUBNODE_1_1,
                4,
                [
                    'All Products',
                    LoadCategoryData::FIRST_LEVEL,
                    LoadCategoryData::SECOND_LEVEL1,
                    LoadCategoryData::THIRD_LEVEL1,
                ]
            ],
            [
                LoadContentNodesData::CATALOG_1_ROOT_SUBNODE_1_2,
                4,
                [
                    'All Products',
                    LoadCategoryData::FIRST_LEVEL,
                    LoadCategoryData::SECOND_LEVEL2,
                    LoadCategoryData::THIRD_LEVEL2,
                ]
            ],
        ];
    }
}
