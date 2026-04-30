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
        <div class="subtitle">Translate content loaded after page load (AJAX) <span class="grey">— may increase API usage on dynamic-content sites</span></div>
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
        <div class="subtitle">Translate Documents (adopt PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX files for specific language)</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_document_yes" name="translate_document" value="1" <?php echo $this->variables->translate_document == 1 ? 'checked' : ''?>>
                <label for="translate_document_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_document_no" name="translate_document" value="0" <?php echo $this->variables->translate_document == 0 ? 'checked' : ''?>>
                <label for="translate_document_no">No</label></div>
        </div>
        <p class="grey mt-2" style="max-width: 720px;">
            When ON and a per-language version of a linked document is uploaded via the ConveyThis dashboard,
            the <code>&lt;a href&gt;</code> on translated pages is swapped to that version.
            When OFF or when no per-language variant exists, the original file is served unchanged.
        </p>
    </div>

    <div class="form-group">
        <div class="subtitle">Translate URLs</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_links_yes" name="translate_links" value="1" <?php echo $this->variables->translate_links == 1 ? 'checked' : ''?>>
                <label for="translate_links_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_links_no" name="translate_links" value="0" <?php echo $this->variables->translate_links == 0 ? 'checked' : ''?>>
                <label for="translate_links_no">No</label></div>
        </div>
        <p class="text-warning" style="margin-top: 8px; font-size: 0.9em; max-width: 720px;">
            <strong>Beta feature.</strong> URL translation may produce broken links on:
            non-ASCII slugs (Cyrillic, Arabic, CJK), custom post types not registered with
            <code>publicly_queryable</code>, or when the translation API times out
            (silent fallback to source slug). Verify hreflang &amp; canonical tags after
            enabling, and re-crawl in Search Console.
        </p>
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

    <div class="form-group mb-4">
        <div class="subtitle">Add trailing slash for links? <span class="text-danger"> *Keep in mind, this setting can cause redirects and affect SEO.</span></div>

        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="use_trailing_slash_default" name="use_trailing_slash" value="0" <?php echo $this->variables->use_trailing_slash == 0 ? 'checked' : ''?>>
                <label for="use_trailing_slash_default">Auto (match WordPress permalink structure)</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="use_trailing_slash_no" name="use_trailing_slash" value="-1" <?php echo $this->variables->use_trailing_slash == -1 ? 'checked' : ''?>>
                <label for="use_trailing_slash_no">Always remove trailing slash ('../example')</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="use_trailing_slash_yes" name="use_trailing_slash" value="1" <?php echo $this->variables->use_trailing_slash == 1 ? 'checked' : ''?>>
                <label for="use_trailing_slash_yes">Always add trailing slash ('../example/')</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="use_trailing_slash_custom" name="use_trailing_slash" value="2" <?php echo $this->variables->use_trailing_slash == 2 ? 'checked' : ''?>>
                <label for="use_trailing_slash_custom">Custom (from sitemap) <span class="text-muted">— respects per-URL slash from your sitemap</span></label></div>
        </div>

            <?php
            // Spec 4 — live status line derived from the stored sitemap map.
            $ct_slash_map_raw = $this->variables->trailing_slash_map ?? [];
            if (is_string($ct_slash_map_raw)) { $ct_slash_map_raw = json_decode($ct_slash_map_raw, true) ?: []; }
            $ct_map_total  = is_array($ct_slash_map_raw) ? count($ct_slash_map_raw) : 0;
            $ct_map_has    = 0;
            $ct_map_no     = 0;
            if ($ct_map_total > 0) {
                foreach ($ct_slash_map_raw as $ct_has) {
                    if ($ct_has) { $ct_map_has++; } else { $ct_map_no++; }
                }
            }
            $ct_custom_selected = ((string)($this->variables->use_trailing_slash ?? '0') === '2');
            $ct_notice_style = $ct_custom_selected ? '' : 'display:none;';
            $ct_notice_class = ($ct_map_total > 0) ? 'alert-success' : 'alert-warning';
            ?>
            <div id="trailing_slash_custom_notice" class="alert <?php echo esc_attr($ct_notice_class); ?> mt-2" style="<?php echo $ct_notice_style; ?>">
                <?php if ($ct_map_total > 0): ?>
                    Sitemap loaded: <strong><?php echo (int)$ct_map_total; ?></strong> URLs
                    (<strong><?php echo (int)$ct_map_has; ?></strong> with slash,
                    <strong><?php echo (int)$ct_map_no; ?></strong> without).
                <?php else: ?>
                    <strong>No sitemap loaded</strong> — Custom mode will fall back to Auto for every URL.
                    Upload a sitemap in the <strong>Links</strong> tab.
                <?php endif; ?>
                <span id="sitemap_map_status"></span>
            </div>

            <?php
            // ─────────────────────────────────────────────────────────────
            // Trailing-slash diagnostic panel
            //
            // Static analysis that highlights conflicts between the plugin
            // setting, WordPress permalink_structure, and the user's intent.
            // A "Run live test" button (below) performs an AJAX probe of a
            // real translated URL to detect server-level 301 rewrites that
            // would override these settings before PHP runs.
            // ─────────────────────────────────────────────────────────────
            $ct_slash_setting  = (int)($this->variables->use_trailing_slash ?? 0);
            $ct_wp_perma       = (string) get_option('permalink_structure', '');
            $ct_wp_slash       = ($ct_wp_perma !== '' && substr($ct_wp_perma, -1) === '/');
            $ct_labels         = [
                '0'  => 'Auto (match WordPress)',
                '1'  => 'Always add',
                '-1' => 'Always remove',
                '2'  => 'Custom (sitemap)',
            ];
            $ct_setting_label  = $ct_labels[(string)$ct_slash_setting] ?? 'Unknown';
            $ct_have_targets   = !empty($this->variables->target_languages);

            // Effective plugin behavior (what applyTrailingSlash() will do)
            $ct_effective      = $ct_slash_setting;
            if ($ct_effective === 0) {
                $ct_effective = $ct_wp_slash ? 1 : ($ct_wp_perma === '' ? 0 : -1);
            }
            $ct_effective_txt  = $ct_effective === 1 ? 'ADD slash' : ($ct_effective === -1 ? 'REMOVE slash' : 'LEAVE AS-IS');

            // Conflict detection
            $ct_conflict = null;
            if ($ct_slash_setting === -1 && $ct_wp_slash) {
                $ct_conflict = 'remove_vs_wp_slash';
            } elseif ($ct_slash_setting === 1 && !$ct_wp_slash && $ct_wp_perma !== '') {
                $ct_conflict = 'add_vs_wp_noslash';
            }
            ?>
            <div class="ct-slash-diagnostic mt-3 p-3 border rounded" style="background:#f8f9fa;">
                <div class="fw-bold mb-2">Trailing-slash diagnostic</div>
                <ul class="mb-2" style="padding-left:1.25em;">
                    <li>Plugin setting: <strong><?php echo esc_html($ct_setting_label); ?></strong>
                        (value: <code><?php echo esc_html((string)$ct_slash_setting); ?></code>)</li>
                    <li>WordPress permalink structure:
                        <code><?php echo esc_html($ct_wp_perma !== '' ? $ct_wp_perma : '(plain — no structure)'); ?></code>
                        <?php if ($ct_wp_perma !== ''): ?>
                            (<?php echo $ct_wp_slash ? 'ends with <code>/</code>' : 'no trailing slash'; ?>)
                        <?php endif; ?>
                    </li>
                    <li>Effective action on translated links: <strong><?php echo esc_html($ct_effective_txt); ?></strong></li>
                    <li>WP canonical override for translated URLs:
                        <?php
                        $ct_rc_registered = has_filter('template_redirect', 'redirect_canonical') !== false;
                        if ($ct_rc_registered): ?>
                            <span class="text-success">active</span>
                            <small class="text-muted">(<code>filter_redirect_canonical_for_translated</code> hooked on <code>redirect_canonical</code>; plugin also issues a proactive 301 via <code>maybe_redirect_translated_slash_canonical</code>)</small>
                        <?php else: ?>
                            <span class="text-warning">WP <code>redirect_canonical</code> is unhooked</span>
                            <small class="text-muted">— plugin's proactive handler <code>maybe_redirect_translated_slash_canonical</code> still fires, so translated-URL canonicalization works; check theme/plugin removals if you rely on WP's own canonical for source URLs.</small>
                        <?php endif; ?>
                    </li>
                </ul>

                <?php if ($ct_conflict === 'remove_vs_wp_slash'): ?>
                    <div class="alert alert-warning mb-2 py-2">
                        <strong>Conflict:</strong> plugin removes slashes, but WordPress permalinks use trailing slashes.
                        The plugin now suppresses WP's slash-only canonical redirect for translated URLs,
                        so WordPress will not fight the setting.
                        However, <strong>a web-server rewrite (nginx/Apache) may still force a trailing slash</strong>
                        before PHP runs. Use the test button below to confirm.
                    </div>
                <?php elseif ($ct_conflict === 'add_vs_wp_noslash'): ?>
                    <div class="alert alert-warning mb-2 py-2">
                        <strong>Conflict:</strong> plugin adds slashes, but WordPress permalinks are slashless.
                        On source-language URLs, WordPress's canonical redirect will strip the slash you added.
                        Consider "Auto" mode instead, or change WordPress's permalink structure under
                        <em>Settings → Permalinks</em>.
                    </div>
                <?php elseif ($ct_slash_setting === 0): ?>
                    <div class="alert alert-info mb-2 py-2">
                        Auto mode — plugin follows WordPress. This is the recommended default.
                    </div>
                <?php endif; ?>

                <?php if ($ct_have_targets): ?>
                    <div>
                        <button type="button" id="ct-slash-diagnose-btn" class="btn btn-sm btn-outline-primary"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('conveythis_diagnose_trailing_slash')); ?>">
                            Run live test
                        </button>
                        <span class="text-muted ms-2" style="font-size:0.875em;">
                            Requests one translated URL without a trailing slash and reports the server's response.
                        </span>
                        <div id="ct-slash-diagnose-result" class="mt-2" style="font-size:0.9em;"></div>
                    </div>

                    <script>
                    (function () {
                        var btn = document.getElementById('ct-slash-diagnose-btn');
                        if (!btn) { return; }
                        var out = document.getElementById('ct-slash-diagnose-result');
                        var render = function (data) {
                            var lines = [];
                            lines.push('<div><strong>Target language prefix:</strong> <code>/' + data.lang_prefix + '/</code></div>');
                            lines.push('<div><strong>Test path:</strong> <code>' + data.test_path + '</code></div>');
                            var fmt = function (label, url, r) {
                                if (r.error) {
                                    return '<div class="mt-1"><strong>' + label + ':</strong> <span class="text-danger">' + r.error + '</span> — <code>' + url + '</code></div>';
                                }
                                var statusCls = r.status >= 200 && r.status < 300 ? 'text-success'
                                              : r.status >= 300 && r.status < 400 ? 'text-warning'
                                              : 'text-danger';
                                var by = r.redirect_by ? ('redirect-by: <code>' + r.redirect_by + '</code>') :
                                         (r.server ? ('server: <code>' + r.server + '</code>') : '');
                                var loc = r.location ? (' → <code>' + r.location + '</code>') : '';
                                return '<div class="mt-1"><strong>' + label + ':</strong> <code>' + url + '</code> → '
                                       + '<span class="' + statusCls + '"><strong>HTTP ' + r.status + '</strong></span>'
                                       + loc + ' ' + by + '</div>';
                            };
                            lines.push(fmt('Without slash', data.url_noslash, data.result_noslash));
                            lines.push(fmt('With slash', data.url_slash, data.result_slash));
                            var verdictMap = {
                                'ok':                               { cls: 'alert-success', txt: 'Server behavior matches plugin setting.' },
                                'expected':                         { cls: 'alert-success', txt: 'Redirect is expected (plugin wants slashes, server adds them).' },
                                'server_overrides_plugin':          { cls: 'alert-danger',  txt: 'Server is overriding the plugin setting. Adjust the server-level rewrite for translated URLs.' },
                                'plugin_adds_but_server_accepts_both': { cls: 'alert-info', txt: 'Server accepts both — plugin will still emit slashed links per the setting.' }
                            };
                            var v = verdictMap[data.verdict] || verdictMap['ok'];
                            lines.push('<div class="alert ' + v.cls + ' mt-2 py-2 mb-0"><strong>Verdict:</strong> ' + v.txt + '</div>');
                            if (data.details && data.details.length) {
                                lines.push('<ul class="mb-0 mt-1" style="padding-left:1.25em;">');
                                data.details.forEach(function (d) { lines.push('<li>' + d + '</li>'); });
                                lines.push('</ul>');
                            }
                            out.innerHTML = lines.join('');
                        };
                        btn.addEventListener('click', function () {
                            out.innerHTML = '<em>Running…</em>';
                            btn.disabled = true;
                            var body = new URLSearchParams();
                            body.append('action', 'conveythis_diagnose_trailing_slash');
                            body.append('nonce', btn.getAttribute('data-nonce'));
                            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
                                .then(function (r) { return r.json(); })
                                .then(function (j) {
                                    if (j && j.success) { render(j.data); }
                                    else {
                                        var msg = (j && j.data && j.data.message) ? j.data.message : 'Unknown error';
                                        out.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + msg + '</div>';
                                    }
                                })
                                .catch(function (e) {
                                    out.innerHTML = '<div class="alert alert-danger py-2 mb-0">Request failed: ' + e + '</div>';
                                })
                                .finally(function () { btn.disabled = false; });
                        });
                    })();
                    </script>
                <?php endif; ?>
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
                <label for="subdomain">Sub-domain (e.g. https://es.example.com)</label></div>
        </div>
        <div id="dns-setup" <?php echo  ($this->variables->url_structure == 'subdomain') ? 'style="display:block"' : '' ?> >
            <div class="card">
                <td class="card-body">
                    <p>Please add CNAME record for each language you wish to use in your DNS manager.</p>
                    <p>For more information, please check: <a href="https://www.conveythis.com/help/add-cname-records-in-dns-manager" target="_blank">How to add CNAME records in DNS manager</a>.</p>

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
                                    <td>dns2.conveythis.com</td>
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

    <div class="form-group">
        <div class="subtitle">Translate Structured Data (JSON-LD)</div>
        <div class="radio-block">
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_structured_data_yes" name="translate_structured_data" value="1" <?php echo $this->variables->translate_structured_data == 1 ? 'checked' : ''?>>
                <label for="translate_structured_data_yes">Yes</label></div>
            <div class="form-check">
                <input type="radio" class="form-check-input me-2" id="translate_structured_data_no" name="translate_structured_data" value="0" <?php echo $this->variables->translate_structured_data == 0 ? 'checked' : ''?>>
                <label for="translate_structured_data_no">No</label></div>
        </div>
        <p class="grey mt-2" style="max-width: 720px;">
            Translates textual fields (<code>headline</code>, <code>description</code>, FAQ Q&amp;A, <code>name</code>)
            inside <code>&lt;script type="application/ld+json"&gt;</code> while protecting URLs
            (<code>url</code>, <code>@id</code>, <code>sameAs</code>, <code>image.url</code>, <code>logo.url</code>),
            identifiers (<code>sku</code>, <code>gtin</code>, <code>mpn</code>, currency codes, prices, dates),
            and your brand name. Output is parsed back and validated; if the translated tree is invalid JSON,
            the original untranslated block is served.
            <strong>Verify with:</strong> Google's Rich Results test on a translated URL.
        </p>
        <p class="text-muted" style="font-size: 0.875em; max-width: 720px;">
            If you see ranking changes you don't want, switch back to &quot;No&quot; and re-crawl with Search Console —
            cached JSON-LD will revert on next translation.
        </p>
    </div>

    <div class="title" id="seo-translation-quality">SEO Translation Quality</div>

    <div class="form-group mb-4">
        <p class="grey">
            ConveyThis routes meta tags and JSON-LD through quality-tier translation and
            protects your brand and key terms from being translated. Bug fixes for broken
            structured-data URLs are always on; the options below fine-tune behavior.
        </p>

        <div class="subtitle">Brand name protection</div>
        <input type="text" name="conveythis_seo_brand"
               value="<?php echo esc_attr($this->variables->seo_brand); ?>"
               placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>"
               class="form-control">
        <label class="grey">Auto-detected from your site title. This term will never be translated in meta tags or JSON-LD.</label>

        <div class="subtitle">Additional terms to preserve</div>
        <textarea name="conveythis_seo_glossary" rows="4" class="form-control"
                  placeholder="One term per line — product names, founder names, taglines"><?php
            echo esc_textarea(implode("\n", (array) $this->variables->seo_glossary));
        ?></textarea>
        <label class="grey">One term per line. Sent to the translation pipeline as "do not translate".</label>

        <div class="subtitle">Enforce length limits on meta descriptions</div>
        <div class="form-check">
            <input type="hidden" name="conveythis_seo_enforce_length" value="0">
            <input type="checkbox" class="form-check-input me-2" id="conveythis_seo_enforce_length"
                   name="conveythis_seo_enforce_length" value="1"
                   <?php checked(1, $this->variables->seo_enforce_length, true); ?>>
            <label for="conveythis_seo_enforce_length">
                Truncate translated meta descriptions to the Google-recommended limits
                (description ≤ 160, og:description ≤ 200, title ≤ 60).
            </label>
        </div>

        <?php if ($this->variables->beta_features) : ?>
        <div class="subtitle">JSON-LD post-translation validation</div>
        <div class="form-check">
            <input type="hidden" name="conveythis_seo_jsonld_validation" value="0">
            <input type="checkbox" class="form-check-input me-2" id="conveythis_seo_jsonld_validation"
                   name="conveythis_seo_jsonld_validation" value="1"
                   <?php checked(1, $this->variables->seo_jsonld_validation, true); ?>>
            <label for="conveythis_seo_jsonld_validation">
                Validate translated JSON-LD; fall back to the original block if validation fails
                (recommended — prevents broken Rich Snippets).
            </label>
        </div>

        <div class="subtitle">Status (last 24h)</div>
        <?php
            $seo_stats = method_exists($this, 'getSeoQualityStats')
                ? $this->getSeoQualityStats()
                : ['translations' => 0, 'fallbacks' => 0, 'truncations' => 0];
        ?>
        <div class="seo-quality-status grey">
            <?php echo (int) $seo_stats['translations']; ?> quality events,
            <?php echo (int) $seo_stats['fallbacks']; ?> JSON-LD fallbacks,
            <?php echo (int) $seo_stats['truncations']; ?> length truncations.
        </div>
        <?php if (($seo_stats['fallbacks'] ?? 0) > 10): ?>
            <div class="alert alert-warning mt-2" style="color:#856404;background:#fff3cd;border:1px solid #ffeeba;padding:8px 12px;margin-top:8px;">
                More than 10 JSON-LD fallbacks in the last 24h — please review your structured data
                or translation quality. Rich Snippet eligibility may be affected.
            </div>
        <?php endif; ?>
        <?php
            $seo_events = method_exists($this, 'getSeoQualityEvents')
                ? $this->getSeoQualityEvents()
                : [];
        ?>
        <?php if (!empty($seo_events)): ?>
        <style>
            details.seo-quality-events { margin-top: 12px; }
            details.seo-quality-events > summary { cursor: pointer; padding: 6px 0; font-weight: 600; }
            .seo-quality-events-wrap { max-height: 60vh; overflow-y: auto; border: 1px solid #e5e5e5; }
            table.seo-quality-events-table { width: 100%; border-collapse: collapse; font-size: 12px; }
            table.seo-quality-events-table th,
            table.seo-quality-events-table td { padding: 6px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: top; text-align: left; }
            table.seo-quality-events-table th { background: #fafafa; position: sticky; top: 0; }
            table.seo-quality-events-table td.diff { font-family: ui-monospace, Menlo, monospace; white-space: pre-wrap; word-break: break-word; max-width: 320px; }
            table.seo-quality-events-table td.diff .orig { color: #555; }
            table.seo-quality-events-table td.diff .trans { color: #004d99; }
            table.seo-quality-events-table td.action button { font-size: 11px; padding: 2px 6px; }
        </style>
        <details class="seo-quality-events">
            <summary>View recent events (<?php echo count($seo_events); ?>)</summary>
            <div class="seo-quality-events-wrap">
                <table class="seo-quality-events-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Lang</th>
                            <th>Reason</th>
                            <th>Path</th>
                            <th>Diff</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($seo_events as $e): ?>
                        <?php
                            $type   = (string) ($e['type'] ?? '');
                            $reason = (string) ($e['reason'] ?? ($e['meta_field'] ?? ''));
                            $lang   = (string) ($e['lang'] ?? '');
                            $path   = (string) ($e['path'] ?? '');
                            $orig   = (string) ($e['original'] ?? '');
                            $trans  = (string) ($e['translated'] ?? '');
                            $id     = isset($e['id']) ? (int) $e['id'] : null;
                        ?>
                        <tr>
                            <td><?php echo esc_html(wp_date('Y-m-d H:i', (int) ($e['ts'] ?? 0))); ?></td>
                            <td><?php echo esc_html($type); ?></td>
                            <td><?php echo esc_html($lang); ?></td>
                            <td><?php echo esc_html($reason); ?></td>
                            <td><?php echo esc_html($path); ?></td>
                            <td class="diff">
                                <?php if ($orig !== '' || $trans !== ''): ?>
                                    <div class="orig">orig: <?php echo esc_html($orig); ?></div>
                                    <div class="trans">trans: <?php echo esc_html($trans); ?></div>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="action">
                                <?php
                                    $canAddGlossary = ($type === 'jsonld_fallback'
                                        && $reason === 'protected_field_modified'
                                        && $orig !== '');
                                ?>
                                <?php if ($canAddGlossary && $id !== null):
                                    $add_url = wp_nonce_url(
                                        add_query_arg(
                                            [
                                                'action' => 'conveythis_add_seo_glossary_term',
                                                'term'   => $orig,
                                                'id'     => (int) $id,
                                            ],
                                            admin_url('admin-post.php')
                                        ),
                                        'conveythis_add_seo_glossary_term'
                                    );
                                ?>
                                    <button type="button" name="conveythis_seo_add_to_glossary" class="button button-small"
                                            onclick="<?php echo esc_attr("window.location.href='" . esc_url_raw($add_url) . "';"); ?>">
                                        <?php echo esc_html(sprintf(__('Add "%s" to glossary', 'conveythis-translate'), $orig)); ?>
                                    </button>
                                <?php elseif ($id !== null):
                                    $dismiss_url = wp_nonce_url(
                                        add_query_arg(
                                            ['action' => 'conveythis_dismiss_seo_quality_event', 'id' => (int) $id],
                                            admin_url('admin-post.php')
                                        ),
                                        'conveythis_dismiss_seo_quality_event'
                                    );
                                ?>
                                    <button type="button" name="conveythis_seo_dismiss" class="button button-small"
                                            onclick="<?php echo esc_attr("window.location.href='" . esc_url_raw($dismiss_url) . "';"); ?>">
                                        <?php echo esc_html__('Dismiss', 'conveythis-translate'); ?>
                                    </button>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php $clear_url = wp_nonce_url(admin_url('admin-post.php?action=conveythis_clear_seo_quality_log'), 'conveythis_clear_seo_quality_log'); ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 10px;"
                  onsubmit="return confirm('Clear all SEO quality events? This cannot be undone.');">
                <input type="hidden" name="action" value="conveythis_clear_seo_quality_log">
                <?php wp_nonce_field('conveythis_clear_seo_quality_log'); ?>
                <button type="button" name="conveythis_seo_clear" class="button"
                        onclick="<?php echo esc_attr("if(confirm('Clear all SEO quality events? This cannot be undone.')){window.location.href='" . esc_url_raw($clear_url) . "';}"); ?>">
                    <?php echo esc_html__('Clear log', 'conveythis-translate'); ?>
                </button>
            </form>
        </details>
        <?php endif; ?>
        <?php endif; // beta_features ?>
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