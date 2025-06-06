<div class="tab-pane fade" id="v-pills-general" role="tabpanel" aria-labelledby="general-tab">

    <div class="title">Region</div>

    <div class="form-group">
        <div class="subtitle">Choose the region to which the Conveythis plugin will be connected to achieve maximum translation speed.</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="select_region" name="conveythis_select_region" value="US" <?php echo strtoupper($this->variables->select_region) == "US" ? 'checked' : '' ?>>
                <label for="select_region">US - North America</label></div>
            <div class="form-check ">
                <input type="radio" class="form-check-input me-2" id="select_region" name="conveythis_select_region" value="EU" <?php echo strtoupper($this->variables->select_region) == "EU" ? 'checked' : '' ?>>
                <label for="select_region">EU - Europe</label></div>
        </div>
    </div>

    <div class="title">Extended settings</div>

    <div class="form-group">
        <div class="subtitle">Redirect visitors to translated pages automatically based on user browser's settings.</div>
        <label class="hide-paid" for="">This feature is not available on Free and Starter plans. If you want to use this feature, please <a href="https://app.conveythis.com/dashboard/pricing/?utm_source=widget&utm_medium=wordpress" target="_blank" class="grey">upgrade your plan</a>.</label>
        <div class="radio-block paid-function">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="auto_translate_yes" name="auto_translate" value="1" <?php echo $this->variables->auto_translate == 1 ? 'checked' : '' ?>>
                <label for="auto_translate_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="auto_translate_no" name="auto_translate" value="0" <?php echo $this->variables->auto_translate == 0 ? 'checked' : '' ?>>
                <label for="auto_translate_no">No</label></div>
        </div>
    </div>

    <div class="form-group">
        <div class="subtitle">Allows the Translation of Dynamic Content (AJAX, WebSockets, etc.)</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="dynamic_translation_yes" name="dynamic_translation" value="1" <?php echo $this->variables->dynamic_translation == 1 ? 'checked' : ''?>>
                <label for="dynamic_translation_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="dynamic_translation_no" name="dynamic_translation" value="0" <?php echo $this->variables->dynamic_translation == 0 ? 'checked' : ''?>>
                <label for="dynamic_translation_no">No</label></div>
        </div>
    </div>

    <div class="form-group">
        <div class="subtitle">Translate Media (adopt images for specific language)</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_media_yes" name="translate_media" value="1" <?php echo $this->variables->translate_media == 1 ? 'checked' : ''?>>
                <label for="translate_media_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_media_no" name="translate_media" value="0" <?php echo $this->variables->translate_media == 0 ? 'checked' : ''?>>
                <label for="translate_media_no">No</label></div>
        </div>
    </div>

    <div class="form-group">
        <div class="subtitle">Translate PDF (adopt PDF files for specific language)</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_document_yes" name="translate_document" value="1" <?php echo $this->variables->translate_document == 1 ? 'checked' : ''?>>
                <label for="translate_document_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_document_no" name="translate_document" value="0" <?php echo $this->variables->translate_document == 0 ? 'checked' : ''?>>
                <label for="translate_document_no">No</label></div>
        </div>
    </div>

    <div class="form-group">
        <div class="subtitle">Translate Links</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_links_yes" name="translate_links" value="1" <?php echo $this->variables->translate_links == 1 ? 'checked' : ''?>>
                <label for="translate_links_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_links_no" name="translate_links" value="0" <?php echo $this->variables->translate_links == 0 ? 'checked' : ''?>>
                <label for="translate_links_no">No</label></div>
        </div>
    </div>

    <div class="form-group mb-4">
        <div class="subtitle">Allow to change text direction from left to right and vice versa.</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="change_direction_yes" name="change_direction" value="1" <?php echo $this->variables->change_direction == 1 ? 'checked' : ''?>>
                <label for="change_direction_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="change_direction_no" name="change_direction" value="0" <?php echo $this->variables->change_direction == 0 ? 'checked' : ''?>>
                <label for="change_direction_no">No</label></div>
        </div>
    </div>

    <div class="form-group">
        <div class="subtitle">Url Structure</div>
        <div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" name="url_structure" id="regular" value="regular"  <?php echo $this->variables->url_structure == 'regular' ? 'checked' : ''?>>
                <label for="regular">Sub-directory (e.g. https://example.com/es/)</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" name="url_structure" id="subdomain" value="subdomain" <?php echo $this->variables->url_structure == 'subdomain' ? 'checked' : ''?>>
                <label for="subdomain">Sub-domain (e.g. https://es.example.com) (Beta)</label></div>
        </div>
        <div id="dns-setup" <?php echo  ($this->variables->url_structure == 'subdomain') ? 'style="display:block"' : '' ?> >
            <div class="card">
                <td class="card-body">
                    <p>Please add CNAME record for each language you wish to use in your DNS manager.</p>
                    <p>For more information, please check: <a href="https://www.conveythis.com/help/how-to-add-cname-records-in-dns-manager/" target="_blank">How to add CNAME records in DNS manager</a>.</p>

                    <table class="table">
                        <thead>
                        <tr>
                            <th scope="col">Language</th>
                            <th scope="col">Name</th>
                            <th scope="col">CNAME</th>
                        </tr>
                        </thead>
                        <tbody id="dns-setup-records">
                        <?php foreach( $this->variables->languages as $language ): ?>
                            <?php if (in_array($language['code2'], $this->variables->target_languages)) :?>
                                <tr>
                                    <td><?= esc_html( $language['title_en'], 'conveythis-translate' ); ?></td>
                                    <td><?= esc_html($language['code2']) ?>.<?php echo esc_html($this->getCurrentDomain())?></td>
                                    <td>dns1.conveythis.com</td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="message h6 d-flex justify-content-center"></div>
                    <div class="d-flex w-auto justify-content-center">
                        <button id="dns-check" class="btn btn-warning">Test DNS connection</button>
                        <span id="dns-loader" class="ms-2" style="display:none;">⏳ Checking...</span>
                    </div>
            </div>
        </div>
    </div>
    <div class="form-group">
        <div class="subtitle">Default Target Language (Optional)</div>
        <label for="">What is the default target language of your website?</label>
        <div class="ui fluid search selection dropdown">
            <input type="hidden" name="default_language" value="<?php echo  esc_html($this->variables->default_language); ?>">
            <i class="dropdown icon"></i>
            <div class="default text"><?php echo esc_html(__( 'Select source language', 'conveythis-translate' )); ?></div>
            <div class="menu" id="default_language_list">
                <div class="item" data-value="">No value</div>
                <?php foreach( $this->variables->languages as $language ): ?>
                    <?php if (in_array($language['code2'], $this->variables->target_languages)) :?>
                        <div class="item" data-value="<?php echo  esc_attr( $language['code2'] ); ?>">
                            <?php echo esc_html( $language['title_en'], 'conveythis-translate' ); ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

            </div>
        </div>
    </div>

    <div class="title">SEO</div>

    <div class="form-group mb-4">
        <div class="subtitle">Hreflang tags</div>
        <div class="form-check">
            <input type="hidden" name="alternate" value="0">
            <input type="checkbox" class="form-check-input me-2" id="hreflang_tags" name="alternate" value="1" <?php checked( 1, $this->variables->alternate, true ); ?>>
            <label for="hreflang_tags">Add to all pages</label>
        </div>
    </div>

    <div class="title">Customize Languages</div>

    <div class="form-group">
        <div class="subtitle">Languages in selectbox</div>
        <div class="form-check">
            <input type="hidden" name="show_javascript" value="0">
            <input type="checkbox" class="form-check-input me-2" id="selectbox" name="show_javascript" value="1"  <?php checked( 1, $this->variables->show_javascript, true ); ?>>
            <label for="selectbox">Show</label>
        </div>
        <div class="subtitle">Languages in menu</div>
        <label for="">You can place the button in a menu area. Go to <a href="<?php esc_url(admin_url( 'nav-menus.php' )) ?>" class="grey">Appearance &gt; Menus</a> and drag and drop the ConveyThis Translate Custom link where you want.</label>

        <div class="subtitle">Languages in widget</div>
        <label for="">You can place the button in a widget area. Go to <a href="<?php esc_url(admin_url( 'widgets.php' )) ?>" class="grey">Appearance &gt; Widgets</a> and drag and drop the ConveyThis Translate Widget where you want.</label>

        <div class="subtitle">Languages with a shortcode</div>
        <label for="">The ConveyThis shortcode [conveythis_switcher] can be placed in any location where you wish to display the widget.</label>
        <label for=""><b>Note</b>: To ensure proper functionality, please use only one shortcode per page.</label>

    </div>

    <div class="form-group">
        <label>Target Language Names</label>
        <table class="table" style="width: 100%; text-align: left;">
            <tbody id="target_languages_translations"></tbody>
        </table>
        <input type="hidden" name="target_languages_translations" value="<?= esc_attr( wp_json_encode( $this->variables->target_languages_translations ) ) ?>">
    </div>

</div>