{*
 * LinkBay manual import page.
 *}

{if !$is_connected}
    <div class="alert alert-warning">{$error|escape:'html':'UTF-8'}</div>
{else}
    {if $error}
        <div class="alert alert-danger">{$error|escape:'html':'UTF-8'}</div>
    {/if}

    <div class="card linkbay-panel">
        <div class="card-header">{$labels.categories|escape:'html':'UTF-8'}</div>
        <div class="card-body">
            <div class="form-group">
                <select id="linkbay-category-select" class="form-control" style="max-width: 480px;"
                        data-link="{$controller_link|escape:'html':'UTF-8'}">
                    <option value="" {if $current_category <= 0 && !$show_all}selected{/if}>{$labels.choose_category|escape:'html':'UTF-8'}</option>
                    <option value="all" {if $show_all}selected{/if}>{$labels.all_categories|escape:'html':'UTF-8'}</option>
                    {if $current_category > 0}
                        <option value="{$parent_category|intval}">← {$labels.back|escape:'html':'UTF-8'}</option>
                    {/if}
                    {foreach $categories as $category}
                        <option value="{$category.id|escape:'html':'UTF-8'}" {if $current_category == $category.id}selected{/if}>
                            {$category.name|escape:'html':'UTF-8'}{if $category.leaf} · {$labels.leaf|escape:'html':'UTF-8'}{/if}
                        </option>
                    {/foreach}
                </select>
            </div>
        </div>
    </div>

    <div class="card linkbay-panel">
        <div class="card-header">{$labels.listings|escape:'html':'UTF-8'}</div>
        <div class="card-body">
            {if $current_category > 0 && $current_category_name}
                <p class="text-muted mb-3">{$labels.browsing|replace:'%name%':$current_category_name|escape:'html':'UTF-8'}</p>
            {/if}

            {if $current_category <= 0 && !$show_all}
                <p class="text-muted">{$labels.choose_category_hint|escape:'html':'UTF-8'}</p>
            {elseif empty($listings)}
                <p class="text-muted">{$labels.no_listings|escape:'html':'UTF-8'}</p>
            {else}
                <form method="post" action="{$controller_link|escape:'html':'UTF-8'}">
                    <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                    <table class="table table-striped linkbay-table">
                        <thead>
                            <tr>
                                <th>{$labels.select|escape:'html':'UTF-8'}</th>
                                <th>{$labels.title_col|escape:'html':'UTF-8'}</th>
                                <th>{$labels.price|escape:'html':'UTF-8'}</th>
                                <th>{$labels.quantity|escape:'html':'UTF-8'}</th>
                                <th>{$labels.category|escape:'html':'UTF-8'}</th>
                                <th>{$labels.marketplace|escape:'html':'UTF-8'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $listings as $listing}
                                <tr>
                                    <td><input type="checkbox" name="item_ids[]" value="{$listing.item_id|escape:'html':'UTF-8'}"></td>
                                    <td>{$listing.title|escape:'html':'UTF-8'}</td>
                                    <td>{$listing.price|escape:'html':'UTF-8'}</td>
                                    <td>{$listing.quantity|escape:'html':'UTF-8'}</td>
                                    <td>{$listing.category_name|escape:'html':'UTF-8'}</td>
                                    <td><span class="badge badge-secondary">{$listing.marketplace_id|escape:'html':'UTF-8'}</span></td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    <button type="submit" name="submitLinkBayImport" class="btn btn-primary">{$labels.import_selected|escape:'html':'UTF-8'}</button>
                </form>
            {/if}
        </div>
    </div>

    <script>
        (function () {
            var select = document.getElementById('linkbay-category-select');
            if (!select) {
                return;
            }
            select.addEventListener('change', function () {
                var base = this.getAttribute('data-link');
                window.location = this.value === ''
                    ? base
                    : base + '&category=' + encodeURIComponent(this.value);
            });
        })();
    </script>
{/if}
