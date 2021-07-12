<?php

namespace Oro\Bundle\ProductBundle\Search;

use Oro\Bundle\EntityConfigBundle\Attribute\AttributeConfigurationProviderInterface;
use Oro\Bundle\EntityConfigBundle\Attribute\AttributeTypeRegistry;
use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;
use Oro\Bundle\LocaleBundle\Entity\Localization;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\WebsiteSearchBundle\Attribute\SearchableInformationProvider;
use Oro\Bundle\WebsiteSearchBundle\Attribute\Type\SearchAttributeTypeInterface;
use Oro\Bundle\WebsiteSearchBundle\Engine\IndexDataProvider;
use Oro\Bundle\WebsiteSearchBundle\Placeholder\LocalizationIdPlaceholder;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * Provides product attributes information for the website search index.
 */
class WebsiteSearchProductIndexDataProvider implements ProductIndexDataProviderInterface
{
    public function __construct(
        protected AttributeTypeRegistry $attributeTypeRegistry,
        protected AttributeConfigurationProviderInterface $configurationProvider,
        protected ProductIndexFieldsProvider $indexFieldsProvider,
        protected PropertyAccessor $propertyAccessor,
        private SearchableInformationProvider $searchableProvider
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getIndexData(Product $product, FieldConfigModel $attribute, array $localizations): \ArrayIterator
    {
        $data = new \ArrayIterator();
        $this->buildIndexData($product, $attribute, $localizations, $data);

        return $data;
    }

    private function buildIndexData(
        Product $product,
        FieldConfigModel $attribute,
        array $localizations,
        \ArrayIterator $data
    ): void {
        $attributeType = $this->getAttributeType($attribute);
        if ($attributeType) {
            $attributeType->isLocalizable($attribute) ?
                $this->getLocalizedFields($product, $attribute, $attributeType, $localizations, $data) :
                $this->buildFields($product, $attribute, $attributeType, null, $data);
        }
    }

    private function getLocalizedFields(
        Product $product,
        FieldConfigModel $attribute,
        SearchAttributeTypeInterface $type,
        array $localizations,
        \ArrayIterator $data
    ): void {
        array_map(
            fn (Localization $localization) => $this->buildFields($product, $attribute, $type, $localization, $data),
            $localizations
        );
    }

    private function buildFields(
        Product $product,
        FieldConfigModel $attribute,
        SearchAttributeTypeInterface $attributeType,
        ?Localization $localization = null,
        \ArrayIterator $data
    ): void {
        $originalValue = $this->propertyAccessor->getValue($product, $attribute->getFieldName());

        $isForceIndexed = $this->indexFieldsProvider->isForceIndexed($attribute->getFieldName());
        $placeholders = $localization ? [LocalizationIdPlaceholder::NAME => $localization->getId()] : [];
        $isLocalized = $localization !== null;

        $fieldNames = [];
        if ($this->isFilterable($attribute, $attributeType, $isForceIndexed)) {
            $filterFieldName = $attributeType
                    ->getFilterableFieldNames($attribute)[SearchAttributeTypeInterface::VALUE_MAIN] ?? '';

            $this->addToFields(
                $data,
                $filterFieldName,
                $attributeType->getFilterableValue($attribute, $originalValue, $localization),
                $placeholders,
                $isLocalized,
                false
            );

            $fieldNames[] = $filterFieldName;
        }

        if ($this->isSortable($attribute, $attributeType, $isForceIndexed)) {
            $sortFieldName = $attributeType->getSortableFieldName($attribute);

            if (!in_array($sortFieldName, $fieldNames, true)) {
                $this->addToFields(
                    $data,
                    $sortFieldName,
                    $attributeType->getSortableValue($attribute, $originalValue, $localization),
                    $placeholders,
                    $isLocalized,
                    false
                );
            }

            $fieldNames[] = $sortFieldName;
        }

        if ($this->configurationProvider->isAttributeSearchable($attribute)) {
            $this->addToFields(
                $data,
                $isLocalized ? IndexDataProvider::ALL_TEXT_L10N_FIELD : IndexDataProvider::ALL_TEXT_FIELD,
                $attributeType->getSearchableValue($attribute, $originalValue, $localization),
                $placeholders,
                $isLocalized,
                true
            );

            if ($this->isBoostable($attribute, $attributeType)) {
                $searchFieldName = $attributeType->getSearchableFieldName($attribute);

                if (!in_array($searchFieldName, $fieldNames, true)) {
                    $this->addToFields(
                        $data,
                        $searchFieldName,
                        $attributeType->getSearchableValue($attribute, $originalValue, $localization),
                        $placeholders,
                        $isLocalized,
                        false
                    );
                }
            }
        }
    }

    /**
     * @param \ArrayIterator $fields
     * @param string $fieldName
     * @param mixed $fieldValue
     * @param array $placeholders
     * @param bool $localized
     * @param bool $searchable
     */
    private function addToFields(
        \ArrayIterator $fields,
        string $fieldName,
        $fieldValue,
        array $placeholders,
        bool $localized,
        bool $searchable
    ): void {
        if (!\is_array($fieldValue)) {
            $fieldValue = [$fieldName => $fieldValue];
        }

        foreach ($fieldValue as $key => $value) {
            $productModel = new ProductIndexDataModel(
                $key,
                $value,
                $placeholders,
                $localized,
                $searchable
            );
            $fields->append($productModel);
        }
    }

    protected function getAttributeType(FieldConfigModel $attribute): ?SearchAttributeTypeInterface
    {
        if (!$this->configurationProvider->isAttributeActive($attribute)) {
            return null;
        }

        $attributeType = $this->attributeTypeRegistry->getAttributeType($attribute);
        if (!$attributeType instanceof SearchAttributeTypeInterface) {
            return null;
        }

        return $attributeType;
    }

    private function isFilterable(FieldConfigModel $attribute, SearchAttributeTypeInterface $type, $force): bool
    {
        return $type->isFilterable($attribute) &&
            ($force || $this->configurationProvider->isAttributeFilterable($attribute));
    }

    private function isSortable(FieldConfigModel $attribute, SearchAttributeTypeInterface $type, $force): bool
    {
        return $type->isSortable($attribute) &&
            ($force || $this->configurationProvider->isAttributeSortable($attribute));
    }

    private function isBoostable(FieldConfigModel $attribute, SearchAttributeTypeInterface $type): bool
    {
        return $type->isSearchable() && $this->searchableProvider->getAttributeSearchBoost($attribute);
    }
}
