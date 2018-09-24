# WooCommerce Mix and Match - Per-item Pricing Discount

### What's This?

Experimental mini-extension for [WooCommerce Mix and Match](https://woocommerce.com/products/woocommerce-mix-and-match-products//) that allows you offer percentage discounts on products that are purchased as part of a Mix and Match container that is **Priced Individually**.

### Important

1. Requires WooCommerce Mix and Match 1.3.0+.
2. This is proof of concept and not officially supported in any way.

### Defining Discount

To configure the percentage discount to a Mix and Match container, navigate to **Product Data >Mix and Match** and locate the **Per-Item Discount (%s)** field, enter in a percent.

![A checkbox labeled "Per-Item Discount(%)" in the product data metabox](https://user-images.githubusercontent.com/507025/45967199-dd43d080-bff2-11e8-8acb-13722e2d28ef.png)

### Discounts in Mix and Match products

When a discount is configured, the price total that's normally displayed is displayed much like a "sale" price with the discounted price shown next to a strikethrough of the original price. The dynamic price that changes as the customer changes the configuration of the container is shown the same.

![An album "bundle" that shows a price discount with a strikethrough](https://user-images.githubusercontent.com/507025/45967903-fa799e80-bff4-11e8-993b-fc8fd783e3f3.png)

### Note

If a Mix and Mathch product has a base **Regular Price** and/or **Sale Price**, its base price component will remain unchanged by the discount.
