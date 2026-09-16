{*
 * LinkBay configuration page.
 *}

<div class="card linkbay-panel">
    <div class="card-header">{$labels.connection|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        {if $is_connected}
            <span class="linkbay-badge linkbay-badge--success">{$labels.connected|escape:'html':'UTF-8'}</span>
        {elseif !$has_credentials}
            <span class="linkbay-badge linkbay-badge--warning">{$labels.credentials_missing|escape:'html':'UTF-8'}</span>
        {else}
            <span class="linkbay-badge linkbay-badge--danger">{$labels.not_connected|escape:'html':'UTF-8'}</span>
        {/if}

        <div class="mt-3">
            <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="d-inline">
                <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                <button type="submit" name="linkbay_connect" class="btn btn-primary">{$labels.connect|escape:'html':'UTF-8'}</button>
            </form>
            <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="d-inline">
                <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                <button type="submit" name="linkbay_test" class="btn btn-secondary">{$labels.test|escape:'html':'UTF-8'}</button>
            </form>
            <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="d-inline">
                <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                <button type="submit" name="linkbay_disconnect" class="btn btn-danger">{$labels.disconnect|escape:'html':'UTF-8'}</button>
            </form>
        </div>

        <p class="text-muted mt-3 mb-0">
            {$labels.support_help|escape:'html':'UTF-8'}
            <a href="mailto:{$support_email|escape:'html':'UTF-8'}">{$support_email|escape:'html':'UTF-8'}</a>
        </p>
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.compat|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <p class="text-muted">{$labels.compat_scoped|escape:'html':'UTF-8'}</p>
        <div class="alert alert-info">{$labels.compat_tax|escape:'html':'UTF-8'}</div>

        <h6 class="mt-3">{$labels.compat_concerns_title|escape:'html':'UTF-8'}</h6>
        {if empty($compat_modules)}
            <p class="text-muted">{$labels.compat_none|escape:'html':'UTF-8'}</p>
        {else}
            <p class="text-muted">{$labels.compat_concerns_text|escape:'html':'UTF-8'}</p>
            <ul class="mb-0">
                {foreach $compat_modules as $mod}
                    <li><code>{$mod|escape:'html':'UTF-8'}</code></li>
                {/foreach}
            </ul>
        {/if}
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.api_settings|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <form method="post" action="{$controller_link|escape:'html':'UTF-8'}">
            <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">

            <div class="form-group">
                <label>{$labels.ebay_code|escape:'html':'UTF-8'}</label>
                <input type="text" name="redirect_uri" value="{$redirect_uri|escape:'html':'UTF-8'}" class="form-control" style="max-width: 480px;">
                <small class="form-text text-muted">{$labels.ebay_code_help|escape:'html':'UTF-8'}</small>
            </div>

            <div class="form-group">
                <label>{$labels.ebay_username|escape:'html':'UTF-8'}</label>
                <input type="text" name="ebay_username" value="{$ebay_username|escape:'html':'UTF-8'}" class="form-control">
                <small class="form-text text-muted">{$labels.ebay_username_help|escape:'html':'UTF-8'}</small>
            </div>

            <div class="form-group">
                <label>{$labels.tax_rule|escape:'html':'UTF-8'}</label>
                <select name="tax_rule" class="form-control">
                    {foreach $tax_rule_options as $rule}
                        <option value="{$rule.id|escape:'html':'UTF-8'}" {if $tax_rule == $rule.id}selected{/if}>{$rule.name|escape:'html':'UTF-8'}</option>
                    {/foreach}
                </select>
                <small class="form-text text-muted">{$labels.tax_rule_help|escape:'html':'UTF-8'}</small>
            </div>

            <div class="form-group">
                <label>{$labels.site_id|escape:'html':'UTF-8'}</label>
                <div class="row">
                    {foreach $site_options as $site}
                        <div class="col-md-4 mb-2">
                            <label class="form-check-label">
                                <input type="checkbox" name="site_ids[]" value="{$site.value|escape:'html':'UTF-8'}" {if in_array($site.value, $site_ids)}checked{/if}>
                                {$site.label|escape:'html':'UTF-8'}
                            </label>
                        </div>
                    {/foreach}
                </div>
                <small class="form-text text-muted">{$labels.site_help|escape:'html':'UTF-8'}</small>
            </div>

            <hr>

            <div class="form-group">
                <label>{$labels.default_category|escape:'html':'UTF-8'}</label>
                <select name="default_category" class="form-control">
                    <option value="0">--</option>
                    {foreach $categories as $category}
                        <option value="{$category.id|escape:'html':'UTF-8'}" {if $default_category == $category.id}selected{/if}>{$category.name|escape:'html':'UTF-8'}</option>
                    {/foreach}
                </select>
            </div>

            <div class="form-group">
                <label>{$labels.sync_frequency|escape:'html':'UTF-8'}</label>
                <input type="number" name="sync_frequency" value="{$sync_frequency|escape:'html':'UTF-8'}" min="1" max="1440" class="form-control">
            </div>

            <div class="form-group">
                <label class="d-block">
                    <input type="checkbox" name="auto_import" value="1" {if $auto_import}checked{/if}>
                    {$labels.auto_import|escape:'html':'UTF-8'}
                </label>
            </div>

            <button type="submit" name="submitLinkBaySettings" class="btn btn-primary">{$labels.save|escape:'html':'UTF-8'}</button>
        </form>
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.cron|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <p>{$labels.cron_help|escape:'html':'UTF-8'}</p>
        <input type="text" value="{$cron_url|escape:'html':'UTF-8'}" class="form-control linkbay-cron-url" readonly>
        <p class="mt-2"><small>{$labels.cron_token|escape:'html':'UTF-8'}: <code>{$cron_token|escape:'html':'UTF-8'}</code></small></p>
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.webhooks|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <p>
            <span class="linkbay-badge {if $webhooks_enabled}linkbay-badge--success{else}linkbay-badge--danger{/if}">
                {if $webhooks_enabled}{$labels.webhooks_enabled|escape:'html':'UTF-8'}{else}{$labels.webhooks_disabled|escape:'html':'UTF-8'}{/if}
            </span>
        </p>
        <p>{$labels.webhooks_help|escape:'html':'UTF-8'}</p>
        <p class="mt-2"><small>{$labels.webhook_url|escape:'html':'UTF-8'}: <code>{$webhook_url|escape:'html':'UTF-8'}</code></small></p>
        <div class="mt-3">
            {if !$webhooks_enabled}
                <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="d-inline">
                    <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                    <button type="submit" name="linkbay_webhooks_enable" class="btn btn-success">{$labels.webhooks_enable|escape:'html':'UTF-8'}</button>
                </form>
            {else}
                <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="d-inline">
                    <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                    <button type="submit" name="linkbay_webhooks_disable" class="btn btn-danger">{$labels.webhooks_disable|escape:'html':'UTF-8'}</button>
                </form>
            {/if}
        </div>
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.buffer|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <p>{$labels.buffer_help|escape:'html':'UTF-8'}</p>

        <form method="post" action="{$controller_link|escape:'html':'UTF-8'}">
            <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label>{$labels.buffer_mode|escape:'html':'UTF-8'}</label>
                    <select name="buffer_mode" class="form-control">
                        <option value="none" {if $buffer_mode == 'none'}selected{/if}>{$labels.buffer_none|escape:'html':'UTF-8'}</option>
                        <option value="percentage" {if $buffer_mode == 'percentage'}selected{/if}>{$labels.buffer_percentage|escape:'html':'UTF-8'}</option>
                        <option value="fixed" {if $buffer_mode == 'fixed'}selected{/if}>{$labels.buffer_fixed|escape:'html':'UTF-8'}</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <label>{$labels.buffer_value|escape:'html':'UTF-8'}</label>
                    <input type="number" name="buffer_value" value="{$buffer_value|escape:'html':'UTF-8'}" min="0" max="100" step="1" class="form-control">
                </div>
                <div class="form-group col-md-4 d-flex align-items-end">
                    <button type="submit" name="submitLinkBaySettings" class="btn btn-primary">{$labels.save|escape:'html':'UTF-8'}</button>
                </div>
            </div>
        </form>

        <hr>
        <p class="text-muted">{$labels.buffer_per_product|escape:'html':'UTF-8'}</p>
        <form method="post" action="{$controller_link|escape:'html':'UTF-8'}">
            <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
            <table class="table table-striped linkbay-table">
                <thead>
                    <tr>
                        <th>{$labels.title_col|escape:'html':'UTF-8'}</th>
                        <th>{$labels.buffer_percentage|escape:'html':'UTF-8'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $buffer_products as $bp}
                        <tr>
                            <td>{$bp.name|escape:'html':'UTF-8'} <small class="text-muted">({$bp.ebay_item_id|escape:'html':'UTF-8'})</small></td>
                            <td>
                                <input type="number" name="buffer_percent[{$bp.id|escape:'html':'UTF-8'}]"
                                       value="{if $bp.buffer_percent !== null}{$bp.buffer_percent|escape:'html':'UTF-8'}{/if}"
                                       min="0" max="100" step="1" class="form-control" style="max-width: 120px;">
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
            <button type="submit" name="submitBufferSettings" class="btn btn-primary">{$labels.buffer_save|escape:'html':'UTF-8'}</button>
        </form>
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.price_rules|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <p class="text-muted">{$labels.price_rules_help|escape:'html':'UTF-8'}</p>

        <div class="d-flex mb-3">
            <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="mr-2">
                <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                <button type="submit" name="linkbay_price_apply_now" class="btn btn-primary">{$labels.price_rule_apply_now|escape:'html':'UTF-8'}</button>
            </form>

            <form method="post" action="{$controller_link|escape:'html':'UTF-8'}">
                <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                <div class="form-check mb-1">
                    <input type="checkbox" name="price_rule_apply_to_ps" id="price_rule_apply_to_ps" value="1"
                           {if $price_rule_apply_to_ps}checked{/if} class="form-check-input">
                    <label class="form-check-label" for="price_rule_apply_to_ps">{$labels.price_rule_apply_to_ps|escape:'html':'UTF-8'}</label>
                    <small class="form-text text-muted">{$labels.price_rule_apply_to_ps_help|escape:'html':'UTF-8'}</small>
                </div>
                <button type="submit" name="submitPriceRuleSettings" class="btn btn-sm btn-secondary">{$labels.price_rule_settings_save|escape:'html':'UTF-8'}</button>
            </form>
        </div>

        {if empty($price_rules)}
            <p class="text-muted">{$labels.price_rules_empty|escape:'html':'UTF-8'}</p>
        {else}
            <table class="table table-striped linkbay-table">
                <thead>
                    <tr>
                        <th>{$labels.price_rule_name|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_min_stock|escape:'html':'UTF-8'} / {$labels.price_rule_max_stock|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_percent|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_priority|escape:'html':'UTF-8'}</th>
                        <th>{$labels.status|escape:'html':'UTF-8'}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $price_rules as $rule}
                        <tr>
                            <td>{$rule.name|escape:'html':'UTF-8'}</td>
                            <td>{$rule.condition|escape:'html':'UTF-8'}</td>
                            <td>
                                {if $rule.percent_positive}
                                    <span class="linkbay-badge linkbay-badge--danger">{$rule.percent|escape:'html':'UTF-8'}</span>
                                {else}
                                    <span class="linkbay-badge linkbay-badge--success">{$rule.percent|escape:'html':'UTF-8'}</span>
                                {/if}
                            </td>
                            <td>{$rule.priority|intval}</td>
                            <td>
                                {if $rule.active}
                                    <span class="linkbay-badge linkbay-badge--success">{$labels.price_rule_enabled|escape:'html':'UTF-8'}</span>
                                {else}
                                    <span class="linkbay-badge linkbay-badge--warning">{$labels.price_rule_disabled|escape:'html':'UTF-8'}</span>
                                {/if}
                            </td>
                            <td class="text-right">
                                <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="d-inline">
                                    <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                                    <input type="hidden" name="id_price_rule" value="{$rule.id|intval}">
                                    <button type="submit" name="price_rule_toggle" class="btn btn-sm btn-secondary">
                                        {if $rule.active}{$labels.price_rule_disabled|escape:'html':'UTF-8'}{else}{$labels.price_rule_enabled|escape:'html':'UTF-8'}{/if}
                                    </button>
                                </form>
                                <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="d-inline">
                                    <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                                    <input type="hidden" name="id_price_rule" value="{$rule.id|intval}">
                                    <button type="submit" name="price_rule_delete" class="btn btn-sm btn-danger">{$labels.delete|escape:'html':'UTF-8'}</button>
                                </form>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}

        <hr>

        <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="form-row align-items-end">
            <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
            <div class="form-group col-md-3">
                <label>{$labels.price_rule_name|escape:'html':'UTF-8'}</label>
                <input type="text" name="price_rule_name" class="form-control" placeholder="Surstock / rareté">
            </div>
            <div class="form-group col-md-2">
                <label>{$labels.price_rule_min_stock|escape:'html':'UTF-8'}</label>
                <input type="number" name="price_rule_min_stock" min="0" class="form-control" placeholder="50">
            </div>
            <div class="form-group col-md-2">
                <label>{$labels.price_rule_max_stock|escape:'html':'UTF-8'}</label>
                <input type="number" name="price_rule_max_stock" min="0" class="form-control" placeholder="5">
            </div>
            <div class="form-group col-md-2">
                <label>{$labels.price_rule_percent|escape:'html':'UTF-8'}</label>
                <input type="number" name="price_rule_percent" step="0.01" class="form-control" placeholder="-5">
                <small class="form-text text-muted">{$labels.price_rule_percent_help|escape:'html':'UTF-8'}</small>
            </div>
            <div class="form-group col-md-2">
                <label>{$labels.price_rule_priority|escape:'html':'UTF-8'}</label>
                <input type="number" name="price_rule_priority" min="0" class="form-control" placeholder="{$price_rule_next_priority|intval}">
                <small class="form-text text-muted">{$labels.price_rule_priority_help|escape:'html':'UTF-8'}</small>
            </div>
            <div class="form-group col-md-1">
                <button type="submit" name="submitPriceRuleAdd" class="btn btn-primary">{$labels.price_rule_add|escape:'html':'UTF-8'}</button>
            </div>
        </form>

        {if !empty($price_rule_products)}
            <hr>
            <h5 class="mb-3">{$labels.price_rule_products_title|escape:'html':'UTF-8'}</h5>
            <table class="table table-striped linkbay-table">
                <thead>
                    <tr>
                        <th>{$labels.price_rule_product|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_stock|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_base_price|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_matched|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_target|escape:'html':'UTF-8'}</th>
                        <th>{$labels.price_rule_status|escape:'html':'UTF-8'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $price_rule_products as $prp}
                        <tr>
                            <td>{$prp.name|escape:'html':'UTF-8'}</td>
                            <td>{$prp.stock|intval}</td>
                            <td>{$prp.base_price|escape:'html':'UTF-8'} {$prp.currency|escape:'html':'UTF-8'}</td>
                            <td>{$prp.rule_name|escape:'html':'UTF-8'}</td>
                            <td>
                                {if $prp.target_price !== null}
                                    <strong>{$prp.target_price|escape:'html':'UTF-8'} {$prp.currency|escape:'html':'UTF-8'}</strong>
                                {else}
                                    —
                                {/if}
                            </td>
                            <td>
                                {if $prp.price_status == 'pushed'}
                                    <span class="linkbay-badge linkbay-badge--success">{$labels.price_status_pushed|escape:'html':'UTF-8'}</span>
                                {elseif $prp.price_status == 'not_pushable'}
                                    <span class="linkbay-badge linkbay-badge--warning">{$labels.price_status_not_pushable|escape:'html':'UTF-8'}</span>
                                {elseif $prp.price_status == 'push_error'}
                                    <span class="linkbay-badge linkbay-badge--danger">{$labels.price_status_push_error|escape:'html':'UTF-8'}</span>
                                {else}
                                    <span class="linkbay-badge">{$labels.price_status_none|escape:'html':'UTF-8'}</span>
                                {/if}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.category_map|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="form-inline mb-3">
            <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
            <input type="text" name="ebay_category_id" placeholder="{$labels.ebay_category_id|escape:'html':'UTF-8'}" class="form-control mr-2">
            <select name="id_category" class="form-control mr-2">
                {foreach $categories as $category}
                    <option value="{$category.id|escape:'html':'UTF-8'}">{$category.name|escape:'html':'UTF-8'}</option>
                {/foreach}
            </select>
            <button type="submit" name="submitCategoryMap" class="btn btn-primary">{$labels.add_mapping|escape:'html':'UTF-8'}</button>
        </form>

        {if empty($category_maps)}
            <p class="text-muted">{$labels.no_mappings|escape:'html':'UTF-8'}</p>
        {else}
            <table class="table table-striped linkbay-table">
                <thead>
                    <tr>
                        <th>{$labels.ebay_category_id|escape:'html':'UTF-8'}</th>
                        <th>{$labels.prestashop_category|escape:'html':'UTF-8'}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $category_maps as $map}
                        <tr>
                            <td>{$map.ebay_category_id|escape:'html':'UTF-8'}</td>
                            <td>{$map.category_name|escape:'html':'UTF-8'} ({$map.id_category|escape:'html':'UTF-8'})</td>
                            <td class="text-right">
                                <form method="post" action="{$controller_link|escape:'html':'UTF-8'}">
                                    <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                                    <input type="hidden" name="id_category_map" value="{$map.id|escape:'html':'UTF-8'}">
                                    <button type="submit" name="delete_category_map" class="btn btn-sm btn-danger">{$labels.delete|escape:'html':'UTF-8'}</button>
                                </form>
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}
    </div>
</div>

<div class="card linkbay-panel">
    <div class="card-header">{$labels.logs|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <form method="post" action="{$controller_link|escape:'html':'UTF-8'}" class="mb-3">
            <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
            <button type="submit" name="linkbay_clear_logs" class="btn btn-sm btn-secondary">{$labels.clear_logs|escape:'html':'UTF-8'}</button>
        </form>

        {if empty($logs)}
            <p class="text-muted">{$labels.no_logs|escape:'html':'UTF-8'}</p>
        {else}
            <table class="table table-striped linkbay-table">
                <thead>
                    <tr>
                        <th>{$labels.date|escape:'html':'UTF-8'}</th>
                        <th>{$labels.action|escape:'html':'UTF-8'}</th>
                        <th>{$labels.status|escape:'html':'UTF-8'}</th>
                        <th>{$labels.message|escape:'html':'UTF-8'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $logs as $log}
                        <tr>
                            <td>{$log.date_add|escape:'html':'UTF-8'}</td>
                            <td>{$log.action|escape:'html':'UTF-8'}</td>
                            <td>{$log.status|escape:'html':'UTF-8'}</td>
                            <td>{$log.message|escape:'html':'UTF-8'}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}
    </div>
</div>
