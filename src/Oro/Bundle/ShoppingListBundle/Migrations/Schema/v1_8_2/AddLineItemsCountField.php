<?php

namespace Oro\Bundle\ShoppingListBundle\Migrations\Schema\v1_8_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\OrderedMigrationInterface;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddLineItemsCountField implements Migration, OrderedMigrationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getOrder(): int
    {
        return 10;
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('oro_shopping_list');
        if (!$table->hasColumn('line_items_count')) {
            $table->addColumn('line_items_count', 'smallint', ['default' => 0]);
        }
    }
}