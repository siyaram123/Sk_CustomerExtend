# Sk_CustomerExtend — Final GraphQL Test Guide

This README is based on the scenarios required by the task.

## 1. Final Test Data

| Item | Value |
|---|---|
| GraphQL URL | `http://localhost/magento247/pub/graphql` |
| General group | `1` |
| Wholesale group | `2` |
| Wholesale Only SKU | `24-MB04` |
| `24-MB04` stock | `50` |
| Wholesale Only SKU | `24-WB02` |
| `24-WB02` stock | `100` |
| Wholesale MOQ | `10` |

### Important quantity clarification

The module implements a **minimum quantity** rule only.

Therefore:

```text
MOQ = 10

quantity 5  -> FAIL
quantity 9  -> FAIL
quantity 10 -> PASS
```

The values `50` and `100` are product stock quantities, not maximum order quantities.

---

# 2. What Was Verified in the Module

The uploaded module contains:

- `wholesale_only` product EAV attribute.
- `sk_customer_group_rule` table with:
  - `rule_id`
  - `customer_group_id`
  - `minimum_qty`
- One global minimum-quantity rule per customer group.
- Guest customer context defaults to General group `1`.
- General customers are denied Wholesale Only products.
- Customers with a positive configured group rule can see exact salable quantity.
- GraphQL fields:
  - `wholesale_only`
  - `customer_group_stock_status`
  - `customer_group_stock_quantity`
  - `minimum_order_quantity`
  - `customer_group_id`
- `addProductsToCart` plugin validates:
  1. account/group access
  2. minimum quantity
- Magento MSI salable quantity API is used for stock quantity/status.
- No third-party service is used.

The module files were also checked for PHP syntax; all PHP files in the uploaded module passed `php -l`.

---

# 3. Installation

Copy the module to:

```text
app/code/Sk/CustomerExtend
```

From Magento root:

```bash
php bin/magento module:enable Sk_CustomerExtend
php bin/magento setup:upgrade
php bin/magento cache:flush
```

If required in development:

```bash
rm -rf generated/code generated/metadata
php bin/magento cache:flush
```

For production mode, if required:

```bash
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

---

# 4. Customer Groups

Use only:

```text
General   = 1
Wholesale = 2
```

Verify:

```sql
SELECT customer_group_id, customer_group_code
FROM customer_group;
```

Create:

- one General customer in group `1`
- one Wholesale customer in group `2`

---

# 5. Product Setup

In Magento Admin:

```text
Catalog > Products
```

Set:

```text
Wholesale Only = Yes
```

for:

```text
24-MB04
24-WB02
```

Expected stock:

```text
24-MB04 = 50
24-WB02 = 100
```

---

# 6. Configure Wholesale MOQ

The module provides the CLI command:

```bash
php bin/magento sk:customerextend:set-min-qty --group-id=2 --min-qty=10
```

Verify:

```sql
SELECT rule_id, customer_group_id, minimum_qty
FROM sk_customer_group_rule;
```

Expected:

```text
customer_group_id = 2
minimum_qty = 10
```

The rule applies globally to products where:

```text
wholesale_only = Yes
```

There is deliberately no `product_id` in the rule table.

---

# 7. Altair Setup

GraphQL endpoint:

```text
http://localhost/magento247/pub/graphql
```

For every test, capture:

1. Request
2. Response
3. Screenshot
4. Expected result
5. Actual result
6. PASS / FAIL

Mask the customer token in screenshots.

---

# 8. Generate General Customer Token

General customer must belong to group `1`.

### Request

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

Use the returned token:

```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

---

# 9. Generate Wholesale Customer Token

Wholesale customer must belong to group `2`.

### Request

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

Use the returned token:

```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

Replace the example credentials with the actual Magento customer credentials.

---

# 10. Create Guest Cart

Do not send an Authorization header.

### Request

```graphql
mutation {
  createEmptyCart
}
```

Expected:

```json
{
  "data": {
    "createEmptyCart": "<GUEST_CART_ID>"
  }
}
```

Use the returned value as:

```text
<GUEST_CART_ID>
```

The module's `CustomerContext` treats a guest as General group `1`.

---

# 11. Create/Get General Customer Cart

Use:

```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

### Request

```graphql
query {
  customerCart {
    id
  }
}
```

Use the returned value as:

```text
<GENERAL_CART_ID>
```

---

# 12. Create/Get Wholesale Customer Cart

Use:

