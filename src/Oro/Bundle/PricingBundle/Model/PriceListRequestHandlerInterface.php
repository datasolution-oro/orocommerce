<?php

namespace Oro\Bundle\PricingBundle\Model;

use Oro\Bundle\PricingBundle\Entity\BasePriceList;

interface PriceListRequestHandlerInterface
{
    const TIER_PRICES_KEY = 'showTierPrices';
    const PRICE_LIST_CURRENCY_KEY = 'priceCurrencies';
    const PRICE_LIST_KEY = 'priceListId';

    /**
     * @return BasePriceList|null
     */
    public function getPriceList();

    /**
     * @return bool
     */
    public function getShowTierPrices();

    /**
     * @param BasePriceList $priceList
     * @return string[]
     */
    public function getPriceListSelectedCurrencies(BasePriceList $priceList);
}
