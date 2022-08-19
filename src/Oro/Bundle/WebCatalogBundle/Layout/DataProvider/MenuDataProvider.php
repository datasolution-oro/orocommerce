<?php

namespace Oro\Bundle\WebCatalogBundle\Layout\DataProvider;

use Doctrine\Common\Cache\CacheProvider;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\ScopeBundle\Entity\Scope;
use Oro\Bundle\WebCatalogBundle\Cache\ResolvedData\ResolvedContentNode;
use Oro\Bundle\WebCatalogBundle\ContentNodeUtils\ContentNodeTreeResolverInterface;
use Oro\Bundle\WebCatalogBundle\Entity\ContentNode;
use Oro\Bundle\WebCatalogBundle\Entity\Repository\ContentNodeRepository;
use Oro\Bundle\WebCatalogBundle\Provider\RequestWebContentScopeProvider;
use Oro\Bundle\WebCatalogBundle\Provider\WebCatalogProvider;
use Oro\Bundle\WebsiteBundle\Manager\WebsiteManager;

/**
 * Layout data provider that helps to build main navigation menu on the front store.
 * Cashes resolved web catalog root items.
 */
class MenuDataProvider
{
    const PRIORITY = 'priority';
    const IDENTIFIER = 'identifier';
    const LABEL = 'label';
    const URL = 'url';
    const CHILDREN = 'children';

    /** @var ManagerRegistry */
    private $doctrine;

    /** @var LocalizationHelper */
    private $localizationHelper;

    /** @var RequestWebContentScopeProvider */
    private $requestWebContentScopeProvider;

    /** @var WebCatalogProvider */
    private $webCatalogProvider;

    /** @var ContentNodeTreeResolverInterface */
    private $contentNodeTreeResolver;

    /** @var WebsiteManager */
    private $websiteManager;

    /** @var CacheProvider */
    private $cache;

    /** @var int */
    private $cacheLifeTime;

    /** @var ContentNode */
    private $rootNode = false;

    public function __construct(
        ManagerRegistry $doctrine,
        WebCatalogProvider $webCatalogProvider,
        ContentNodeTreeResolverInterface $contentNodeTreeResolver,
        LocalizationHelper $localizationHelper,
        RequestWebContentScopeProvider $requestWebContentScopeProvider,
        WebsiteManager $websiteManager
    ) {
        $this->doctrine = $doctrine;
        $this->webCatalogProvider = $webCatalogProvider;
        $this->contentNodeTreeResolver = $contentNodeTreeResolver;
        $this->localizationHelper = $localizationHelper;
        $this->requestWebContentScopeProvider = $requestWebContentScopeProvider;
        $this->websiteManager = $websiteManager;
    }

    /**
     * @param CacheProvider $cache
     * @param int           $lifeTime
     */
    public function setCache(CacheProvider $cache, $lifeTime = 0)
    {
        $this->cache = $cache;
        $this->cacheLifeTime = $lifeTime;
    }

    /**
     * @param int|null $maxNodesNestedLevel
     *
     * @return array
     */
    public function getItems(int $maxNodesNestedLevel = null)
    {
        $scopes = $this->requestWebContentScopeProvider->getScopes();
        if ($scopes) {
            $cacheKey = $this->getCacheKey($scopes, $maxNodesNestedLevel);
            $rootItem = $this->cache->fetch($cacheKey);
            if (!$rootItem) {
                $rootItem = $this->getResolvedItems($scopes, $maxNodesNestedLevel);
                $this->cache->save($cacheKey, $rootItem, $this->cacheLifeTime);
            }

            return $rootItem[self::CHILDREN] ?? [];
        }

        return [];
    }

    private function getResolvedItems(array $scopes, int $maxNodesNestedLevel = null): array
    {
        $resolvedItems = [];
        foreach ($scopes as $scope) {
            $resolvedItems[] = $this->getResolvedRootItem($scope, $maxNodesNestedLevel);
        }

        $rootItem = $this->mergeItems(array_filter($resolvedItems));
        if ($rootItem) {
            $rootItem = reset($rootItem);
        }

        return $rootItem;
    }