```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

### Request

```graphql
query {
  customerCart {
    id
  }
}
```

Use the returned value as:

```text
<WHOLESALE_CART_ID>
```

---

# 13. GraphQL Product Query

The module extends `ProductInterface` with:

```text
wholesale_only
customer_group_stock_status
customer_group_stock_quantity
minimum_order_quantity
customer_group_id
```

Use this query for the product visibility tests:

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

For `24-WB02`, change only the SKU.

---

# REQUIRED PRODUCT QUERY SCENARIOS

## Scenario 1 — Guest: `24-MB04`

### Header

No Authorization header.

### Request

Use the product query above with:

```text
SKU = 24-MB04
```

### Expected

```text
wholesale_only = true
customer_group_id = 1
customer_group_stock_status = IN_STOCK
customer_group_stock_quantity = null/hidden
minimum_order_quantity = 0
```

The actual stock is `50`, but the guest must not receive the exact quantity.

---

## Scenario 2 — Guest: `24-WB02`

No Authorization header.

Use the same query with:

```text
SKU = 24-WB02
```

### Expected

```text
wholesale_only = true
customer_group_id = 1
customer_group_stock_status = IN_STOCK
customer_group_stock_quantity = null/hidden
minimum_order_quantity = 0
```

The actual stock is `100`, but the guest must not receive the exact quantity.

---

## Scenario 3 — General Customer: `24-MB04`

### Header

```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

### Expected

```text
wholesale_only = true
customer_group_id = 1
customer_group_stock_status = IN_STOCK
customer_group_stock_quantity = null/hidden
minimum_order_quantity = 0
```

General customer must not see the exact stock quantity.

---

## Scenario 4 — General Customer: `24-WB02`

### Header

```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

Use:

```text
SKU = 24-WB02
```

### Expected

```text
wholesale_only = true
customer_group_id = 1
customer_group_stock_status = IN_STOCK
customer_group_stock_quantity = null/hidden
minimum_order_quantity = 0
```

---

## Scenario 5 — Wholesale Customer: `24-MB04`

### Header

```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

### Expected

```text
wholesale_only = true
customer_group_id = 2
customer_group_stock_status = IN_STOCK
customer_group_stock_quantity = 50
minimum_order_quantity = 10
```

This verifies:

- customer context = Wholesale
- exact salable quantity is visible
- MOQ is visible

---

## Scenario 6 — Wholesale Customer: `24-WB02`

### Header

```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

Use:

```text
SKU = 24-WB02
```

### Expected

```text
wholesale_only = true
customer_group_id = 2
customer_group_stock_status = IN_STOCK
customer_group_stock_quantity = 100
minimum_order_quantity = 10
```

---

# REQUIRED ADD-TO-CART SCENARIOS

## Scenario 7 — General Customer Cannot Add `24-MB04`

### Header

```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<GENERAL_CART_ID>"
    cartItems: [
      {
        sku: "24-MB04"
        quantity: 1
      }
    ]
  ) {
    cart {
      id
    }
  }
}
```

### Expected

Mutation fails before the product is added.

Expected error:

```text
This product is not available for your account type.
```

This verifies the General customer restriction.

---

## Scenario 8 — General Customer Cannot Add `24-WB02`

### Header

```text
Authorization: Bearer <GENERAL_CUSTOMER_TOKEN>
```

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<GENERAL_CART_ID>"
    cartItems: [
      {
        sku: "24-WB02"
        quantity: 1
      }
    ]
  ) {
    cart {
      id
    }
  }
}
```

### Expected

```text
This product is not available for your account type.
```

---

## Scenario 9 — Guest Cannot Add `24-MB04`

### Header

No Authorization header.

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<GUEST_CART_ID>"
    cartItems: [
      {
        sku: "24-MB04"
        quantity: 1
      }
    ]
  ) {
    cart {
      id
    }
  }
}
```

### Expected

```text
This product is not available for your account type.
```

This verifies the requirement that guests are treated as General by default.

---

## Scenario 10 — Guest Cannot Add `24-WB02`

### Header

No Authorization header.

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<GUEST_CART_ID>"
    cartItems: [
      {
        sku: "24-WB02"
        quantity: 1
      }
    ]
  ) {
    cart {
      id
    }
  }
}
```

### Expected

```text
This product is not available for your account type.
```

---

# MOQ TESTS

The configured MOQ is:

```text
10
```

The module validates MOQ directly inside the `addProductsToCart` resolver plugin.

## Scenario 11 — Wholesale `24-MB04`, quantity below MOQ

### Header

```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<WHOLESALE_CART_ID>"
    cartItems: [
      {
        sku: "24-MB04"
        quantity: 5
      }
    ]
  ) {
    cart {
      id
    }
  }
}
```

### Expected

Mutation fails.

Expected error format:

