# Sk_CustomerExtend

Magento 2 module implementing customer-group based stock visibility and minimum quantity rules for Wholesale Only products.

## Features

- Adds the `wholesale_only` product EAV attribute.
- Adds `sk_customer_group_rule`, storing one minimum quantity per customer group.
- The rule is global for all products where `wholesale_only = Yes`; product IDs are intentionally not stored in the rule table.
- Guests are treated as the General customer group by default.
- General customers cannot add Wholesale Only products.
- Wholesale customers can see exact salable quantity when a positive group rule is configured.
- Other customer groups can also be enabled by configuring a positive rule.
- Exposes GraphQL fields for wholesale flag, customer group, stock status, conditional stock quantity and minimum order quantity.
- Enforces access and MOQ in `addProductsToCart`.
- Uses Magento MSI salable quantity APIs.
- No third-party service is required.

## Installation

Copy this directory to:

    app/code/Sk/CustomerExtend

Run:

    php bin/magento module:enable Sk_CustomerExtend
    php bin/magento setup:upgrade
    php bin/magento cache:flush

Production mode:

    php bin/magento setup:di:compile
    php bin/magento setup:static-content:deploy -f
    php bin/magento cache:flush

## Existing installation migration

If you previously installed a version using `sk_product_group_rule`, this version intentionally uses a new table name and schema. In a disposable local environment, remove the old table before `setup:upgrade`:

    DROP TABLE IF EXISTS sk_product_group_rule;

The new table is:

    sk_customer_group_rule

Do not drop an old production table until its data has been backed up/migrated.

## Product configuration

In Admin:

    Catalog > Products > <product>

Set:

    Wholesale Only = Yes

Products with `Wholesale Only = No` are unaffected.

## Customer groups

Find actual group IDs with:

    SELECT customer_group_id, customer_group_code FROM customer_group;

Do not assume IDs because they can differ between environments.

## Configure minimum quantity

The rule table has one row per customer group:

    customer_group_id | minimum_qty

Example:

    Wholesale | 10
    Dealer    | 25

This means every Wholesale Only product has MOQ 10 for Wholesale customers and MOQ 25 for Dealer customers.

CLI:

    php bin/magento sk:customerextend:set-min-qty --group-id=2 --min-qty=10

Delete:

    php bin/magento sk:customerextend:delete-min-qty --group-id=2

A positive rule enables the customer group for Wholesale Only products. General customers are denied regardless of whether a General rule exists.

## Database

    sk_customer_group_rule

Columns:

    rule_id
    customer_group_id
    minimum_qty

Unique key:

    customer_group_id

Foreign key:

    customer_group_id -> customer_group.customer_group_id

There is deliberately no `product_id`. The `wholesale_only` EAV attribute determines whether a product is subject to the group rule.

## GraphQL

Endpoint:

    https://YOUR-DOMAIN/graphql

ProductInterface fields:

    wholesale_only
    customer_group_stock_status
    customer_group_stock_quantity
    minimum_order_quantity
    customer_group_id

Example:

    query {
      products(filter: { sku: { eq: "WHOLESALE-001" } }) {
        items {
          sku
          name
          wholesale_only
          customer_group_id
          customer_group_stock_status
          customer_group_stock_quantity
          minimum_order_quantity
        }
      }
    }
# Sk_CustomerExtend
