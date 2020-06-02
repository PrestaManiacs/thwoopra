{*
* 2006-2020 THECON SRL
*
* NOTICE OF LICENSE
*
* DISCLAIMER
*
* YOU ARE NOT ALLOWED TO REDISTRIBUTE OR RESELL THIS FILE OR ANY OTHER FILE
* USED BY THIS MODULE.
*
* @author    THECON SRL <contact@thecon.ro>
* @copyright 2006-2020 THECON SRL
* @license   Commercial
*}

{literal}
<script>
    woopra.track('checkout', {
        total_items: {/literal}{$w_ordered|count}{literal},
        discount_amount: {/literal}{$w_discount_amount|escape:'htmlall':'UTF-8'}{literal},
        tax_amount: {/literal}{$w_tax_amount|escape:'htmlall':'UTF-8'}{literal},
        shipping_amount: {/literal}{$w_shipping_amount|escape:'htmlall':'UTF-8'}{literal},
        total_amount: {/literal}{$w_total_amount|escape:'htmlall':'UTF-8'}{literal},
        order_id: "{/literal}{$w_reference|escape:'htmlall':'UTF-8'}{literal}"
    });

    {/literal}{foreach from=$w_ordered item=product}{literal}
        woopra.track('item checkout', {
            product_name: "{/literal}{$product.name|escape:'htmlall':'UTF-8'}{literal}",
            product_price: {/literal}{$product.price|escape:'htmlall':'UTF-8'}{literal},
            product_sku: "{/literal}{$product.sku|escape:'htmlall':'UTF-8'}{literal}",
            product_url: "{/literal}{$product.url|escape:'htmlall':'UTF-8'}{literal}",
            product_category: "{/literal}{$product.category|escape:'htmlall':'UTF-8'}{literal}",
            quantity: {/literal}{$product.quantity|escape:'htmlall':'UTF-8'}{literal}
        });
    {/literal}{/foreach}{literal}
</script>
{/literal}

