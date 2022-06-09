<?php

namespace Oro\Bundle\RedirectBundle\Tests\Unit\Generator;

use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\RedirectBundle\Entity\Repository\SlugRepository;
use Oro\Bundle\RedirectBundle\Entity\Slug;
use Oro\Bundle\RedirectBundle\Entity\SluggableInterface;
use Oro\Bundle\RedirectBundle\Event\RestrictSlugIncrementEvent;
use Oro\Bundle\RedirectBundle\Generator\DTO\SlugUrl;
use Oro\Bundle\RedirectBundle\Generator\UniqueSlugResolver;
use Oro\Bundle\RedirectBundle\Helper\SlugQueryRestrictionHelperInterface;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Component\Testing\Unit\EntityTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class UniqueSlugResolverTest extends \PHPUnit\Framework\TestCase
{
    use EntityTrait;

    /**
     * @var SlugRepository|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $repository;

    /**
     * @var AclHelper|\PHPUnit\Framework\MockObject\MockObject
     */
    private $aclHelper;

    /**
     * @var EventDispatcherInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $eventDispatcher;

    /** @var SlugQueryRestrictionHelperInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $slugQueryRestrictionHelper;

    /**
     * @var UniqueSlugResolver
     */
    protected $uniqueSlugResolver;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SlugRepository::class);
        $this->aclHelper = $this->createMock(AclHelper::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->slugQueryRestrictionHelper = $this->createMock(SlugQueryRestrictionHelperInterface::class);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->any())
            ->method('getRepository')
            ->with(Slug::class)
            ->willReturn($this->repository);

        $this->uniqueSlugResolver = new UniqueSlugResolver($registry, $this->aclHelper, $this->eventDispatcher);
        $this->uniqueSlugResolver->setSlugQueryRestrictionHelper($this->slugQueryRestrictionHelper);
    }

    public function testResolveNewSlug()
    {
        $slug = '/test';
        $slugUrl = new SlugUrl($slug);

        /** @var SluggableInterface|\PHPUnit\Framework\MockObject\MockObject $entity **/
        $entity = $this->createMock(SluggableInterface::class);

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $this->repository->expects($this->once())
            ->method('getOneDirectUrlBySlugQueryBuilder')
            ->with($slug, $entity)
            ->willReturn($queryBuilder);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RestrictSlugIncrementEvent::class), RestrictSlugIncrementEvent::NAME);

        $this->aclHelper
            ->expects($this->never())
            ->method('apply')
            ->with($queryBuilder)
            ->willReturn($query);

        $this->slugQueryRestrictionHelper
            ->expects($this->once())
            ->method('restrictQueryBuilder')
            ->with($queryBuilder)
            ->willReturn($queryBuilder);

        $this->assertEquals($slug, $this->uniqueSlugResolver->resolve($slugUrl, $entity));
    }

    public function testResolveExistingSlug()
    {
        $slug = '/test';
        $existingSlug = '/test-1';
        $expectedSlug = '/test-2';

        $slugUrl = new SlugUrl($slug);

        /** @var SluggableInterface|\PHPUnit\Framework\MockObject\MockObject $entity **/
        $entity = $this->createMock(SluggableInterface::class);

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(new Slug());
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $this->repository->expects($this->once())
            ->method('getOneDirectUrlBySlugQueryBuilder')
            ->with($slug, $entity)
            ->willReturn($queryBuilder);

        $restrictSlugIncrementEvent = new RestrictSlugIncrementEvent($queryBuilder, $entity);
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($restrictSlugIncrementEvent, RestrictSlugIncrementEvent::NAME);

        $this->aclHelper
            ->expects($this->never())
            ->method('apply')
            ->with($queryBuilder)
            ->willReturn($query);

        $this->slugQueryRestrictionHelper
            ->expects($this->once())
            ->method('restrictQueryBuilder')
            ->with($queryBuilder)
            ->willReturn($queryBuilder);

        $this->repository->expects($this->once())
            ->method('findRestrictedAllDirectUrlsByPattern')
            ->with('/test-%', $this->slugQueryRestrictionHelper, $entity)
            ->willReturn([$existingSlug]);

        $this->assertEquals($expectedSlug, $this->uniqueSlugResolver->resolve($slugUrl, $entity));
    }

    public function testResolveExistingSlugWithinBatch()
    {
        $slug = '/test';
        /** @var Localization $frLocalization */
        $frLocalization = $this->createMock(Localization::class);

        $slugUrl = new SlugUrl($slug);
        $slugUrlFr = new SlugUrl($slug, $frLocalization);

        /** @var SluggableInterface|\PHPUnit\Framework\MockObject\MockObject $entity1 **/
        $entity1 = $this->createMock(SluggableInterface::class);
        $entity1->expects($this->any())
            ->method('getId')
            ->willReturn(1);
        /** @var SluggableInterface|\PHPUnit\Framework\MockObject\MockObject $entity2 **/
        $entity2 = $this->createMock(SluggableInterface::class);
        $entity2->expects($this->any())
            ->method('getId')
            ->willReturn(2);

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->any())
            ->method('getOneOrNullResult')
            ->willReturn(null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->exactly(2))
            ->method('getQuery')
            ->willReturn($query);

        $this->repository->expects($this->any())
            ->method('getOneDirectUrlBySlugQueryBuilder')
            ->willReturn($queryBuilder);

        $this->eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(RestrictSlugIncrementEvent::class), RestrictSlugIncrementEvent::NAME);

        $this->aclHelper
            ->expects($this->never())
            ->method('apply')
            ->with($queryBuilder)
            ->willReturn($query);

        $this->slugQueryRestrictionHelper
            ->expects($this->exactly(2))
            ->method('restrictQueryBuilder')
            ->with($queryBuilder)
            ->willReturn($queryBuilder);

        $this->repository->expects($this->any())
            ->method('findAllDirectUrlsByPattern')
            ->willReturn([]);

        $this->assertEquals($slug, $this->uniqueSlugResolver->resolve($slugUrl, $entity1));
        $this->assertEquals($slug, $this->uniqueSlugResolver->resolve($slugUrlFr, $entity1));
        $this->assertEquals('/test-1', $this->uniqueSlugResolver->resolve($slugUrl, $entity2));
        $this->assertEquals('/test-1', $this->uniqueSlugResolver->resolve($slugUrlFr, $entity2));
    }

    public function testResolveExistingIncrementedSlug()
    {
        $slug = '/test-1';
        $existingSlug = '/test-1';
        $expectedSlug = '/test-2';

        $slugUrl = new SlugUrl($slug);

        /** @var SluggableInterface|\PHPUnit\Framework\MockObject\MockObject $entity **/
        $entity = $this->createMock(SluggableInterface::class);

        $query = $this->createMock(AbstractQuery::class);
        $query->expects($this->exactly(2))
            ->method('getOneOrNullResult')
            ->willReturnOnConsecutiveCalls(
                [$this->getEntity(Slug::class, ['id' => 123])],
                [$this->getEntity(Slug::class, ['id' => 42])]
            );
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder
            ->expects($this->exactly(2))
            ->method('getQuery')
            ->willReturn($query);

        $this->repository->expects($this->exactly(2))
            ->method('getOneDirectUrlBySlugQueryBuilder')
            ->withConsecutive(
                [$slug, $entity],
                ['/test', $entity]
            )
            ->willReturn($queryBuilder);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(RestrictSlugIncrementEvent::class), RestrictSlugIncrementEvent::NAME);

        $this->aclHelper
            ->expects($this->never())
            ->method('apply')
            ->with($queryBuilder)
            ->willReturn($query);

        $this->slugQueryRestrictionHelper
            ->expects($this->exactly(2))
            ->method('restrictQueryBuilder')
            ->with($queryBuilder)
            ->willReturn($queryBuilder);

        $this->repository->expects($this->once())
            ->method('findRestrictedAllDirectUrlsByPattern')
            ->with('/test-%', $this->slugQueryRestrictionHelper, $entity)
            ->willReturn([$existingSlug]);

        $this->assertEquals($expectedSlug, $this->uniqueSlugResolver->resolve($slugUrl, $entity));
    }
}
