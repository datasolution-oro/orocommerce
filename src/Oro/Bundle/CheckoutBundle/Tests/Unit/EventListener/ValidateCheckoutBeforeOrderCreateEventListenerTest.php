<?php

declare(strict_types=1);

namespace Oro\Bundle\CheckoutBundle\Tests\Unit\EventListener;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\EventListener\ValidateCheckoutBeforeOrderCreateEventListener;
use Oro\Bundle\CheckoutBundle\Provider\CheckoutValidationGroupsBySourceEntityProvider;
use Oro\Bundle\CheckoutBundle\Tests\Unit\Model\Action\CheckoutSourceStub;
use Oro\Bundle\ShoppingListBundle\Entity\ShoppingList;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Component\Action\Event\ExtendableConditionEvent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidateCheckoutBeforeOrderCreateEventListenerTest extends TestCase
{
    private ValidatorInterface|MockObject $validator;

    private CheckoutValidationGroupsBySourceEntityProvider|MockObject $validationGroupsProvider;

    private ValidateCheckoutBeforeOrderCreateEventListener $listener;

    protected function setUp(): void
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->validationGroupsProvider = $this->createMock(CheckoutValidationGroupsBySourceEntityProvider::class);

        $this->listener = new ValidateCheckoutBeforeOrderCreateEventListener(
            $this->validator,
            $this->validationGroupsProvider
        );
    }

    public function testOnBeforeOrderCreateWhenContextNotWorkflowItem(): void
    {
        $event = new ExtendableConditionEvent(new \stdClass());

        $this->validator
            ->expects(self::never())
            ->method(self::anything());

        $this->listener->onBeforeOrderCreate($event);
    }

    public function testOnBeforeOrderCreateWhenEntityNotCheckout(): void
    {
        $context = (new WorkflowItem())
            ->setEntity(new \stdClass());
        $event = new ExtendableConditionEvent($context);

        $this->validator
            ->expects(self::never())
            ->method(self::anything());

        $this->listener->onBeforeOrderCreate($event);
    }

    public function testOnBeforeOrderCreateWhenNoViolations(): void
    {
        $shoppingList = new ShoppingList();
        $checkout = (new Checkout())
            ->setSource((new CheckoutSourceStub())->setShoppingList($shoppingList));
        $context = (new WorkflowItem())
            ->setEntity($checkout);
        $event = new ExtendableConditionEvent($context);

        $validationGroups = new GroupSequence(['Default', 'checkout_before_order_create%from_alias%']);
        $this->validationGroupsProvider
            ->expects(self::once())
            ->method('getValidationGroupsBySourceEntity')
            ->with([$validationGroups->groups], $shoppingList)
            ->willReturn([$validationGroups]);

        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with($context->getEntity(), null, [$validationGroups])
            ->willReturn(new ConstraintViolationList());

        $this->listener->onBeforeOrderCreate($event);

        self::assertCount(0, $event->getErrors());
    }

    public function testOnBeforeOrderCreateWhenHasViolations(): void
    {
        $shoppingList = new ShoppingList();
        $checkout = (new Checkout())
            ->setSource((new CheckoutSourceStub())->setShoppingList($shoppingList));
        $context = (new WorkflowItem())
            ->setEntity($checkout);
        $event = new ExtendableConditionEvent($context);

        $validationGroups = new GroupSequence(['Default', 'checkout_before_order_create%from_alias%']);
        $this->validationGroupsProvider
            ->expects(self::once())
            ->method('getValidationGroupsBySourceEntity')
            ->with([$validationGroups->groups], $shoppingList)
            ->willReturn([$validationGroups]);

        $violation1 = new ConstraintViolation('sample violation1', null, [], $checkout, null, $checkout);
        $violation2 = new ConstraintViolation('sample violation2', null, [], $checkout, null, $checkout);
        $this->validator
            ->expects(self::once())
            ->method('validate')
            ->with($context->getEntity(), null, [$validationGroups])
            ->willReturn(new ConstraintViolationList([$violation1, $violation2]));

        $this->listener->onBeforeOrderCreate($event);

        self::assertEquals(
            [
                ['message' => $violation1->getMessage(), 'context' => $violation1],
                ['message' => $violation2->getMessage(), 'context' => $violation2],
            ],
            $event->getErrors()->toArray()
        );
    }
}
