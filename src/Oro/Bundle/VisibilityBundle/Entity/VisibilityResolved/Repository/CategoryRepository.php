<?php

namespace Oro\Bundle\VisibilityBundle\Entity\VisibilityResolved\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\EntityBundle\ORM\InsertQueryExecutorInterface;
use Oro\Bundle\ScopeBundle\Entity\Scope;
use Oro\Bundle\VisibilityBundle\Entity\Visibility\CategoryVisibility;
use Oro\Bundle\VisibilityBundle\Entity\VisibilityResolved\CategoryVisibilityResolved;

/**
 * Composite primary key fields order:
 *  - scope
 *  - category
 */
class CategoryRepository extends ServiceEntityRepository
{
    use CategoryVisibilityResolvedTermTrait;
    use BasicOperationRepositoryTrait;

    const INSERT_BATCH_SIZE = 500;

    /**
     * @param Category $category
     * @param int $configValue
     * @return bool
     */
    public function isCategoryVisible(Category $category, $configValue)
    {
        $visibility = $this->getFallbackToAllVisibility($category);
        if ($visibility === CategoryVisibilityResolved::VISIBILITY_FALLBACK_TO_CONFIG) {
            $visibility = $configValue;
        }

        return $visibility === CategoryVisibilityResolved::VISIBILITY_VISIBLE;
    }

    /**
     * @param int $visibility visible|hidden
     * @param int $configValue
     * @return array
     */
    public function getCategoryIdsByVisibility($visibility, $configValue)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('category.id')
            ->from('OroCatalogBundle:Category', 'category')
            ->orderBy('category.id');

        $terms = [$this->getCategoryVisibilityResolvedTerm($qb, $configValue)];

        if ($visibility === CategoryVisibilityResolved::VISIBILITY_VISIBLE) {
            $qb->andWhere($qb->expr()->gt(implode(' + ', $terms), 0));
        } else {
            $qb->andWhere($qb->expr()->lte(implode(' + ', $terms), 0));
        }

        $categoryVisibilityResolved = $qb->getQuery()->getArrayResult();

        return array_map('current', $categoryVisibilityResolved);
    }

    /**
     * @param int $visibility visible|hidden|config
     * @return array
     */
    public function getCategoryIdsByNotResolvedVisibility($visibility)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('category.id')
            ->from('OroCatalogBundle:Category', 'category')
            ->leftJoin(
                'Oro\Bundle\VisibilityBundle\Entity\VisibilityResolved\CategoryVisibilityResolved',
                'cvr',
                Join::WITH,
                $qb->expr()->eq($this->getRootAlias($qb), 'cvr.category')
            )
            ->orderBy('category.id');

        if ($visibility === CategoryVisibilityResolved::VISIBILITY_FALLBACK_TO_CONFIG) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->isNull('cvr.visibility'),
                    $qb->expr()->eq('cvr.visibility', ':visibility')
                )
            );
        } else {
            $qb->andWhere($qb->expr()->eq('cvr.visibility', ':visibility'));
        }
        $qb->setParameter('visibility', $visibility);

        return array_map('current', $qb->getQuery()->getArrayResult());
    }

    public function clearTable()
    {
        // TRUNCATE can't be used because it can't be rolled back in case of DB error
        $this->createQueryBuilder('cvr')
            ->delete()
            ->getQuery()
            ->execute();
    }

    public function insertStaticValues(InsertQueryExecutorInterface $insertExecutor, Scope $scope)
    {
        $visibilityCondition = sprintf(
            "CASE WHEN cv.visibility = '%s' THEN %s ELSE %s END",
            CategoryVisibility::VISIBLE,
            CategoryVisibilityResolved::VISIBILITY_VISIBLE,
            CategoryVisibilityResolved::VISIBILITY_HIDDEN
        );

        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'cv.id',
                'IDENTITY(cv.category)',
                $visibilityCondition,
                (string)CategoryVisibilityResolved::SOURCE_STATIC,
                (string)$scope->getId()
            )
            ->from('OroVisibilityBundle:Visibility\CategoryVisibility', 'cv')
            ->where('cv.visibility != :config')
            ->setParameter('config', CategoryVisibility::CONFIG);

        $insertExecutor->execute(
            $this->getClassName(),
            ['sourceCategoryVisibility', 'category', 'visibility', 'source', 'scope'],
            $queryBuilder
        );
    }

    /**
     * @param InsertQueryExecutorInterface $insertExecutor
     * @param array $categoryIds
     * @param int $visibility
     * @param Scope $scope
     */
    public function insertParentCategoryValues(
        InsertQueryExecutorInterface $insertExecutor,
        array $categoryIds,
        $visibility,
        Scope $scope
    ) {
        if (!$categoryIds) {
            return;
        }

        $sourceCondition = sprintf(
            'CASE WHEN c.parentCategory IS NOT NULL THEN %d ELSE %d END',
            CategoryVisibilityResolved::SOURCE_PARENT_CATEGORY,
            CategoryVisibilityResolved::SOURCE_STATIC
        );

        $queryBuilder = $this->getEntityManager()->createQueryBuilder();
        $queryBuilder
            ->select(
                'c.id',
                (string)$queryBuilder->expr()->literal($visibility),
                $sourceCondition,
                (string)$scope->getId()
            )
            ->from('OroCatalogBundle:Category', 'c')
            ->leftJoin('OroVisibilityBundle:Visibility\CategoryVisibility', 'cv', 'WITH', 'cv.category = c')
            ->andWhere('cv.visibility IS NULL')     // parent category fallback
            ->andWhere('c.id IN (:categoryIds)');   // specific category IDs

        foreach (array_chunk($categoryIds, self::INSERT_BATCH_SIZE) as $ids) {
            $queryBuilder->setParameter('categoryIds', $ids);
            $insertExecutor->execute(
                $this->getClassName(),
                ['category', 'visibility', 'source', 'scope'],
                $queryBuilder
            );
        }
    }

    /**
     * @param Category $category
     * @return int visible|hidden|config
     */
    public function getFallbackToAllVisibility(Category $category)
    {
        $configFallback = CategoryVisibilityResolved::VISIBILITY_FALLBACK_TO_CONFIG;
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('COALESCE(cvr.visibility, '. $qb->expr()->literal($configFallback).')')
            ->from('OroCatalogBundle:Category', 'category')
            ->leftJoin(
                'OroVisibilityBundle:VisibilityResolved\CategoryVisibilityResolved',
                'cvr',
                Join::WITH,
                $qb->expr()->eq('cvr.category', 'category')
            )
            ->where($qb->expr()->eq('category', ':category'))
            ->setParameter('category', $category);

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array $categoryIds
     * @param int $visibility
     */
    public function updateCategoryVisibilityByCategory(array $categoryIds, $visibility)
    {
        if (!$categoryIds) {
            return;
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->update('OroVisibilityBundle:VisibilityResolved\CategoryVisibilityResolved', 'cvr')
            ->set('cvr.visibility', ':visibility')
            ->andWhere($qb->expr()->in('IDENTITY(cvr.category)', ':categoryIds'))
            ->setParameter('categoryIds', $categoryIds)
            ->setParameter('visibility', $visibility);

        $qb->getQuery()->execute();
    }
}
