<?php

namespace Oro\Bundle\WebsiteSearchBundle\EventListener;

use Oro\Bundle\ConfigBundle\Event\ConfigUpdateEvent;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\VisibilityBundle\Visibility\Resolver\CategoryVisibilityResolver;
use Oro\Bundle\WebsiteSearchBundle\Event\ReindexationRequestEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Listens to category default visibility change and triggers products reindexation.
 */
class DefaultCategoryVisibilityListener
{
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Trigger full reindex if default category visibility was changed.
     *
     * @param ConfigUpdateEvent $event
     */
    public function onUpdateAfter(ConfigUpdateEvent $event)
    {
        if ($event->isChanged(CategoryVisibilityResolver::OPTION_CATEGORY_VISIBILITY)) {
            $reindexationEvent = new ReindexationRequestEvent([Product::class]);
            $this->eventDispatcher->dispatch($reindexationEvent, ReindexationRequestEvent::EVENT_NAME);
        }
    }
}
