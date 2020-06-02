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
    {/literal}{if $w_logged}{literal}
    woopra.identify({
        email: "{/literal}{$w_email|escape:'htmlall':'UTF-8'}{literal}",
        name: "{/literal}{$w_name|escape:'htmlall':'UTF-8'}{literal}",
    });
    {/literal}{/if}{literal}

    var w_controller = "{/literal}{$w_controller|escape:'htmlall':'UTF-8'}{literal}";
    var track_pv = "{/literal}{$track_pv|escape:'htmlall':'UTF-8'}{literal}";
    if (w_controller === 'product' && track_pv === '1') {
        var productDetails = {/literal}{$w_prod_vars|json_encode nofilter}{literal};
        woopra.track('view product', {
            product_name: productDetails.name,
            product_price: productDetails.price,
            product_sku: productDetails.reference,
            product_category: productDetails.category,
            product_url: productDetails.url
        });
    } else {
        woopra.track();
    }

    var track_cf = "{/literal}{$track_cf|escape:'htmlall':'UTF-8'}{literal}";
    if (track_cf === '1') {
        var w_subject = "{/literal}{$w_subject|escape:'htmlall':'UTF-8'}{literal}";
        var w_message = "{/literal}{$w_message|escape:'htmlall':'UTF-8'}{literal}";
        var w_from = "{/literal}{$w_from|escape:'htmlall':'UTF-8'}{literal}";
        woopra.track("contact form", {
            subject: w_subject,
            message: w_message,
            email_from: w_from
        });
    }
</script>
{/literal}
