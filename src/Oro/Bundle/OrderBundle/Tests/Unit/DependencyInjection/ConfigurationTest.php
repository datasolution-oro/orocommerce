<?php

namespace Oro\Bundle\OrderBundle\Tests\Unit\DependencyInjection;

use Oro\Bundle\OrderBundle\DependencyInjection\Configuration;
use Oro\Bundle\OrderBundle\Provider\OrderStatusesProviderInterface;
use Oro\Bundle\ProductBundle\Entity\Product;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit\Framework\TestCase
{
    public function testEmptyConfiguration(): void
    {
        $this->assertEquals(
            [
                'settings' => [
                    'resolved' => true,
                    'backend_product_visibility' => [
                        'value' => [Product::INVENTORY_STATUS_IN_STOCK, Product::INVENTORY_STATUS_OUT_OF_STOCK],
                        'scope' => 'app'
                    ],
                    'frontend_product_visibility' => [
                        'value' => [Product::INVENTORY_STATUS_IN_STOCK, Product::INVENTORY_STATUS_OUT_OF_STOCK],
                        'scope' => 'app'
                    ],
                    'order_automation_enable_cancellation' => ['value' => false, 'scope' => 'app'],
                    'order_automation_applicable_statuses' => [
                        'value' => [OrderStatusesProviderInterface::INTERNAL_STATUS_OPEN],
                        'scope' => 'app'
                    ],
                    'order_automation_target_status' => [
                        'value' => OrderStatusesProviderInterface::INTERNAL_STATUS_CANCELLED,
                        'scope' => 'app'
                    ],
                    'order_creation_new_internal_order_status' => [
                        'value' => OrderStatusesProviderInterface::INTERNAL_STATUS_OPEN,
                        'scope' => 'app'
                    ],
                    'order_creation_new_order_owner' => ['value' => null, 'scope' => 'app'],
                    'order_previously_purchased_period' => ['value' => 90, 'scope' => 'app'],
                    'enable_purchase_history' => ['value' => false, 'scope' => 'app'],
                ]
            ],
            (new Processor())->processConfiguration(new Configuration(), [])
        );
    }
}
