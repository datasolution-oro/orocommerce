<?php

namespace Oro\Bundle\RFPBundle\Storage;

use Doctrine\Common\Collections\Collection;
use Oro\Bundle\ProductBundle\Storage\ProductDataStorage;
use Oro\Bundle\RFPBundle\Entity\Request as RFPRequest;
use Oro\Bundle\RFPBundle\Entity\RequestProduct;

/**
 * Extracts data from RFPRequest and puts it into storage. Saved data is used later during Quote creation.
 */
class RequestToQuoteDataStorage
{
    private ProductDataStorage $storage;

    public function __construct(ProductDataStorage $storage)
    {
        $this->storage = $storage;
    }

    public function saveToStorage(RFPRequest $rfpRequest): void
    {
        $data = [
            ProductDataStorage::ENTITY_DATA_KEY => [
                'customerUser' => $rfpRequest->getCustomerUser()?->getId(),
                'customer' => $rfpRequest->getCustomer()?->getId(),
                'request' => $rfpRequest->getId(),
                'poNumber' => $rfpRequest->getPoNumber(),
                'shipUntil' => $rfpRequest->getShipUntil(),
                'assignedUsers' => $this->getEntitiesIds($rfpRequest->getAssignedUsers()),
                'assignedCustomerUsers' => $this->getEntitiesIds($rfpRequest->getAssignedCustomerUsers()),
                'website' => $rfpRequest->getWebsite()?->getId(),
            ],
        ];

        foreach ($rfpRequest->getRequestProducts() as $requestProduct) {
            $items = $this->getRequestProductItems($requestProduct);

            $data[ProductDataStorage::ENTITY_ITEMS_DATA_KEY][] = [
                ProductDataStorage::PRODUCT_SKU_KEY =>  $requestProduct->getProductSku(),
                ProductDataStorage::PRODUCT_ID_KEY =>  $requestProduct->getProduct()->getId(),
                ProductDataStorage::PRODUCT_QUANTITY_KEY => null,
                'commentCustomer' => $requestProduct->getComment(),
                'requestProductItems' => $items,
            ];
        }

        $this->storage->set($data);
    }

    private function getRequestProductItems(RequestProduct $requestProduct): array
    {
        $items = [];
        foreach ($requestProduct->getRequestProductItems() as $requestProductItem) {
            $productUnitCode = $requestProductItem->getProductUnit()?->getCode();

            $items[] = [
                'price' => $requestProductItem->getPrice(),
                'quantity' => $requestProductItem->getQuantity(),
                'productUnit' => $productUnitCode,
                'productUnitCode' => $productUnitCode,
                'requestProductItem' => $requestProductItem->getId(),
            ];
        }

        return $items;
    }

    private function getEntitiesIds(Collection $collection): array
    {
        $ids = [];
        foreach ($collection as $item) {
            if (method_exists($item, 'getId') && $item->getId()) {
                $ids[] = $item->getId();
            }
        }

        return $ids;
    }
}
