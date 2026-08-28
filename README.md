# Sk_CustomerExtend

Magento 2 module implementing customer-group based stock visibility and minimum quantity rules for Wholesale Only products.

## Features

- Adds the `wholesale_only` product EAV attribute.
- Created table `sk_customer_group_rule`, storing one minimum quantity per customer group.
- The rule is global for all products where `wholesale_only = Yes`; product IDs are intentionally not stored in the rule table.
- Guests are treated as the General customer group by default.
- General customers cannot add Wholesale Only products.
- Wholesale customers can see exact salable quantity when a positive group rule is configured.
- - Exposes GraphQL fields for wholesale flag, customer group, stock status, conditional stock quantity and minimum order quantity.
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

## Product configuration

In Admin:

    Catalog > Products > <product>

Set:

    Wholesale Only = Yes

Products with `Wholesale Only = No` are unaffected.

## Customer groups

For this test environment, use only these customer groups:

    General   = 1
    Wholesale = 2

Verify with:

    SELECT customer_group_id, customer_group_code FROM customer_group;

## Configure minimum quantity

The rule table has one row per customer group:

    customer_group_id | minimum_qty

Example:

    Wholesale | 10

This means every Wholesale Only product has MOQ 10 for Wholesale customers. General customers remain denied for Wholesale Only products.

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

    http://localhost/magento247/pub/graphql

ProductInterface fields:

    wholesale_only
    customer_group_stock_status
    customer_group_stock_quantity
    minimum_order_quantity
    customer_group_id

