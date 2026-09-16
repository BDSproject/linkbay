{*
 * LinkBay visual dashboard: checklist of the problems to fix.
 *}

<div class="card linkbay-panel">
    <div class="card-header">{$labels.title|escape:'html':'UTF-8'}</div>
    <div class="card-body">
        <p class="text-muted">{$labels.intro|escape:'html':'UTF-8'}</p>

        {if !$has_problems}
            <div class="linkbay-empty-state">
                <div class="linkbay-empty-state__icon">&#10003;</div>
                <h3 class="linkbay-empty-state__title">{$labels.all_good_title|escape:'html':'UTF-8'}</h3>
                <p class="linkbay-empty-state__text">{$labels.all_good_text|escape:'html':'UTF-8'}</p>
                <a href="{$import_link|escape:'html':'UTF-8'}" class="btn btn-primary">
                    <i class="material-icons">download</i> {$labels.import_now|escape:'html':'UTF-8'}
                </a>
            </div>
        {else}
            <div class="linkbay-problem-summary">
                <span class="linkbay-badge linkbay-badge--danger">
                    {$labels.problems_count|replace:'%count%':$problems_total|escape:'html':'UTF-8'}
                </span>
            </div>

            <ul class="linkbay-checklist">
                {foreach $problems as $problem}
                    <li class="linkbay-checklist__item">
                        <span class="linkbay-checklist__icon" aria-hidden="true">&#9888;</span>
                        <div class="linkbay-checklist__body">
                            <span class="linkbay-checklist__title">
                                {$problem.title|escape:'html':'UTF-8'}
                                <span class="linkbay-badge linkbay-badge--warning">{$problem.count|intval}</span>
                            </span>
                            <span class="linkbay-checklist__description">{$problem.description|escape:'html':'UTF-8'}</span>
                        </div>
                        <div class="linkbay-checklist__actions">
                            {if $problem.fix == 'config'}
                                <a href="{$config_link|escape:'html':'UTF-8'}" class="btn btn-secondary">
                                    <i class="material-icons">settings</i> {$labels.configure|escape:'html':'UTF-8'}
                                </a>
                            {else}
                                <form method="post" action="{$controller_link|escape:'html':'UTF-8'}">
                                    <input type="hidden" name="linkbay_token" value="{$csrf_token|escape:'html':'UTF-8'}">
                                    <button type="submit" name="{$problem.fix|escape:'html':'UTF-8'}" class="btn btn-primary">
                                        <i class="material-icons">build</i> {$labels.repair|escape:'html':'UTF-8'}
                                    </button>
                                </form>
                            {/if}
                        </div>
                    </li>
                {/foreach}
            </ul>
        {/if}
    </div>
</div>
