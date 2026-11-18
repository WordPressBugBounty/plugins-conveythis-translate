<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="tab-pane fade" id="v-pills-cache" role="tabpanel" aria-labelledby="cache-tab">

    <div class="form-group">
        <div class="title m-0">Translation cache</div>

        <div class="block-setting form-group paid-function">
            <p class="m-0">We store translation cache in your WordPress project to optimize translation speed.</p>
            <p class="mb-2"><strong>Note:</strong> If you've made translation updates using our dashboard, press the button below to reset both the local cache and the shared server-side translation cache for this domain.</p>
            <button class="btn btn-primary" type="button" id="clear_translate_cache" style="background-color: white">
                <span class="spinner-cache spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                Clear translation cache
            </button>
            <label class="clear-success d-none" style="color: green">The translation cache was cleared successfully.</label>
            <label class="clear-failure d-none" style="color: red">No cache found to clear.</label>
        </div>

        <div class="block-setting form-group">
            <div class="form-check">
                <input type="checkbox" class="form-check-input me-2" id="conveythis_refresh_page_cache" <?php checked(get_option('conveythis_refresh_page_cache', '1'), '1'); ?>>
                <label for="conveythis_refresh_page_cache">Refresh the page cache when translations change</label>
            </div>
            <p class="m-0">When a translation is edited in the dashboard, by import, glossary or AI agents, ConveyThis clears your site's page cache (WP Rocket, LiteSpeed Cache, WP Super Cache and similar plugins), so visitors see the change within a few minutes. Untick this if you prefer to clear the page cache yourself.</p>
            <label class="refresh-page-cache-saved d-none" style="color: green">Saved.</label>
        </div>

    </div>

</div>