```text
Minimum order quantity for 24-MB04 is 10.
```

The important validation is:

```text
5 < 10
```

---

## Scenario 12 — Wholesale `24-MB04`, quantity equal to MOQ

Change quantity to:

```text
10
```

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<WHOLESALE_CART_ID>"
    cartItems: [
      {
        sku: "24-MB04"
        quantity: 10
      }
    ]
  ) {
    cart {
      id
      items {
        product {
          sku
        }
        quantity
      }
    }
  }
}
```

### Expected

```text
SUCCESS
```

Because:

```text
10 >= 10
```

---

## Scenario 13 — Wholesale `24-WB02`, quantity below MOQ

### Header

```text
Authorization: Bearer <WHOLESALE_CUSTOMER_TOKEN>
```

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<WHOLESALE_CART_ID>"
    cartItems: [
      {
        sku: "24-WB02"
        quantity: 5
      }
    ]
  ) {
    cart {
      id
    }
  }
}
```

### Expected

Mutation fails with the module's MOQ error:

```text
Minimum order quantity for 24-WB02 is 10.
```

---

## Scenario 14 — Wholesale `24-WB02`, quantity equal to MOQ

Change quantity to:

```text
10
```

### Request

```graphql
mutation {
  addProductsToCart(
    cartId: "<WHOLESALE_CART_ID>"
    cartItems: [
      {
        sku: "24-WB02"
        quantity: 10
      }
    ]
  ) {
    cart {
      id
      items {
        product {
          sku
        }
        quantity
      }
    }
  }
}
```

### Expected

```text
SUCCESS
```

Because:

```text
10 >= 10
```

---

# FINAL REQUIRED TEST MATRIX

| # | Customer | SKU | Operation | Expected |
|---:|---|---|---|---|
| 1 | Guest | `24-MB04` | Product query | Status only, quantity hidden |
| 2 | Guest | `24-WB02` | Product query | Status only, quantity hidden |
| 3 | General `1` | `24-MB04` | Product query | Status only, quantity hidden |
| 4 | General `1` | `24-WB02` | Product query | Status only, quantity hidden |
| 5 | Wholesale `2` | `24-MB04` | Product query | Quantity `50`, MOQ `10` |
| 6 | Wholesale `2` | `24-WB02` | Product query | Quantity `100`, MOQ `10` |
| 7 | General `1` | `24-MB04` | Add qty `1` | Account-type error |
| 8 | General `1` | `24-WB02` | Add qty `1` | Account-type error |
| 9 | Guest | `24-MB04` | Add qty `1` | Account-type error |
| 10 | Guest | `24-WB02` | Add qty `1` | Account-type error |
| 11 | Wholesale `2` | `24-MB04` | Add qty `5` | MOQ error |
| 12 | Wholesale `2` | `24-MB04` | Add qty `10` | Success |
| 13 | Wholesale `2` | `24-WB02` | Add qty `5` | MOQ error |
| 14 | Wholesale `2` | `24-WB02` | Add qty `10` | Success |

---

# Requirement-to-Scenario Mapping

| Requirement | Covered By |
|---|---|
| Wholesale Only attribute | Scenarios 1–6 |
| Wholesale sees actual quantity | Scenarios 5–6 |
| General sees only stock status | Scenarios 3–4 |
| General cannot add Wholesale Only product | Scenarios 7–8 |
| Configurable MOQ | Configuration + Scenarios 11–14 |
| MOQ visible in product response | Scenarios 5–6 |
| Customer-context-aware GraphQL | Scenarios 3–6 |
| Guest treated as General | Scenarios 1–2, 9–10 |
| MOQ enforced in `addProductsToCart` | Scenarios 11–14 |
| No third-party service | Module implementation |

---

# No Maximum-Quantity Testing

Do **not** create a custom test such as:

```text
24-MB04 max order = 50
24-WB02 max order = 100
```

That is not part of this task.

Correct interpretation:

```text
24-MB04 current stock = 50
24-WB02 current stock = 100
Wholesale MOQ = 10
```

The custom rule is:

```text
quantity < 10  -> reject
quantity >= 10 -> allow
```

If Magento rejects an order because the requested quantity exceeds actual salable inventory, that is normal Magento/MSI inventory behavior and is **not a custom maximum-quantity feature**.

---

# Altair Evidence Checklist

For each of the 14 required scenarios, capture:

```text
[ ] GraphQL endpoint
[ ] Authorization header (token masked)
[ ] GraphQL request
[ ] GraphQL response
[ ] Screenshot
[ ] Expected result
[ ] Actual result
[ ] PASS / FAIL
```