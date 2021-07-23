<?php

namespace Oro\Bundle\SaleBundle\Tests\Unit\Provider;

use Oro\Bundle\CustomerBundle\Entity\Customer;
use Oro\Bundle\CustomerBundle\Entity\CustomerAddress;
use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\CustomerBundle\Entity\CustomerUserAddress;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\SaleBundle\Entity\Quote;
use Oro\Bundle\SaleBundle\Provider\QuoteAddressProvider;
use Oro\Bundle\SaleBundle\Provider\QuoteAddressSecurityProvider;
use Oro\Bundle\UserBundle\Entity\User;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Yaml\Parser;

class QuoteAddressSecurityProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var QuoteAddressSecurityProvider */
    private $provider;

    /** @var \PHPUnit\Framework\MockObject\MockObject|AuthorizationCheckerInterface */
    private $authorizationChecker;

    /** @var \PHPUnit\Framework\MockObject\MockObject|FrontendHelper */
    private $frontendHelper;

    /** @var \PHPUnit\Framework\MockObject\MockObject|QuoteAddressProvider */
    private $quoteAddressProvider;

    protected function setUp(): void
    {
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->frontendHelper = $this->createMock(FrontendHelper::class);
        $this->quoteAddressProvider = $this->createMock(QuoteAddressProvider::class);

        $this->provider = new QuoteAddressSecurityProvider(
            $this->authorizationChecker,
            $this->frontendHelper,
            $this->quoteAddressProvider,
            CustomerAddress::class,
            CustomerUserAddress::class
        );
    }

    /**
     * @dataProvider manualEditDataProvider
     * @param string $type
     * @param string $permissionName
     * @param bool $permission
     */
    public function testIsManualEditGranted($type, $permissionName, $permission)
    {
        $this->authorizationChecker->expects($this->atLeastOnce())
            ->method('isGranted')
            ->with($permissionName)
            ->willReturn($permission);

        $this->assertEquals($permission, $this->provider->isManualEditGranted($type));
    }

    /**
     * @return array
     */
    public function manualEditDataProvider()
    {
        return [
            ['shipping', 'oro_quote_address_shipping_allow_manual_backend', true],
            ['shipping', 'oro_quote_address_shipping_allow_manual_backend', false],
        ];
    }

    /**
     * @dataProvider userDataProvider
     * @param object|null $user
     * @param string $permissionPostfix
     */
    public function testIsAddressGrantedWithManualAllowed($user, $permissionPostfix)
    {
        $this->quoteAddressProvider->expects($this->never())
            ->method($this->anything());

        $customer = new Customer();
        $customerUser = new CustomerUser();
        $quote = (new Quote())->setCustomer($customer)->setCustomerUser($customerUser);

        $this->frontendHelper->expects($this->any())
            ->method('isFrontendRequest')
            ->willReturn($user instanceof CustomerUser);
        $this->authorizationChecker->expects($this->once())
            ->method('isGranted')
            ->with('oro_quote_address_shipping_allow_manual' . $permissionPostfix)
            ->willReturn(true);

        $this->assertTrue($this->provider->isAddressGranted($quote, 'shipping'));
    }

    /**
     * @dataProvider permissionsDataProvider
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     *
     * @param object|null $user
     * @param array|null $permissions
     * @param bool $hasCustomer
     * @param bool $hasCustomerUser
     * @param bool $hasCustomerAddresses
     * @param bool $hasCustomerUserAddresses
     * @param bool $expected
     */
    public function testIsAddressGranted(
        $user,
        $permissions,
        $hasCustomer,
        $hasCustomerUser,
        $hasCustomerAddresses,
        $hasCustomerUserAddresses,
        $expected
    ) {
        $this->frontendHelper->expects($this->any())
            ->method('isFrontendRequest')
            ->willReturn($user instanceof CustomerUser);
        $this->authorizationChecker->expects($this->any())
            ->method('isGranted')
            ->with($this->isType('string'))
            ->willReturnMap($permissions);

        $this->quoteAddressProvider->expects($this->any())
            ->method('getCustomerAddresses')
            ->willReturn($hasCustomerAddresses ? [new CustomerAddress()] : null);
        $this->quoteAddressProvider->expects($this->any())
            ->method('getCustomerUserAddresses')
            ->willReturn($hasCustomerUserAddresses ? [new CustomerUserAddress()] : null);

        $quote = new Quote();
        if ($hasCustomer) {
            $quote->setCustomer(new Customer());
        }
        if ($hasCustomerUser) {
            $quote->setCustomerUser(new CustomerUser());
        }

        $this->assertEquals($expected, $this->provider->isAddressGranted($quote, 'shipping'));
    }

    /**
     * @return array
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function permissionsDataProvider()
    {
        $finder = new Finder();
        $yaml = new Parser();
        $data = [];

        $finder->files()->in(__DIR__ . DIRECTORY_SEPARATOR . 'fixtures');
        foreach ($finder as $file) {
            $fixture = $yaml->parse(file_get_contents($file));
            foreach ($fixture as $fixtureName => $fixtureData) {
                foreach ($this->userDataProvider() as $name => $userFixture) {
                    $rowData = ['user' => $userFixture[0]];
                    $permissionsMap = [];
                    $fixtureData['permissions']['oro_quote_address_shipping_allow_manual'] = false;
                    foreach ($fixtureData['permissions'] as $permission => $isGranted) {
                        if (strpos($permission, ';') === false) {
                            $permission .= $userFixture[1];
                        }
                        $permissionsMap[] = [$permission, null, $isGranted];
                    }
                    $rowData['permissions'] = $permissionsMap;
                    $rowData['hasCustomer'] = $fixtureData['hasCustomer'];
                    $rowData['hasCustomerUser'] = $fixtureData['hasCustomerUser'];
                    $rowData['hasCustomerAddresses'] = $fixtureData['hasCustomerAddresses'];
                    $rowData['hasCustomerUserAddresses'] = $fixtureData['hasCustomerUserAddresses'];
                    $rowData['expected'] = $fixtureData['expected'];

                    $data[$name . ' ' . $fixtureName] = $rowData;
                }
            }
        }

        return $data;
    }

    public function userDataProvider(): array
    {
        return [
            'user' => [new User(), QuoteAddressProvider::ADMIN_ACL_POSTFIX],
            'customer_user' => [new CustomerUser(), ''],
            'none' => [null, QuoteAddressProvider::ADMIN_ACL_POSTFIX]
        ];
    }
}