Example:

    query {
      products(filter: { sku: { eq: "24-MB04" } }) {
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


# Altair Authentication and Cart Setup

## General Customer Token (Group 1)

```graphql
mutation {
  generateCustomerToken(
    email: "general@example.com"
    password: "General@123"
  ) {
    token
  }
}
```

Use the returned token in Altair:

```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

The customer must belong to General group `1`.

## Wholesale Customer Token (Group 2)

```graphql
mutation {
  generateCustomerToken(
    email: "wholesale@example.com"
    password: "Wholesale@123"
  ) {
    token
  }
}
```

Use the returned token in Altair:

```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

The customer must belong to Wholesale group `2`.

Use the actual Magento customer credentials in your environment.

## Guest Cart ID

Do not send an Authorization header.

```graphql
mutation {
  createEmptyCart
}
```

The returned value is the Guest cart ID:

```text
<GUEST_CART_ID>
```

## General Customer Cart ID

Use the General token and run:

```graphql
query {
  customerCart {
    id
  }
}
```

The returned ID is:

```text
<GENERAL_CART_ID>
```

## Wholesale Customer Cart ID

Use the Wholesale token and run:

```graphql
query {
  customerCart {
    id
  }
}
```

The returned ID is:

```text
<WHOLESALE_CART_ID>
```

**Use `createEmptyCart` for Guest and `customerCart` for logged-in General/Wholesale customers.**

# Altair Test Scenarios

Use the GraphQL endpoint:

```text
http://localhost/magento247/pub/graphql
```

Wholesale Only test SKUs:

```text
24-MB04
24-WB02
```

For each scenario, capture the Altair request, response, and screenshot. Mask customer tokens in evidence.

## Prerequisites

1. Enable the module and run the installation commands above.
2. Create/verify a General customer and a Wholesale customer.
3. Find actual customer group IDs:

```sql
SELECT customer_group_id, customer_group_code FROM customer_group;
```

4. Set `Wholesale Only = Yes` for `24-MB04` and `24-WB02`.
5. Configure Wholesale MOQ, for example:

```bash
php bin/magento sk:customerextend:set-min-qty --group-id=2 --min-qty=10
```

6. Verify the rule:

```sql
SELECT * FROM sk_customer_group_rule;
```

7. Make sure inventory/salable quantities are known before testing.

## Altair Authentication

### Guest
No `Authorization` header.

### General
```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

### Wholesale
```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

# Product Query Scenarios

## 1. Guest - 24-MB04

```graphql
query {
  products(filter: { sku: { eq: "24-MB04" } }) {
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
```

Expected: product is returned; guest is treated as General; exact stock quantity must not be exposed.

## 2. Guest - 24-WB02

Use the same query with `24-WB02`. Expected: status only, no exact quantity.

## 3. General - 24-MB04

Use the same query with the General token. Expected: `wholesale_only = true`, stock status only, no exact salable quantity, and no Wholesale MOQ exposed to the General customer.

## 4. General - 24-WB02

Repeat Scenario 3 with `24-WB02`.

## 5. Wholesale - 24-MB04

Use the Wholesale token and the same query. Expected: Wholesale group ID, stock status, exact salable quantity, and `minimum_order_quantity = 10` (assuming the configured rule is 10).

## 6. Wholesale - 24-WB02

Repeat Scenario 5 with `24-WB02`.

# Add To Cart Scenarios

## 7. General - Add 24-MB04

```graphql
mutation {
  addProductsToCart(
    cartId: "<GENERAL_CART_ID>"
    cartItems: [{ sku: "24-MB04", quantity: 1 }]
  ) {
    cart { id }
  }
}
```

Expected: mutation fails gracefully with the module's account-type error, e.g. `This product is not available for your account type`.

## 8. General - Add 24-WB02

Repeat Scenario 7 with `24-WB02`. Expected: same access-denied behavior.

## 9. Guest - Add 24-MB04

```graphql
mutation {
  addProductsToCart(
    cartId: "<GUEST_CART_ID>"
    cartItems: [{ sku: "24-MB04", quantity: 1 }]
  ) {
    cart { id }
  }
}
```

Expected: rejected because guests are General by default.

## 10. Guest - Add 24-WB02

Repeat Scenario 9 with `24-WB02`.

## 11. Wholesale - 24-MB04 Below MOQ

Assuming MOQ 10, try quantity 5:

```graphql
mutation {
  addProductsToCart(
    cartId: "<WHOLESALE_CART_ID>"
    cartItems: [{ sku: "24-MB04", quantity: 5 }]
  ) {
    cart { id }
  }
}
```

Expected: rejected with the module's minimum-order-quantity error.

## 12. Wholesale - 24-MB04 Exact MOQ

Change quantity to `10`. Expected: success and quantity 10 in the cart.

## 13. Wholesale - 24-MB04 Above MOQ

Change quantity to `15`. Expected: success if inventory is sufficient.

## 14. Wholesale - 24-WB02 Below MOQ

Use `24-WB02`, quantity `5`. Expected: MOQ rejection.

## 15. Wholesale - 24-WB02 Exact MOQ

Use `24-WB02`, quantity `10`. Expected: success if inventory is sufficient.

## 16. Wholesale - 24-WB02 Above MOQ

Use `24-WB02`, quantity `15`. Expected: success if inventory is sufficient.

# Boundary And Inventory Scenarios

## 17. MOQ Boundary - 9 vs 10

For each Wholesale Only SKU:

- Quantity 9: expected failure.
- Quantity 10: expected success.

This confirms the minimum is enforced at `addProductsToCart`.

## 18. Wholesale - Quantity Greater Than Stock - 24-MB04

`24-MB04` has stock quantity 50. Request quantity 51. Expected: normal Magento MSI inventory rejection.

## 19. Wholesale - Quantity Greater Than Stock - 24-WB02

Request a quantity greater than the actual salable quantity. Expected: normal Magento MSI inventory rejection.

## 20. Out Of Stock - 24-MB04 - Wholesale

Set salable quantity to zero and query the product. Expected: `customer_group_stock_status = OUT_OF_STOCK`; exact quantity behavior should match the implemented Wholesale resolver.

## 21. Out Of Stock - 24-MB04 - General

Query as General. Expected: `OUT_OF_STOCK` with no confidential exact quantity exposed.

## 22. Out Of Stock - 24-WB02

`24-WB02` has stock quantity 100. Request quantity 101. Expected: normal Magento MSI inventory rejection.

# Non-Wholesale Regression Scenarios

## 23. Normal Product Query

Select a product whose `Wholesale Only = No` and query:

```graphql
query {
  products(filter: { sku: { eq: "<NORMAL_PRODUCT_SKU>" } }) {
    items {
      sku
      name
      wholesale_only
      customer_group_stock_status
      customer_group_stock_quantity
      minimum_order_quantity
    }
  }
}
```

Expected: Wholesale Only restrictions do not apply.

## 24. General - Add Normal Product

Use `addProductsToCart` with `<NORMAL_PRODUCT_SKU>` and quantity 1. Expected: normal Magento behavior/success when inventory permits.

## 25. Wholesale - Add Normal Product

Repeat Scenario 24 using the Wholesale token. Expected: normal Magento behavior.

# Customer Group Rule Scenarios

## 26. Group-Specific MOQ

For this test, configure only Wholesale group 2 with MOQ 10. General group 1 remains denied for Wholesale Only products. Query a Wholesale Only SKU as each group and verify `minimum_order_quantity` follows the logged-in customer group.

## 27. General Group Does Not Enable Wholesale Only Purchase

General group ID 1 must remain denied for Wholesale Only products. Do not configure a General MOQ rule for this test.

## 28. Delete Wholesale Rule

```bash
php bin/magento sk:customerextend:delete-min-qty --group-id=2
```

Query `24-MB04` as Wholesale and verify the no-positive-rule behavior implemented by the module. Recreate the rule afterward:

```bash
php bin/magento sk:customerextend:set-min-qty --group-id=2 --min-qty=10
```

# Final Test Data

```text
GraphQL URL: http://localhost/magento247/pub/graphql
General group: 1
Wholesale group: 2
Wholesale MOQ: 10
24-MB04 stock: 50
24-WB02 stock: 100
```

## Add-to-Cart Boundary Matrix

| SKU | Stock | Qty 5 | Qty 9 | Qty 10 | Qty 15 | Qty = Stock | Qty > Stock |
|---|---:|---|---|---|---|---|---|
| `24-MB04` | 50 | FAIL MOQ | FAIL MOQ | PASS | PASS | 50 PASS | 51 FAIL stock |
| `24-WB02` | 100 | FAIL MOQ | FAIL MOQ | PASS | PASS | 100 PASS | 101 FAIL stock |

## Access Matrix

| Customer | Group | Query 24-MB04 | Query 24-WB02 | Add Wholesale Only |
|---|---:|---|---|---|
| Guest | 1 | Status only | Status only | FAIL |
| General | 1 | Status only | Status only | FAIL |
| Wholesale | 2 | Exact 50 + MOQ 10 | Exact 100 + MOQ 10 | PASS when MOQ and stock pass |

# Test Evidence Checklist

For every scenario record:

- Scenario number and name
- Customer type: Guest / General / Wholesale / other configured group
- SKU: `24-MB04` or `24-WB02`
- Altair endpoint
- Request headers (mask token)
- GraphQL request
- GraphQL response
- Screenshot
- Expected result
- Actual result
- PASS/FAIL

# Acceptance Matrix

| Requirement | Scenarios |
|---|---|
| Wholesale Only attribute | 1-6 |
| Guest treated as General | 1, 2, 9, 10 |
| General sees status, not exact quantity | 3, 4, 21, 22 |
| Wholesale sees exact salable quantity | 5, 6, 20, 22 |
| Customer-group-aware GraphQL | 3-6, 26 |
| MOQ visible in product response | 5, 6, 26 |
| General access denied | 7, 8, 27 |
| Guest access denied | 9, 10 |
| MOQ enforced in addProductsToCart | 11-17 |
| Magento inventory validation preserved | 18, 19 |
| Out-of-stock handling | 20-22 |
| Normal products unaffected | 23-25 |
| Rule deletion/reconfiguration | 26-28 |