    private function mergeItems(array $resolvedItems): array
    {
        if (!$resolvedItems) {
            return [];
        }

        return array_reduce($resolvedItems, function ($accum, $item) {
            $identifier = $item[self::PRIORITY];
            if (array_key_exists($identifier, $accum)) {
                $children = array_merge($accum[$identifier][self::CHILDREN], $item[self::CHILDREN]);
                $accum[$identifier][self::CHILDREN] = $children;
            } else {
                $accum[$identifier] = $item;
            }

            $accum[$identifier][self::CHILDREN] = $this->mergeItems($accum[$identifier][self::CHILDREN]);
            ksort($accum);

            return $accum;
        }, []);
    }

    /**
     * @param Scope $scope
     * @param int|null $maxNodesNestedLevel
     * @return array
     */
    private function getResolvedRootItem(Scope $scope, int $maxNodesNestedLevel = null)
    {
        $rootItem = [];
        $rootNode = $this->getRootNode();
        if (!$rootNode) {
            $webCatalog = $this->webCatalogProvider->getWebCatalog();
            if ($webCatalog) {
                $rootNode = $this->getContentNodeRepository()->getRootNodeByWebCatalog($webCatalog);
            }
        }

        if ($rootNode) {
            $resolvedNode = $this->contentNodeTreeResolver->getResolvedContentNode($rootNode, $scope);
            if ($resolvedNode) {
                $rootItem = $this->prepareItemsData($resolvedNode, $maxNodesNestedLevel);
            }
        }

        return $rootItem;
    }

    /**
     * @param ResolvedContentNode $node
     * @param int|null $remainingNestedLevel
     *
     * @return array
     */
    private function prepareItemsData(ResolvedContentNode $node, int $remainingNestedLevel = null)
    {
        $result = [];

        $result[self::IDENTIFIER] = $node->getIdentifier();
        $result[self::LABEL] = (string)$this->localizationHelper->getLocalizedValue($node->getTitles());
        $result[self::PRIORITY] = $node->getPriority();
        $result[self::URL] = (string)$this->localizationHelper
            ->getLocalizedValue($node->getResolvedContentVariant()->getLocalizedUrls());

        $result[self::CHILDREN] = [];
        if (null === $remainingNestedLevel || $remainingNestedLevel > 0) {
            $childrenRemainingNestedLevel = null !== $remainingNestedLevel ? $remainingNestedLevel - 1 : null;
            foreach ($node->getChildNodes() as $child) {
                $result[self::CHILDREN][] = $this->prepareItemsData(
                    $child,
                    $childrenRemainingNestedLevel
                );
            }
        }

        return $result;
    }

    /**
     * @return ContentNode|null
     */
    private function getRootNode()
    {
        if ($this->rootNode === false) {
            $website = $this->websiteManager->getCurrentWebsite();
            $this->rootNode = $this->webCatalogProvider->getNavigationRoot($website);
        }

        return $this->rootNode;
    }

    /**
     * @return ContentNodeRepository
     */
    private function getContentNodeRepository()
    {
        return $this->doctrine->getRepository(ContentNode::class);
    }

    private function getCacheKey(array $scopes, ?int $maxNodesNestedLevel): string
    {
        $scopes = array_map(fn (Scope $scope) => $scope->getId(), $scopes);
        sort($scopes);
        $rootNode = $this->getRootNode();
        $localization = $this->localizationHelper->getCurrentLocalization();
        $scopesKey = implode("_", array_fill(0, count($scopes), "%s"));

        return sprintf(
            'menu_items_%s_%s_%s_%s',
            (string)$maxNodesNestedLevel,
            vsprintf($scopesKey, $scopes) ?: 0,
            $rootNode ? $rootNode->getId() : 0,
            $localization ? $localization->getId() : 0
        );
    }
}
