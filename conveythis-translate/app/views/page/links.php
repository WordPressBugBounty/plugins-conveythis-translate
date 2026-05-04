<div class="tab-pane fade" id="v-pills-links" role="tabpanel" aria-labelledby="links-tab">

    <?php if ($this->variables->translate_links == 1) : ?>
        <?php
        $conveythis_my_translations_url = rtrim(CONVEYTHIS_APP_URL, '/') . '/dashboard/translation/edit-translation/';
        ?>
        <div class="alert alert-warning" style="margin: 0 0 16px 0; max-width: 900px;">
            <strong>Translate URLs is enabled.</strong>
            To review or change how links are translated, sign in to
            <a href="<?php echo esc_url($conveythis_my_translations_url); ?>" target="_blank" rel="noopener noreferrer">the ConveyThis dashboard</a>,
            open <strong>Translation Management</strong> → <strong>My Translations</strong>, and edit the relevant URL entries from there.
        </div>
    <?php endif; ?>

    <!-- Sitemap & Trailing Slash Discovery -->
    <div class="form-group paid-function">
        <div class="title">Sitemap & Trailing Slash Discovery</div>

        <div class="alert alert-primary" role="alert">
            <strong>What this does:</strong> Search engines treat <code>/page</code> and <code>/page/</code> as two different URLs. When ConveyThis builds translated URLs (e.g. <code>/fr/about</code>, <code>/es/about/</code>), each one should use the same trailing-slash convention as the original page on your site — otherwise you risk duplicate content, redirect chains, and lost SEO signal.
            <br><br>
            <strong>How to use it:</strong>
            <ol class="mb-0">
                <li>In <em>Extended settings → Add trailing slash for links</em>, pick <strong>Custom (from sitemap)</strong>.</li>
                <li>Paste your sitemap URL below (e.g. <code>https://yourdomain.com/sitemap.xml</code> or <code>https://yourdomain.com/sitemap_index.xml</code>). Yoast, RankMath, and WP core sitemaps all work; sitemap indexes are followed one level deep.</li>
                <li>Click <strong>Scan Sitemap</strong>. The plugin fetches the sitemap server-side, reads every <code>&lt;loc&gt;</code>, and records whether each URL ends in <code>/</code>.</li>
                <li>The result is a per-URL map stored in <code>wp_options</code> (<code>conveythis_trailing_slash_map</code>). At render time, each translated link is looked up in that map and the matching slash style is applied. URLs not in the map fall back to the WordPress permalink setting.</li>
            </ol>
        </div>

        <label>Sitemap URL</label>
        <div class="row mb-3">
            <div class="col-md-8">
                <input type="text" class="form-control conveythis-input-text" id="sitemap_url_input"
                       placeholder="https://example.com/sitemap.xml"
                       value="<?php echo esc_attr($this->variables->trailing_slash_auto_source !== 'wordpress' ? $this->variables->trailing_slash_auto_source : ''); ?>">
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary" type="button" id="btn_scan_sitemap">Scan Sitemap</button>
                <span id="sitemap_loader" style="display:none;" class="ms-2">Scanning...</span>
            </div>
        </div>

        <div id="sitemap_scan_summary" class="alert alert-success" style="display:none;"></div>
        <div id="sitemap_scan_errors" class="alert alert-warning" style="display:none;"></div>

        <div id="sitemap_url_table_wrapper" style="display:none; max-height:400px; overflow-y:auto;" class="mb-3">
            <table class="table table-sm table-striped" id="sitemap_url_table">
                <thead><tr><th>URL Path</th><th>Has Trailing Slash</th><th>Override</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>

        <input type="hidden" name="conveythis_trailing_slash_map" id="conveythis_trailing_slash_map"
               value='<?php echo esc_attr(wp_json_encode($this->variables->trailing_slash_map)); ?>'>
        <input type="hidden" name="conveythis_trailing_slash_auto_source" id="conveythis_trailing_slash_auto_source"
               value="<?php echo esc_attr($this->variables->trailing_slash_auto_source); ?>">
    </div>

    <!-- Link Translation Rules -->
    <div class="form-group paid-function">
        <div class="title">Link Translation Rules</div>

        <div class="alert alert-primary" role="alert">
            <strong>What this does:</strong> Fine-grained control over <em>which</em> links on your pages get their slugs translated. By default, every internal link is rewritten to the translated version when a visitor views the page in another language. Use this section to carve out exceptions — e.g. keep legal URLs in English, skip auto-generated archive links, or restrict translation to the main content area only.
            <br><br>
            <strong>How to use it:</strong>
            <ul class="mb-0">
                <li><strong>Translation Scope</strong> — limit rewriting to a part of the page: everywhere (default), the content area only, or navigation only. Links outside the chosen scope are left untouched.</li>
                <li><strong>URL Pattern Rules</strong> — an ordered list of include/exclude patterns. Rules are evaluated top-down; the first match wins. If no rule matches, the link <em>is</em> translated. Useful patterns: exclude <code>/legal/</code> (prefix), exclude <code>.pdf</code> (suffix), include <code>/blog/</code> only, or regex for anything more complex.</li>
                <li><strong>Post Type Slug Translation</strong> — per-post-type kill switch. When unchecked, URLs for that post type still receive the language prefix (e.g. <code>/fr/</code>) but the slug itself stays in the source language. Handy for post types whose slugs are identifiers rather than words.</li>
            </ul>
        </div>

        <label>Translation Scope</label>
        <div class="row mb-3 g-2 align-items-center">
            <div class="col-md-12" id="link_rules_scope_col">
                <select name="conveythis_link_rules_scope" id="link_rules_scope" class="form-select conveythis-input-text w-100">
                    <option value="all" <?php echo ($this->variables->link_rules_scope ?? 'all') === 'all' ? 'selected' : ''; ?>>All links on the page</option>
                    <option value="content" <?php echo ($this->variables->link_rules_scope ?? '') === 'content' ? 'selected' : ''; ?>>Content area only</option>
                    <option value="navigation" <?php echo ($this->variables->link_rules_scope ?? '') === 'navigation' ? 'selected' : ''; ?>>Navigation only</option>
                </select>
            </div>
        </div>

        <label>URL Pattern Rules</label>
        <div class="alert alert-info" role="alert">
            Rules are evaluated in order. First matching rule wins. If no rule matches, the link IS translated.
        </div>
        <div id="link_rules_wrapper" class="mb-3">
            <?php
            $link_rules = $this->variables->link_rules ?? [];
            if (!empty($link_rules)):
                foreach ($link_rules as $rule): ?>
                    <div class="link_rule position-relative w-100 row mb-2 align-items-center">
                        <div class="col-md-2">
                            <select class="form-select rule_type">
                                <option value="exclude" <?php echo ($rule['type'] ?? '') === 'exclude' ? 'selected' : ''; ?>>Exclude</option>
                                <option value="include" <?php echo ($rule['type'] ?? '') === 'include' ? 'selected' : ''; ?>>Include</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select rule_match">
                                <option value="prefix" <?php echo ($rule['match'] ?? '') === 'prefix' ? 'selected' : ''; ?>>Starts with</option>
                                <option value="suffix" <?php echo ($rule['match'] ?? '') === 'suffix' ? 'selected' : ''; ?>>Ends with</option>
                                <option value="contains" <?php echo ($rule['match'] ?? '') === 'contains' ? 'selected' : ''; ?>>Contains</option>
                                <option value="exact" <?php echo ($rule['match'] ?? '') === 'exact' ? 'selected' : ''; ?>>Exact match</option>
                                <option value="regex" <?php echo ($rule['match'] ?? '') === 'regex' ? 'selected' : ''; ?>>Regex</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control rule_pattern conveythis-input-text"
                                   placeholder="/path/" value="<?php echo esc_attr($rule['pattern'] ?? ''); ?>">
                        </div>
                        <div class="col-md-2">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input rule_enabled"
                                       <?php echo ($rule['enabled'] ?? true) ? 'checked' : ''; ?>>
                                <label>Enabled</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-danger remove_link_rule">Remove</button>
                        </div>
                    </div>
                <?php endforeach;
            endif; ?>
        </div>
        <button class="btn btn-primary" type="button" id="add_link_rule">+ Add Rule</button>
        <input type="hidden" name="conveythis_link_rules" id="conveythis_link_rules"
               value='<?php echo esc_attr(wp_json_encode($this->variables->link_rules ?? [])); ?>'>

        <hr class="my-4">

        <label>Post Type Slug Translation</label>
        <div class="alert alert-info" role="alert">
            Toggle slug translation per WordPress post type. When disabled, URLs for that post type will keep the language prefix but the slug won't be translated.
        </div>
        <div id="post_type_toggles" class="mb-3">
            <?php
            $post_types = get_post_types(['public' => true], 'objects');
            $pt_settings = $this->variables->link_rules_post_types ?? [];
            foreach ($post_types as $pt): ?>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input post_type_toggle"
                           data-post-type="<?php echo esc_attr($pt->name); ?>"
                           <?php echo (!isset($pt_settings[$pt->name]) || $pt_settings[$pt->name]) ? 'checked' : ''; ?>>
                    <label><?php echo esc_html($pt->label); ?> <code>(<?php echo esc_html($pt->name); ?>)</code></label>
                </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="conveythis_link_rules_post_types" id="conveythis_link_rules_post_types"
               value='<?php echo esc_attr(wp_json_encode($this->variables->link_rules_post_types ?? [])); ?>'>
    </div>

    <!-- System Links (existing) -->
    <div class="form-group paid-function">

        <div class="title">System links</div>

        <div>
            <div class="alert alert-primary" role="alert">
                <strong>What this does:</strong> Registers URLs that aren't regular WordPress posts/pages but still need to be served in the visitor's language — things like <code>/404.html</code>, a static <code>/maintenance</code> page, or a custom system endpoint. When a visitor hits one of these paths on a translated subdomain/prefix, ConveyThis knows to translate the response body instead of treating it as an unknown URL.
                <br><br>
                <strong>How to use it:</strong> Enter one path per entry. Use relative paths only — start with <code>/</code>, and do <em>not</em> include the scheme (<code>https://</code>) or the domain. Examples: <code>/404.html</code>, <code>/checkout/thank-you</code>, <code>/system/maintenance</code>.
            </div>
        </div>

        <label>System links</label>

        <div id="system_link_wrapper" class="col-md-12">
            <?php if (
                    isset($this->variables->system_links) &&
                    is_array($this->variables->system_links) &&
                    count($this->variables->system_links) > 0
            ) : ?>
                <?php foreach( $this->variables->system_links as $link ): ?>
                    <?php if (is_array($link)) : ?>
                        <div class="system_link position-relative w-100">
                            <input type="hidden" class="system_link_id" value="<?php echo (isset($link['link_id']) ? esc_attr($link['link_id']) : '') ?>"/>
                            <button type="submit" name="submit" class="conveythis-delete-page"></button>
                            <div class="row w-100 mb-2">
                                <div class="ui input w-100">
                                    <input
                                            type="text"
                                            id="link_enter"
                                            class="link_text w-100 conveythis-input-text"
                                            placeholder="Enter link (/404.html or /path/path...)"
                                            value="<?php echo (isset($link['link']) ? esc_attr($link['link']): '') ?>"
                                    >
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <input type="hidden" name="conveythis_system_links" value='<?php echo esc_attr( wp_json_encode( $this->variables->system_links ) ); ?>'>
        <button class="btn btn-primary mt-2" type="button" id="add_system_link">+ Add System Link</button>

    </div>
</div>
