<link rel="stylesheet" href="/modules/paynl_paymentmethods/paynl.css" />


{foreach from=$profiles key=k item=v}
    <div class="row">
        <div class="col-xs-12">
            <p class="payment_module" >
                <a data-ajax="false" class="paynl_paymentmethod " href="{$link->getModuleLink('paynl_paymentmethods', 'payment', [pid => {$v.id}], true)|escape:'html'}" title="{l s=$v.name mod='paynl_paymentmethods'}">
                    <img src="https://static.pay.nl/payment_profiles/75x75/{$v.id}.png" alt="{$v.name}"  />
                    {$v.name}{if isset($v.extraCosts)}  <span class="">{$v.extraCosts}</span> {/if}
                </a>
            </p>
        </div>
    </div>
{/foreach}
