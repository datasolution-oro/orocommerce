@ticket-BB-21658

Feature: Product short description and brand microdata schema org
  In order to have short description schema.org on website product lists and view pages
  As an Administrator
  I want to have ability to change product description that used in schema.org microdata description used on product pages
  As a Guest
  I want to have ability to view short product description schema.org on product list and view pages

  Scenario: Feature Background
    Given sessions active:
      | Admin | first_session  |
      | Buyer | second_session |
    And I set configuration property "oro_product.related_products_min_items" to "1"
    And I set configuration property "oro_product.upsell_products_min_items" to "1"
    And I disable configuration options:
      | oro_product.microdata_without_prices_disabled |
    And I proceed as the Admin
    And I login as administrator

  Scenario: Prepare test product with descriptions and brand
    Given I go to Products/ Brand
    And click "Create Brand"
    And I fill "Brand Form" with:
      | Name | Test Brand |
    When I save and close form
    Then I should see "Brand has been saved" flash message
    And I go to Products/ Products
    And click "Create Product"
    And I click "Continue"
    And I fill "ProductForm" with:
      | SKU               | TestSKU123                           |
      | Name              | Test Product                         |
      | Brand             | Test Brand                           |
      | Status            | Enable                               |
      | Is Featured       | Yes                                  |
      | Description       | <p>Test Product Full Description</p> |
      | Short Description | Test Product Short Description       |
    When I save and duplicate form
    Then I should see "Product has been saved and duplicated" flash message
    And I click "Edit"
    And I fill "ProductForm" with:
      | SKU    | TestSKU456 |
      | Status | Enable     |
    And I save and duplicate form
    And I click "Edit"
    And I fill "ProductForm" with:
      | SKU    | TestSKU789 |
      | Status | Enable     |
    When I click "Select related products"
    Then I select following records in "SelectRelatedProductsGrid" grid:
      | TestSKU123 |
      | TestSKU456 |
    And I click "Select products"
    And I click "Up-sell Products"
    And I click "Select up-sell products"
    And I select following records in "SelectUpsellProductsGrid" grid:
      | TestSKU123 |
      | TestSKU456 |
    And I click "Select products"
    And I save form

  Scenario: Check short schema.org product description and brand in featured products are not present on the store frontend
    Given I proceed as the Buyer
    When I go to the homepage
    Then I should not see schema org brand for "TestSKU456" in "Featured Products Block"
    And I should not see schema org description for "TestSKU456" in "Featured Products Block"

  Scenario: Check short schema.org product description and brand in search product list are not present on the store frontend
    When I type "TestSKU789" in "search"
    And I click "Search Button"
    Then I should not see schema org brand for "TestSKU789" in "Product Frontend Grid"
    And I should not see schema org description for "TestSKU789" in "Product Frontend Grid"

  Scenario: Check short schema.org product description and brand in product view page are not present on the store frontend
    When I click "View Details"
    Then I should see schema org description "Test Product Full Description" on page
    And I should not see schema org brand on page
    And I should not see schema org brand for "TestSKU123" in "Related Products Block"
    And I should not see schema org description for "TestSKU123" in "Upsell Products Block"

  Scenario: Enable schema org product description and trigger search reindex for the affected products
    Given I proceed as the Admin
    And I enable configuration options:
      | oro_product.schema_org_description_field_enabled |
    And I set configuration property "oro_product.schema_org_description_field" to "oro_product_short_description"
    And I go to Products/ Products
    And I click Edit TestSKU123 in grid
    And I save and close form
    And I go to Products/ Products
    And I click Edit TestSKU456 in grid
    And I save and close form
    And I go to Products/ Products
    And I click Edit TestSKU789 in grid
    And I save and close form

  Scenario: Check short schema.org product description and brand in featured products on the store frontend
    Given I proceed as the Buyer
    When I go to the homepage
    Then I should see schema org brand "Test Brand" for "TestSKU123" in "Featured Products Block"
    And I should see schema org description "Test Product Short Description" for "TestSKU123" in "Featured Products Block"

  Scenario: Check short schema.org product description and brand in search product list on the store frontend
    When I type "TestSKU789" in "search"
    And I click "Search Button"
    Then I should see schema org brand "Test Brand" for "TestSKU789" in "Product Frontend Grid"
    And I should see schema org description "Test Product Short Description" for "TestSKU789" in "Product Frontend Grid"

  Scenario: Check short schema.org product description and brand in product view page on the store frontend
    When I click "View Details"
    Then I should see schema org description "Test Product Short Description" on page
    And I should see schema org brand "Test Brand" for "TestSKU123" in "Related Products Block"
    And I should see schema org description "Test Product Short Description" for "TestSKU123" in "Upsell Products Block"
