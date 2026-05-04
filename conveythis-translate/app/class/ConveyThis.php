<?php

require_once 'Variables.php';

class ConveyThis {
    public static $instance;
    private $variables;
    private $ConveyThisCache;
    private $ConveyThisSEO;
    private $nodePathList = [];
    private $nodePathListSpace = [];
    private $templateVarMap = [];
    private $wpTrailingSlashDetected;
    private $trailingSlashMapCache = [];
    private $trailingSlashMapLoaded = false;
    private $last_glossary_response_raw;
    /**
     * Captured at the top of init() before the plugin's own REQUEST_URI rewrite
     * strips the /lang/ prefix. Used by maybe_redirect_translated_slash_canonical()
     * to compute the correct slash shape against what the browser actually asked for.
     */
    private $original_request_uri_for_slash_canonical = null;

    /** SEO meta-field classification lookup. Keys are lowercased name/property values. */
    private static $META_FIELD_TO_TYPE = [
        'description'         => 'description',
        'og:description'      => 'og:description',
        'twitter:description' => 'twitter:description',
        'title'               => 'title',
        'og:title'            => 'og:title',
        'twitter:title'       => 'twitter:title',
        'keywords'            => 'keywords',
        // Twitter card "label/data" pairs — emitted by Rank Math Pro and similar
        // SEO plugins (e.g. "Time to read" / "Less than a minute"). Previously
        // leaked English on translated pages because the write-back ladder
        // didn't know about them.
        'twitter:label1'      => 'twitter:label1',
        'twitter:data1'       => 'twitter:data1',
        'twitter:label2'      => 'twitter:label2',
        'twitter:data2'       => 'twitter:data2',
    ];

    /** SEO cache version constant — bump to invalidate the plugin file cache for forward-compat milestones. */
    const CONVEYTHIS_SEO_CACHE_VERSION = 1;

    function __construct() {
        if (!isset($_SERVER['HTTP_HOST'])) {
            $_SERVER['HTTP_HOST'] = parse_url(home_url(), PHP_URL_HOST) ?: '';
        }
        if (!isset($_SERVER['REQUEST_URI'])) {
            $_SERVER['REQUEST_URI'] = '/';
        }
        if (!isset($_SERVER['REQUEST_SCHEME'])) {
            $_SERVER['REQUEST_SCHEME'] = is_ssl() ? 'https' : 'http';
        }

        $this->print_log('__construct()');
        $this->print_log("***********************");
        $this->print_log("CONVEYTHIS_APP_URL:" . CONVEYTHIS_APP_URL);
        $this->print_log("CONVEYTHIS_API_URL:" . CONVEYTHIS_API_URL);
        $this->print_log("CONVEYTHIS_API_PROXY_URL:" . CONVEYTHIS_API_PROXY_URL);
        $this->print_log("CONVEYTHIS_API_PROXY_URL_FOR_EU:" . CONVEYTHIS_API_PROXY_URL_FOR_EU);
        $this->print_log("CONVEYTHIS_JAVASCRIPT_PLUGIN_URL:" . CONVEYTHIS_JAVASCRIPT_PLUGIN_URL);
        $this->print_log("***********************");
        $this->variables = new Variables();
        $this->ConveyThisCache = new ConveyThisCache();
        $this->ConveyThisSEO = new ConveyThisSEO();

        uasort($this->variables->languages, function ($a, $b) {
            if (strcmp($a['title_en'], $b['title_en']) > 0) {
                return 1;
            } else if (strcmp($a['title_en'], $b['title_en']) < 0) {
                return -1;
            } else {
                return 0;
            }
        });

        uasort($this->variables->flags, function ($a, $b) {
            if (strcmp($a['title'], $b['title']) > 0) {
                return 1;
            } else if (strcmp($a['title'], $b['title']) < 0) {
                return -1;
            } else {
                return 0;
            }
        });

        $this->variables->blockpages_items = [];
        if ($this->variables->blockpages) {
            foreach ($this->variables->blockpages as $blockpage) {
                if (!empty($blockpage)) {
                    $page_url = $this->getPageUrl($blockpage);
                    $this->variables->blockpages_items[] = $page_url;
                }
            }
        }

        $this->variables->exclusions = $this->send('GET', '/admin/account/domain/pages/excluded/?referrer=' . urlencode($_SERVER['HTTP_HOST']));

        $active_plugins = get_option('active_plugins');

        if (empty($this->variables->is_active)) {
            $url = home_url();
            $domain_name = $this->getPageHost($url);

            $account = $this->getAccountByApiKey($this->variables->api_key);

            if (!empty($account)) {
                $domain = $this->getDomainDetails($account['account_id'], $domain_name);
                // $this->print_log("@@@ domain: " . json_encode($domain));
            }

            if (!empty($domain) && $domain[0]['is_active'] === '1') {
                update_option('is_active_domain', ['is_active' => 1]);
            }
        }

        // get domain_id for Edit translations link
        $url = home_url();
        $domain_name = $this->getPageHost($url);
        $account = $this->getAccountByApiKey($this->variables->api_key);
        if (!empty($account)) {
            $domain = $this->getDomainDetails($account['account_id'], $domain_name);
            $this->print_log("@@@ domain: " . json_encode($domain));
            $domain_id = "";
            if (!empty($domain)) {
                $domain_id = $domain[0]['domain_id'];
                $this->print_log("domain_id: " . $domain_id);
                $this->variables->domain_id = $domain_id;
            }
        }

        add_filter('conveythis_get_dom_checkers', array($this, 'conveythis_register_default_dom_checkers'));
        add_filter('conveythis_add_json_keys', array($this, 'conveythis_add_default_json_keys'));

        add_filter('get_target_languages', array($this, 'get_target_languages'));

        add_filter('plugin_row_meta', array($this, 'row_meta'), 10, 2);
        add_filter('wp_nav_menu', array($this, '_menu_shortcode'), 20, 2);

        add_action('init', array($this, 'init'));
        add_action('template_redirect', array($this, 'maybe_redirect_translated_slug_404'), 1);
        add_action('template_redirect', array($this, 'maybe_redirect_translated_slash_canonical'), 1);
        add_action('wp_footer', array($this, 'output_conveythis_api_debug_comment'), 9999);

        add_action('update_option', array($this, 'plugin_update_option'), 10, 3);

        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'), 10);

        add_action('admin_head-nav-menus.php', array($this, 'add_nav_menu_meta_boxes'));
        add_filter('nav_menu_link_attributes', array($this, 'magellanlinkfilter'), 10, 3);

        add_action('widgets_init', 'wp_register_widget');
        add_shortcode('conveythis_switcher', array($this, 'get_conveythis_shortcode'));

        add_action('wp_ajax_conveythis_save_all_settings', array($this, 'ajax_conveythis_save_settings'));
        add_action('wp_ajax_conveythis_parse_sitemap', array($this, 'ajax_conveythis_parse_sitemap'));
        add_action('wp_ajax_check_dns', array($this, 'handle_check_dns'));
        add_action('wp_ajax_conveythis_diagnose_trailing_slash', array($this, 'ajax_diagnose_trailing_slash'));

        // SEO Translation Quality hooks
        add_action('init',                                  array($this, 'maybe_run_seo_v1_upgrade'), 5);
        add_action('admin_notices',                         array($this, 'show_seo_v1_upgrade_notice'));
        if ($this->variables->beta_features) {
            add_action('admin_notices',                         array($this, 'show_seo_quality_notice'));
            add_action('admin_notices',                         array($this, 'show_structured_data_discovery_notice'));
            add_action('admin_post_conveythis_dismiss_structured_data_notice', array($this, 'handle_dismiss_structured_data_notice'));
            add_action('admin_post_conveythis_clear_seo_quality_log', array($this, 'handle_clear_seo_quality_log'));
            add_action('admin_post_conveythis_dismiss_seo_quality_event', array($this, 'handle_dismiss_seo_quality_event'));
            add_action('admin_post_conveythis_add_seo_glossary_term', array($this, 'handle_add_seo_glossary_term'));
        }
        add_action('admin_post_conveythis_seo_warmup',      array($this, 'handle_seo_warmup_start'));
        add_action('admin_post_conveythis_seo_warmup_skip', array($this, 'handle_seo_warmup_skip'));
        add_action('admin_notices',                               array($this, 'show_seo_quality_flash_notice'));
        add_action('conveythis_seo_warmup_tick',            array($this, 'process_seo_warmup_tick'));

        //RankMath
        //sitemap
        if (in_array('seo-by-rank-math/rank-math.php', $active_plugins)) {
            add_action('init', array($this->ConveyThisSEO, 'rm_enable_custom_sitemap'));
            add_action('parse_query', array($this->ConveyThisSEO, 'rank_math_sitemap_init'), 0);
            add_action('rank_math/sitemap/url', array($this->ConveyThisSEO, 'sitemap_add_translated_urls'), 10, 2);
            // OpenGraph
            add_action('rank_math/opengraph/url', array($this, 'rank_math_opengraph_url'), 10, 2);
        }

        //Yoast sitemap
        if (in_array('wordpress-seo/wp-seo.php', $active_plugins)) {
            add_action('init', array($this->ConveyThisSEO, 'yo_enable_custom_sitemap'));
            add_action('pre_get_posts', array($this->ConveyThisSEO, 'wpseo_init_sitemap'), 1);
            add_action('wpseo_sitemap_url', array($this->ConveyThisSEO, 'sitemap_add_translated_urls'), 10, 2);
            //OpenGraph
            add_action('wpseo_opengraph_url', array($this, 'rank_math_opengraph_url'), 10, 2);
        }

        //SeoPress sitemap
        if (in_array('wp-seopress/seopress.php', $active_plugins)) {
            add_action('init', array($this->ConveyThisSEO, 'sp_custom_sitemaps_rewrite_rule'));
            add_filter('query_vars', array($this->ConveyThisSEO, 'sp_add_query_vars_filter'));
            add_filter('seopress_sitemaps_xml_index', array($this->ConveyThisSEO, 'sp_sitemaps_xml_index'));
            add_action('template_redirect', array($this->ConveyThisSEO, 'sp_serve_custom_sitemaps'));
            add_action('seopress_sitemaps_url', array($this->ConveyThisSEO, 'sitemap_add_translated_urls'), 10, 2);
            //OpenGraph
            add_action('seopress_social_og_url', array($this, 'seopress_opengraph_url'), 10, 2);
        }

        add_action('wp_ajax_conveythis_clear_all_cache', array('ConveyThisCache', 'clearAllCache'));
        add_action('wp_ajax_conveythis_dismiss_all_cache', array('ConveyThisCache', 'dismissAllCacheMessages'));
        add_action('pre_post_update', array($this, 'clear_post'), 10, 2);
        // Spec 5: when a post slug changes, clear cache for BOTH old and new
        // translated URLs. pre_post_update only has access to the pre-save
        // state, so it misses the new slug. post_updated runs after the update
        // with both $post_before and $post_after and can compare names.
        add_action('post_updated', array($this, 'invalidate_on_slug_change'), 10, 3);
        add_action('before_delete_post', array($this, 'invalidate_on_post_delete'), 10, 1);

        if (strpos($_SERVER['REQUEST_URI'], '/wp-admin/') !== false) {
            if (isset($_POST['exclusions'])) { //phpcs:ignore
                $this->updateRules($_POST['exclusions'], 'exclusion'); //phpcs:ignore
            }
            if (isset($_POST['glossary'])) { //phpcs:ignore
                $this->updateRules($_POST['glossary'], 'glossary'); //phpcs:ignore
            }
            if (isset($_POST['exclusion_blocks'])) { //phpcs:ignore
                $this->updateRules($_POST['exclusion_blocks'], 'exclusion_blocks'); //phpcs:ignore
            }
            if (isset($_POST['clear_translate_cache']) && $_POST['clear_translate_cache']) { //phpcs:ignore
                // 1) Clear WP-side file cache at CONVEYTHIS_CACHE_TRANSLATIONS_PATH.
                $result = $this->ConveyThisCache->clear_cached_translations(true);

                // 2) Ask the API to purge this domain's rows from tbl_cache and
                //    invalidate the matching CDN proxy cache. Without this, the
                //    shared tbl_cache keeps serving stale translations for
                //    segments/links edited from the dashboard.
                $api_result = $this->clearTranslateCacheOnApi();

                header('Content-Type: application/json', 1);
                echo json_encode(array(
                    'clear_cache_translate' => $result,
                    'api_cache'             => $api_result,
                ));
                exit();
            }
        }

        $flag_replaces = ['NV2' => 'af', '5iM' => 'al', '5W5' => 'dz', '0Iu' => 'ad', 'R3d' => 'ao', '16M' => 'ag', 'V1f' => 'ar', 'q9U' => 'am', '2Os' => 'au', '8Dv' => 'at', 'Wg1' => 'az', '' => 'xk', '0qL' => 'bs', 'D9A' => 'bh', '63A' => 'bd', 'u7L' => 'bb', 'O8S' => 'by', '0AT' => 'be', 'lH4' => 'bz', 'I2x' => 'bj', 'D9z' => 'bt', '8Vs' => 'bo', 'Z1t' => 'ba', 'Vf3' => 'bw', '1oU' => 'br', '3rE' => 'bn', 'x8P' => 'bf', '5qZ' => 'bi', 'o8B' => 'kh', '3cO' => 'cm', 'P4g' => 'ca', 'R5O' => 'cv', 'kN9' => 'cf', 'V5u' => 'td', 'wY3' => 'cl', 'Z1v' => 'cn', 'a4S' => 'co', 'N6k' => 'km', 'WK0' => 'cg', 'PP7' => 'cr', '6PX' => 'ci', '7KQ' => 'hr', 'vU2' => 'cu', '1ZY' => 'cz', 'Kv5' => 'cd', 'Ro2' => 'dk', 'MS7' => 'dj', 'E7U' => 'dm', 'Eu2' => 'do', 'D90' => 'ec', '7LL' => 'eg', '0zL' => 'sv', 'b8T' => 'gq', '8Gl' => 'er', 'VJ8' => 'ee', 'ZH1' => 'et', 'E1f' => 'fj', 'nM4' => 'fi', 'E77' => 'fr', 'R1u' => 'ga', 'TZ6' => 'gm', '8Ou' => 'ge', '6Mr' => 'gh', 'kY8' => 'gr', 'yG1' => 'gd', 'aE8' => 'gt', '6Lm' => 'gn', 'I39' => 'gw', 'Mh5' => 'gy', 'Qx7' => 'ht', 'm5Q' => 'hn', 'OU2' => 'hu', 'Ho8' => 'is', 'My6' => 'in', 'G0m' => 'id', 'Vo7' => 'ir', 'z7I' => 'iq', '5Tr' => 'ie', '5KS' => 'il', 'BW7' => 'it', 'u6W' => 'jm', '4YX' => 'jp', 's2B' => 'jo', 'QA5' => 'kz', 'X3y' => 'ke', 'l2H' => 'ki', 'P5F' => 'kw', 'uP6' => 'kg', 'Qy5' => 'la', 'j1D' => 'lv', 'Rl2' => 'lb', 'lB1' => 'ls', '9Qw' => 'lr', 'v6I' => 'ly', '2GH' => 'li', 'uI6' => 'lt', 'EV8' => 'lu', '6GV' => 'mk', '4tE' => 'mg', 'O9C' => 'mw', 'C9k' => 'my', '1Q3' => 'mv', 'Yi5' => 'ml', 'N11' => 'mt', 'Z3x' => 'mh', 'F18' => 'mr', 'mH4' => 'mu', '8Qb' => 'mx', 'H6t' => 'fm', 'FD8' => 'md', 't0X' => 'mc', 'X8h' => 'mn', '61A' => 'me', 'M2e' => 'ma', 'J7N' => 'mz', 'YB9' => 'mm', 'r0H' => 'na', 'M09' => 'nr', 'E0c' => 'np', '8jV' => 'nl', '0Mi' => 'nz', '5dN' => 'ni', 'Rj0' => 'ne', '8oM' => 'ng', '3Yz' => 'kp', '4KE' => 'no', '8NL' => 'om', 'n4T' => 'pk', '8G2' => 'pw', '93O' => 'pa', 'FD4' => 'pg', 'y5O' => 'py', '4MJ' => 'pe', '2qL' => 'ph', 'j0R' => 'pl', '0Rq' => 'pt', 'a8S' => 'qa', 'nC7' => 'ro', 'D1H' => 'ru', '8UD' => 'rw', 'X2d' => 'kn', 'I5e' => 'lc', '3Kf' => 'vc', '54E' => 'ws', 'K4F' => 'sm', 'cZ9' => 'st', 'J06' => 'sa', 'x2O' => 'sn', 'GC6' => 'rs', 'JE6' => 'sc', 'mS4' => 'sl', 'O6e' => 'sg', 'Y2i' => 'sk', 'ZR1' => 'si', '0U1' => 'sb', '3fH' => 'so', '7xS' => 'za', '0W3' => 'kr', 'H4u' => 'ss', 'A5d' => 'es', '9JL' => 'lk', 'Wh1' => 'sd', '7Rb' => 'sr', 'f6L' => 'sz', 'oZ3' => 'se', '8aW' => 'ch', 'UZ9' => 'sy', '00T' => 'tw', '7Qa' => 'tj', 'VU7' => 'tz', 'V6r' => 'th', '52C' => 'tl', 'HH3' => 'tg', '8Ox' => 'to', 'oZ8' => 'tt', 'pD6' => 'tn', 'YZ9' => 'tr', 'Tm5' => 'tm', 'u0Y' => 'tv', 'eJ2' => 'ug', '2Mg' => 'ua', 'DT3' => 'ae', 'Dw0' => 'gb', 'R04' => 'us', 'aL9' => 'uy', 'zJ3' => 'uz', 'D0Y' => 'vu', 'FG2' => 'va', 'Eg6' => 've', 'l2A' => 'vn', 'YZ0' => 'ye', '9Be' => 'zm', '80Y' => 'zw', '00H' => 'hk', '00P' => 'ha'];

        if ($this->variables->style_change_flag && count($this->variables->style_change_flag)) {
            $update_flag = false;
            foreach ($this->variables->style_change_flag as $key => $flag) {
                if (isset($flag_replaces[$flag])) {
                    $this->variables->style_change_flag[$key] = $flag_replaces[$flag];
                    $update_flag = true;
                }
            }
            if ($update_flag) {
                update_option('style_change_flag', $this->variables->style_change_flag);
            }
        }

        $this->print_log("/// trailing slash setting: " . $this->variables->use_trailing_slash);
    }

    private function is_local_link($url) {
        $this->print_log("* is_local_link()");
        if (strpos($url, '#') === 0) {
            $this->print_log("anchor link: ignored " . $url);
            return false;
        }

        $ignored_patterns = [
            // WordPress internals
            'wp-admin', 'wp-login', 'wp-json', 'xmlrpc.php', 'tsu-admin',

            // Email & communication
            'mailto:', 'tel:', 'sms:', 'fax:', 'mms:', 'sip:', 'sips:', 'callto:', 'voicemail:',

            // Map and geo links
            'geo:', 'maps:', 'comgooglemaps:', 'waze:', 'applemaps:', 'map:', 'mapsapp:',

            // Messaging & social apps
            'skype:', 'whatsapp:', 'viber:', 'tg:', 'telegram:', 'fb:', 'facebook:',
            'messenger:', 'instagram:', 'threads:', 'twitter:', 'x.com:', 't.me:',
            'discord:', 'snapchat:', 'signal:', 'wechat:', 'line:', 'kakaotalk:',
            'zoommtg:', 'zoomus:', 'slack:', 'teams:', 'meet:', 'facetime:',
            'linkedin:', 'pinterest:', 'reddit:', 'clubhouse:', 'youtube:', 'yt:',

            // File and network protocols
            'file:', 'ftp:', 'sftp:', 'ftps:', 'afp:', 'smb:', 'nfs:', 'dav:',
            'data:', 'blob:', 'chrome:', 'about:', 'intent:', 'itms:', 'market:',

            // Payment & commerce schemes
            'upi:', 'pay:', 'paypal:', 'venmo:', 'cashapp:', 'stripe:', 'alipay:',
            'wepay:', 'gcash:', 'paytm:', 'revolut:', 'square:', 'bank:',

            // Custom and system intents
            'android-app:', 'ios-app:', 'intent:', 'content:', 'mailto:', 'sms:',
            'chrome-extension:', 'javascript:', 'vbscript:',

            // Security or internal use
            'javascript:', 'vbscript:', 'about:blank', 'edge:', 'chrome:', 'safari:',
            'opera:', 'moz-extension:',

            // Streaming / media
            'spotify:', 'soundcloud:', 'deezer:', 'twitch:', 'netflix:', 'hulu:',
            'disneyplus:', 'primevideo:', 'itunes:',

            // Developer / internal links
            'localhost', '127.0.0.1', '::1', 'test:', 'dev:', 'staging:', 'docker:', 'git:',

            // Miscellaneous or duplicate safe entries
            'intent:', 'line:', 'tel:', 'fax:', 'sms:', 'file:', 'ftp:', 'mailto:'
        ];


        // Check if URL contains any ignored pattern
        foreach ($ignored_patterns as $pattern) {
            if (strpos($url, $pattern) !== false) {
                $this->print_log("$pattern link: ignored " . $url);
                return false;
            }
        }

        $host = parse_url(home_url(), PHP_URL_HOST);
        $check = parse_url($url, PHP_URL_HOST);

        if ($check === null || $check === $host) {
            $this->print_log("+++ is_local_link: true:" . $url);
            return true;
        } else {
            $this->print_log("--- is_local_link: false:" . $url);
            return false;
        }
    }

    /**
     * Apply trailing slash rule to a URL.
     * Handles all modes: 0 (auto/WP), 1 (add), -1 (remove), 2 (custom/sitemap).
     *
     * The sitemap map (conveythis_trailing_slash_map) is consulted ONLY in mode 2.
     * In modes 0/1/-1 the admin has chosen an explicit global policy, so per-URL
     * map data is deliberately ignored. Do not "fix" this — it is the contract.
     */
    private function applyTrailingSlash(string $url): string
    {
        if (empty($url) || strpos($url, '#') === 0) {
            return $url;
        }

        $parsed = parse_url($url);
        $path = isset($parsed['path']) ? $parsed['path'] : '';

        if ($path === '' || $path === '/') {
            return $url;
        }

        // Don't add slash after file extensions. Filterable so integrators can extend
        // without patching the plugin (e.g. custom CDN asset types, uncommon archive
        // formats). The default list mirrors WP core's wp_get_mime_types() practical
        // subset plus common downloadables (docs, modern media/images/fonts).
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $blocklist = apply_filters('conveythis_trailing_slash_extension_blocklist', [
            // text / markup
            'html','htm','xml','json','php','txt','css','js','map',
            // documents
            'pdf','doc','docx','xls','xlsx','ppt','pptx','csv',
            // images
            'jpg','jpeg','png','gif','svg','webp','avif','ico','bmp','tif','tiff','heic',
            // fonts
            'woff','woff2','ttf','eot','otf',
            // archives
            'zip','gz','tar','rar','7z','bz2',
            // audio / video
            'mp3','mp4','avi','mov','wmv','flv','swf',
            'webm','ogg','oga','ogv','wav','flac','m4a','m4v','mkv','3gp','aac',
        ]);
        if ($ext && in_array($ext, $blocklist, true)) {
            return $url;
        }

        $mode = (int)$this->variables->use_trailing_slash;

        // Auto: match WordPress permalink structure
        if ($mode === 0) {
            if (!isset($this->wpTrailingSlashDetected)) {
                $structure = get_option('permalink_structure');
                if (empty($structure)) {
                    $this->wpTrailingSlashDetected = 0; // Plain permalinks — no change
                } else {
                    $this->wpTrailingSlashDetected = (substr($structure, -1) === '/') ? 1 : -1;
                }
            }
            $mode = $this->wpTrailingSlashDetected;
            if ($mode === 0) {
                return $url;
            }
        }

        // Custom: look up in sitemap slash map, fall back to auto
        if ($mode === 2) {
            if (!$this->trailingSlashMapLoaded) {
                $this->trailingSlashMapCache = $this->variables->trailing_slash_map ?? [];
                $this->trailingSlashMapLoaded = true;
            }
            $pathWithSlash = rtrim($path, '/') . '/';
            $pathWithout   = rtrim($path, '/');
            if (isset($this->trailingSlashMapCache[$pathWithSlash])) {
                $mode = 1;
            } elseif (isset($this->trailingSlashMapCache[$pathWithout])) {
                $mode = -1;
            } else {
                // Not in map — fall back to Auto
                if (!isset($this->wpTrailingSlashDetected)) {
                    $structure = get_option('permalink_structure');
                    if (empty($structure)) {
                        $this->wpTrailingSlashDetected = 0;
                    } else {
                        $this->wpTrailingSlashDetected = (substr($structure, -1) === '/') ? 1 : -1;
                    }
                }
                $mode = $this->wpTrailingSlashDetected;
                if ($mode === 0) {
                    return $url;
                }
            }
        }

        // Apply the resolved mode to the path only
        if ($mode === 1) {
            if (substr($path, -1) !== '/') {
                $path .= '/';
            }
        } elseif ($mode === -1) {
            if (substr($path, -1) === '/') {
                $path = substr($path, 0, -1);
            }
        }

        // Reconstruct URL
        $result = '';
        if (isset($parsed['scheme'])) {
            $result .= $parsed['scheme'] . '://';
        }
        if (isset($parsed['host'])) {
            $result .= $parsed['host'];
        }
        if (isset($parsed['port'])) {
            $result .= ':' . $parsed['port'];
        }
        $result .= $path;
        if (isset($parsed['query'])) {
            $result .= '?' . $parsed['query'];
        }
        if (isset($parsed['fragment'])) {
            $result .= '#' . $parsed['fragment'];
        }

        return $result;
    }

    /**
     * Canonicalize an <a href> path for dedup when collecting/looking up link
     * segments. Ensures that `/contact-us` and `/contact-us/` on the same page
     * map to a single dictionary key, using the same rule the plugin applies at
     * render time (`use_trailing_slash`).
     *
     * Disable with: add_filter('conveythis_canonicalize_link_paths', '__return_false');
     */
    private function canonicalizeLinkPath(string $path): string
    {
        if (!apply_filters('conveythis_canonicalize_link_paths', true)) {
            return $path;
        }
        if ($path === '' || $path === '/' || strpos($path, '#') === 0) {
            return $path;
        }
        $canonical = $this->applyTrailingSlash($path);
        if ($canonical !== $path) {
            $this->print_log("/// canonicalizeLinkPath: {$path} => {$canonical}");
        }
        return $canonical;
    }

    /**
     * Prevent WP core redirect_canonical() from overriding the plugin's
     * `use_trailing_slash` setting on translated URLs.
     *
     * Only slash-only differences between the requested URL and the WP canonical
     * URL are suppressed. All other canonicalizations continue to run normally:
     *   - pagination fixups
     *   - attachment redirects
     *   - wrong or deleted-slug redirects
     *   - preserved query-string normalization
     *   - front-page canonicalization
     *
     * This filter is registered only for translated requests (see init()), so
     * source-language URLs are unaffected.
     *
     * @param string|false $redirect_url  URL WP proposes to redirect to, or false.
     * @param string       $requested_url Incoming request URL.
     * @return string|false
     */
    public function filter_redirect_canonical_for_translated($redirect_url, $requested_url)
    {
        if (!is_string($redirect_url) || !is_string($requested_url)) {
            return $redirect_url;
        }
        if (empty($this->variables->language_code)) {
            return $redirect_url;
        }
        if (rtrim($redirect_url, '/') === rtrim($requested_url, '/')) {
            // WP core's proposal is slash-only and may have stripped the /lang/ prefix.
            // Instead of blindly suppressing, compute the correct slash shape from the
            // actual requested URL — applyTrailingSlash() honours all four modes and
            // preserves the language route. If our correction differs from the request,
            // issue the redirect ourselves; otherwise fall back to suppression.
            $corrected = $this->applyTrailingSlash($requested_url);
            if ($corrected !== $requested_url) {
                $this->print_log("* redirect_canonical replaced (slash-only diff): $requested_url -> $corrected");
                wp_safe_redirect($corrected, 301);
                exit;
            }
            $this->print_log("* redirect_canonical suppressed (slash-only diff, already canonical): $requested_url");
            return false;
        }
        return $redirect_url;
    }

    /**
     * Proactively canonicalise trailing-slash shape on translated-URL requests.
     *
     * WP core's redirect_canonical() does not fire for plugin-rewritten /lang/
     * routes — so without this handler, both /ru/slug and /ru/slug/ serve 200,
     * creating duplicate content that burns crawl budget. This handler runs at
     * template_redirect priority 1 (alongside the 404-recovery hook), compares
     * the requested URL against applyTrailingSlash()'s verdict, and issues a
     * 301 to the canonical shape when they disagree.
     *
     * Guards:
     *  - Only on GET requests.
     *  - Only when a translation language is in play (language_code non-empty).
     *    Source-language requests go through WP core's own redirect_canonical.
     *  - Skipped on subdomain URL structures — language lives on the host there,
     *    so slash logic is identical to source URLs and WP core handles it.
     *  - No-ops when the request URI is already canonical (no redirect loop).
     */
    public function maybe_redirect_translated_slash_canonical() {
        if (empty($this->variables->language_code)) return;
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) return;
        // Canonicalize both GET (real navigations) and HEAD (crawler / curl probes) —
        // if we 301 one but 200 the other, Google's crawler sees inconsistent behavior.
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
        if ($method !== 'GET' && $method !== 'HEAD') return;
        if (!empty($this->variables->url_structure) && $this->variables->url_structure === 'subdomain') return;

        // Use the request URI the browser sent, NOT the one init() has since rewritten
        // to strip the /lang/ prefix. Without this, applyTrailingSlash() would compare
        // the canonical shape against an internal path and the redirect would never fire.
        $req = $this->original_request_uri_for_slash_canonical;
        if (!is_string($req) || $req === '' || $req === '/') return;

        $scheme = is_ssl() ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        if ($host === '') return;
        $requested = $scheme . '://' . $host . $req;

        $corrected = $this->applyTrailingSlash($requested);
        if ($corrected !== $requested) {
            $this->print_log("* slash_canonical_translated: $requested -> $corrected");
            wp_safe_redirect($corrected, 301);
            exit;
        }
    }

    /**
     * AJAX: probe a translated URL to detect server-level trailing-slash
     * rewrites (nginx / Apache / CDN) that fire before PHP runs and would
     * override the plugin's `use_trailing_slash` setting.
     *
     * Sends one un-redirected HEAD to a translated URL without a trailing
     * slash, and one to the same URL with a trailing slash. Reports the
     * status code, Location header, and X-Redirect-By for each so the user
     * can tell whether a redirect (if any) is coming from the plugin, from
     * WordPress core, or from the web server.
     *
     * Requires manage_options capability. No side effects.
     */
    public function ajax_diagnose_trailing_slash()
    {
        check_ajax_referer('conveythis_diagnose_trailing_slash', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions'], 403);
        }

        $targets = $this->variables->target_languages_translations ?? [];
        if (!is_array($targets)) {
            $targets = is_string($targets) ? (json_decode($targets, true) ?: []) : [];
        }
        $lang_prefix = '';
        if (!empty($targets)) {
            $first = reset($targets);
            if (is_string($first) && $first !== '') {
                $lang_prefix = $first;
            }
        }
        if ($lang_prefix === '' && !empty($this->variables->target_languages)) {
            $lang_prefix = (string) $this->variables->target_languages[0];
        }
        if ($lang_prefix === '') {
            wp_send_json_error(['message' => 'No target languages are configured.']);
        }

        // Pick a real path to test: first published page that isn't the front page.
        $page_ids = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 3,
            'fields'         => 'ids',
        ]);
        $test_path = '';
        foreach ($page_ids as $pid) {
            $perma = get_permalink($pid);
            if (!$perma) { continue; }
            $parts = wp_parse_url($perma);
            $p = $parts['path'] ?? '';
            if ($p && $p !== '/' && strpos($p, '/' . $lang_prefix . '/') !== 0) {
                $test_path = $p;
                break;
            }
        }
        if ($test_path === '') {
            wp_send_json_error(['message' => 'Could not find a published page to use as a test path.']);
        }

        $home        = untrailingslashit(home_url());
        $path_trim   = '/' . trim($test_path, '/');
        $url_noslash = $home . '/' . $lang_prefix . $path_trim;
        $url_slash   = $url_noslash . '/';

        $probe = function ($url) {
            $res = wp_remote_head($url, [
                'redirection' => 0,
                'timeout'     => 8,
                'headers'     => ['Accept' => 'text/html'],
            ]);
            if (is_wp_error($res)) {
                return ['error' => $res->get_error_message()];
            }
            return [
                'status'      => (int) wp_remote_retrieve_response_code($res),
                'location'    => (string) wp_remote_retrieve_header($res, 'location'),
                'redirect_by' => (string) wp_remote_retrieve_header($res, 'x-redirect-by'),
                'server'      => (string) wp_remote_retrieve_header($res, 'server'),
            ];
        };

        $result_noslash = $probe($url_noslash);
        $result_slash   = $probe($url_slash);

        // Classification
        $verdict   = 'ok';
        $details   = [];
        $effective = (int) ($this->variables->use_trailing_slash ?? 0);
        if ($effective === 0) {
            $struct    = (string) get_option('permalink_structure', '');
            $effective = ($struct !== '' && substr($struct, -1) === '/') ? 1 : (($struct === '') ? 0 : -1);
        }

        if (!isset($result_noslash['error'])) {
            $s = $result_noslash['status'];
            if ($s >= 300 && $s < 400) {
                $by = $result_noslash['redirect_by'] ?: ($result_noslash['server'] ?: 'unknown');
                $details[] = "Request WITHOUT slash returned {$s}, redirect emitted by: " . ($by ?: 'unknown');
                if ($effective === -1) {
                    $verdict = 'server_overrides_plugin';
                    $details[] = 'Plugin is set to REMOVE slashes but the server is adding one. The server-level rule must be relaxed for /' . $lang_prefix . '/ URLs.';
                } elseif ($effective === 1) {
                    $verdict = 'expected';
                    $details[] = 'Consistent with plugin setting (add slashes).';
                }
            } elseif ($s === 200) {
                $details[] = 'Request WITHOUT slash returned 200 — server accepts slashless URLs.';
                if ($effective === 1) {
                    $verdict = 'plugin_adds_but_server_accepts_both';
                }
            }
        }

        if (!isset($result_slash['error'])) {
            $s = $result_slash['status'];
            if ($s >= 300 && $s < 400) {
                $by = $result_slash['redirect_by'] ?: ($result_slash['server'] ?: 'unknown');
                $details[] = "Request WITH slash returned {$s}, redirect emitted by: " . ($by ?: 'unknown');
                if ($effective === 1) {
                    $verdict = 'server_overrides_plugin';
                }
            }
        }

        wp_send_json_success([
            'lang_prefix'       => $lang_prefix,
            'test_path'         => $path_trim,
            'url_noslash'       => $url_noslash,
            'url_slash'         => $url_slash,
            'result_noslash'    => $result_noslash,
            'result_slash'      => $result_slash,
            'plugin_setting'    => (int) ($this->variables->use_trailing_slash ?? 0),
            'plugin_effective'  => $effective,
            'verdict'           => $verdict,
            'details'           => $details,
        ]);
    }

    /**
     * Check if a link's slug should be translated based on configured rules.
     * Language prefix is always added regardless — this only controls slug translation.
     */
    private function shouldTranslateLink(string $path, string $context = 'content'): bool
    {
        $rules = $this->variables->link_rules ?? [];
        $scope = $this->variables->link_rules_scope ?? 'all';
        $pt_settings = $this->variables->link_rules_post_types ?? [];

        // Check scope
        if ($scope !== 'all') {
            if ($scope === 'content' && $context === 'navigation') {
                return false;
            }
            if ($scope === 'navigation' && $context === 'content') {
                return false;
            }
        }

        // Check post type toggles
        if (!empty($pt_settings)) {
            $post_id = url_to_postid(home_url($path));
            if ($post_id > 0) {
                $post_type = get_post_type($post_id);
                if ($post_type && isset($pt_settings[$post_type]) && !$pt_settings[$post_type]) {
                    return false;
                }
            }
        }

        // Check URL pattern rules (first match wins)
        if (!empty($rules)) {
            foreach ($rules as $rule) {
                if (!($rule['enabled'] ?? true)) { continue; }
                $pattern = $rule['pattern'] ?? '';
                if (empty($pattern)) { continue; }

                $matched = false;
                switch ($rule['match'] ?? 'prefix') {
                    case 'prefix':
                        $matched = (strpos($path, $pattern) === 0);
                        break;
                    case 'suffix':
                        $matched = (substr($path, -strlen($pattern)) === $pattern);
                        break;
                    case 'contains':
                        $matched = (strpos($path, $pattern) !== false);
                        break;
                    case 'exact':
                        $matched = ($path === $pattern);
                        break;
                    case 'regex':
                        $matched = (bool)@preg_match($pattern, $path);
                        break;
                }

                if ($matched) {
                    return ($rule['type'] ?? 'exclude') === 'include';
                }
            }
        }

        // No rule matched — default: translate
        return true;
    }

    /**
     * Determine the context of a link based on its position in the DOM tree.
     */
    private function getLinkContext(\DOMNode $node): string
    {
        $current = $node->parentNode;
        while ($current && $current instanceof \DOMElement) {
            $tag = strtolower($current->nodeName);
            if (in_array($tag, ['nav', 'header'])) {
                return 'navigation';
            }
            if (in_array($tag, ['main', 'article', 'section'])) {
                return 'content';
            }
            if ($tag === 'footer') {
                return 'navigation';
            }
            $class = $current->getAttribute('class');
            if ($class && preg_match('/\b(nav|menu|navigation|header)\b/i', $class)) {
                return 'navigation';
            }
            if ($class && preg_match('/\b(content|article|post|entry)\b/i', $class)) {
                return 'content';
            }
            $current = $current->parentNode;
        }
        return 'content';
    }

    public function get_target_languages() {
        $this->print_log("* get_target_languages()");
        return $this->variables->target_languages;
    }

    public function ajax_conveythis_save_settings() {
        $this->print_log("* ajax_conveythis_save_settings()");
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }

        if (!check_ajax_referer('conveythis_ajax_save', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $fields = [
            'api_key',
            'source_language',
            'target_languages',
            'target_languages_translations',
            'default_language',
            'style_change_language', 'style_change_flag',
            'style_flag',
            'style_text',
            'style_position_vertical',
            'style_position_horizontal',
            'style_indenting_vertical',
            'style_indenting_horizontal',
            'auto_translate',
            'hide_conveythis_logo',
            'dynamic_translation',
            'translate_media',
            'translate_document',
            'translate_links',
            'translate_structured_data',
            'change_direction',
            'conveythis_clear_cache',
            'conveythis_select_region',
            'is_active_domain',
            'alternate',
            'accept_language',
            'blockpages',
            'show_javascript',
            'style_position_type',
            'style_position_vertical_custom',
            'style_selector_id',
            'url_structure',
            'style_background_color',
            'style_hover_color',
            'style_border_color',
            'style_text_color',
            'style_corner_type',
            'custom_css_json',
            'style_widget',
            'conveythis_system_links',
            'exclusions',
            'glossary',
            'exclusion_blocks',
            'use_trailing_slash',
            'conveythis_trailing_slash_map',
            'conveythis_trailing_slash_auto_source',
            'conveythis_link_rules',
            'conveythis_link_rules_post_types',
            'conveythis_link_rules_scope',
        ];

        $incoming = $_POST['settings'] ?? [];

        // These fields come as JSON strings and need to be decoded
        $try_json = ['exclusions', 'glossary', 'exclusion_blocks', 'target_languages_translations',
                     'conveythis_trailing_slash_map', 'conveythis_link_rules', 'conveythis_link_rules_post_types'];

        foreach ($try_json as $json_field) {
            if (isset($incoming[$json_field])) {
                // Only decode if it's a string (JSON), not already an array
                if (is_string($incoming[$json_field])) {
                    $decoded = json_decode(stripslashes($incoming[$json_field]), true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $incoming[$json_field] = $decoded;
                    }
                }
            }
        }

        // Glossary: prefer top-level POST to avoid truncation of large JSON inside settings
        $glossary = null;
        $glossary_from_top = false;
        if (isset($_POST['glossary']) && is_string($_POST['glossary'])) {
            $glossary_raw = wp_unslash($_POST['glossary']);
            $this->print_log('[Glossary Save] POST[glossary] received, raw length=' . strlen($glossary_raw));
            $glossary = json_decode(stripslashes($glossary_raw), true);
            if (! is_array($glossary)) {
                $this->print_log('[Glossary Save] POST[glossary] json_decode failed: ' . json_last_error_msg());
                $glossary = null;
            } else {
                $glossary_from_top = true;
                $this->print_log('[Glossary Save] POST[glossary] decoded OK, rules count=' . count($glossary));
            }
        }
        if ($glossary === null) {
            $from_incoming = $incoming['glossary'] ?? null;
            $this->print_log('[Glossary Save] Using settings[glossary], is_array=' . (is_array($from_incoming) ? 'yes' : 'no') . ', count=' . (is_array($from_incoming) ? count($from_incoming) : 'n/a'));
            $glossary = $from_incoming;
        }
        // So that update_option('glossary') later saves the same full array we send to the API
        if (is_array($glossary)) {
            $glossary = $this->filter_glossary_to_active_target_languages($glossary);
            $incoming['glossary'] = $glossary;
        }

        $exclusions = $incoming['exclusions'] ?? null;
        $exclusion_blocks = $incoming['exclusion_blocks'] ?? null;
        $clear_translate_cache = $incoming['clear_translate_cache'] ?? null;

        if ($exclusions) {
            $this->updateRules($exclusions, 'exclusion');
        }
        $glossary_rules_sent = is_array($glossary) ? count($glossary) : 0;
        $glossary_debug = null;
        if ($glossary) {
            $this->print_log('[Glossary Save] Calling updateRules(glossary) with ' . $glossary_rules_sent . ' rules');
            $glossary_debug = $this->updateRules($glossary, 'glossary');
        } else {
            $this->print_log('[Glossary Save] No glossary data to save');
        }
        if ($exclusion_blocks) {
            $this->updateRules($exclusion_blocks, 'exclusion_blocks');
        }
        if ($clear_translate_cache) {
            $this->ConveyThisCache->clear_cached_translations(true);
            // Mirror the "Clear translation cache" button: also purge the API
            // tbl_cache rows for this domain and the CDN proxy cache.
            $this->clearTranslateCacheOnApi();
        }

        if (!check_ajax_referer('conveythis_ajax_save', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        $old_trailing_slash = get_option('use_trailing_slash', '0');

        // Spec 4: reject custom-mode save when no sitemap map is available.
        // Falling back to Auto silently would hide the misconfiguration.
        if (isset($incoming['use_trailing_slash']) && (string)$incoming['use_trailing_slash'] === '2') {
            $incoming_map = $incoming['conveythis_trailing_slash_map'] ?? null;
            $stored_map   = get_option('conveythis_trailing_slash_map', []);
            if (is_string($stored_map)) { $stored_map = json_decode($stored_map, true) ?: []; }
            $map_has_entries = (is_array($incoming_map) && !empty($incoming_map))
                               || (is_array($stored_map) && !empty($stored_map));
            if (!$map_has_entries) {
                wp_send_json_error('Custom trailing slash requires a sitemap. Upload a sitemap in the Links tab, then try again.');
                return;
            }
        }

        foreach ($fields as $field) {
            if (isset($incoming[$field])) {
                $unslashed = wp_unslash($incoming[$field]);

                // SECURITY FIX: Block serialized input to prevent PHP Object Injection
                if (is_serialized($unslashed)) {
                    // Skip this field but continue processing others
                    $this->print_log("SECURITY: Blocked serialized data in field: $field");
                    continue; // Skip this field, continue with others
                }

                $value = $unslashed;

                if ($field === 'style_change_language' || $field === 'style_change_flag') {
                    if (is_array($value)) {
                        $value = array_values(array_filter($value, function ($v) {
                            return $v !== '' && $v !== null;
                        }));
                    }
                }

                update_option($field, $value);
            }
        }

        // Clear cache if trailing slash mode changed
        $new_trailing_slash = $incoming['use_trailing_slash'] ?? $old_trailing_slash;
        if ($old_trailing_slash != $new_trailing_slash) {
            $this->ConveyThisCache->clear_cached_translations(true);
            $this->print_log("/// trailing slash mode changed: $old_trailing_slash -> $new_trailing_slash, cache cleared");
        }

        if (!array_key_exists('style_change_language', $incoming)) {
            update_option('style_change_language', []);
        }

        if (!array_key_exists('style_change_flag', $incoming)) {
            update_option('style_change_flag', []);
        }

        // update all parameters
        $this->variables = new Variables();
        $this->updateDataPlugin();
        $this->clearCacheButton();

        $response_data = [
            'message'             => 'save',
            'glossary_rules_sent' => $glossary_rules_sent,
        ];
        if ( $glossary_debug !== null && is_array( $glossary_debug ) ) {
            $response_data['glossary_debug'] = $glossary_debug;
            $response_data['glossary_debug']['api_urls'] = [
                'proxy_first' => defined( 'CONVEYTHIS_API_PROXY_URL' ) ? CONVEYTHIS_API_PROXY_URL : '',
                'direct_fallback' => defined( 'CONVEYTHIS_API_URL' ) ? CONVEYTHIS_API_URL : '',
                'glossary_endpoint' => '/admin/account/domain/pages/glossary/',
                'region' => isset( $this->variables->select_region ) ? $this->variables->select_region : 'US',
            ];
        }
        return wp_send_json_success( $response_data );
    }

    public function handle_check_dns() {
        $this->print_log("* handle_check_dns()");

        // CSRF + capability gate. Without these an attacker can force a logged-in
        // admin's browser to fire arbitrary DNS lookups (rebinding probes).
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied', 403);
        }
        if (!check_ajax_referer('conveythis_ajax_save', 'nonce', false)) {
            wp_send_json_error('Invalid nonce', 403);
        }

        $subdomains = $this->variables->target_languages;
        $host = $_SERVER["HTTP_HOST"];
        $results = [];

        foreach ($subdomains as $subdomain) {
            $fullDomain = "{$subdomain}.{$host}";
            $cnameRecords = dns_get_record($fullDomain, DNS_CNAME);
            if (!empty($cnameRecords)) {
                foreach ($cnameRecords as $record) {
                    $results[$fullDomain][] = $record['target'];
                }
            } else {
                $results[$fullDomain] = null;
            }
        }

        wp_send_json_success([
            'message' => 'CNAME records fetched',
            'records' => $results
        ]);
    }

    /**
     * Chunked sitemap scan.
     *
     * One HTTP request cannot reliably cover the whole scan: on sites whose
     * SEO plugin (Rank Math, Yoast, …) regenerates sitemap XML on every hit
     * without caching, each child sitemap can take 30–60s to produce. A
     * sitemap index with six children × 50s = 5 minutes, far beyond nginx's
     * default 60s fastcgi_read_timeout — nginx returns a 504 even though PHP
     * is still running. That timeout lives in the server config and cannot be
     * raised from within a plugin.
     *
     * Solution: the handler owns a queue (persisted in a WP transient) and
     * processes items only until a per-call wall-clock budget is hit, then
     * returns progress to the client along with a job_id. The client polls
     * the same endpoint with the job_id until the server reports done=true.
     *
     * Request shape:
     *   - First call:     sitemap_url=…       (no job_id)
     *   - Continuations:  job_id=…            (sitemap_url ignored)
     *
     * Response (success):
     *   { done: bool, job_id, processed, remaining, url_count, errors,
     *     // present only when done=true (legacy shape preserved for small
     *     // sitemaps that finish in one call):
     *     total, with_slash, without_slash, map }
     */
    public function ajax_conveythis_parse_sitemap() {
        $this->print_log("* ajax_conveythis_parse_sitemap()");
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access denied');
        }
        if (!check_ajax_referer('conveythis_ajax_save', 'nonce', false)) {
            wp_send_json_error('Invalid nonce');
        }

        $job_id_in   = isset($_POST['job_id'])      ? sanitize_text_field(wp_unslash($_POST['job_id']))   : '';
        $sitemap_url = isset($_POST['sitemap_url']) ? sanitize_url(wp_unslash($_POST['sitemap_url']))     : '';

        if ($job_id_in === '' && (empty($sitemap_url) || !filter_var($sitemap_url, FILTER_VALIDATE_URL))) {
            wp_send_json_error('Invalid sitemap URL');
        }

        // Reverse-proxy-friendly: each call does a bounded amount of work.
        // @-prefixed because some managed hosts disable these functions via
        // disable_functions; failing silently is fine, the per-call budget
        // keeps us well under the default execution limit anyway.
        @set_time_limit(120);
        @ignore_user_abort(true);

        // Load-or-create job state.
        if ($job_id_in !== '') {
            $state = $this->getSitemapJobState($job_id_in);
            if ($state === null) {
                wp_send_json_error('Scan session expired — please start again.');
            }
            $job_id = $job_id_in;
        } else {
            $state = [
                'queue'     => [['url' => $sitemap_url, 'depth' => 0]],
                'seen'      => [$sitemap_url => true],
                'slash_map' => [],
                'errors'    => [],
                'root_url'  => $sitemap_url,
                'processed' => 0,
                'started_at'=> time(),
            ];
            $job_id = $this->newSitemapJobId();
        }

        // Per-call budget. Defaults target nginx's 60s fastcgi_read_timeout:
        // we stop before starting a new fetch once ~25s has elapsed, so worst
        // case (one slow 50–55s fetch then check) we still finish around 55s.
        // Filterable so environments with a longer proxy read-timeout can do
        // more work per call and finish fewer roundtrips.
        $budget_seconds = (float)apply_filters('conveythis_sitemap_chunk_budget', 25.0);
        $max_per_call   = (int)  apply_filters('conveythis_sitemap_chunk_max_items', 20);
        if ($budget_seconds < 2) { $budget_seconds = 2; }
        if ($max_per_call   < 1) { $max_per_call   = 1; }

        $deadline = microtime(true) + $budget_seconds;
        $processed_this_call = 0;

        while (!empty($state['queue'])) {
            // Check budget BEFORE picking up the next item — but always do at
            // least one fetch per call so the queue always drains.
            if ($processed_this_call > 0) {
                if ($processed_this_call >= $max_per_call) { break; }
                if (microtime(true) >= $deadline) { break; }
            }

            $item = array_shift($state['queue']);
            $url  = $item['url'];
            $depth = (int)$item['depth'];
            if ($depth > 2) { continue; }

            $children = $this->fetchAndParseOneSitemap($url, $state['slash_map'], $state['errors']);
            $state['processed']++;
            $processed_this_call++;

            // Enqueue newly-discovered child sitemaps (dedup so that a weird
            // self-referencing index can't cause an infinite loop).
            foreach ($children as $childUrl) {
                if (isset($state['seen'][$childUrl])) { continue; }
                $state['seen'][$childUrl] = true;
                $state['queue'][] = ['url' => $childUrl, 'depth' => $depth + 1];
            }
        }

        $done = empty($state['queue']);

        if ($done) {
            // Whole scan finished — persist results and emit legacy shape.
            if (empty($state['slash_map']) && !empty($state['errors'])) {
                $this->deleteSitemapJobState($job_id);
                wp_send_json_error('Failed to parse sitemap: ' . implode('; ', $state['errors']));
            }
            update_option('conveythis_trailing_slash_map', wp_json_encode($state['slash_map']), false);
            update_option('conveythis_trailing_slash_auto_source', $state['root_url']);
            $this->deleteSitemapJobState($job_id);

            $with_slash    = count(array_filter($state['slash_map'], function ($v) { return $v === true; }));
            $without_slash = count($state['slash_map']) - $with_slash;

            wp_send_json_success([
                'done'          => true,
                'job_id'        => $job_id,
                'processed'     => $state['processed'],
                'remaining'     => 0,
                'url_count'     => count($state['slash_map']),
                'total'         => count($state['slash_map']),
                'with_slash'    => $with_slash,
                'without_slash' => $without_slash,
                'map'           => $state['slash_map'],
                'errors'        => $state['errors'],
            ]);
        }

        // More work to do — persist state, tell the client to call back.
        $this->saveSitemapJobState($job_id, $state);
        wp_send_json_success([
            'done'      => false,
            'job_id'    => $job_id,
            'processed' => $state['processed'],
            'remaining' => count($state['queue']),
            'url_count' => count($state['slash_map']),
            'errors'    => $state['errors'],
        ]);
    }

    /**
     * Transient-backed job state. Per-user so two admins can scan
     * concurrently without stomping on each other. TTL 1 hour: if the admin
     * wanders off, the partial scan is garbage-collected automatically.
     */
    private function sitemapJobTransientKey(string $job_id): string {
        return 'ct_sitemap_job_' . get_current_user_id() . '_' . $job_id;
    }

    private function newSitemapJobId(): string {
        // 16 alphanumerics → 95 bits of entropy, plenty to avoid any
        // collision across concurrent scans.
        if (function_exists('wp_generate_password')) {
            return wp_generate_password(16, false, false);
        }
        return bin2hex(random_bytes(8));
    }

    private function getSitemapJobState(string $job_id): ?array {
        if (!preg_match('/^[A-Za-z0-9]{8,64}$/', $job_id)) { return null; }
        $state = get_transient($this->sitemapJobTransientKey($job_id));
        return is_array($state) ? $state : null;
    }

    private function saveSitemapJobState(string $job_id, array $state): void {
        set_transient($this->sitemapJobTransientKey($job_id), $state, HOUR_IN_SECONDS);
    }

    private function deleteSitemapJobState(string $job_id): void {
        delete_transient($this->sitemapJobTransientKey($job_id));
    }

    /**
     * Fetch a sitemap body.
     *
     * Default path: a plain wp_remote_get() — what every normal WP install needs.
     *
     * Container fallback path (only when this PHP process is itself running inside
     * a Linux container AND the URL targets the current site): in Dockerized WP
     * setups the admin-facing hostname (e.g. wp-test.conveythis.local:8080) is
     * often unreachable from inside the container due to hairpin NAT / split-horizon
     * DNS, so wp_remote_get() fails with "Could not connect" even though the URL
     * is valid in the browser. In that case — and only that case — we retry with a
     * cURL CONNECT_TO override routing the TCP connection through 127.0.0.1 on the
     * container's own listen port (and, on Docker Desktop, through host.docker.internal)
     * while preserving the original Host header so WordPress serves the right site.
     *
     * On bare-metal / VPS installs the container fallbacks are deliberately NOT
     * attempted — they would just time out and leak confusing "host.docker.internal"
     * messages into the admin UI on hosts that have nothing to do with Docker.
     *
     * Returns the raw response body on success, or null if all attempts failed
     * (errors are appended to $errors).
     */
    private function fetchSitemapBody(string $url, array &$errors): ?string {
        $attempts = [['url' => $url, 'connect_to' => null, 'label' => 'direct']];

        $parsed = parse_url($url);
        if ($parsed && !empty($parsed['host'])) {
            $host = strtolower($parsed['host']);
            $scheme = !empty($parsed['scheme']) ? $parsed['scheme'] : 'http';
            $port = !empty($parsed['port']) ? (int)$parsed['port'] : ($scheme === 'https' ? 443 : 80);

            if ($this->isSameSiteHost($host, $port) && $this->isRunningInContainer()) {
                // We can't reliably discover the container-internal listen port from
                // PHP ($_SERVER['SERVER_PORT'] often reflects the external port the
                // browser connected to — Apache's UseCanonicalPhysicalPort is off by
                // default). Probe a set of plausible internal ports; scheme-matched
                // so we don't speak HTTP to TLS sockets or vice versa.
                $candidatePorts = ($scheme === 'https')
                    ? [443, 8443]
                    : [80, 8080, 8000];
                if (!empty($_SERVER['SERVER_PORT'])) {
                    $candidatePorts[] = (int)$_SERVER['SERVER_PORT'];
                }
                $candidatePorts = array_values(array_unique(array_filter($candidatePorts, function ($p) {
                    return $p > 0 && $p < 65536;
                })));

                foreach ($candidatePorts as $cp) {
                    if ($cp === $port) { continue; } // already covered by the direct attempt
                    $attempts[] = [
                        'url' => $url,
                        'connect_to' => sprintf('%s:%d:127.0.0.1:%d', $host, $port, $cp),
                        'label' => "loopback 127.0.0.1:{$cp}",
                    ];
                }
                // Docker Desktop exposes the host under this name from within containers
                // (no-op on plain Linux Docker — fails fast, doesn't block the flow).
                $attempts[] = [
                    'url' => $url,
                    'connect_to' => sprintf('%s:%d:host.docker.internal:%d', $host, $port, $port),
                    'label' => "host.docker.internal:{$port}",
                ];
            }
        }

        // Buffer per-attempt errors locally; only surface them to the caller if
        // every attempt failed. Otherwise a working fallback would still show a
        // "direct attempt failed" warning, which is noise in container setups.
        $attemptErrors = [];

        foreach ($attempts as $attempt) {
            $filter = null;
            if ($attempt['connect_to'] !== null) {
                // cURL ≥ 7.49 supports CURLOPT_CONNECT_TO; gracefully skip if unavailable.
                if (!defined('CURLOPT_CONNECT_TO')) {
                    continue;
                }
                $connectTo = $attempt['connect_to'];
                $filter = function ($handle) use ($connectTo) {
                    @curl_setopt($handle, CURLOPT_CONNECT_TO, [$connectTo]);
                };
                add_action('http_api_curl', $filter);
            }

            // Default 60s covers real-world slow sitemap generators (Rank Math /
            // Yoast on uncached large sites regularly take 40–55s on the first
            // hit). Filterable so sites whose generator is slower still can
            // raise it without patching the plugin.
            $fetchTimeout = (int)apply_filters('conveythis_sitemap_fetch_timeout', 60, $attempt['url']);
            if ($fetchTimeout < 5) { $fetchTimeout = 5; }

            $response = wp_remote_get($attempt['url'], [
                'timeout'     => $fetchTimeout,
                'sslverify'   => false, // local/dev certs are frequently self-signed
                'redirection' => 5,
                'user-agent'  => 'ConveyThis-Sitemap-Scanner/1.0',
            ]);

            if ($filter !== null) {
                remove_action('http_api_curl', $filter);
            }

            if (is_wp_error($response)) {
                $attemptErrors[] = sprintf('%s [%s]: %s', $attempt['url'], $attempt['label'], $response->get_error_message());
                continue;
            }

            $code = (int)wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                $attemptErrors[] = sprintf('%s [%s]: HTTP %d', $attempt['url'], $attempt['label'], $code);
                continue;
            }

            return wp_remote_retrieve_body($response);
        }

        // All attempts failed — promote buffered errors to caller. If every
        // error was a timeout, lead with a plain-language hint so admins aren't
        // left decoding raw cURL error strings; the original messages still
        // follow for anyone who needs to debug further.
        $allTimedOut = !empty($attemptErrors);
        foreach ($attemptErrors as $e) {
            if (stripos($e, 'timed out') === false && stripos($e, 'timeout') === false) {
                $allTimedOut = false;
                break;
            }
        }
        if ($allTimedOut) {
            $errors[] = sprintf(
                'The server at %s took too long to generate this sitemap. '
                . 'Some SEO plugins (Rank Math, Yoast, …) generate sitemap XML on the fly and can be slow on the first request. '
                . 'Try the scan again in a minute, or raise the timeout via the "conveythis_sitemap_fetch_timeout" filter.',
                parse_url($url, PHP_URL_HOST) ?: $url
            );
        }
        foreach ($attemptErrors as $e) {
            $errors[] = $e;
        }
        return null;
    }

    /**
     * Return true if the given host:port corresponds to the current WP install
     * (i.e. matches home_url, site_url, or the current admin request's Host header).
     * Used to decide when container-loopback fallbacks are safe to apply.
     */
    private function isSameSiteHost(string $host, int $port): bool {
        $candidates = [];

        foreach (['home_url', 'site_url'] as $fn) {
            if (!function_exists($fn)) { continue; }
            $u = call_user_func($fn, '/');
            $p = parse_url($u);
            if (!empty($p['host'])) {
                $scheme = !empty($p['scheme']) ? $p['scheme'] : 'http';
                $hostPort = !empty($p['port']) ? (int)$p['port'] : ($scheme === 'https' ? 443 : 80);
                $candidates[] = [strtolower($p['host']), $hostPort];
            }
        }

        if (!empty($_SERVER['HTTP_HOST'])) {
            $hh = strtolower($_SERVER['HTTP_HOST']);
            if (strpos($hh, ':') !== false) {
                list($hHost, $hPort) = explode(':', $hh, 2);
                $candidates[] = [$hHost, (int)$hPort];
            } else {
                $candidates[] = [$hh, (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 443 : 80];
            }
        }

        foreach ($candidates as $c) {
            if ($c[0] === $host && $c[1] === $port) { return true; }
        }
        return false;
    }

    /**
     * Detect whether this PHP process is running inside a Linux container
     * (Docker, Podman, containerd / Kubernetes, LXC). Used to gate the
     * container-specific networking fallbacks in fetchSitemapBody() so they
     * don't fire — and don't leak confusing "host.docker.internal" errors
     * into the admin UI — on plain VPS / bare-metal WordPress installs where
     * Docker isn't involved at all.
     *
     * Heuristic, not authoritative: a false negative just means we skip a
     * hairpin-NAT fallback that likely wouldn't have helped anyway; a false
     * positive just means we do one extra wasted attempt on failure. Cached
     * per-request so the probe runs at most once.
     */
    private function isRunningInContainer(): bool {
        static $cached = null;
        if ($cached !== null) { return $cached; }

        // Canonical markers: Docker writes /.dockerenv, Podman writes
        // /run/.containerenv. Either one is a strong signal.
        if (@file_exists('/.dockerenv') || @file_exists('/run/.containerenv')) {
            return $cached = true;
        }

        // Cgroup-based check catches containerd-shim, Kubernetes pods, LXC,
        // and Docker variants that don't ship /.dockerenv (e.g. some
        // distroless base images). Non-Linux hosts have no /proc/1/cgroup
        // and silently fall through to false.
        $cgroup = @file_get_contents('/proc/1/cgroup');
        if (is_string($cgroup) && preg_match('#\b(docker|containerd|kubepods|lxc|podman)\b#', $cgroup)) {
            return $cached = true;
        }

        return $cached = false;
    }

    /**
     * Fetch, decompress and parse ONE sitemap document — no recursion.
     *
     * If the document is a <sitemapindex>, the discovered child <loc> URLs
     * are returned to the caller so it can decide when to process them
     * (e.g. the chunked AJAX handler schedules each child as its own HTTP
     * roundtrip to stay under reverse-proxy read timeouts).
     *
     * If the document is a <urlset>, the URL entries are merged into
     * $slash_map directly (path => bool-has-trailing-slash).
     *
     * @param string  $url       sitemap URL to fetch
     * @param array   $slash_map mutated: path => has-trailing-slash
     * @param array   $errors    mutated: list of human-readable error strings
     * @return string[]          child sitemap URLs discovered (empty unless index)
     */
    private function fetchAndParseOneSitemap(string $url, array &$slash_map, array &$errors): array {
        if (count($slash_map) >= 50000) { return []; }

        $body = $this->fetchSitemapBody($url, $errors);
        if ($body === null) { return []; }

        // Handle gzipped content
        if (substr($url, -3) === '.gz') {
            $body = @gzdecode($body);
            if ($body === false) {
                $errors[] = $url . ': Failed to decompress gzip';
                return [];
            }
        }

        libxml_use_internal_errors(true);
        // XXE-safe parse: disable entity loader (PHP <8.0) and block network DTD fetch
        // via LIBXML_NONET. PHP 8.0+ disables external entities by default; the
        // libxml_disable_entity_loader call is a harmless no-op there.
        if (function_exists('libxml_disable_entity_loader')) {
            $prev_entity_loader = libxml_disable_entity_loader(true);
        }
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET);
        if (isset($prev_entity_loader)) {
            libxml_disable_entity_loader($prev_entity_loader);
        }
        if ($xml === false) {
            $errors[] = $url . ': Invalid XML';
            libxml_clear_errors();
            return [];
        }

        // Register namespaces (sitemaps use default namespace)
        $namespaces = $xml->getNamespaces(true);
        $ns = isset($namespaces['']) ? $namespaces[''] : '';

        if ($ns) {
            $xml->registerXPathNamespace('sm', $ns);
            $sitemapLocs = $xml->xpath('//sm:sitemapindex/sm:sitemap/sm:loc');
            $urlLocs = $xml->xpath('//sm:urlset/sm:url/sm:loc');
        } else {
            $sitemapLocs = $xml->xpath('//sitemapindex/sitemap/loc');
            $urlLocs = $xml->xpath('//urlset/url/loc');
        }

        // Sitemap index: collect child URLs and return them to the caller
        // (the caller's queue driver decides when/how to process them).
        if (!empty($sitemapLocs)) {
            $children = [];
            foreach ($sitemapLocs as $loc) {
                $childUrl = trim((string)$loc);
                if ($childUrl !== '') { $children[] = $childUrl; }
            }
            libxml_clear_errors();
            return $children;
        }

        // urlset: merge URL entries into the trailing-slash map
        if (!empty($urlLocs)) {
            foreach ($urlLocs as $loc) {
                $locUrl = trim((string)$loc);
                if (empty($locUrl)) { continue; }
                $path = parse_url($locUrl, PHP_URL_PATH);
                if ($path === null || $path === '') { $path = '/'; }
                $has_slash = (substr($path, -1) === '/');
                $slash_map[$path] = $has_slash;
                if (count($slash_map) >= 50000) { break; }
            }
        }

        libxml_clear_errors();
        return [];
    }

    public function conveythis_register_default_dom_checkers($content) {
        $this->print_log("* conveythis_register_default_dom_checkers()");
        return $content;
    }

    public function clear_post($post_id, $post_data) {
        $this->print_log("* clear_post()");
        $postLink = get_permalink($post_id);
        foreach ($this->variables->target_languages as $targetLanguage) {
            $clearUrl = $this->getTranslateSiteUrl($postLink, $targetLanguage);
            ConveyThisCache::clearPageCache($clearUrl, null);
        }
    }

    /**
     * Spec 5: clear translated-URL caches when a post's slug actually changes.
     * Runs on `post_updated`, which fires after the update with both the
     * pre-save and post-save post objects, so we can detect a slug rename and
     * invalidate caches under BOTH the old and new permalinks.
     *
     * @param int     $post_id
     * @param WP_Post $post_after
     * @param WP_Post $post_before
     */
    public function invalidate_on_slug_change($post_id, $post_after, $post_before) {
        if (!$post_before || !$post_after) { return; }
        // Guard the same way the original did: same name = no URL change = no
        // invalidation. Also catch post_type changes (post -> page etc.) which
        // do reshape the permalink. Anything else (content/meta/categories) is
        // intentionally a no-op so the slug cache isn't churned by every save.
        $slug_changed = $post_before->post_name !== $post_after->post_name;
        $type_changed = $post_before->post_type !== $post_after->post_type;
        if (!$slug_changed && !$type_changed) { return; }
        $this->print_log("* invalidate_on_slug_change() {$post_before->post_name} -> {$post_after->post_name}");

        $languages = $this->variables->target_languages ?? [];
        if (empty($languages)) { return; }

        // Rebuild old permalink from the pre-save state. get_permalink() on
        // the live post would return the new URL already, so we construct the
        // old one by swapping the post name back in.
        $new_link = get_permalink($post_id);
        $old_link = ($new_link && $slug_changed)
            ? str_replace('/' . $post_after->post_name . '/', '/' . $post_before->post_name . '/', $new_link)
            : $new_link;

        foreach ($languages as $targetLanguage) {
            if ($old_link) {
                ConveyThisCache::clearPageCache($this->getTranslateSiteUrl($old_link, $targetLanguage), null);
            }
            if ($new_link) {
                ConveyThisCache::clearPageCache($this->getTranslateSiteUrl($new_link, $targetLanguage), null);
            }
        }

        // Forward-direction slug cache invalidation. Bounded surface: at most
        // 2 paths (old + new) x N target_languages. Spec:
        // docs/superpowers/specs/2026-05-02-sitemap-slug-cache-design.md.
        if ($this->ConveyThisCache instanceof ConveyThisCache) {
            $sourceLang = (string) ($this->variables->source_language ?? '');
            if ($sourceLang !== '') {
                $old_path = $old_link ? parse_url($old_link, PHP_URL_PATH) : null;
                $new_path = $new_link ? parse_url($new_link, PHP_URL_PATH) : null;
                if (is_string($old_path) && $old_path !== '') {
                    $this->ConveyThisCache->delete_fwd_slug_for_path_all_langs($old_path, $sourceLang, $languages);
                }
                if (is_string($new_path) && $new_path !== '' && $new_path !== $old_path) {
                    $this->ConveyThisCache->delete_fwd_slug_for_path_all_langs($new_path, $sourceLang, $languages);
                }
            }
        }
    }

    /**
     * Spec 5: clear translated-URL caches when a post is deleted, so stale
     * translations aren't served after the underlying post is gone.
     */
    public function invalidate_on_post_delete($post_id) {
        $this->print_log("* invalidate_on_post_delete($post_id)");
        $postLink = get_permalink($post_id);
        if (!$postLink) { return; }
        $languages = $this->variables->target_languages ?? [];
        foreach ($languages as $targetLanguage) {
            ConveyThisCache::clearPageCache($this->getTranslateSiteUrl($postLink, $targetLanguage), null);
        }

        // Forward-direction slug cache: drop the deleted post's source path
        // across all target languages so freed shard space is reclaimed by cron
        // and a future post reusing the same path gets a clean fetch.
        if ($this->ConveyThisCache instanceof ConveyThisCache) {
            $sourceLang = (string) ($this->variables->source_language ?? '');
            $path = parse_url($postLink, PHP_URL_PATH);
            if ($sourceLang !== '' && is_string($path) && $path !== '' && !empty($languages)) {
                $this->ConveyThisCache->delete_fwd_slug_for_path_all_langs($path, $sourceLang, $languages);
            }
        }
    }

    public function rank_math_opengraph_url($url) {
        $this->print_log("* rank_math_opengraph_url()");
        if (!empty($this->variables->language_code)) {
            $urlParts = parse_url($url);
            if (isset($urlParts['host']) && isset($urlParts['path'])) {
                $url = $urlParts['scheme'] . '://' . $urlParts['host'] . '/' . $this->variables->language_code . $urlParts['path'];
            }
        }
        return $url;
    }

    public function seopress_opengraph_url($html_url) {
        $this->print_log("* seopress_opengraph_url()");
        if (!empty($this->variables->language_code)) {
            preg_match('/content="([^"]+)"/', $html_url, $matches);
            if (isset($matches[1])) {
                $url = $matches[1];

                $urlParts = parse_url($url);
                if (isset($urlParts['host'])) {
                    $url = $urlParts['scheme'] . '://' . $urlParts['host'] . '/' . $this->variables->language_code . $urlParts['path'];

                    $pattern = '/(content=")([^"]+)(")/';
                    $replacement = '${1}' . $url . '${3}';
                    $html_url = preg_replace($pattern, $replacement, $html_url);

                }
            }
        }
        return $html_url;
    }

    public function magellanlinkfilter($attr, $post, $menu) {
        $this->print_log("* magellanlinkfilter()");
        preg_match('/\[ConveyThis_(.*)\]/', $post->title, $matches);

        if (!empty($matches)) {
            $language = $this->searchLanguage($matches[1]);

            if (!empty($language)) {
                if (!empty($this->variables->language_code)) {
                    if ($language['code2'] === $this->variables->source_language) {
                        $language = $this->searchLanguage($this->variables->language_code);
                    } else if ($language['code2'] === $this->variables->language_code) {
                        $language = $this->searchLanguage($this->variables->source_language);
                    }
                }

                $site_url = $this->variables->site_url;
                $prefix = $this->getPageUrl($site_url);

                if (!empty($this->variables->url_structure) && $this->variables->url_structure == "subdomain") {
                    $location = $this->getSubDomainLocation($language['code2']);
                } else {
                    $location = $this->getLocation($prefix, $language['code2']);
                }
                $icon = $this->genIcon($language['language_id'], $language['flag']);
                $attr['translate'] = 'no';
                $attr['href'] = $location;
                $attr['class'] = "conveythis-no-translate notranslate";

                if ($this->variables->style_text === 'full-text') {
                    $post->title = $icon . $language['title'];
                }
                if ($this->variables->style_text === 'short-text') {
                    $post->title = $icon . strtoupper($language['code3']);
                }
                if ($this->variables->style_text === 'without-text') {
                    $post->title = $icon;
                }
            }
        }
        return $attr;
    }

    public function genIcon($language_id, $flag) {
        $this->print_log("* genIcon()");
        $i = 0;
        while ($i < 5) { // Limit to 5 language/flag pairs
            if (!empty($this->variables->style_change_language[$i]) && $this->variables->style_change_language[$i] == $language_id) {
                $flag = $this->variables->style_change_flag[$i];
            }
            $i++;
        }
        $icon = '';
        if ($this->variables->style_flag === 'rect') {
            $icon = '<span style="height: 20px; width: 30px; background-image: url(\'//cdn.conveythis.com/images/flags/svg/' . $flag . '.png\'); display: inline-block; background-size: contain; background-position: 50% 50%; background-repeat: no-repeat; background-color: transparent; margin-right: 10px; vertical-align: middle;"></span>'; // v3/rectangular
        }
        if ($this->variables->style_flag === 'sqr') {
            $icon = '<span style="height: 24px; width: 24px; background-image: url(\'//cdn.conveythis.com/images/flags/svg/' . $flag . '.png\'); display: inline-block; background-size: contain; background-position: 50% 50%; background-repeat: no-repeat; background-color: transparent; margin-right: 10px; vertical-align: middle;"></span>'; // v3/square
        }
        if ($this->variables->style_flag === 'cir') {
            $icon = '<span style="height: 24px; width: 24px; background-image: url(\'//cdn.conveythis.com/images/flags/svg/' . $flag . '.png\'); display: inline-block; background-size: contain; background-position: 50% 50%; background-repeat: no-repeat; background-color: transparent; margin-right: 10px; vertical-align: middle;"></span>'; // v3/round
        }
        if ($this->variables->style_flag === 'without-flag') {
            $icon = '';
        }
        return $icon;
    }

    public function _menu_shortcode($menu, $args) {
        return do_shortcode($menu);
    }

    public function add_nav_menu_meta_boxes() {
        $this->print_log("* add_nav_menu_meta_boxes()");
        add_meta_box('conveythis_nav_link', __('ConveyThis', 'conveythis-translate'), array($this, 'nav_menu_links'), 'nav-menus', 'side', 'low');
    }

    public function nav_menu_links() {
        $this->print_log("* nav_menu_links()");
        $languages = array();
        if (!empty($this->variables->language_code)) {
            $current_language_code = $this->variables->language_code;
        } else {
            $current_language_code = $this->variables->source_language;
        }

        $language = $this->searchLanguage($current_language_code);
        if (!empty($language)) {
            $languages[] = array(
                'id' => $language['language_id'],
                'title' => $language['title'],
                'title_en' => $language['title_en'],
            );
        }

        if (!empty($this->variables->language_code)) {
            $language = $this->searchLanguage($this->variables->source_language);
            if (!empty($language)) {
                $languages[] = array(
                    'id' => $language['language_id'],
                    'title' => $language['title'],
                    'title_en' => $language['title_en'],
                );
            }
        }

        foreach ($this->variables->target_languages as $language_code) {
            $language = $this->searchLanguage($language_code);
            if (!empty($language)) {
                if ($current_language_code != $language['code2']) {
                    $languages[] = array(
                        'id' => $language['language_id'],
                        'title' => $language['title'],
                        'title_en' => $language['title_en'],
                    );
                }
            }
        }
        require_once CONVEY_PLUGIN_ROOT_PATH . 'app/templates/posttype-conveythis-languages.php';
    }

    public function row_meta($links, $file) {
        $this->print_log("* row_meta()");
        $plugin = plugin_basename(__FILE__);
        if ($plugin == $file) {
            $links[] = '<a href="https://www.conveythis.com/help-center/support-and-resources/?utm_source=widget&utm_medium=wordpress" target="_blank">' . __('FAQ', 'conveythis-translate') . '</a>';
            $links[] = '<a href="https://wordpress.org/support/plugin/conveythis-translate" target="_blank">' . __('Support', 'conveythis-translate') . '</a>';
            $links[] = '<a href="https://wordpress.org/plugins/conveythis-translate/#reviews" target="_blank">' . __('Rate this plugin', 'conveythis-translate') . '</a>';
        }
        return $links;
    }

    public static function settings_link($links) {
        array_push($links, '<a href="options-general.php?page=convey_this">' . __('Settings', 'conveythis-translate') . '</a>');
        return $links;
    }

    public function admin_menu() {
        add_menu_page(
            'ConveyThis Settings',
            'ConveyThis',
            'manage_options',
            'convey_this',
            array($this, 'pluginOptions'),
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgdmlld0JveD0iMCAwIDEwMCAxMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxnIGNsaXAtcGF0aD0idXJsKCNjbGlwMF8xMDFfODMpIj4KPG1hc2sgaWQ9Im1hc2swXzEwMV84MyIgc3R5bGU9Im1hc2stdHlwZTpsdW1pbmFuY2UiIG1hc2tVbml0cz0idXNlclNwYWNlT25Vc2UiIHg9IjAiIHk9IjEwIiB3aWR0aD0iOTIiIGhlaWdodD0iODAiPgo8cGF0aCBkPSJNMCAxMEg5MS4xNTc4VjkwSDBWMTBaIiBmaWxsPSJ3aGl0ZSIvPgo8L21hc2s+CjxnIG1hc2s9InVybCgjbWFzazBfMTAxXzgzKSI+CjxwYXRoIGZpbGwtcnVsZT0iZXZlbm9kZCIgY2xpcC1ydWxlPSJldmVub2RkIiBkPSJNNjAuMDY4OSA4OS45MDg5QzU2LjA5MzUgODkuOTA4OSA1Mi4yNjEyIDg5LjM3ODUgNDguNjY4NSA4OC4zMjA1QzQ4LjMzMjYgODguMjIzOCA0OC4xNDEzIDg3Ljk4MzQgNDguMDQ2MyA4Ny42OTQ3QzQ3Ljk5ODEgODcuMzU3NyA0OC4wOTMxIDg3LjA2OSA0OC4zMzI2IDg2LjgyODdDNDguNzE2NyA4Ni40OTE3IDQ4Ljk1NjIgODYuMDExIDQ4Ljk1NjIgODUuNDMzNkM0OC45NTYyIDg0LjQyNCA0OC4xNDEzIDgzLjU1NzkgNDcuMDg4MyA4My41NTc5SDI5Ljg5MTNDMjguODg1IDgzLjU1NzkgMjguMDIxOSA4Mi43NDAyIDI4LjAyMTkgODEuNjgwOEMyOC4wMjE5IDgwLjY3MTEgMjguODM2OSA3OS44MDUxIDI5Ljg5MTMgNzkuODA1MUgzNS4zNTJDMzYuMzU4MiA3OS44MDUxIDM3LjIxOTkgNzguOTg3NCAzNy4yMTk5IDc3LjkyOEMzNy4yMTk5IDc2LjkxODMgMzYuNDA1IDc2LjA1MjMgMzUuMzUyIDc2LjA1MjNIMzAuMDM0NEgyOS45ODYzSDIzLjYxNTdDMjIuNjA5NCA3Ni4wNTIzIDIxLjc0NzggNzUuMjM0NiAyMS43NDc4IDc0LjE3NTFDMjEuNzQ3OCA3My4xNjU0IDIyLjU2MTMgNzIuMjk5NCAyMy42MTU3IDcyLjI5OTRIMjguNjQ1NUMyOS42NTE4IDcyLjI5OTQgMzAuNTEzNSA3MS40ODE3IDMwLjUxMzUgNzAuNDIyM0MzMC41MTM1IDY5LjQxMjYgMjkuNjk4NiA2OC41NDY2IDI4LjY0NTUgNjguNTQ2NkgyMC44ODQ3QzE5Ljg3OTggNjguNTQ2NiAxOS4wMTY3IDY3LjcyODkgMTkuMDE2NyA2Ni42NzA5QzE5LjAxNjcgNjUuNjU5OCAxOS44MzE2IDY0Ljc5MzcgMjAuODg0NyA2NC43OTM3SDM1LjMwMzhDMzYuMzEgNjQuNzkzNyAzNy4xNzE3IDYzLjk3NiAzNy4xNzE3IDYyLjkxOEMzNy4xNzE3IDYxLjkwNjkgMzYuMzU4MiA2MS4wNDA5IDM1LjMwMzggNjEuMDQwOUgxNi42MjE2QzE1LjYxNTMgNjEuMDQwOSAxNC43NTM2IDYwLjIyMzIgMTQuNzUzNiA1OS4xNjUyQzE0Ljc1MzYgNTguMTU0MSAxNS41Njg1IDU3LjI4OCAxNi42MjE2IDU3LjI4OEgyMi45NDUzQzIzLjk1MTYgNTcuMjg4IDI0LjgxMzMgNTYuNDcwMyAyNC44MTMzIDU1LjQxMjNDMjQuODEzMyA1NC40MDEyIDIzLjk5ODQgNTMuNTM1MiAyMi45NDUzIDUzLjUzNTJIMTYuMjg3MUMxNS4yODA4IDUzLjUzNTIgMTQuNDE3NyA1Mi43MTc1IDE0LjQxNzcgNTEuNjU5NUMxNC40MTc3IDUwLjY0ODQgMTUuMjMyNiA0OS43ODIzIDE2LjI4NzEgNDkuNzgyM0gyOS4wMjgyQzMwLjAzNDQgNDkuNzgyMyAzMC44OTYxIDQ4Ljk2NDcgMzAuODk2MSA0Ny45MDY2QzMwLjg5NjEgNDYuODk1NSAzMC4wODI2IDQ2LjAyOTUgMjkuMDI4MiA0Ni4wMjk1SDE5Ljc4MzRDMTguNzc3MiA0Ni4wMjk1IDE3LjkxNTUgNDUuMjExOCAxNy45MTU1IDQ0LjE1MzhDMTcuOTE1NSA0My4xNDI3IDE4LjcyOSA0Mi4yNzgxIDE5Ljc4MzQgNDIuMjc4MUgyMy4zMjhDMjQuMzM0MiA0Mi4yNzgxIDI1LjE5NTkgNDEuNDU5IDI1LjE5NTkgNDAuNDAwOUMyNS4xOTU5IDM5LjM5MTMgMjQuMzgyNCAzOC41MjUyIDIzLjMyOCAzOC41MjUySDE2LjA0NzZDMTUuMDQxMyAzOC41MjUyIDE0LjE3ODIgMzcuNzA2MSAxNC4xNzgyIDM2LjY0ODFDMTQuMTc4MiAzNS42Mzg0IDE0Ljk5MzEgMzQuNzcyNCAxNi4wNDc2IDM0Ljc3MjRIMzYuMTE4N0MzNy4xMjM1IDM0Ljc3MjQgMzcuOTg2NyAzMy45NTMzIDM3Ljk4NjcgMzIuODk1M0MzNy45ODY3IDMxLjk4MDkgMzcuMzE0OSAzMS4yMTE1IDM2LjQ1MzIgMzEuMDY3OUgzMC43NTNDMzAuNjU2NiAzMS4wNjc5IDMwLjU2MTcgMzEuMDY3OSAzMC40MTcxIDMxLjAxOTZIMjkuMjE5NUMyOS4xMjQ2IDMxLjAxOTYgMjkuMDI4MiAzMS4wNjc5IDI4Ljg4NSAzMS4wNjc5SDEzLjUwNzhDMTIuNTAxNiAzMS4wNjc5IDExLjYzOTkgMzAuMjQ4OCAxMS42Mzk5IDI5LjE5MDhDMTEuNjM5OSAyOC4xODExIDEyLjQ1NDggMjcuMzE1MSAxMy41MDc4IDI3LjMxNTFIMjguODg1QzI5LjAyODIgMjcuMzE1MSAyOS4xNzI4IDI3LjMxNTEgMjkuMzE1OSAyNy4zNjJIMzAuMjc0QzMwLjQxNzEgMjcuMzE1MSAzMC41NjE3IDI3LjMxNTEgMzAuNzA0OCAyNy4zMTUxSDMxLjYxNDdDMzIuNDI5NiAyNy4xMjE3IDMzLjAwNSAyNi40MDA3IDMzLjAwNSAyNS40ODYzQzMzLjAwNSAyNC41NzE5IDMyLjMzMzIgMjMuODAyNSAzMS40NzE1IDIzLjY1NzVIMjkuMzY0MUMyOC4zNTc4IDIzLjY1NzUgMjcuNDk2MSAyMi44Mzk4IDI3LjQ5NjEgMjEuNzgxOEMyNy40OTYxIDIwLjc3MDcgMjguMzA5NiAxOS45MDQ2IDI5LjM2NDEgMTkuOTA0Nkg0My44NzgyQzQ0Ljg4NDQgMTkuOTA0NiA0NS43NDYxIDE5LjA4NyA0NS43NDYxIDE4LjAyODlDNDUuNzQ2MSAxNy4xMTQ1IDQ1LjA3NTggMTYuMzQ1MiA0NC4yMTQxIDE2LjIwMDJIMzQuOTIxMUMzMy45MTQ5IDE2LjIwMDIgMzMuMDUxOCAxNS4zODI1IDMzLjA1MTggMTQuMzI0NEMzMy4wNTE4IDEzLjMxMzQgMzMuODY2NyAxMi40NDczIDM0LjkyMTEgMTIuNDQ3M0g0Ni41MTI5QzUwLjg3MjMgMTAuODYwMyA1NS42MTQ1IDEwLjA5MDkgNjAuNzM5MiAxMC4wOTA5QzY0LjI4MzggMTAuMDkwOSA2Ny41NDIxIDEwLjM3OTYgNzAuNTExMiAxMC45NTdDNzMuNDMzNiAxMS41MzQzIDc2LjExNjQgMTIuMzUyIDc4LjUxMTYgMTMuMzYxN0M4MC45MDY4IDE0LjM3MjggODMuMTEwNiAxNS42MjI4IDg1LjE2OTkgMTcuMDY2MkM4Ni45ODk2IDE4LjM2NiA4OC42NjYyIDE5Ljc2MSA5MC4yNDc5IDIxLjMwMTFDOTAuNTgyNCAyMS42MzY3IDkwLjU4MjQgMjIuMTY3MiA5MC4yOTQ3IDIyLjUwMjhMODAuNzE1NCAzMy41NjkzQzgwLjU3MDkgMzMuNzYxMyA4MC4zNzk1IDMzLjg1OCA4MC4xNCAzMy44NThDNzkuOTAwNSAzMy44NTggNzkuNzA5MiAzMy44MDk3IDc5LjUxNzkgMzMuNjY2Qzc2LjczODYgMzEuMjU5OSA3My45MTI2IDI5LjMzNTggNzEuMDg2NiAyNy44OTI0QzY4LjAyMTEgMjYuMzUyMyA2NC41MjMzIDI1LjU4MyA2MC42OTI1IDI1LjU4M0M1Ny40ODI0IDI1LjU4MyA1NC41MTE4IDI2LjIwNzMgNTEuODMwNCAyNy40NTg3QzQ5LjA5OTMgMjguNzEwMSA0Ni43NTI0IDMwLjM5MzggNDQuNzg4MSAzMi42MDY2QzQyLjgyMzcgMzQuODE5MyA0MS4yOTE3IDM3LjMyMjIgNDAuMTg5MSA0MC4yMDlDMzkuMDg3OSA0My4wOTU4IDM4LjU2MDYgNDYuMTc0NiAzOC41NjA2IDQ5LjQ0NjdWNDkuNjg3MUMzOC41NjA2IDUyLjk1NzggMzkuMDg3OSA1Ni4wODUgNDAuMTg5MSA1OC45NzE4QzQxLjI5MTcgNjEuOTA2OSA0Mi43NzcgNjQuNDU2NyA0NC42OTMxIDY2LjYyMjVDNDYuNjA5MiA2OC44MzUyIDQ4Ljk1NjIgNzAuNTY3MyA1MS42ODU4IDcxLjgxODdDNTQuNDE2OSA3My4xMTcxIDU3LjM4NiA3My43NDI4IDYwLjY5MjUgNzMuNzQyOEM2NS4wNTA1IDczLjc0MjggNjguNzM5NiA3Mi45MjUxIDcxLjc1NyA3MS4zMzY3Qzc0LjU4MyA2OS44NDQ5IDc3LjM2MjIgNjcuODI1NiA4MC4xNCA2NS4zMjI3QzgwLjQ3NTkgNjQuOTg1NyA4MS4wMDE3IDY1LjAzNDEgODEuMzM3NiA2NS4zNzExTDkwLjgyMTkgNzQuOTk0MkM5MS4xNTc4IDc1LjMyOTggOTEuMTU3OCA3NS44NjAzIDkwLjgyMTkgNzYuMTk1OUM4OS4wNTAzIDc4LjA3MyA4Ny4xODI0IDc5Ljc1NjcgODUuMzEzIDgxLjI0ODVDODMuMjA1NiA4Mi45MzIyIDgwLjkwNjggODQuMzc1NiA3OC40MTUyIDg1LjU3ODdDNzUuOTI1MSA4Ni43ODA0IDczLjE0NTkgODcuNjk0NyA3MC4xNzY3IDg4LjMyMDVDNjcuMTExMiA4OS42MjAyIDYzLjc1OCA4OS45MDg5IDYwLjA2ODkgODkuOTA4OVpNMy4zNTMyMiA0Ni40MTQ5SDQuMDIzNThDNS4wMjk4MyA0Ni40MTQ5IDUuODkxNTIgNDcuMjMyNiA1Ljg5MTUyIDQ4LjI5MkM1Ljg5MTUyIDQ5LjMwMTcgNS4wNzgwMiA1MC4xNjc3IDQuMDIzNTggNTAuMTY3N0gzLjM1MzIyQzIuMzQ2OTcgNTAuMTY3NyAxLjQ4NTI4IDQ5LjM1IDEuNDg1MjggNDguMjkyQzEuNTMyMDUgNDcuMjMyNiAyLjM0Njk3IDQ2LjQxNDkgMy4zNTMyMiA0Ni40MTQ5Wk05LjI0NDc1IDQ2LjQxNDlIMTEuOTc1OEMxMi45ODA2IDQ2LjQxNDkgMTMuODQzNyA0Ny4yMzI2IDEzLjg0MzcgNDguMjkyQzEzLjg0MzcgNDkuMzAxNyAxMy4wMjg4IDUwLjE2NzcgMTEuOTc1OCA1MC4xNjc3SDkuMjQ0NzVDOC4yMzg1IDUwLjE2NzcgNy4zNzY4MSA0OS4zNSA3LjM3NjgxIDQ4LjI5MkM3LjM3NjgxIDQ3LjIzMjYgOC4xOTE3MyA0Ni40MTQ5IDkuMjQ0NzUgNDYuNDE0OVpNOS43NzE5NiAyMC40MzUxSDEyLjE2NzFDMTMuMTczNCAyMC40MzUxIDE0LjAzNTEgMjEuMjUyOCAxNC4wMzUxIDIyLjMxMDhDMTQuMDM1MSAyMy4zMjE5IDEzLjIyMDEgMjQuMTg3OSAxMi4xNjcxIDI0LjE4NzlIOS43NzE5NkM4Ljc2NTcxIDI0LjE4NzkgNy45MDQwMiAyMy4zNjg4IDcuOTA0MDIgMjIuMzEwOEM3LjkwNDAyIDIxLjMwMTEgOC43NjU3MSAyMC40MzUxIDkuNzcxOTYgMjAuNDM1MVpNMTcuMTQ4OCAyMC40MzUxSDI0LjE0MjlDMjUuMTQ3OCAyMC40MzUxIDI2LjAxMDkgMjEuMjUyOCAyNi4wMTA5IDIyLjMxMDhDMjYuMDEwOSAyMy4zMjE5IDI1LjE5NTkgMjQuMTg3OSAyNC4xNDI5IDI0LjE4NzlIMTcuMTQ4OEMxNi4xNDI1IDI0LjE4NzkgMTUuMjgwOCAyMy4zNjg4IDE1LjI4MDggMjIuMzEwOEMxNS4yODA4IDIxLjMwMTEgMTYuMDk0MyAyMC40MzUxIDE3LjE0ODggMjAuNDM1MVpNMjAuODM3OSA3OS45MDA0SDIyLjk0NTNDMjMuOTUxNiA3OS45MDA0IDI0LjgxMzMgODAuNzE5NSAyNC44MTMzIDgxLjc3NzVDMjQuODEzMyA4Mi43ODcyIDIzLjk5ODQgODMuNjUzMiAyMi45NDUzIDgzLjY1MzJIMjAuODM3OUMxOS44MzE2IDgzLjY1MzIgMTguOTY4NSA4Mi44MzU1IDE4Ljk2ODUgODEuNzc3NUMxOC45Njg1IDgwLjcxOTUgMTkuODMxNiA3OS45MDA0IDIwLjgzNzkgNzkuOTAwNFpNNi4xMzEwNCA1Ny41NzY3SDEwLjY4MThDMTEuNjg4MSA1Ny41NzY3IDEyLjU0OTggNTguMzk0NCAxMi41NDk4IDU5LjQ1MzhDMTIuNTQ5OCA2MC40NjM1IDExLjczNjMgNjEuMzI5NiAxMC42ODE4IDYxLjMyOTZINi4xMzEwNEM1LjEyNDc5IDYxLjMyOTYgNC4yNjMxIDYwLjUxMTkgNC4yNjMxIDU5LjQ1MzhDNC4yNjMxIDU4LjM5NDQgNS4xMjQ3OSA1Ny41NzY3IDYuMTMxMDQgNTcuNTc2N1pNMjcuNTQyOSAxMy4wMjQ3SDMwLjI3NEMzMS4yODAyIDEzLjAyNDcgMzIuMTQxOSAxMy44NDM4IDMyLjE0MTkgMTQuOTAxOEMzMi4xNDE5IDE1LjkxMTUgMzEuMzI4NCAxNi43Nzc1IDMwLjI3NCAxNi43Nzc1SDI3LjU0MjlDMjYuNTM4MSAxNi43Nzc1IDI1LjY3NSAxNS45NTk4IDI1LjY3NSAxNC45MDE4QzI1LjY3NSAxMy44OTA3IDI2LjUzODEgMTMuMDI0NyAyNy41NDI5IDEzLjAyNDdaTTEuODY3OTQgMzUuMjA0N0g5LjA1MzQyQzEwLjA1OTcgMzUuMjA0NyAxMC45MjE0IDM2LjAyMjQgMTAuOTIxNCAzNy4wODE4QzEwLjkyMTQgMzguMDkxNSAxMC4xMDc5IDM4Ljk1NzUgOS4wNTM0MiAzOC45NTc1SDEuODY3OTRDMC44MTM1MDQgMzguOTU3NSAwIDM4LjA5MTUgMCAzNy4wODE4QzAgMzYuMDcwNyAwLjgxMzUwNCAzNS4yMDQ3IDEuODY3OTQgMzUuMjA0N1pNNy40NzMxOCA2NC45ODU3SDkuODY4MzRDMTAuODczMiA2NC45ODU3IDExLjczNjMgNjUuODA0OCAxMS43MzYzIDY2Ljg2MjhDMTEuNzM2MyA2Ny44NzI1IDEwLjkyMTQgNjguNzM4NSA5Ljg2ODM0IDY4LjczODVINy40NzMxOEM2LjQ2NjkzIDY4LjczODUgNS42MDM4MiA2Ny45MjA5IDUuNjAzODIgNjYuODYyOEM1LjYwMzgyIDY1Ljg1MTcgNi40MTg3NCA2NC45ODU3IDcuNDczMTggNjQuOTg1N1oiIGZpbGw9IiNBQUFBQUEiLz4KPC9nPgo8bWFzayBpZD0ibWFzazFfMTAxXzgzIiBzdHlsZT0ibWFzay10eXBlOmx1bWluYW5jZSIgbWFza1VuaXRzPSJ1c2VyU3BhY2VPblVzZSIgeD0iNTciIHk9IjM3IiB3aWR0aD0iNDQiIGhlaWdodD0iMjYiPgo8cGF0aCBkPSJNNTcuMTg3NyAzNy40NzAxSDEwMFY2Mi41ODgxSDU3LjE4NzdWMzcuNDcwMVoiIGZpbGw9IndoaXRlIi8+CjwvbWFzaz4KPGcgbWFzaz0idXJsKCNtYXNrMV8xMDFfODMpIj4KPHBhdGggZD0iTTk5LjYzNDQgNDguOTk4N0M5OS4yODI5IDQ4LjM0MTcgOTguNTc3MSA0OC4wNDAyIDk3Ljk3NDggNDcuNjc0N0M5MC43NTk2IDQzLjQxNDIgODIuNDU1OSA0MS4wNjc4IDc0LjA5ODMgNDAuOTI0MkM3Mi43MjA3IDM4Ljg1NjUgNzAuMzExNCAzNy41Mjk3IDY3LjgzODMgMzcuNTIyNkM2NC43MTMzIDM3LjUyMTEgNjEuNTg2OCAzNy40ODg0IDU4LjQ2MDMgMzcuNTA2OUM1Ny41MDIzIDM3LjUzOTYgNTcuMDQ0NSAzOC45Mjc2IDU3LjgwMTMgMzkuNTMwNUM1OS42NTY1IDQwLjk0MjYgNjEuNTU4NCA0Mi4yOTA4IDYzLjQyNSA0My42ODcyQzY0LjcwNDggNDQuNjg1NSA2NS40ODk5IDQ2LjI5NjcgNjUuNDg1NyA0Ny45Mjc5QzY1LjQ5NDIgNDkuMzU0MiA2NS40OTEzIDUwLjc3OTEgNjUuNDk3IDUyLjIwNTRDNjUuNDkxMyA1My44MDI0IDY0LjczNDUgNTUuMzgyNCA2My40NzYgNTYuMzU3OUM2MS42MDgxIDU3Ljc1NzIgNTkuNzAwNCA1OS4xMDI1IDU3Ljg0MSA2MC41MTE4QzU3LjA2NzIgNjEuMTQ3NCA1Ny42MDU3IDYyLjU3MjMgNTguNjAyMSA2Mi41Mjk3QzYxLjY4MDMgNjIuNTQ5NiA2NC43NiA2Mi41NDUzIDY3LjgzODMgNjIuNTU4MUM3MC4zMTE0IDYyLjU2OTUgNzIuNzM0OSA2MS4yNzQgNzQuMTI5NSA1OS4yMTM0QzgyLjgzOTkgNTkuMDYyNyA5MS41MjIgNTYuNjAxMSA5OC45NTcgNTEuOTk2NEM5OS45MjM1IDUxLjM4MDYgMTAwLjIyMyA0OS45NzcxIDk5LjYzNDQgNDguOTk4N1oiIGZpbGw9IiNBQUFBQUEiLz4KPC9nPgo8cGF0aCBkPSJNNjAuNTE4MyA1MS4yMTcyQzYxLjAzNDIgNTEuMjY0MiA2MS41NTQzIDUwLjk0NTYgNjEuNzI0NCA1MC40NDc5QzYxLjk1ODIgNDkuODUyIDYxLjYxMjQgNDkuMTE4MyA2MS4wMDU5IDQ4LjkyMzRDNTkuMTAyNSA0OC43ODQxIDU3LjE3NjQgNDguODg5MyA1NS4yNjMxIDQ4Ljg0ODFDNTQuNjA4NCA0OC44ODUgNTMuNzk5MSA0OC42NjMyIDUzLjMxNDQgNDkuMjUwNUM1Mi42NzUyIDQ5LjkxODkgNTMuMTg4MyA1MS4xODQ1IDU0LjExMDkgNTEuMTg4OEM1Ni4yNDY3IDUxLjIxNTggNTguMzgxMSA1MS4yMDMgNjAuNTE4MyA1MS4yMTcyWiIgZmlsbD0iI0FBQUFBQSIvPgo8cGF0aCBkPSJNNjAuOTE1IDQ1LjA2NjhDNTcuNDEwMiA0NC45ODQzIDUzLjg5OTYgNDUuMDQyNiA1MC4zOTE5IDQ1LjAwNzFDNDkuODY0NyA0NC45NTczIDQ5LjMwMDYgNDUuMjI2MSA0OS4xMTUgNDUuNzQ5NEM0OC43OTc1IDQ2LjQ1MTkgNDkuMzcyOSA0Ny4zNTIxIDUwLjEzODIgNDcuMzQ3OEM1My42MDA2IDQ3LjM3MiA1Ny4wNjI5IDQ3LjM2NzcgNjAuNTI2NyA0Ny4zODM0QzYxLjA0NCA0Ny40MjYgNjEuNTcxMiA0Ny4xMTMyIDYxLjc0MTMgNDYuNjA4M0M2MS45ODM2IDQ1Ljk4MTIgNjEuNTcyNiA0NS4yMDE5IDYwLjkxNSA0NS4wNjY4WiIgZmlsbD0iI0FBQUFBQSIvPgo8cGF0aCBkPSJNNjAuOTE1IDUyLjc4NDRDNTcuNDEwMiA1Mi43MDE5IDUzLjg5OTYgNTIuNzYwMyA1MC4zOTE5IDUyLjcyNDdDNDkuODY0NyA1Mi42NzQ5IDQ5LjMwMDYgNTIuOTQyMyA0OS4xMTUgNTMuNDY3QzQ4Ljc5NzUgNTQuMTY4MSA0OS4zNzI5IDU1LjA2ODMgNTAuMTM4MiA1NS4wNjU0QzUzLjYwMDYgNTUuMDg5NiA1Ny4wNjI5IDU1LjA4MzkgNjAuNTI2NyA1NS4xMDFDNjEuMDQ0IDU1LjE0MjIgNjEuNTcxMiA1NC44MzA4IDYxLjc0MTMgNTQuMzI0NUM2MS45ODM2IDUzLjY5NzQgNjEuNTcyNiA1Mi45MTk1IDYwLjkxNSA1Mi43ODQ0WiIgZmlsbD0iI0FBQUFBQSIvPgo8L2c+CjxkZWZzPgo8Y2xpcFBhdGggaWQ9ImNsaXAwXzEwMV84MyI+CjxyZWN0IHdpZHRoPSIxMDAiIGhlaWdodD0iMTAwIiBmaWxsPSJ3aGl0ZSIvPgo8L2NsaXBQYXRoPgo8L2RlZnM+Cjwvc3ZnPgo='
        );
    }

    public function admin_notices() {
        if (!extension_loaded('xml')) {
            ?>
            <div class="error settings-error notice is-dismissible">
                <p>
                    <?php echo esc_html(__('Plugin requires installing php-xml extension.', 'conveythis-translate')); ?>
                </p>
            </div>
            <?php
        }
    }

    public function admin_init() {
        // SEO Translation Quality options
        register_setting('my-plugin-settings', 'conveythis_seo_brand');
        register_setting('my-plugin-settings', 'conveythis_seo_glossary');
        register_setting('my-plugin-settings', 'conveythis_seo_enforce_length');
        register_setting('my-plugin-settings', 'conveythis_seo_jsonld_validation');
        register_setting('my-plugin-settings-group', 'conveythis_seo_brand');
        register_setting('my-plugin-settings-group', 'conveythis_seo_glossary');
        register_setting('my-plugin-settings-group', 'conveythis_seo_enforce_length');
        register_setting('my-plugin-settings-group', 'conveythis_seo_jsonld_validation');

        register_setting('my-plugin-settings', 'api_key', array($this, 'check_api_key'));
        register_setting('my-plugin-settings', 'source_language');
        register_setting('my-plugin-settings', 'target_languages', array($this, 'check_target_languages'));
        register_setting('my-plugin-settings-group', 'api_key', array($this, 'check_api_key'));
        register_setting('my-plugin-settings-group', 'source_language');
        register_setting('my-plugin-settings-group', 'target_languages', array($this, 'check_target_languages'));
        register_setting('my-plugin-settings-group', 'target_languages_translations');
        register_setting('my-plugin-settings-group', 'default_language');
        register_setting('my-plugin-settings-group', 'style_change_language', array($this, 'check_style_change_language'));
        register_setting('my-plugin-settings-group', 'style_change_flag', array($this, 'check_style_change_flag'));
        register_setting('my-plugin-settings-group', 'style_flag');
        register_setting('my-plugin-settings-group', 'style_text');
        register_setting('my-plugin-settings-group', 'style_position_vertical');
        register_setting('my-plugin-settings-group', 'style_position_horizontal');
        register_setting('my-plugin-settings-group', 'style_indenting_vertical');
        register_setting('my-plugin-settings-group', 'style_indenting_horizontal');
        register_setting('my-plugin-settings-group', 'auto_translate');
        register_setting('my-plugin-settings-group', 'hide_conveythis_logo');
        register_setting('my-plugin-settings-group', 'dynamic_translation');
        register_setting('my-plugin-settings-group', 'translate_media');
        register_setting('my-plugin-settings-group', 'translate_document');
        register_setting('my-plugin-settings-group', 'translate_links');
        register_setting('my-plugin-settings-group', 'translate_structured_data');
        register_setting('my-plugin-settings-group', 'change_direction');
        register_setting('my-plugin-settings-group', 'conveythis_clear_cache');
        register_setting('my-plugin-settings-group', 'conveythis_select_region');
        register_setting('my-plugin-settings-group', 'is_active_domain');
        register_setting('my-plugin-settings-group', 'alternate');
        register_setting('my-plugin-settings-group', 'accept_language');
        register_setting('my-plugin-settings-group', 'blockpages', array($this, 'check_blockpages'));
        register_setting('my-plugin-settings-group', 'show_javascript');
        register_setting('my-plugin-settings-group', 'style_position_type');
        register_setting('my-plugin-settings-group', 'style_position_vertical_custom');
        register_setting('my-plugin-settings-group', 'style_selector_id');
        register_setting('my-plugin-settings-group', 'url_structure');
        register_setting('my-plugin-settings-group', 'style_background_color');
        register_setting('my-plugin-settings-group', 'style_hover_color');
        register_setting('my-plugin-settings-group', 'style_border_color');
        register_setting('my-plugin-settings-group', 'style_text_color');
        register_setting('my-plugin-settings-group', 'style_corner_type');
        register_setting('my-plugin-settings-group', 'custom_css_json');
        register_setting('my-plugin-settings-group', 'style_widget');
        register_setting('my-plugin-settings-group', 'conveythis_system_links');
        register_setting('my-plugin-settings-group', 'use_trailing_slash');

        if (!empty($_REQUEST['page']) && $_REQUEST['page'] == 'convey_this') //phpcs:ignore
        {
            if (!empty($this->variables->api_key)) {
                if (($key = array_search($this->variables->source_language, $this->variables->target_languages)) !== false) { //remove source_language from target_languages
                    unset($this->variables->target_languages[$key]);
                }

                $data = $this->send('PUT', '/website/update/', array(
                        'referrer' => '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                        'source_language' => $this->variables->source_language ?: 'en',
                        'target_languages' => $this->variables->target_languages ?: ['en'],
                        'accept_language' => $this->variables->accept_language,
                        'blockpages' => $this->variables->blockpages_items,
                        'technology' => 'wordpress')
                );

                if (!empty($data) && $data['domains_count'] > 0 && empty($this->variables->is_active)) {
                    $this->variables->is_active = [];
                }

                //$this->variables->exclusions = $this->send(  'GET', '/admin/account/domain/pages/excluded/?referrer='. urlencode($_SERVER['HTTP_HOST']) );
                $this->variables->glossary = $this->send('GET', '/admin/account/domain/pages/glossary/?referrer=' . urlencode($this->normalizeReferrerForApi(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '')));
                $this->variables->exclusion_blocks = $this->send('GET', '/admin/account/domain/excluded/blocks/?referrer=' . urlencode($_SERVER['HTTP_HOST']));

                if (isset($_GET["settings-updated"])) //phpcs:ignore
                {
                    $this->updateDataPlugin();
                    $this->clearCacheButton();
                }

                $this->ConveyThisCache->clear_cached_translations(true);
            }
        }
    }

    public function updateDataPlugin() {
        // Security: Check user capabilities before performing privileged operations
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $this->print_log("* updateDataPlugin()");
        if (($key = array_search($this->variables->source_language, $this->variables->target_languages)) !== false) { //remove source_language from target_languages
            unset($this->variables->target_languages[$key]);
        }

        $this->send('PUT', '/plugin/settings/', array(
            'referrer' => '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'accept_language' => $this->variables->accept_language,
            'blockpages' => $this->variables->blockpages_items,
            'technology' => 'wordpress',
            'settings' => $this->buildPluginSettingsPayload(),
        ));
    }

    /**
     * Spec 6: build the `settings` blob PUT to /plugin/settings/. Extracted
     * from updateDataPlugin() so it can be tested directly and so the
     * serialization stays in one place as new fields get added.
     *
     * @return array
     */
    public function buildPluginSettingsPayload() {
        $payload = array(
            'source_language' => $this->variables->source_language ?: 'en',
            'target_languages' => $this->variables->target_languages ?: ['en'],
            'style_change_language' => $this->variables->style_change_language,
            'style_change_flag' => $this->variables->style_change_flag,
            'default_language' => $this->variables->default_language,
            'style_flag' => $this->variables->style_flag,
            'style_text' => $this->variables->style_text,
            'style_position_vertical' => $this->variables->style_position_vertical,
            'style_position_horizontal' => $this->variables->style_position_horizontal,
            'style_indenting_vertical' => $this->variables->style_indenting_vertical,
            'style_indenting_horizontal' => $this->variables->style_indenting_horizontal,
            'auto_translate' => $this->variables->auto_translate,
            'hide_conveythis_logo' => $this->variables->hide_conveythis_logo,
            'dynamic_translation' => $this->variables->dynamic_translation,
            'translate_media' => $this->variables->translate_media,
            'translate_document' => $this->variables->translate_document,
            'translate_links' => $this->variables->translate_links,
            'translate_structured_data' => $this->variables->translate_structured_data,
            'change_direction' => $this->variables->change_direction,
            'style_position_type' => $this->variables->style_position_type,
            'style_position_vertical_custom' => $this->variables->style_position_vertical_custom,
            'style_selector_id' => $this->variables->style_selector_id,
            'url_structure' => $this->variables->url_structure,
            'style_background_color' => $this->variables->style_background_color,
            'style_hover_color' => $this->variables->style_hover_color,
            'style_border_color' => $this->variables->style_border_color,
            'style_text_color' => $this->variables->style_text_color,
            'style_corner_type' => $this->variables->style_corner_type,
            'custom_css_json' => $this->variables->custom_css_json,
            'style_widget' => $this->variables->style_widget,
            'select_region' => $this->variables->select_region,
            'use_trailing_slash' => $this->variables->use_trailing_slash,
            'trailing_slash_auto_source' => $this->variables->trailing_slash_auto_source,
            'link_rules' => $this->variables->link_rules,
            'link_rules_post_types' => $this->variables->link_rules_post_types,
            'link_rules_scope' => $this->variables->link_rules_scope,

            // Spec 6 additions — previously stored only in wp_options, now
            // synced so a reinstall can pull them back.
            'seo_brand' => $this->variables->seo_brand ?? '',
            'seo_glossary' => $this->variables->seo_glossary ?? [],
            // AI-assisted translation path is not yet shipped; always send 0
            // so the API has the key for forward-compat but never treats the
            // feature as enabled until the implementation lands.
            'seo_use_ai' => 0,
        );

        // trailing_slash_map can be large (50k+ URLs). Only include when the
        // user has actually picked Custom mode, to keep the common payload
        // small. Older API/App builds ignore unknown keys, so this is safe.
        if ((string)($this->variables->use_trailing_slash ?? '0') === '2') {
            $map = $this->variables->trailing_slash_map ?? [];
            if (is_string($map)) { $map = json_decode($map, true) ?: []; }
            $payload['trailing_slash_map'] = $map;
        }

        return $payload;
    }

    function clearCacheButton() {
        // Security: Check user capabilities before performing privileged operations
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $this->print_log("* clearCacheButton()");
        $this->send('DELETE', '/plugin/clean-button-cache/', array(
                'referrer' => '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                'api_key' => $this->variables->api_key
            )
        );
    }

    /**
     * Ask the API to purge this domain's rows from tbl_cache and invalidate the
     * matching CDN proxy cache. Invoked by the "Clear translation cache" button
     * so admins can recover from stale shared cache after editing translations.
     *
     * @return array API response payload (see Controller\Plugin::clearTranslateCache).
     */
    function clearTranslateCacheOnApi() {
        // Security: only admins can trigger a server-side cache purge.
        if (!current_user_can('manage_options')) {
            return array();
        }

        $this->print_log("* clearTranslateCacheOnApi()");

        $target_languages = array();
        if (is_array($this->variables->target_languages)) {
            $target_languages = array_values($this->variables->target_languages);
        }

        $response = $this->send('DELETE', '/plugin/clean-translate-cache/', array(
            'referrer'         => '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            'api_key'          => $this->variables->api_key,
            'target_languages' => $target_languages,
        ));

        return is_array($response) ? $response : array();
    }

    function reqOnGetSettingsUser() {
        $this->print_log("* reqOnGetSettingsUser()");
        $api_key = $this->variables->api_key;
        $domain_name = $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : '';

        if (!$api_key) {
            return array();
        }

        $req_method = "GET";
        $request_uri = '/plugin/settings/' . $api_key . '/' . $domain_name . '/';
        $headers = [
            'X-Api-Key' => $this->variables->api_key
        ];

        if (strpos($request_uri, '/admin/') === 0) {
            $headers['X-Auth-Token'] = API_AUTH_TOKEN;
        }

        $response = $this->httpRequest($request_uri, [
            'headers' => $headers,
            'body' => null,
            'method' => $req_method,
            'redirection' => '10',
            'httpversion' => '1.1',
            'blocking' => true,
            'cookies' => []
        ], false);

        $body = $response['body'];
        $data = json_decode($body, true);

        return (!empty($data['data']) ? $data['data'] : array());
    }

    public function writeDataInBD($from_js = false) {
        $this->print_log("* writeDataInBD()");
        $data = $this->reqOnGetSettingsUser();
        foreach ($data as $option_name => $new_value) {
            $current_value = get_option($option_name, 'option_does_not_exist');
            if ($current_value === 'option_does_not_exist') {
                continue;
            }
            if ($current_value !== $new_value) {
                update_option($option_name, $new_value);
            }
        }
        if ($from_js) {
            return $data;
        }
    }

    public function getSettingsOnStart($api_key, $from_js) {
        $this->print_log("* getSettingsOnStart()");
        $this->variables->api_key = $api_key;
        $data = $this->writeDataInBD($from_js);
        if (!empty($data)) {
            $res = array('source_language' => $data['source_language'], 'target_language' => $data['target_languages'][0]);
            return $res;
        }
    }

    public function dataCheckAPI() {
        $this->print_log("* dataCheckAPI()");
        $this->writeDataInBD();
    }

    public function check_style_change_language($value) {
        $this->print_log("* check_style_change_language()");
        if (!is_array($value)) {
            return array();
        }
        return $value;
    }

    public function check_style_change_flag($value) {
        $this->print_log("* check_style_change_flag()");
        if (!is_array($value)) {
            return array();
        }
        return $value;
    }

    public function check_blockpages($value) {
        $this->print_log("* check_blockpages()");
        if (!is_array($value)) {
            return array();
        }
        return $value;
    }

    public function check_api_key($value) {
        $this->print_log("* check_api_key()");
        $pattern = '/^(pub_)?[a-zA-Z0-9]{32}$/';
        if (preg_match($pattern, $value)) {
            return $value;
        } else {
            $message = 'The API key you supplied is incorrect. Please try again.';
            add_settings_error('conveythis-translate', '501', $message, 'error');
            return '';
        }
    }

    public function check_target_languages($value) {
        $this->print_log("* check_target_languages()");
        if (!empty($value)) {
            $target_languages = array();
            if (is_string($value)) {
                $language_codes = explode(',', $value);
            } elseif (is_array($value)) {
                $language_codes = $value;
            }
            foreach ($language_codes as $language_code) {
                $language = $this->searchLanguage($language_code);

                if (!empty($language)) {
                    $target_languages[] = $language['code2'];
                }
            }
            return $target_languages;
        } else {
            return array();
        }
    }

    public function searchLanguage($value) {
        $this->print_log("* searchLanguage()");
        foreach ($this->variables->languages as $language) {
            if ($value === $language['code2'] || $value === $language['title_en']) {
                return $language;
            }
        }
    }

    public function getAccountByApiKey($apiKey) {
        $this->print_log("* getAccountByApiKey()");
        $response = $this->send('GET', '/admin/account/api-key/' . $apiKey . '/');
        return $response;
    }

    public function getDomainDetails($accountId, $domainName) {
        $this->print_log("* getDomainDetails()");
        $response = $this->send('GET', '/admin/account/' . $accountId . '/wordpress_domain/' . base64_encode($domainName) . '/');
        return $response;
    }

    function getPageUrl($str) {
        $this->print_log("* getPageUrl()");
        $n = 0;
        $length = strlen($str);
        $buffer = '';
        $step = 0;

        while ($n < $length) {
            if ($str[$n] === '/') {
                if ($step === 1) {
                    $step = 2;
                }

                if ($step === 0) {
                    $buffer = '/';
                    $step = 1;
                }
            } else {
                if ($step === 2) {
                    $buffer = '';
                    $step = 0;
                }
                if ($step === 1) {
                    $step = 3;
                }
            }

            if ($str[$n] === '?' || $str[$n] === '#') {
                break;
            }
            if ($step === 3) {
                $buffer .= $str[$n];
            }

            $n++;
        }

        $buffer = trim($buffer);
        $buffer = rtrim($buffer, '/');

        if (empty($buffer)) {
            $buffer = '/';
        }
        $result = rtrim($buffer, '/') . '/';
        // $this->print_log($result);
        return $result;
    }

    function getPageHost($url) {
        $this->print_log("* getPageHost()");
        $urlData = parse_url($url);
        $host = isset($urlData['host']) ? trim(preg_replace('/^www\./', '', $urlData['host'])) : null;
        return $host;
    }

    function init() {
        $this->print_log("* init()");
        // Snapshot the original REQUEST_URI before the plugin's own rewrite strips
        // the /lang/ prefix (see line ~2297). maybe_redirect_translated_slash_canonical()
        // reads this to compare the browser's request against applyTrailingSlash()'s
        // canonical shape, so the 301 redirect fires on the shape the user actually
        // typed — not the rewritten internal path.
        $this->original_request_uri_for_slash_canonical = isset($_SERVER['REQUEST_URI'])
            ? wp_unslash($_SERVER['REQUEST_URI'])
            : null;
        if (strpos($_SERVER["REQUEST_URI"], '/wp-json/') !== false) {
            return;
        }
        if (strpos($_SERVER["REQUEST_URI"], '/conveythis-404/') !== false) {
            get_template_part(404);
            return;
        }
        $this->variables->site_url = home_url();
        $this->variables->site_host = $this->getPageHost($this->variables->site_url);
        $this->variables->site_prefix = $this->getPageUrl($this->variables->site_url);
        $this->variables->referrer = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
//        $this->choose_seo();

        $isExcluded = $this->isPageExcluded($this->getPageUrl($this->variables->referrer), $this->variables->exclusions);

        if (!is_admin() && !$isExcluded) {
            $this->print_log("@@@ url_structure:" . $this->variables->url_structure . " @@@ :");
            if (empty($this->variables->url_structure) || $this->variables->url_structure != "subdomain") { // not subdomains

                $this->print_log("@@@ structure: NOT subdomain @@@ : ");
                if ($this->variables->auto_translate && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                    if (class_exists('Locale')) {
                        $browserLanguage = locale_accept_from_http($_SERVER['HTTP_ACCEPT_LANGUAGE']);
                        $browserLanguage = substr($browserLanguage, 0, 2);
                    } else {
                        $browserLanguage = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
                    }

                    if (in_array($browserLanguage, $this->variables->target_languages)) {
                        session_start();
                        if (empty($_SESSION['conveythis-autoredirected'])) {
                            $_SESSION['conveythis-autoredirected'] = true;
                            $preventAutoRedirect = false;
                            foreach ($this->variables->target_languages as $key => $language) {    //check if already contains translate language prefix

                                if (strpos($_SERVER["REQUEST_URI"], '/' . $language . '/') !== false
                                    && strpos($_SERVER["REQUEST_URI"], '/' . $language . '/') === 0) {
                                    $preventAutoRedirect = true;
                                }
                            }

                            if (!$preventAutoRedirect) {
                                $location = $this->getLocation($this->variables->site_prefix, $browserLanguage);
                                header("Location: " . $location);
                                die();
                            }
                        }
                    }
                }

                if (!empty($this->variables->target_languages)) {
                    $tempRequestUri = $_SERVER["REQUEST_URI"];
                    $tempRequestUri = parse_url($tempRequestUri, PHP_URL_PATH);
                    if (substr($tempRequestUri, -1) != "/") {
                        $tempRequestUri .= "/";
                    }

                    preg_match('/^(' . str_replace('/', '\/', $this->variables->site_prefix) . '([^\/]+)\/)(.*)/', $tempRequestUri, $matches);

                    /*
                                        if (!empty($matches)) {
                                            $this->variables->language_code = array_search(urldecode(trim($matches[2])), $this->variables->target_languages_translations);
                                        }
                    */

                    if (!empty($matches)) {
                        $haystack = $this->variables->target_languages_translations;

                        if (!is_array($haystack)) {
                            $logFile = __DIR__ . '/language_code_error.log';
                            $message = "[" . date('Y-m-d H:i:s') . "] target_languages_translations is not an array. Value: " . print_r($haystack, true) . "\n";
                            file_put_contents($logFile, $message, FILE_APPEND);
                        } else {
                            $this->variables->language_code = array_search(
                                urldecode(trim($matches[2])),
                                $haystack
                            );
                        }
                    }

                    if (!$this->variables->language_code) {
                        preg_match('/^(' . str_replace('/', '\/', $this->variables->site_prefix) . '(' . implode('|', $this->variables->target_languages) . ')\/)(.*)/', $tempRequestUri, $matches);
                        if (!empty($matches)) {
                            $this->variables->language_code = esc_attr($matches[2]);
                        }
                    }
                    if (!in_array($this->variables->default_language, $this->variables->target_languages)) {
                        $this->variables->default_language = '';
                    }
                    if (!$this->variables->language_code && strpos($_SERVER['REQUEST_URI'], 'wp-login') === false && strpos($_SERVER['REQUEST_URI'], 'wp-admin') === false) {
                        if (!isset($_SERVER['HTTP_REFERER']) || !$_SERVER['HTTP_REFERER'] || $this->variables->site_host != $this->getPageHost($_SERVER['HTTP_REFERER'])) {
                            $this->variables->language_code = isset($this->variables->target_languages_translations[$this->variables->default_language]) ? $this->variables->target_languages_translations[$this->variables->default_language] : $this->variables->default_language;
                        }
                        if ($this->variables->language_code) {
                            $translated_slug = $this->find_translation($_SERVER['REQUEST_URI'], $this->variables->source_language, $this->variables->default_language, '//' . $_SERVER['HTTP_HOST']);

                            if ($translated_slug) {
                                $_SERVER['REQUEST_URI'] = $translated_slug;
                            }
                            header('Location: /' . $this->variables->language_code . $_SERVER["REQUEST_URI"], true, 302);
                            exit();
                        }
                    }

                    if ($this->variables->language_code) {
                        // Do not use esc_url() on internal paths: it can break or empty Unicode/apostrophe paths.
                        $tmp = $matches[1];
                        $origin = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
                        $origin_path = parse_url($origin, PHP_URL_PATH);
                        if (!is_string($origin_path) || $origin_path === '') {
                            $origin_path = '/';
                        }
                        $new_path = substr_replace($origin_path, $this->variables->site_prefix, 0, strlen($tmp));
                        if (!is_string($new_path) || $new_path === '') {
                            $new_path = '/' . ltrim($origin_path, '/');
                        }
                        $query = parse_url($origin, PHP_URL_QUERY);
                        $_SERVER['REQUEST_URI'] = $new_path . (($query !== null && $query !== '') ? '?' . $query : '');
                        $this->variables->referrer = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

                        if (trim($matches[3]) && $this->variables->translate_links) {
                            $slug = '/' . urldecode(trim($matches[3]));
                            $original_slug = $this->find_original_slug($slug, $this->variables->source_language, $this->variables->language_code, $this->variables->referrer);
                            if ($original_slug) {
                                $this->rewriteRequestUriTranslatedTailToOriginal($matches[3], $original_slug);
                                $this->variables->referrer = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                            }
                        }

                        $page_url = $this->getPageUrl($this->variables->referrer);
                        if (in_array($page_url, $this->variables->blockpages_items)) {
                            $_SERVER["REQUEST_URI"] = $origin;
                            $this->variables->language_code = null;
                        }
                        if (preg_match("/\/(feed|wp-json)\//", $page_url)) {    //prevent translation of RSS and wp-json
                            $this->variables->language_code = null;
                        }
                    }
                }

                if (!empty($this->variables->source_language) && !empty($this->variables->target_languages)) {
                    $page_url = $this->getPageUrl($this->variables->referrer);

                    if (!in_array($page_url, $this->variables->blockpages_items)) {
                        $this->getCurrentPlan();
                        add_action('wp_footer', array($this, 'inline_script'));
                    }
                }

                if (!empty($this->variables->alternate)) {
                    add_action('wp_head', array($this, 'alternate'), 0);
                }

                if (!empty($this->variables->language_code)) {
                    add_filter('locale', function ($value) {
                        return $this->variables->language_code;
                    });
                    // Respect the plugin's `use_trailing_slash` setting on translated URLs.
                    // WP's core redirect_canonical() otherwise forces permalink_structure's
                    // slash convention, overriding the plugin's choice and causing a 301
                    // that strips the /lang/ prefix. We suppress ONLY slash-only redirects;
                    // all other canonicalizations (pagination, attachments, bad query args,
                    // wrong slug, etc.) continue to run normally.
                    add_filter('redirect_canonical', array($this, 'filter_redirect_canonical_for_translated'), 10, 2);
                } elseif (!class_exists('WooCommerce')) {
                    add_filter('locale', function ($value) {
                        $langs = explode('_', $value);
                        return $langs[0];
                    });
                }
                $local_lang = get_locale();
                $this->print_log("##### local_lang: " . $local_lang);
                if (substr($local_lang, 0, 2) != $this->variables->source_language) {
                    ob_start(array($this, 'translatePage'));
                }
            } else { // subdomain
                $this->print_log("@@@ structure:subdomain @@@");
                if (!empty($this->variables->source_language) && !empty($this->variables->target_languages)) {
                    $this->getCurrentPlan();
                    if (!empty($this->variables->alternate)) {
                        add_action('wp_head', array($this, 'alternate'), 0);
                    }
                    add_action('wp_footer', array($this, 'inline_script'));
                }

                if (!empty($this->variables->language_code)) {
                    add_filter('locale', function ($value) {
                        return $this->variables->language_code;
                    });
                } elseif (!class_exists('WooCommerce')) {
                    add_filter('locale', function ($value) {
                        $langs = explode('_', $value);
                        return $langs[0];
                    });
                }
                $local_lang = get_locale();
                $this->print_log("##### local_lang: " . $local_lang);
                $local_lang_short = explode("_", (get_locale()));

                if (is_array($local_lang_short) && isset($local_lang_short[0]) && strlen($local_lang_short[0]) == 2) {
                    $local_lang = $local_lang_short[0];
                }
                $this->print_log("##### local_lang short: " . $local_lang);
                // somehow translations were working for subdomains without translatePage function. Or never worked at all
                //I just wanted to use
                /*
                if (substr($local_lang, 0, 2) != $this->variables->source_language) {
                    ob_start(array($this, 'translatePage'));
                }
                */
            }


            add_action('wp_footer', array($this, 'html_plugin'));

        } else {
            new ConveyThisAdminNotices();
        }
    }

    function getCurrentPlan() {
        $this->print_log("* getCurrentPlan()");
        $domain_name = $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : '';

        $protocol = 'http://';
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $protocol = 'https://';
        }
        $fullUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

        $response = $this->httpRequest("/website/code/get?api_key=" . $this->variables->api_key . "&domain_name=" . $domain_name . "&referer=" . base64_encode($fullUrl), array(
            'method' => "GET"
        ), true, $this->variables->select_region);

        $responseBody = wp_remote_retrieve_body($response);

        if (!empty($responseBody)) {
            $json = json_decode($responseBody);
            if (!empty($json->code)) {
                if (strpos($json->code, "conveythis_trial_expired") !== false) {
                    $this->variables->plan = 'trial-expired';
                } else {
                    $this->variables->plan = 'paid';
                }
                if (preg_match('/no_translate_element_ids:\s*(\[.+\])/U', $json->code, $matches)) {
                    $this->variables->exclusion_block_ids = json_decode($matches[1]);
                }
                if (preg_match('/no_translate_element_classes:\s*(\[.+\])/U', $json->code, $matches)) {
                    $this->variables->exclusion_block_classes = json_decode($matches[1]);
                }
                if (preg_match('/is_exceeded:/U', $json->code, $matches)) {
                    $this->variables->exceeded = true;
                }
            } else {
                $this->variables->show_widget = false;
            }
        }
    }

    public function alternate() {
        $this->print_log("* alternate()");
        $site_url_parts = parse_url(home_url());
        $site_domain = $site_url_parts["scheme"] . "://" . $site_url_parts["host"];
        $site_url = home_url();

        $prefix = $this->getPageUrl($site_url);

        if (!empty($this->variables->url_structure) && $this->variables->url_structure == "subdomain") {
            $sourcePath = $this->getSourcePathForHreflang();
            $location = $this->getSubDomainLocationUsingSyntheticRequest($this->variables->source_language, $sourcePath, false);
            $hreflang = $this->variables->source_language;
            $href_link = $this->applyTrailingSlash(esc_attr($location));
            $this->print_log("### alternate href_link: " . $href_link);
            echo '<link href="' . $href_link . '" hreflang="x-default" rel="alternate">' . PHP_EOL;
            echo '<link href="' . $href_link . '" hreflang="' . esc_attr($hreflang) . '" rel="alternate">';
        } else {
            $sourcePath = $this->getSourcePathForHreflang();
            $location = $this->getLocationUsingSyntheticRequest($prefix, $this->variables->source_language, $sourcePath, false);
            $hreflang = $this->variables->source_language;
            $href_link = $this->applyTrailingSlash(esc_attr($site_domain . $location));
            $this->print_log("### alternate href_link: " . $href_link);
            echo '<link href="' . $href_link . '" hreflang="x-default" rel="alternate">' . PHP_EOL;
            echo '<link href="' . $href_link . '" hreflang="' . esc_attr($hreflang) . '" rel="alternate">';
        }
        echo "\n";

        $data = array_merge($this->variables->target_languages, array($this->variables->source_language));

        $_temp_blockpages = [];

        if ($this->variables->blockpages) {
            foreach ($this->variables->blockpages as $blockpages) {
                $_temp_blockpages[] = str_replace($site_domain, '', $blockpages);
            }
        }

        foreach ($data as $value) {
            $language = $this->searchLanguage($value);
            if (!empty($language)) {
                if (!empty($this->variables->url_structure) && $this->variables->url_structure == "subdomain") {
                    $location = $this->getSubDomainLocationForPublicUrl($language['code2'], true);
                    $href_link = $this->applyTrailingSlash(esc_attr($location));
                    echo '<link href="' . $href_link . '" hreflang="' . esc_attr($language['code2']) . '"  rel="alternate">';
                } else {
                    $location = $this->getLocationForPublicUrl($prefix, $language['code2'], true);

                    if ($language['code2'] !== $this->variables->source_language) {
                        $_short_url = str_replace($site_domain . '/' . $language['code2'], '', esc_attr($site_domain . $location));

                        if (!in_array($_short_url, $_temp_blockpages)) {
                            $href_link = $this->applyTrailingSlash(esc_attr($site_domain . $location));
                            echo '<link href="' . $href_link . '" hreflang="' . esc_attr($language['code2']) . '" rel="alternate">';
                        } else {
                            continue;
                        }
                    }

                }
            }
            echo "\n";
        }
    }

    public function html_plugin() {
        if (!$this->variables->show_widget ||
            empty($this->variables->target_languages) ||
            empty($this->variables->source_language) ||
            is_404()
        ) {
            return;
        }
        $this->print_log("* html_plugin()");
        $data = array_merge($this->variables->target_languages, [$this->variables->source_language]);
        $site_url = home_url();
        $site_url_parts = parse_url($site_url);
        $site_domain = "{$site_url_parts["scheme"]}://{$site_url_parts["host"]}";
        $prefix = $this->getPageUrl($site_url);
        $languageList = [];
        $linkList = ['current' => '', 'alternates' => ''];

        foreach ($data as $value) {
            $language = $this->searchLanguage($value);
            if (!empty($language)) {
                $language_code = $language['code2'];
                $location = (!empty($this->variables->url_structure) && $this->variables->url_structure === "subdomain")
                    ? $this->getSubDomainLocationForPublicUrl($language_code, true)
                    : $this->getLocationForPublicUrl($prefix, $language_code, true);
                $this->print_log("$$$ site_domain $$$ : " . $site_domain);
                $this->print_log("$$$ location $$$ : " . $location);
                if ($this->variables->url_structure === "subdomain") {
                    $language_item = esc_attr($location);
                } else {
                    $language_item = esc_attr($site_domain . $location);
                }
                $this->print_log("$$$ language_item $$$ : " . $language_item);
                $languageList[$this->getTitle($language)] = $language_item;
            }
        }

        $currentPageUrl = $this->getPageUrl($this->variables->referrer);

        $this->print_log("languageList:" . json_encode($languageList));

        foreach ($languageList as $code => $href) {
            $hrefPageUrl = $this->getPageUrl($href);
            $href_link = $this->applyTrailingSlash($href);
            $this->print_log("def href_link:" . $href);
            $this->print_log("new href_link:" . $href_link);

            if ($hrefPageUrl === $currentPageUrl) {
                $linkList['current'] .= "<a href='{$href_link}' translate='no'>{$code}</a>";
            } else {
                $linkList['alternates'] .= PHP_EOL . "<a href='{$href_link}' translate='no'>{$code}</a>";
            }
        }

        $conveythisLogo = '<div><span>Powered by </span><a href="https://www.conveythis.com/?utm_source=conveythis_drop_down_btn" alt="conveythis.com">ConveyThis</a></div>';
        $conveythisLogo = $this->variables->hide_conveythis_logo ?  "" : $conveythisLogo;

        $languageHtml = '<div class="conveythis-widget-languages" id="language-list" style="display: none;">
                            ' . $conveythisLogo . '
                            <div class="conveythis-widget-language" role="button" tabindex="0">' . $linkList['alternates'] . PHP_EOL . '</div>
                        </div>
                        <div class="conveythis-widget-current-language-wrapper" aria-controls="language-list" aria-expanded="false">
                            <div class="conveythis-widget-language" role="button" tabindex="0">' . $linkList['current'] . '<div class="conveythis-language-arrow"></div></div>
                        </div>';

        $pluginHtml = '<div id="conveythis-wrapper" class="conveythis-no-translate conveythis-source">
                            <div class="conveythis-widget-main">' . $languageHtml . '</div>
                       </div>';

        echo $pluginHtml;
    }

    private function getTitle($language) {
        $this->print_log("* getTitle()");
        $title = '';
        switch ($this->variables->style_text) {
            case 'full-text';
                $title = $language['title_en'];
                break;
            case 'full-text-native';
                $title = $language['title'];
                break;
            case 'short-text-alfa-2';
                $title = $language['code2'];
                break;
            case 'short-text';
                $title = $language['code3'];
                break;
            case 'without-text';
                $title = $language['code2'];
                break;
        }
        return $title;
    }

    private function deleteQueryParams($url, $alternate_link) {
        $this->print_log("* deleteQueryParams()");
        $parsedUrl = parse_url($url);
        if (isset($parsedUrl['query'])) {
            if ($alternate_link) $parsedUrl['query'] = '';
            parse_str($parsedUrl['query'], $queryParams);
            foreach ($this->variables->query_params_block as $param) {
                if (array_key_exists($param, $queryParams)) {
                    unset($queryParams[$param]);
                }
            }
            $newUrl = $parsedUrl['path'];
            if (!empty($queryParams)) {
                $newUrl .= '?' . http_build_query($queryParams, '', '&');
            }
            return $newUrl;
        }
        return $url;
    }

    public function getLocation($prefix, $language_code, $alternate_link = false) {
        $this->print_log("* getLocation()");
        $url = $this->deleteQueryParams($_SERVER["REQUEST_URI"], $alternate_link);

        if ($this->variables->source_language == $language_code) {
            return $url;
        } else {
            if (isset($this->variables->target_languages_translations[$language_code])) {
                $language_code = $this->variables->target_languages_translations[$language_code];
            }

            if (strpos($url, '/' . $language_code . '/') === 0) { //check if already contains language prefix
                //  $this->print_log("case #0: $url");
                return $url;
            } else {
                if ($url === '/') {
                    $result = substr_replace($url, $prefix . '' . $language_code, 0, strlen($prefix));
                    //  $this->print_log("case #1: $result");
                    return $result;
                }
                $result = substr_replace($url, $prefix . '' . $language_code . '/', 0, strlen($prefix));
                // $this->print_log("case #2: $result");
                return $result;
            }
        }
    }

    /**
     * Canonical source path for the current resource (for hreflang / language URLs).
     * On 404 or before REQUEST_URI is normalized, referrer may still contain /{lang}/translated-slug/;
     * resolve to source slug via find_original_slug when translate_links is on.
     */
    private function getSourcePathForHreflang(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $path = $this->getPageUrl($this->variables->referrer);
        if ($path === '/' || $path === '') {
            $req = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
            $path = $req ? ('/' . ltrim($req, '/')) : '/';
        }
        $path = '/' . ltrim(trim($path), '/');
        if ($path !== '/' && substr($path, -1) !== '/') {
            $path .= '/';
        }

        if (
            !empty($this->variables->translate_links)
            && !empty($this->variables->language_code)
            && $path !== '/'
        ) {
            $afterStrip = $this->stripLeadingLanguageSegmentFromPath($path);
            if ($afterStrip !== $path && $afterStrip !== '/') {
                $orig = $this->find_original_slug(
                    $afterStrip,
                    $this->variables->source_language,
                    $this->variables->language_code,
                    $this->variables->referrer
                );
                if (is_string($orig) && $orig !== '') {
                    $o = '/' . trim($orig, '/');
                    $cached = (substr($o, -1) === '/') ? $o : ($o . '/');
                    return $cached;
                }
            }
        }

        $cached = $path;
        return $cached;
    }

    private function getLocationUsingSyntheticRequest(string $prefix, string $language_code, string $pathOnly, bool $alternate_link): string
    {
        $saved = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $query = parse_url($saved, PHP_URL_QUERY);
        $_SERVER['REQUEST_URI'] = $pathOnly . ($query && !$alternate_link ? '?' . $query : '');
        try {
            return $this->getLocation($prefix, $language_code, $alternate_link);
        } finally {
            $_SERVER['REQUEST_URI'] = $saved;
        }
    }

    private function getSubDomainLocationUsingSyntheticRequest(string $language_code, string $pathOnly, bool $alternative_link): string
    {
        $saved = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $query = parse_url($saved, PHP_URL_QUERY);
        $_SERVER['REQUEST_URI'] = $pathOnly . ($query && !$alternative_link ? '?' . $query : '');
        try {
            return $this->getSubDomainLocation($language_code, $alternative_link);
        } finally {
            $_SERVER['REQUEST_URI'] = $saved;
        }
    }

    /**
     * Subfolder hreflang/widget URLs: same translated slug as link translation (find-translation API), not source slug + lang.
     */
    public function getLocationForPublicUrl(string $prefix, string $language_code, bool $alternate_link = false): string
    {
        if (empty($this->variables->translate_links)) {
            return $this->getLocation($prefix, $language_code, $alternate_link);
        }

        $url = $this->deleteQueryParams($_SERVER['REQUEST_URI'], $alternate_link);

        if ($this->variables->source_language == $language_code) {
            return $this->getLocation($prefix, $language_code, $alternate_link);
        }

        $effectiveLang = $language_code;
        if (isset($this->variables->target_languages_translations[$language_code])) {
            $effectiveLang = $this->variables->target_languages_translations[$language_code];
        }

        $sourcePath = $this->getSourcePathForHreflang();
        if ($sourcePath === '/' || $sourcePath === '') {
            return $this->getLocation($prefix, $language_code, $alternate_link);
        }

        $translatedRaw = $this->lookupTranslatedPathForHreflang($sourcePath, $effectiveLang);
        if (!is_string($translatedRaw) || $translatedRaw === '') {
            return $this->getLocation($prefix, $language_code, $alternate_link);
        }

        $translatedPath = '/' . ltrim($translatedRaw, '/');
        if (substr($translatedPath, -1) !== '/') {
            $translatedPath .= '/';
        }

        // Do not use shouldUseTranslatedLinkPath here: url_to_postid() often fails for ConveyThis
        // localized slugs (e.g. Cyrillic) while the URL is valid; find_translation is authoritative for hreflang.

        if (strpos($url, '/' . $effectiveLang . '/') === 0) {
            return $this->getLocation($prefix, $language_code, $alternate_link);
        }

        $tail = trim($translatedPath, '/');
        $prefixTrim = rtrim($prefix, '/');
        if ($prefixTrim === '') {
            $built = '/' . $language_code . '/' . $tail . '/';
        } else {
            $built = $prefixTrim . '/' . $language_code . '/' . $tail . '/';
        }

        if (!$alternate_link) {
            $parsedFull = parse_url($this->deleteQueryParams($_SERVER['REQUEST_URI'], false));
            if (!empty($parsedFull['query'])) {
                $built .= '?' . $parsedFull['query'];
            }
        }

        return $built;
    }

    public function getSubDomainLocationForPublicUrl($language_code, $alternative_link = false) {
        if (empty($this->variables->translate_links)) {
            return $this->getSubDomainLocation($language_code, $alternative_link);
        }

        if ($this->variables->source_language == $language_code) {
            return $this->getSubDomainLocation($language_code, $alternative_link);
        }

        $effectiveLang = $language_code;
        if (isset($this->variables->target_languages_translations[$language_code])) {
            $effectiveLang = $this->variables->target_languages_translations[$language_code];
        }

        $sourcePath = $this->getSourcePathForHreflang();
        if ($sourcePath === '/' || $sourcePath === '') {
            return $this->getSubDomainLocation($language_code, $alternative_link);
        }

        $translatedRaw = $this->lookupTranslatedPathForHreflang($sourcePath, $effectiveLang);
        if (!is_string($translatedRaw) || $translatedRaw === '') {
            return $this->getSubDomainLocation($language_code, $alternative_link);
        }

        $translatedPath = '/' . ltrim($translatedRaw, '/');
        if (substr($translatedPath, -1) !== '/') {
            $translatedPath .= '/';
        }

        $pathOnly = $translatedPath;
        if (!$alternative_link) {
            $parsedFull = parse_url($this->deleteQueryParams($_SERVER['REQUEST_URI'], false));
            if (!empty($parsedFull['query'])) {
                $pathOnly .= '?' . $parsedFull['query'];
            }
        }

        return $_SERVER["REQUEST_SCHEME"] . "://" . $language_code . "." . $_SERVER["HTTP_HOST"] . $pathOnly;
    }

    /**
     * Look up the translated slug for a given source path + target language.
     * Public so ConveyThisSEO::modify_url() can keep sitemap URLs aligned with hreflang/canonical.
     *
     * Three-tier caching, in order:
     *   L1: static $reqCache  — request-scoped dedup. Free, microsecond reads,
     *                            covers the "render 93 hreflang tags on one page" case.
     *   L2: ConveyThisCache::get_fwd_slug — on-disk shard, survives FPM workers.
     *                            Covers sitemap crawls (the hot path that exhausted
     *                            php-fpm pools before this fix).
     *   L3: find_translation()   — cold path, hits the ConveyThis API. Result is
     *                            written to L2 (positive or negative) so the next
     *                            caller short-circuits.
     *
     * Negative cache is required: paths with no translation are extremely common
     * (post not yet translated to obscure language) and re-asking the API every
     * time would defeat the cache. Short TTL (CONVEYTHIS_FWD_SLUG_TTL_NEGATIVE)
     * lets a newly-translated post become discoverable within hours.
     *
     * Spec: docs/superpowers/specs/2026-05-02-sitemap-slug-cache-design.md.
     */
    public function lookupTranslatedPathForHreflang(string $sourcePath, string $targetLanguageCode): ?string
    {
        static $reqCache = [];
        $key = $sourcePath . '|' . $targetLanguageCode;
        if (array_key_exists($key, $reqCache)) {
            return $reqCache[$key];
        }

        $sourceLang = (string) ($this->variables->source_language ?? '');

        if ($sourcePath !== '' && $sourceLang !== '' && $targetLanguageCode !== '' && $this->ConveyThisCache instanceof ConveyThisCache) {
            $hit = $this->ConveyThisCache->get_fwd_slug($sourcePath, $sourceLang, $targetLanguageCode);
            if (!empty($hit['hit']) && empty($hit['expired'])) {
                return $reqCache[$key] = ($hit['neg'] ? null : $hit['value']);
            }
        }

        $result = $this->find_translation($sourcePath, $sourceLang, $targetLanguageCode, $this->variables->referrer);

        if (!is_string($result) || $result === '') {
            if ($sourcePath !== '' && $sourceLang !== '' && $targetLanguageCode !== '' && $this->ConveyThisCache instanceof ConveyThisCache) {
                $this->ConveyThisCache->set_fwd_slug($sourcePath, $sourceLang, $targetLanguageCode, null, true);
            }
            return $reqCache[$key] = null;
        }

        if ($sourcePath !== '' && $sourceLang !== '' && $targetLanguageCode !== '' && $this->ConveyThisCache instanceof ConveyThisCache) {
            $this->ConveyThisCache->set_fwd_slug($sourcePath, $sourceLang, $targetLanguageCode, $result, false);
        }
        return $reqCache[$key] = $result;
    }

    /**
     * Canonical URL for the current language with translated slug when translate_links is enabled
     * (same path lookup as sitemap/hreflang via {@see lookupTranslatedPathForHreflang()}, not
     * {@see replaceLink()} which only matches paths present in on-page translation items).
     *
     * @param string $href Raw canonical href from the document.
     * @param string $langUi Current UI language code (e.g. widget code2).
     * @return string|null Absolute URL, or null to use legacy language-prefix-only rewriting.
     */
    private function buildTranslatedCanonicalFromHref(string $href, string $langUi): ?string
    {
        $parsed = wp_parse_url($href);
        if (!is_array($parsed)) {
            $parsed = [];
        }
        if (empty($parsed['host'])) {
            $home = wp_parse_url(home_url());
            if (!is_array($home)) {
                $home = [];
            }
            $parsed['scheme'] = $parsed['scheme'] ?? ($home['scheme'] ?? (is_ssl() ? 'https' : 'http'));
            $parsed['host'] = $home['host'] ?? '';
            if (!empty($home['port'])) {
                $parsed['port'] = $home['port'];
            }
        }

        $path = isset($parsed['path']) ? $parsed['path'] : '/';
        if ($path !== '/' && $path !== '') {
            $path = $this->stripLeadingLanguageSegmentFromPath($path);
        }
        $parsed['path'] = $path;

        $effectiveLang = $langUi;
        if (isset($this->variables->target_languages_translations[$langUi])) {
            $effectiveLang = $this->variables->target_languages_translations[$langUi];
        }

        if (!empty($this->variables->url_structure) && $this->variables->url_structure === 'subdomain') {
            if ($path === '/' || $path === '') {
                $sch = $parsed['scheme'] ?? 'https';
                $port = !empty($parsed['port']) ? ':' . (int) $parsed['port'] : '';
                return $sch . '://' . $langUi . '.' . $parsed['host'] . $port . '/';
            }
            $sourceForLookup = $path;
            if (substr($sourceForLookup, -1) !== '/') {
                $sourceForLookup .= '/';
            }
            $translatedRaw = $this->lookupTranslatedPathForHreflang($sourceForLookup, $effectiveLang);
            if (!is_string($translatedRaw) || $translatedRaw === '') {
                return null;
            }
            $tp = '/' . trim($translatedRaw, '/');
            if (substr($tp, -1) !== '/') {
                $tp .= '/';
            }
            $sch = $parsed['scheme'] ?? 'https';
            $port = !empty($parsed['port']) ? ':' . (int) $parsed['port'] : '';
            return $sch . '://' . $langUi . '.' . $parsed['host'] . $port . $tp;
        }

        // Subfolder: must use find_translation (lookupTranslatedPathForHreflang), not replaceLink().
        // replaceLink() resolves slugs only via searchSegment() against this page's translation items;
        // canonical URLs are often absent from page body → slug never translated. Same as sitemap/hreflang.
        $sch = $parsed['scheme'] ?? 'https';
        $port = !empty($parsed['port']) ? ':' . (int) $parsed['port'] : '';
        $hostPart = $parsed['host'] ?? '';
        $prefixTrim = rtrim((string) ($this->variables->site_prefix ?? '/'), '/');

        if ($path === '/' || $path === '') {
            if ($prefixTrim === '') {
                $pathPart = '/' . $langUi . '/';
            } else {
                $pathPart = $prefixTrim . '/' . $langUi . '/';
            }
            return $sch . '://' . $hostPart . $port . $pathPart;
        }

        $sourceForLookup = $path;
        if (substr($sourceForLookup, -1) !== '/') {
            $sourceForLookup .= '/';
        }
        $translatedRaw = $this->lookupTranslatedPathForHreflang($sourceForLookup, $effectiveLang);
        if (!is_string($translatedRaw) || $translatedRaw === '') {
            return null;
        }
        $tail = trim($translatedRaw, '/');
        if ($prefixTrim === '') {
            $pathPart = '/' . $langUi . '/' . $tail . '/';
        } else {
            $pathPart = $prefixTrim . '/' . $langUi . '/' . $tail . '/';
        }
        return $sch . '://' . $hostPart . $port . $pathPart;
    }

    public function getSubDomainLocation($language_code, $alternative_link = false) {
        $this->print_log("* getSubDomainLocation()");
        $_url = $this->deleteQueryParams($_SERVER["REQUEST_URI"], $alternative_link);

        if ($this->variables->source_language == $language_code) {
            return $_SERVER["REQUEST_SCHEME"] . "://" . $_SERVER["HTTP_HOST"] . $_url;
        } else {
            return $_SERVER["REQUEST_SCHEME"] . "://" . $language_code . "." . $_SERVER["HTTP_HOST"] . $_url;
        }
    }

    function pluginOptions() {
        $this->print_log("* pluginOptions()");
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }
        if (empty($_GET["settings-updated"])) {
            $this->dataCheckAPI();
        }
        require_once CONVEY_PLUGIN_ROOT_PATH . 'app/views/index.php';
    }

    public function inline_script() {
        $this->print_log("* inline script()");
        if (is_404()) {
            return;
        }
        if (!$this->variables->show_widget) {
            return;
        }
        $site_url = $this->variables->site_url;
        $prefix = $this->getPageUrl($site_url);
        $languages = array();
        if (!empty($this->variables->language_code)) {
            $current_language_code = $this->variables->language_code;
        } else {
            $current_language_code = $this->variables->source_language;
        }
        $language = $this->searchLanguage($current_language_code);
        if (!empty($language)) {
            if (!empty($this->variables->url_structure) && $this->variables->url_structure == "subdomain") {
                $location = $this->getSubDomainLocation($language['code2']);
            } else {
                $location = $this->getLocation($prefix, $language['code2']);
            }
            $languages[] = '{"id":"' . esc_attr($language['language_id']) . '", "location":"' . esc_attr($location) . '", "active":true}';
        }

        if (!empty($this->variables->language_code)) {
            $language = $this->searchLanguage($this->variables->source_language);
            if (!empty($language)) {
                if (!empty($this->variables->url_structure) && $this->variables->url_structure == "subdomain") {
                    $location = $this->getSubDomainLocation($language['code2']);
                } else {
                    $location = $this->getLocation($prefix, $language['code2']);
                }
                $languages[] = '{"id":"' . esc_attr($language['language_id']) . '", "location":"' . esc_attr($location) . '", "active":false}';
            }
        }

        if (($key = array_search($this->variables->source_language, $this->variables->target_languages)) !== false) { //remove source_language from target_languages
            unset($this->variables->target_languages[$key]);
        }

        foreach ($this->variables->target_languages as $language_code) {
            $language = $this->searchLanguage($language_code);
            if (!empty($language)) {
                if ($current_language_code != $language['code2']) {
                    if (!empty($this->variables->url_structure) && $this->variables->url_structure == "subdomain") {
                        $location = $this->getSubDomainLocation($language['code2']);
                    } else {
                        $location = $this->getLocation($prefix, $language['code2']);
                    }
                    $languages[] = '{"id":"' . esc_attr($language['language_id']) . '", "location":"' . esc_attr($location) . '", "active":false}';
                }
            }
        }

        $source_language_id = 0;

        if (!empty($this->variables->source_language)) {
            $language = $this->searchLanguage($this->variables->source_language);
            if (!empty($language)) {
                $source_language_id = $language['language_id'];
            }
        }

        $temp = array();

        // Ensure arrays are properly indexed and iterate through all values
        // Re-index arrays to ensure sequential indices (0, 1, 2, ...)
        $style_change_language = is_array($this->variables->style_change_language) ? array_values($this->variables->style_change_language) : array();
        $style_change_flag = is_array($this->variables->style_change_flag) ? array_values($this->variables->style_change_flag) : array();

        // Iterate through all available pairs (up to 5)
        $maxPairs = min(5, max(count($style_change_language), count($style_change_flag)));
        
        for ($i = 0; $i < $maxPairs; $i++) {
            if (!empty($style_change_language[$i])) {
                $langId = $style_change_language[$i];
                $flagCode = !empty($style_change_flag[$i]) ? $style_change_flag[$i] : '';
                $temp[] = '"' . $langId . '":"' . $flagCode . '"';
            }
        }

        $change = '{' . implode(',', $temp) . '}';

        $positionTop = 'null';
        $positionBottom = 'null';
        $positionLeft = 'null';
        $positionRight = 'null';

        if ($this->variables->style_position_type == 'custom' && $this->variables->style_selector_id != '') {
            if ($this->variables->style_position_vertical_custom == 'top') {
                $positionTop = 50;
                $positionBottom = "null";
            } else {
                $positionTop = "null";
                $positionBottom = 0;
            }

            $positionLeft = "null";
            $positionRight = 25;
            $styleSelectorId = $this->variables->style_selector_id ?: null;
        } else {
            if ($this->variables->style_position_vertical == 'top') {
                $positionTop = $this->variables->style_indenting_vertical ?: 0;
                $positionBottom = "null";
            } else {
                $positionTop = "null";
                $positionBottom = $this->variables->style_indenting_vertical ?: 0;
            }
            if ($this->variables->style_position_horizontal == 'right') {
                $positionRight = (!is_null($this->variables->style_indenting_horizontal) && !empty($this->variables->style_indenting_horizontal)) ? $this->variables->style_indenting_horizontal : 24;
                $positionLeft = "null";
            } else {
                $positionRight = "null";
                $positionLeft = (!is_null($this->variables->style_indenting_horizontal) && !empty($this->variables->style_indenting_horizontal)) ? $this->variables->style_indenting_horizontal : 24;
            }
            $styleSelectorId = null;
        }

        if ($this->variables->plan == 'trial-expired') {
            wp_enqueue_script('conveythis-trial-expired', plugins_url('../widget/js/trial-expired.js', __FILE__), [], CONVEYTHIS_PLUGIN_VERSION, false);
            return;
        }

        if (!empty($this->variables->api_key)) {
            //  $parts = explode('/', CONVEYTHIS_JAVASCRIPT_PLUGIN_URL_OLD);
            // $cdn_version = end($parts);

            wp_enqueue_script('conveythis-notranslate', plugin_dir_url(__DIR__) . 'widget/js/notranslate.js', [], CONVEYTHIS_PLUGIN_VERSION, false);
            wp_enqueue_script('conveythis-conveythis', CONVEYTHIS_JAVASCRIPT_PLUGIN_URL . "/conveythis-initializer.js", [], false, false);

            $initScript = '
                document.addEventListener("DOMContentLoaded", function(e) {
                    document.querySelectorAll(".conveythis-source").forEach(element => element.remove());
                    ConveyThis_Initializer.init({
                        api_key: "' . esc_attr($this->variables->api_key) . '",
                        is_wordpress: "' . $this->searchLanguage($current_language_code)['language_id'] . '",
                        auto_translate: "' . esc_attr($this->variables->auto_translate ? $this->variables->auto_translate : '1') . '",
                        languages:[' . implode(', ', $languages) . '],
                    })
                });
            ';

            wp_add_inline_script('conveythis-conveythis', $initScript);
        }
    }

    function DOMinnerHTML(DOMNode $element) {
        $this->print_log("* DOMinnerHTML()");
        $innerHTML = "";
        $children = $element->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }

    function shouldTranslateWholeTag($element) {
        $this->print_log("* shouldTranslateWholeTag()");
        for ($i = 0; $i < count($element->childNodes); $i++) {
            $child = $element->childNodes->item($i);

            if (in_array(strtoupper($child->nodeName), $this->variables->siblingsAvoidArray)) {
                return false;
            }
        }
        return true;
    }

    function allowTranslateWholeTag($element) {
        $this->print_log("* allowTranslateWholeTag()");
        for ($i = 0; $i < count($element->childNodes); $i++) {
            $child = $element->childNodes->item($i);
            if (in_array(strtoupper($child->nodeName), $this->variables->siblingsAllowArray)) {
                $outerHTML = $element->ownerDocument->saveHTML($child);
                if (preg_match("/>(\s*[^<>\s]+[\s\S]*?)</", $outerHTML)) {
                    return true;
                } else if (strtoupper($child->nodeName) == "BR") {
                    $innerHTML = $this->DOMinnerHTML($element);
                    if (preg_match("/\s*[^<>\s]+\s*<br>\s*[^<>\s]+/i", $innerHTML)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    function isTextNodeExists($element) {
       // $this->print_log("* isTextNodeExists()");
        for ($i = 0; $i < count($element->childNodes); $i++) {
            $child = $element->childNodes->item($i);
            if ($child->nodeName == "#text" && trim($child->textContent)) {
                return true;
            }
        }
        return false;
    }

    // DOM
    function domRecursiveRead($doc) {
        // $this->print_log("* domRecursiveRead()");
        foreach ($doc->childNodes as $child) {
            if ($child->nodeType === 3) {
                $originalValue = $child->textContent;
                $value = trim($child->textContent);

                if (!empty($value)) {
                    if ($child->nextSibling || $child->previousSibling) {
                        if ($child->parentNode && $this->allowTranslateWholeTag($child->parentNode) && $this->shouldTranslateWholeTag($child->parentNode)) {
                            $value = trim($this->DOMinnerHTML($child->parentNode));
                            $value = preg_replace("/\<!--(.*?)\-->/", "", $value);
                            $this->variables->segments[$value] = $value;
                            $this->collectNode($child->parentNode, 'innerHTML', $value, $originalValue);
                        } else {
                            $this->variables->segments[$value] = $value;
                            $this->collectNode($child, 'textContent', $value, $originalValue);
                        }
                    } else {
                        $this->variables->segments[$value] = $value;
                        $this->collectNode($child, 'textContent', $value, $originalValue);
                    }
                }

            } else {
                if ($child->nodeType === 1) {
                    if ($child->hasAttribute('title')) {
                        $attrValue = trim($child->getAttribute('title'));
                        if (!empty($attrValue)) {
                            $this->collectNode($child, 'title', $attrValue);
                        }
                    }

                    if ($child->hasAttribute('alt')) {
                        $attrValue = trim($child->getAttribute('alt'));
                        if (!empty($attrValue)) {
                            $this->collectNode($child, 'alt', $attrValue);
                        }
                    }

                    if ($child->hasAttribute('placeholder')) {
                        $attrValue = trim($child->getAttribute('placeholder'));
                        if (!empty($attrValue)) {
                            $this->collectNode($child, 'placeholder', $attrValue);
                        }
                    }

                    if ($child->hasAttribute('type')) {
                        $attrTypeValue = trim($child->getAttribute('type'));

                        if (strcasecmp($attrTypeValue, 'submit') === 0 || strcasecmp($attrTypeValue, 'reset') === 0) {
                            if ($child->hasAttribute('value')) {
                                $attrValue = trim($child->getAttribute('value'));
                                if (!empty($attrValue)) {
                                    $this->collectNode($child, 'value', $attrValue);
                                }
                            }
                        }
                    }

                    if (!empty($attrValue)) {
                        $this->variables->segments[$attrValue] = $attrValue;
                    }

                    if (strcasecmp($child->nodeName, 'meta') === 0) {
                        if ($child->hasAttribute('name') || $child->hasAttribute('property')) {
                            $metaAttributeName = strtolower(trim(
                                $child->hasAttribute('name')
                                    ? $child->getAttribute('name')
                                    : $child->getAttribute('property')
                            ));

                            // Static lookup keeps meta tag matching O(1) instead of per-tag strcasecmp ladder.
                            // Tags accepted segments with content_type='seo_meta' + the canonical meta_field.
                            if (isset(self::$META_FIELD_TO_TYPE[$metaAttributeName]) && $child->hasAttribute('content')) {
                                $metaAttrValue = trim($child->getAttribute('content'));

                                if (!empty($metaAttrValue)) {
                                    $this->variables->segments[$metaAttrValue] = $metaAttrValue;
                                    $this->variables->segment_meta[$metaAttrValue] = [
                                        'content_type' => 'seo_meta',
                                        'meta_field'   => self::$META_FIELD_TO_TYPE[$metaAttributeName],
                                    ];
                                    $this->collectNode($child, 'content', $metaAttrValue);
                                }
                            }
                        }
                    }

                    if ($child->nodeName == 'img') {
                        // error_log('* if($child->nodeName' . print_r($child->nodeName, true));
                        if ($this->variables->translate_media) {
                            $src = $child->getAttribute("src");
                            // error_log('* $src' . print_r($src, true));
                            $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
                            // error_log('* $ext' . print_r($ext, true));
                            if (strpos($ext, "?") !== false) $ext = substr($ext, 0, strpos($ext, "?"));

                            if (in_array($ext, $this->variables->imageExt)) {
                                $this->variables->segments[$src] = $src;
                                $this->collectNode($child, 'src', $src);
                            }
                        }

                        if ($child->hasAttribute('title')) {
                            $title = trim($child->getAttribute('title'));
                            if (!empty($title)) {
                                $this->variables->segments[$title] = $title;
                                $this->collectNode($child, 'title', $title);
                            }
                        }

                        if ($child->hasAttribute('alt')) {
                            $alt = trim($child->getAttribute('alt'));
                            if (!empty($alt)) {
                                $this->variables->segments[$alt] = $alt;
                                $this->collectNode($child, 'alt', $alt);
                            }
                        }
                    }

                    $shouldReadChild = true;

                    if ($child->nodeName == 'a') {
                        if ($this->variables->translate_document) {
                            $href = $child->getAttribute("href");
                            $ext = strtolower(pathinfo($href, PATHINFO_EXTENSION));
                            if (strpos($ext, "?") !== false) {
                                $ext = substr($ext, 0, strpos($ext, "?"));
                            }
                            if (in_array($ext, $this->variables->documentExt)) {
                                $this->variables->segments[$href] = $href;
                                $this->collectNode($child, 'href', $href);
                            }
                        }

                        if ($this->variables->translate_links) {
                            $href = $child->getAttribute("href");
                            $pageHost = $this->getPageHost($href);
                            $link = parse_url($href);

                            if ((!$pageHost || $pageHost == $this->variables->site_host) && isset($link['path']) && $link['path'] && $link['path'] != '/') {
                                // File/media URLs (PDFs, images, etc.) must not enter the slug-translation
                                // pipeline — it would split /wp-content/uploads/ on slashes and translate the
                                // path components as text, producing /contenuto-wp/caricamenti/... in Italian.
                                $pathExt = strtolower(pathinfo($link['path'], PATHINFO_EXTENSION));
                                $isFileUrl = $pathExt !== '' && in_array($pathExt, $this->variables->avoidUrlExt, true);

                                if (!$isFileUrl && $this->shouldTranslateLink($link['path'], $this->getLinkContext($child))) {
                                    $canonicalPath = $this->canonicalizeLinkPath($link['path']);
                                    $this->variables->segments[$canonicalPath] = $canonicalPath;
                                    $this->variables->links[$canonicalPath] = $canonicalPath;
                                    $this->collectNode($child, 'href', $canonicalPath);
                                }
                            }
                        }

                        $translateAttr = $child->getAttribute("translate");
                        if ($translateAttr && $translateAttr == "no") {
                            // no need to walk inside
                            $shouldReadChild = false;
                        }
                    }

                    if (in_array(strtoupper($child->nodeName), $this->variables->siblingsAllowArray)) {
                        if ($child->parentNode) {
                            if ($this->isTextNodeExists($child->parentNode) && $this->allowTranslateWholeTag($child->parentNode) && $this->shouldTranslateWholeTag($child->parentNode)) {
                                // no need to walk inside
                                $shouldReadChild = false;
                            }
                        }
                    }

                    if ($child->hasAttribute('class')) {
                        $class = $child->getAttribute("class");
                        if (strpos($class, 'conveythis-no-translate') !== false) {
                            // no need to walk inside
                            $shouldReadChild = false;
                        }
                    }

                    if ($child->hasAttribute('id')) {
                        $idAdminWP = $child->getAttribute("id");
                        if (strpos($idAdminWP, 'wpadminbar') !== false) {
                            // no need to walk inside
                            $shouldReadChild = false;
                        }
                    }

                    foreach ($this->variables->exclusion_block_ids as $exclusionBlockId) {
                        if ($child->hasAttribute('id') && $child->getAttribute("id") == $exclusionBlockId) {
                            // no need to walk inside
                            $shouldReadChild = false;
                            break;
                        }
                    }

                    if ($child->hasAttribute('class')) {
                        $classes = preg_split('/\s+/', trim($child->getAttribute('class')));
                        foreach ($this->variables->exclusion_block_classes as $exclusionBlockClass) {
                            if (in_array($exclusionBlockClass, $classes)) {
                                // no need to walk inside
                                $shouldReadChild = false;
                                break;
                            }
                        }
                    }

                    if (strcasecmp($child->nodeName, 'script') !== 0 && strcasecmp($child->nodeName, 'style') !== 0 && $shouldReadChild == true) {
                        $this->domRecursiveRead($child);
                    }
                }
            }
        }
    }

    private function collectNode($item, $attr, $value, $originalValue = '') {
        // $this->print_log("* collectNode()");
        // Add node original value and attribute in list so then we can find the element by its DOM path and replace original content for each element with translation
        $path = $item->getNodePath();

        $leftSpace = preg_match('/^\s+/u', (string)$originalValue, $l) ? $l[0] : '';
        $rightSpace = preg_match('/\s+$/u', (string)$originalValue, $r) ? $r[0] : '';

        if (!isset($this->nodePathList[$path])) {
            $this->nodePathList[$path] = [];
            $this->nodePathListSpace[$path] = [];
        }

        $this->nodePathList[$path][$attr] = $value;
        $this->nodePathListSpace[$path][$attr] = ['left' => $leftSpace, 'right' => $rightSpace];
    }

    function replaceSegments($doc) {
        $this->print_log("* replaceSegments()");
        // Get all elements of document
        $xpath = new DOMXPath($doc);
        $elements = $xpath->query('//text() | //*');

        foreach ($elements as $el) {
            // If translate is not allowed don't do anything
            if ($el->nodeType === 1 && $el->hasAttribute('translate') && trim($el->getAttribute('translate')) === 'no') {
                continue;
            }
            // Check if there is translation remained for each element
            $node_path = $el->getNodePath();
            if (isset($this->nodePathList[$node_path])) {
                foreach ($this->nodePathList[$node_path] as $attr => $value) {
                    // If translation is found replace current text or attribute with translation
                    $segment = $this->searchSegment($value);

                    if ($segment) {
                        if (isset($this->nodePathListSpace[$node_path][$attr])) {
                            $space = $this->nodePathListSpace[$node_path][$attr];
                            if (isset($space["left"]) && isset($space["right"])) {
                                $segment = $space["left"] . $segment . $space["right"];
                            }
                        }

                        if ($attr == 'innerHTML') {
                            $el->innerHTML = $segment;
                        } elseif ($attr == 'textContent') {
                            if ($el->parentNode && $el->parentNode->childNodes->length == 1) {
                                $el->parentNode->innerHTML = $segment;
                            } else {
                                $el->textContent = $segment;
                            }
                        } else {
                            // Enforce per-field length limits on meta content swaps so translated
                            // meta descriptions / og:descriptions stay under Google's truncation thresholds.
                            if ($el->nodeName === 'meta' && $attr === 'content') {
                                $metaInfo = $this->variables->segment_meta[$value] ?? null;
                                if (is_array($metaInfo) && ($metaInfo['content_type'] ?? '') === 'seo_meta') {
                                    $result = $this->enforceMetaLengthLimits(
                                        (string) $value,
                                        (string) $segment,
                                        (string) ($metaInfo['meta_field'] ?? 'description')
                                    );
                                    $segment = $result['text'];
                                }
                            }
                            $el->setAttribute($attr, $segment);
                        }
                    }
                }
            }

            // Srcset attribute handler
            if ($el->nodeName == 'img' && $this->variables->translate_media) {
                if ($el->hasAttribute("srcset")) {
                    $this->ConveyThisCache->clear_cached_translations(true);
                    // error_log('* post $this->ConveyThisCache->clear_cached_translations(true)');
                    $src_value = parse_url(trim($el->getAttribute('src')));
                    // error_log('* $src_value' . print_r($src_value, true));
                    $srcset_value = $el->getAttribute('srcset');
                    // error_log('* $srcset_value' . print_r($srcset_value, true));
                    $urls = explode(',', $srcset_value);
                    // error_log('* $urls' . print_r($urls, true));
                    foreach ($urls as &$url) {
                        $srcset_parts = parse_url(trim($url));
                        $width = explode(' ', trim($url))[1];

                        if (isset($srcset_parts['path'])) {
                            // error_log('* $srcset_parts["path"]' . " " . print_r($srcset_parts['path'], true));
                            // error_log('* $src_value["path"]' . " " . print_r($src_value['path'], true));
                            // error_log('* $url' . " " . print_r($url, true));
                            // $url = str_replace($srcset_parts['path'], $src_value['path'], $url) . ' ' . $width;
                            $replaced_url = str_replace($srcset_parts['path'], $src_value['path'], $url);
                            // error_log('* all of $src_value' . " " . print_r($src_value, true));
                            if ($this->urlExists($replaced_url)) {
                                $url = str_replace($srcset_parts['path'], $src_value['path'], $url) . ' ' . $width;
                            } else {
                                $url = $src_value['scheme'] . '://' . $src_value['host'] . $src_value['path'] . ' ' . $width;
                            }
                            // error_log('* $url' . " " . print_r($url, true));
                        }
                    }

                    $replaced_srcset = implode(', ', $urls);
                    // error_log('* $replaced_srcset = implode' . " " . print_r($replaced_srcset, true));
                    $el->setAttribute('srcset', $replaced_srcset);
                }
            }

            if ($el->nodeName == 'a') {
                // Replace link url with current language segment
                $href = $el->getAttribute('href');
                if (!preg_match('/\/wp-content\//', $href)) {
                    $replaced_href = $this->replaceLink($href, $this->variables->language_code);
                    if ($replaced_href && $replaced_href !== $href) {
                        $el->setAttribute('href', $replaced_href);
                    }
                }

                // $href = $el->getAttribute('href');

            } elseif ($el->nodeName == 'form') {
                $action = $el->getAttribute('action');
                $replaced_action = $this->replaceLink($action, $this->variables->language_code);
                if ($replaced_action && $replaced_action !== $action) {
                    $el->setAttribute('action', $replaced_action);
                }
            } elseif ($el->nodeName == 'article') {
                if ($el->hasAttribute('data-permalink')) {
                    $replaced_link = $this->replaceLink($el->getAttribute('data-permalink'), $this->variables->language_code);
                    if ($replaced_link) {
                        $el->setAttribute('data-permalink', $replaced_link);
                    }
                }
            }
        }

        $language_code = $this->variables->language_code;
        if (isset($this->variables->target_languages_translations[$language_code])) {
            $language_code = $this->variables->target_languages_translations[$language_code];
        }

        $anchors = $xpath->query('//a[@href]');

        foreach ($anchors as $a) {
            if ($a->getAttribute('translate') !== 'no' && ($this->variables->url_structure !== "subdomain")) {
                $href = $a->getAttribute('href');
                $wp_content_in_link = preg_match('/\/wp-content\//', $href);
                if (!$wp_content_in_link) {
                    $parsedHref = parse_url($href);
                    $path = isset($parsedHref['path']) ? $parsedHref['path'] : '';

                    $path_parts = array_filter(explode('/', ltrim($path, '/')));
                    $alreadyHasLang = in_array($language_code, $path_parts, true);

                    if (!$alreadyHasLang) {
                        $newHref = $this->replaceLink($href, $language_code);
                        if ($href !== $newHref) {
                            $a->setAttribute('href', $newHref);
                        }
                    }
                }
                $href_link = $href;
                if ($this->is_local_link($href) && (!$this->isPageExcluded($href, $this->variables->exclusions)) && !$wp_content_in_link) {
                    $this->print_log(">-- " . $href_link);

                    // add language code to links inside text segments
                    $lang = $this->variables->language_code;
                    if ($this->variables->url_structure == "subdomain") {
                        $this->print_log("url_structure == subdomain");
                        // just add subdomain lang before the href_link, if it is not there already
                    } else {
                        $this->print_log("url_structure != subdomain, default subfolders");
                        $prefix_end = '/' . $lang;
                        $prefix_end_length = strlen($prefix_end);
                        if ((strpos($href_link, '/' . $lang . '/') === false && ($prefix_end_length === 0 || substr($href_link, -$prefix_end_length) !== $prefix_end))) {
                            // if not yet contains ../es/.. in the middle of the link and not ends with .../es for the main page
                            $this->print_log("111 strpos(href_link, '/' . lang . '/' === false" . " ($href_link)");

                            if (preg_match('#^(https?://[^/]+)(/.*)?$#', $href_link, $matches)) {
                                $domain = $matches[1];
                                $path = isset($matches[2]) ? $matches[2] : '/';
                                $path = '/' . ltrim($path, '/');
                                $href_link = $domain . '/' . $lang . $path;
                            } else {
                                // For relative links
                                $href_link = '/' . $lang . '/' . ltrim($href_link, '/');
                            }
                        }
                    }

                    $this->print_log("->- " . $href_link);
                    $href_link = $this->applyTrailingSlash($href_link);

                    $this->print_log("--> " . $href_link);
                    $a->setAttribute('href', $href_link);
                }

            }
        }
        $canonical = $xpath->query("//link[@rel='canonical']");

        if ($canonical->length > 0) {
            $canonicalTag = $canonical->item(0);
            $currentHref = $canonicalTag->getAttribute('href');
            $urlComponents = parse_url($currentHref);

            $scheme = isset($urlComponents['scheme']) ? $urlComponents['scheme'] : 'https';
            $host = isset($urlComponents['host']) ? $urlComponents['host'] : '';
            $rawPath = isset($urlComponents['path']) ? $urlComponents['path'] : '';
            $pathRest = ltrim($rawPath, '/');

            if (isset($this->variables->language_code)) {
                $langUi = (string) $this->variables->language_code;
                $newHref = null;
                if (
                    !empty($this->variables->translate_links)
                    && $langUi !== ''
                    && $this->is_local_link($currentHref)
                    && !$this->isPageExcluded($currentHref, $this->variables->exclusions)
                ) {
                    $newHref = $this->buildTranslatedCanonicalFromHref($currentHref, $langUi);
                }
                if ($newHref === null || $newHref === '') {
                    $newHref = $scheme . '://' . $host . '/' . $langUi;
                    if ($pathRest !== '') {
                        $newHref .= '/' . $pathRest;
                    }
                }
            } else {
                $newHref = $scheme . '://' . $host;
                if ($pathRest !== '') {
                    $newHref .= '/' . $pathRest;
                }
            }

            $uCanon = parse_url($newHref);
            if (!is_array($uCanon)) {
                $uCanon = [];
            }
            $qsRaw = isset($uCanon['query']) ? $uCanon['query'] : '';
            if ($qsRaw === '' && !empty($urlComponents['query'])) {
                $qsRaw = $urlComponents['query'];
            }
            if ($qsRaw !== '') {
                // SEO: strip tracking parameters from canonical to avoid duplicate signals.
                parse_str($qsRaw, $qs);
                $tracking_exact = apply_filters('conveythis_canonical_tracking_params', [
                    'fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid', '_ga', 'yclid', 'dclid',
                    '_hsenc', '_hsmi', 'igshid', 'mkt_tok',
                ]);
                foreach (array_keys($qs) as $k) {
                    $kl = strtolower($k);
                    if (in_array($kl, $tracking_exact, true)) { unset($qs[$k]); continue; }
                    if (strpos($kl, 'utm_') === 0) { unset($qs[$k]); }
                }
                $cleanQuery = http_build_query($qs);
                unset($uCanon['query']);
                if ($cleanQuery !== '') {
                    $uCanon['query'] = $cleanQuery;
                }
                $newHref = $this->unparse_url($uCanon);
            }

            $newHref = $this->applyTrailingSlash($newHref);
            $canonicalTag->setAttribute('href', esc_url($newHref));
        } else {
            $home_parts = wp_parse_url(home_url());
            $scheme = isset($home_parts['scheme']) ? $home_parts['scheme'] : (is_ssl() ? 'https' : 'http');
            $host = isset($home_parts['host']) ? $home_parts['host'] : '';
            if (!empty($home_parts['port'])) {
                $host .= ':' . (int) $home_parts['port'];
            }

            $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
            $req = wp_parse_url($request_uri);
            $path = isset($req['path']) ? ltrim((string) $req['path'], '/') : '';

            if (isset($this->variables->language_code)) {
                $lang = (string) $this->variables->language_code;
                if ($lang !== '') {
                    if (strpos($path, $lang . '/') === 0) {
                        $path = substr($path, strlen($lang) + 1);
                    } elseif ($path === $lang) {
                        $path = '';
                    }
                }
                $modifiedUrl = $scheme . '://' . $host . '/' . $lang . ($path !== '' ? '/' . $path : '');
            } else {
                $modifiedUrl = $scheme . '://' . $host . ($path !== '' ? '/' . $path : '');
            }

            if (!empty($req['query'])) {
                // SEO: strip tracking parameters before they end up in canonical.
                // Otherwise UTM/fbclid/gclid create duplicate canonicals → split PageRank,
                // wasted crawl budget. Filterable so site owners can extend.
                parse_str($req['query'], $qs);
                $tracking_exact = apply_filters('conveythis_canonical_tracking_params', [
                    'fbclid', 'gclid', 'msclkid', 'mc_cid', 'mc_eid', '_ga', 'yclid', 'dclid',
                    '_hsenc', '_hsmi', 'igshid', 'mkt_tok',
                ]);
                $tracking_prefixes = ['utm_'];
                foreach (array_keys($qs) as $k) {
                    $kl = strtolower($k);
                    if (in_array($kl, $tracking_exact, true)) { unset($qs[$k]); continue; }
                    foreach ($tracking_prefixes as $p) {
                        if (strpos($kl, $p) === 0) { unset($qs[$k]); break; }
                    }
                }
                $cleanQuery = http_build_query($qs);
                if ($cleanQuery !== '') {
                    $modifiedUrl .= '?' . $cleanQuery;
                }
            }

            // SEO: apply trailing slash BEFORE esc_url so the final canonical matches
            // the page's actual permalink (esc_url may normalize differently).
            $modifiedUrl = $this->applyTrailingSlash($modifiedUrl);
            $modifiedUrl = esc_url($modifiedUrl);
            $head = $doc->getElementsByTagName('head')->item(0);
            if (!empty($head)) {
                $newCanonical = $doc->createElement('link');
                $newCanonical->setAttribute('rel', 'canonical');
                $newCanonical->setAttribute('href', $modifiedUrl);
                $head->appendChild($newCanonical);
            }
        }

        return $doc->saveHTML();
    }


    function domRecursiveApply($doc, $items) {
        $this->print_log("* domRecursiveApply()");
        foreach ($doc->childNodes as $child) {
            if ($child->nodeType === 3) {
                $value = $child->textContent;
                $segment = $this->searchSegment($value, $items);
                if (!empty($segment)) {
                    $child->textContent = $segment;
                }
            } else {
                if ($child->nodeType === 1) {
                    if ($child->hasAttribute('title')) {
                        $attrValue = $child->getAttribute('title');
                        $segment = $this->searchSegment($attrValue, $items);
                        if (!empty($segment)) {
                            $child->setAttribute('title', $segment);
                        }
                    }

                    if ($child->hasAttribute('alt')) {
                        $attrValue = $child->getAttribute('alt');
                        $segment = $this->searchSegment($attrValue, $items);

                        if (!empty($segment)) {
                            $child->setAttribute('alt', $segment);
                        }
                    }

                    if ($child->hasAttribute('placeholder')) {
                        $attrValue = $child->getAttribute('placeholder');
                        $segment = $this->searchSegment($attrValue, $items);

                        if (!empty($segment)) {
                            $child->setAttribute('placeholder', $segment);
                        }
                    }

                    if ($child->hasAttribute('type')) {
                        $attrValue = trim($child->getAttribute('type'));

                        if (strcasecmp($attrValue, 'submit') === 0 || strcasecmp($attrValue, 'reset') === 0) {
                            if ($child->hasAttribute('value')) {
                                $attrValue = $child->getAttribute('value');
                                $segment = $this->searchSegment($attrValue, $items);

                                if (!empty($segment)) {
                                    $child->setAttribute('value', $segment);
                                }
                            }
                        }
                    }

                    if (strcasecmp($child->nodeName, 'img') === 0) {
                        if ($child->hasAttribute('src')) {
                            $metaAttrValue = trim($child->getAttribute('src'));

                            if (!empty($metaAttrValue)) {
                                if (strpos($metaAttrValue, '//') === false) {
                                    if (strncmp($metaAttrValue, $this->variables->site_url, strlen($this->variables->site_url)) !== 0) {
                                        $newAttrValue = rtrim($this->variables->site_url, '/') . '/' . ltrim($metaAttrValue, '/');

                                        $child->setAttribute('src', $newAttrValue);
                                    }
                                }
                            }
                        }
                    }

                    if (strcasecmp($child->nodeName, 'a') === 0) {

                        if ($child->hasAttribute('href')) {
                            $href = $child->hasAttribute('href');

                            if (!filter_var($href, FILTER_VALIDATE_URL)) {
                                $href = preg_replace('/[^\p{L}\p{N}\-._~:\/?#\[\]@!$&\'()*+,;=%]/u', '', $href);
                            }

                            $metaAttrValue = trim($href);

                            if (!empty($metaAttrValue)) {
                                if ($metaAttrValue !== '#') {
                                    if ($child->hasAttribute('translate')) {
                                        $metaAttrValue = trim($child->getAttribute('translate'));

                                        if ($metaAttrValue === 'no') {

                                        } else {
                                            $temp = $this->replaceLink($metaAttrValue, $this->variables->language_code);
                                            $child->setAttribute('href', $temp);
                                        }
                                    } else {
                                        $temp = $this->replaceLink($metaAttrValue, $this->variables->language_code);
                                        $child->setAttribute('href', $temp);
                                    }
                                }
                            }
                        }
                    }

                    if (strcasecmp($child->nodeName, 'meta') === 0) {
                        if ($child->hasAttribute('name') || $child->hasAttribute('property')) {
                            $metaAttributeName = strtolower(trim(
                                $child->hasAttribute('name')
                                    ? $child->getAttribute('name')
                                    : $child->getAttribute('property')
                            ));

                            // Unified with the collection side (domRecursiveRead) so the
                            // two passes never drift. Previously a hardcoded strcasecmp
                            // ladder here missed any field added to $META_FIELD_TO_TYPE
                            // (e.g. twitter:label*/data*).
                            if (isset(self::$META_FIELD_TO_TYPE[$metaAttributeName])) {
                                if ($child->hasAttribute('content')) {
                                    $metaAttrValue = $child->getAttribute('content');
                                    $segment = $this->searchSegment($metaAttrValue, $items);

                                    if (!empty($segment)) {
                                        $child->setAttribute('content', $segment);
                                    }
                                }
                            }
                        }
                    }

                    if (strcasecmp($child->nodeName, 'script') !== 0 && strcasecmp($child->nodeName, 'style') !== 0) {
                        if ($child->hasAttribute('translate')) {
                            $metaAttrValue = trim($child->getAttribute('translate'));

                            if ($metaAttrValue === 'no') {

                            } else {
                                $this->domRecursiveApply($child, $items);
                            }
                        } else {
                            $this->domRecursiveApply($child, $items);
                        }
                    }
                }
            }
        }
    }

    function replaceLink($value, $language_code) {
        $this->print_log("* replaceLink()");
        //  $this->print_log($value);

        $aPos = strpos($value, '//');

        if ($this->isPageExcluded($value, $this->variables->exclusions)) {
            return $value;
        }

        if ($aPos !== false) {
            $ePos = strpos($this->variables->site_url, '//');
            $aStr = substr($value, $aPos);
            $eStr = substr($this->variables->site_url, $ePos);
            $eLen = strlen($eStr);
            if (strncmp($aStr, $eStr, $eLen) !== 0) {
                return $value;
            }
        }

        if (strpos($value, '#') === 0
            || strpos($value, 'mailto:') === 0
            || strpos($value, 'tel:') === 0
            || strpos($value, 'javascript:') === 0) {
            return $value;
        }

        $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
        if (strpos($ext, "?") !== false) {
            $ext = substr($ext, 0, strpos($ext, "?"));
        }

        if (in_array($ext, $this->variables->avoidUrlExt)) {
            return $value;
        }

        if (isset($this->variables->target_languages_translations[$language_code])) {
            $language_code = $this->variables->target_languages_translations[$language_code];
        }

        $link = parse_url($value);

        if (!isset($link['path'])) {
            $link['path'] = '/';
        }

        if (isset($link['path']) && stripos($link['path'], 'wp-admin') === false && stripos($link['path'], 'wp-login') === false) {
            if ($this->variables->translate_links) {
                $pageHost = $this->getPageHost($value);
                if ((!$pageHost || $pageHost == $this->variables->site_host) && $link['path'] && $link['path'] != '/') {
                    if ($this->shouldTranslateLink($link['path'])) {
                        $sourcePath = $link['path'];
                        $lookupPath = $this->canonicalizeLinkPath($link['path']);
                        $item = $this->searchSegmentItem($lookupPath);
                        if (is_array($item) && !empty($item['translate_text'])) {
                            $translated = $item['translate_text'];
                            // Items flagged is_link by the API are URL-slug translations
                            // already sanitized server-side. Trust them directly — same
                            // policy as hreflang (see lookupTranslatedPathForHreflang and
                            // the comment near getLocationForPublicUrl): url_to_postid()
                            // returns 0 for virtual ConveyThis slugs (Cyrillic, CJK,
                            // dashboard-managed renames), so shouldUseTranslatedLinkPath
                            // would falsely reject otherwise-valid translated paths.
                            if (!empty($item['is_link'])) {
                                $link['path'] = $translated;
                            } elseif ($this->shouldUseTranslatedLinkPath($sourcePath, $translated)) {
                                $link['path'] = $translated;
                            } else {
                                $this->print_log("Skipping unresolved translated link path: {$sourcePath} => {$translated}");
                            }
                        }
                    }
                }
            }
            /*
            if ($link['path'] === '/') {
                $link['path'] = substr_replace( $link['path'], $this->variables->site_prefix . '' . $language_code  , 0, strlen( $this->variables->site_prefix ) );
            } else {
                $link['path'] = substr_replace( $link['path'], $this->variables->site_prefix . '' . $language_code . '/', 0, strlen( $this->variables->site_prefix ) );
            }
            */

            if ($link['path'] === '') {
                $link['path'] = substr_replace(
                    $link['path'],
                    $this->variables->site_prefix . $language_code,
                    0,
                    strlen($this->variables->site_prefix)
                );
            } else if ($link['path'] === '/') {
                $link['path'] = substr_replace($link['path'], $this->variables->site_prefix . '' . $language_code, 0, strlen($this->variables->site_prefix));
            } else {
                $link['path'] = substr_replace($link['path'], $this->variables->site_prefix . '' . $language_code . '/', 0, strlen($this->variables->site_prefix));
            }

            $rewritten = $this->unparse_url($link);
            return $this->applyTrailingSlash($rewritten);
        }
        //  $this->print_log($value);
        return $value;
    }

    private function shouldUseTranslatedLinkPath(string $sourcePath, string $translatedPath): bool
    {
        $sourcePath = '/' . ltrim($sourcePath, '/');
        $translatedPath = '/' . ltrim($translatedPath, '/');

        if ($translatedPath === $sourcePath) {
            return true;
        }

        $sourceForLookup = $this->stripLeadingLanguageSegmentFromPath($sourcePath);
        $translatedForLookup = $this->stripLeadingLanguageSegmentFromPath($translatedPath);

        $sourcePostId = url_to_postid(home_url($sourceForLookup));
        if ($sourcePostId <= 0) {
            // If source path is not a WP post/page URL (archives, custom routes), avoid false negatives.
            return true;
        }

        $translatedPostId = url_to_postid(home_url($translatedForLookup));
        return $translatedPostId > 0;
    }

    /**
     * url_to_postid() does not know ConveyThis /{lang}/ prefixes; strip one leading lang segment for lookup.
     */
    private function stripLeadingLanguageSegmentFromPath(string $path): string
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            return '/';
        }
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));
        if ($parts === []) {
            return '/';
        }
        $codes = [];
        if (!empty($this->variables->source_language)) {
            $codes[] = $this->variables->source_language;
        }
        if (!empty($this->variables->target_languages) && is_array($this->variables->target_languages)) {
            $codes = array_merge($codes, $this->variables->target_languages);
        }
        if (!empty($this->variables->target_languages_translations) && is_array($this->variables->target_languages_translations)) {
            $codes = array_merge($codes, array_keys($this->variables->target_languages_translations));
            $codes = array_merge($codes, array_values($this->variables->target_languages_translations));
        }
        $codes = array_unique(array_filter($codes));
        if ($codes !== [] && in_array($parts[0], $codes, true)) {
            array_shift($parts);
        }
        if ($parts === []) {
            return '/';
        }
        return '/' . implode('/', $parts) . '/';
    }

    function unparse_url($parsed_url) {
        $this->print_log("* unparse_url()");
        $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

        // $path is a path segment from parse_url(), not a full URL — FILTER_VALIDATE_URL was always false here.
        $path = preg_replace('/[^\p{L}\p{N}\-._~:\/?#\[\]@!$&\'()*+,;=%]/u', '', $path);

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    function domLoad($output) {
        $this->print_log("* domLoad()");
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        libxml_use_internal_errors(true);

        // Inject UTF-8 charset declaration so DOMDocument interprets the
        // document correctly. Replaces deprecated mb_convert_encoding().
        // Compatible with PHP 7.x and PHP 8.x.
        if (stripos($output, '<head') !== false) {
            $output = preg_replace(
                '/<head(\s[^>]*)?>/i',
                '<head$1><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">',
                $output,
                1
            );
        } else {
            $output = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body>' . $output . '</body></html>';
        }

        $doc->loadHTML($output);
        libxml_clear_errors();
        return $doc;
    }

    function searchSegment($value) {
        $this->print_log("* searchSegment()");
        $item = $this->searchSegmentItem($value);
        if (!$item) {
            return $item; // false (hash miss) or null (no match) — preserves prior contract
        }
        $source_text = $this->normalizeSearchSegmentValue($value);
        // API/dashboard may return the same URL with only different casing for
        // root-relative /wp-content/ paths (absolute https URLs are often unchanged).
        if ($this->isRootRelativeWpContentSegment($source_text)) {
            $translatedRaw = isset($item['translate_text']) ? $item['translate_text'] : '';
            $translatedNorm = trim(html_entity_decode((string) $translatedRaw));
            if ($translatedNorm !== '') {
                if (!extension_loaded('mbstring')) {
                    $sameCaseFolding = (strcasecmp($source_text, $translatedNorm) === 0);
                } else {
                    $sameCaseFolding = (mb_strtolower($source_text, 'UTF-8') === mb_strtolower($translatedNorm, 'UTF-8'));
                }
                if ($sameCaseFolding && strcmp($source_text, $translatedNorm) !== 0) {
                    return $this->restoreTemplateVars($source_text);
                }
            }
        }
        return $this->restoreTemplateVars(str_replace($source_text, $item['translate_text'], $source_text));
    }

    /**
     * Like searchSegment() but returns the full translation item instead of just
     * the rewritten string. Lets callers inspect metadata such as the `is_link`
     * flag (set by the API for URL-slug translations) so they can apply
     * appropriate policy — e.g. trust slug translations directly without running
     * url_to_postid() guards that fail on virtual ConveyThis slugs.
     *
     * Returns the matching item array, false when the source isn't in
     * segments_hash (matches searchSegment's fast-path), or null when no item
     * matches after the three normalization passes.
     */
    function searchSegmentItem($value) {
        $source_text = $this->normalizeSearchSegmentValue($value);

        if (count($this->variables->segments_hash) && !isset($this->variables->segments_hash[md5($source_text)])) {
            return false;
        }

        if (empty($this->variables->items) || $source_text === '') {
            return null;
        }

        // Pass 1: exact match
        foreach ($this->variables->items as $item) {
            $source_text2 = isset($item['source_text']) ? html_entity_decode($item['source_text']) : '';
            if (strcmp($source_text, trim($source_text2)) === 0) {
                return $item;
            }
        }

        // Root-relative /wp-content/ only: case-insensitive matching could pair
        // mixed-case filenames with a different-casing cache/API row.
        if ($this->isRootRelativeWpContentSegment($source_text)) {
            return null;
        }

        // Pass 2: case-insensitive match
        if (!extension_loaded('mbstring')) {
            $sourceLower = iconv('UTF-8', 'utf-8//TRANSLIT//IGNORE', $source_text);
        } else {
            $sourceLower = mb_strtolower($source_text, 'UTF-8');
        }
        $sourceLower = trim($sourceLower);

        foreach ($this->variables->items as $item) {
            $source_text2 = isset($item['source_text']) ? html_entity_decode($item['source_text']) : '';
            if (!extension_loaded('mbstring')) {
                $source2Lower = iconv('UTF-8', 'utf-8//TRANSLIT//IGNORE', $source_text2);
            } else {
                $source2Lower = mb_strtolower($source_text2, 'UTF-8');
            }
            if (strcmp($sourceLower, trim($source2Lower)) === 0) {
                return $item;
            }
        }

        // Pass 3: case-insensitive + tags-stripped match
        foreach ($this->variables->items as $item) {
            $source_text2 = isset($item['source_text']) ? html_entity_decode($item['source_text']) : '';
            if (!extension_loaded('mbstring')) {
                $source2Lower = iconv('UTF-8', 'utf-8//TRANSLIT//IGNORE', $source_text2);
            } else {
                $source2Lower = mb_strtolower($source_text2, 'UTF-8');
            }
            if (strcmp($sourceLower, wp_strip_all_tags($source2Lower)) === 0) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Shared normalization for searchSegment / searchSegmentItem so both paths
     * derive the same source key from the input value (decode HTML entities,
     * protect template vars, strip HTML comments).
     */
    private function normalizeSearchSegmentValue($value) {
        $source_text = html_entity_decode($value);
        $source_text = $this->protectTemplateVars($source_text);
        return trim(preg_replace("/\<!--(.*?)\-->/", "", $source_text));
    }

    /**
     * Root-relative WordPress uploads/assets only (not https://…), matching the
     * case where the API often alters casing only for path-only /wp-content/ URLs.
     * Keeps behavior unchanged for absolute URLs and non-upload segments.
     */
    private function isRootRelativeWpContentSegment(string $value): bool {
        if ($value === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $value) || strpos($value, '//') === 0) {
            return false;
        }
        return stripos($value, '/wp-content/') !== false || stripos($value, 'wp-content/') === 0;
    }

    public function is_wordpress_url($url) {
        $this->print_log("* is_wordpress_url()");
        foreach ($this->variables->wp_patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        return false;
    }

    private function checkRequestURI() {
        $this->print_log("* checkRequestURI()");
        if (is_array($this->variables->system_links) && isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            foreach ($this->variables->system_links as $system_link) {
                if (isset($system_link['link']) && $system_link['link'] == $requestUri) {
                    return false;
                }
            }
        }
        return true;
    }

    public function translatePage($content) {
        $this->print_log("* translatePage()");
        // $this->print_log(gettype($content));
        // $this->print_log("content:");
        //  $this->print_log(json_encode($content));

        if (
            $this->checkRequestURI()
            &&
            (
                is_404() ||
                $this->is_wordpress_url($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])
            )
        ) {
            return $content;
        }

        if (!is_admin() && !empty($this->variables->language_code) && !empty($content)) {
            $this->print_log("!is_admin() && !empty(this->variables->language_code) && !empty(content)");
            if (extension_loaded('xml')) {
                $this->print_log("extension_loaded('xml')");
                $scriptContainer = [];
                $ldscriptContainer = [];
                $ldJsonScripts = [];
                $commentedScripts = [];
                if ($this->variables->translate_structured_data) {
                    $content = preg_replace_callback('#<!--\s*<script([^>]*)>(.*?)</script>\s*-->#s', function ($m) use (&$commentedScripts) {
                        $key = '__COMMENTED_SCRIPT_' . md5($m[0]) . '__';
                        $commentedScripts[$key] = $m[0];
                        return $key;
                    }, $content);
                }

                // Strip all JS content
                $content = preg_replace_callback("#<script([^>]*)>(.*?)</script>#s", function ($matches) use (&$scriptContainer, &$ldscriptContainer) {
                    $originalScript = $matches[2];
                    $scriptKey = md5($originalScript);

                    $scriptContainer[$scriptKey] = $originalScript;

                    // ld+json array
                    //  $this->print_log('$this->variables->translate_structured_data');
                    //  $this->print_log($this->variables->translate_structured_data);
                    if ($this->variables->translate_structured_data && strpos($matches[1], 'type="application/ld+json"') !== false) {
                        $this->print_log('1 - type="application/ld+json"');
                        $data = json_decode($originalScript, true);

                        if ($data !== null) {
                            $this->print_log('2 - type="application/ld+json"');

                            // Stash a clean copy of the pre-translation tree so post-validation
                            // can fall back to the original if the translated JSON-LD is malformed.
                            // Must happen BEFORE any mutation.
                            $this->variables->ldOriginal[$scriptKey] = $data;

                            // Collect text segments first — uses NO_TRANSLATE_KEYS + type rules via the type-aware walker.
                            $this->recursiveAddTextValues($data, $this->variables->segments_seen, $this->variables->NO_TRANSLATE_KEYS);

                            // Then rewrite URLs. The walker is key-aware so protected keys
                            // (sameAs, image, logo, contentUrl, …) keep their original URLs.
                            $this->recursiveReplaceLinks($data);

                            $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            $ldscriptContainer[$scriptKey] = $encoded;

                            return "<script" . $matches[1] . ">" . $scriptKey . "</script>";
                        }
                    }

                    return "<script" . $matches[1] . ">" . $scriptKey . "</script>";
                }, $content);

                if ($this->variables->translate_structured_data) {
                    // Validate JSON-LD scripts
                    $ldJsonScripts = $this->filterLdJsonScripts($ldscriptContainer);

                    $this->print_log('$ldJsonScripts:');
                    $this->print_log(json_encode($ldJsonScripts));
                }

                require_once 'JSLikeHTMLElement.php';

                $doc = $this->domLoad($content);
                $doc->registerNodeClass('DOMElement', 'JSLikeHTMLElement');

                $language = $this->searchLanguage($this->variables->language_code);
                if (isset($language['rtl']) && $this->variables->change_direction && $doc->documentElement) {
                    $doc->documentElement->setAttribute('dir', 'rtl');
                }

                $content = $doc->saveHTML();

                if ($this->variables->plan != 'free') {
                    $this->domRecursiveRead($doc);
                    if (!empty($commentedScripts) && is_array($commentedScripts)) {
                        $commentedKeys = array_keys($commentedScripts);

                        $unsetCount = 0;
                        foreach ($commentedKeys as $key) {
                            if (isset($this->variables->segments[$key])) {
                                unset($this->variables->segments[$key]);
                                $unsetCount++;
                            }
                        }

                        if ($unsetCount < count($commentedKeys)) {
                            foreach ($this->variables->segments as $segmentKey => $segmentValue) {
                                if (strpos($segmentKey, '__COMMENTED_SCRIPT_') === 0) {
                                    unset($this->variables->segments[$segmentKey]);
                                }
                            }
                        }
                    }

                    // $this->print_log('DICT - $this->variables->segments:');
                    // $this->print_log($this->variables->segments);

                    sort($this->variables->segments);

                    $update_cache = isset($_POST['action']) && $_POST['action'] == 'conveythis_update_cache' ? true : false; //phpcs:ignore

                    $cacheKey = md5(serialize(array_merge(
                        $this->variables->segments,
                        $this->variables->links,
                        [$this->variables->referrer, self::CONVEYTHIS_SEO_CACHE_VERSION]
                    )));
                    $this->variables->items = $this->ConveyThisCache->get_cached_translations($this->variables->source_language, $this->variables->language_code, $this->variables->referrer, $cacheKey);
                    //  $this->print_log('CACHED - $this->variables->items');
                    // $this->print_log($this->variables->items);

                    $this->variables->segments = $this->filterSegments($this->variables->segments);
                    // $this->print_log('ARRAY - $this->variables->segments:');
                    //  $this->print_log($this->variables->segments);
                    if (!empty($this->variables->items) && !$this->allowCache($this->variables->items)) {
                        $this->print_log('!empty($this->variables->items) && !$this->allowCache($this->variables->items)');
                        $this->ConveyThisCache->clear_cached_translations(false, $this->variables->referrer, $this->variables->source_language, $this->variables->language_code);
                    }
                    if (empty($this->variables->items)) {
                        for ($i = 1; $i <= 3; $i++) {
                            // $this->print_log("$i".' json_encode(for$this->variables->segments)');
                            //  $this->print_log(json_encode($this->variables->segments));
                            // All segments stay in 'segments' so the regular API path persists
                            // them to DB (preventing retranslation on L1 expiry).
                            // The SEO-specific arrays are ADDITIVE metadata for quality routing —
                            // they hint to the API which segments deserve the quality provider chain
                            // but do NOT partition segments away from the DB-persistent path.
                            $payload = [
                                'referrer'           => $this->variables->referrer,
                                'source_language'    => $this->variables->source_language,
                                'target_language'    => $this->variables->language_code,
                                'segments'           => $this->variables->segments,
                                'links'              => $this->variables->links,
                                'use_trailing_slash' => $this->variables->use_trailing_slash,
                            ];

                            if ($this->variables->plan != 'free') {
                                $seoMeta = $this->splitSegmentsByType('seo_meta');
                                $seoLd   = $this->splitSegmentsByType('seo_structured_data');

                                if (!empty($seoMeta)) {
                                    $payload['seo_meta_segments'] = $this->buildSeoMetaPayload($seoMeta);
                                }
                                if (!empty($seoLd)) {
                                    $payload['seo_structured_data_segments'] = array_values($seoLd);
                                }
                                if (!empty($this->variables->seo_brand)) {
                                    $payload['seo_brand_hint'] = $this->variables->seo_brand;
                                }
                                $extraGlossary = apply_filters('conveythis_seo_preserved_keywords', $this->variables->seo_glossary);
                                if (!empty($extraGlossary) && is_array($extraGlossary)) {
                                    $payload['seo_glossary'] = array_values(array_unique(array_filter($extraGlossary)));
                                }
                            }

                            $response = $this->send('POST', '/website/translate/', $payload, true);
                            $this->print_log('response:');
                            $this->print_log($response);
                            if (isset($response['error'])) {
                                if (!$update_cache) {
                                    header('Location: ' . $this->variables->referrer, true, 302);
                                    exit();
                                }
                                break;
                            }

                            if (!empty($response)) {
                                if (!empty($this->variables->segments)) {
                                    $new_response = array();
                                    $this_segments = $this->variables->segments;
                                    foreach ($response as $response_val) {
                                        foreach ($this_segments as $segments_val) {
                                            if (!empty($response_val["source_text"]) and !empty($segments_val) and $this->comparisonSegments($response_val["source_text"], $segments_val))
                                                $new_response[] = $response_val;
                                        }
                                    }
                                }
                                if (!empty($new_response)) $response = $new_response;
                                $this->variables->items = $response;
                                break;
                            }
                        }

                        $this->variables->items = $this->removeDuplicates($this->variables->items, 'source_text');

                        if ($this->allowCache($this->variables->items)) {
                            $this->ConveyThisCache->save_cached_translations(
                                $this->variables->source_language,
                                $this->variables->language_code,
                                $this->variables->referrer,
                                $this->variables->items,
                                $cacheKey
                            );
                        }

                        $this->print_log('$this->variables->items');
                        $this->print_log($this->variables->items);

                        $clearUrl = $this->getTranslateSiteUrl($this->variables->referrer, $this->variables->language_code);
                        ConveyThisCache::clearPageCache($clearUrl, null);

                        if ($update_cache) {
                            return json_encode(array('success' => true));
                        }
                    }
                }
                if ($this->variables->translate_structured_data) {
                    $translations = [];
                    foreach ($this->variables->items as $item) {
                        $src = trim($item['source_text']);
                        $dst = trim($item['translate_text']);

                        if ($src !== '' && $dst !== '') {
                            $translations[$src] = $dst;
                        }
                    }

                    $this->print_log('$translations:');
                    $this->print_log(json_encode($translations));


                    foreach ($ldJsonScripts as $key => &$jsonData) {
                        $this->print_log('$this->recursiveReplaceTranslations:');

                        $original = $this->variables->ldOriginal[$key] ?? null;

                        $this->recursiveReplaceTranslations($jsonData, $translations, $this->variables->NO_TRANSLATE_KEYS);

                        // Post-validation with fallback to the original untranslated tree on failure —
                        // prevents serving malformed JSON-LD that would break Rich Snippets.
                        if ($this->variables->beta_features && $this->variables->seo_jsonld_validation && is_array($original)) {
                            $valid = $this->validateTranslatedJsonLd($jsonData, $original, $key);
                            if (!$valid) {
                                $mode = apply_filters('conveythis_seo_validation_mode', 'fallback_on_failure');
                                if ($mode === 'fallback_on_failure') {
                                    $jsonData = $original;
                                } elseif ($mode === 'strict') {
                                    unset($scriptContainer[$key]);
                                    continue;
                                }
                                // 'log_only' — keep translation as-is, flag already logged
                            }
                        }

                        // SEO: rewrite schema.org `inLanguage` to the active translated language
                        // AFTER any validation fallback, so even reverted blocks declare the page's
                        // actual language. Otherwise schema validators see lang mismatch on translated
                        // pages → reduced rich-snippet trust.
                        if (!empty($this->variables->language_code)) {
                            $this->rewriteInLanguage($jsonData, $this->variables->language_code);
                        }

                        $scriptContainer[$key] = json_encode($jsonData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                    unset($jsonData);

                    $this->print_log('$scriptContainer:');
                    $this->print_log(json_encode($scriptContainer));
                }

                $this->print_log('$this->variables->segments:');
                $this->print_log(json_encode($this->variables->segments));
                foreach ($this->variables->segments as $segment) {
                    $source_text = trim(preg_replace("/\<!--(.*?)\-->/", "", html_entity_decode($segment)));
                    $this->variables->segments_hash[md5($source_text)] = 1;
                }

                update_option('is_translated', '1');
                $content = $this->replaceSegments($doc);

                // return JS content
                $content = strtr($content, $scriptContainer);
                $content = strtr($content, $commentedScripts);
                $content = html_entity_decode($content, ENT_HTML5, 'UTF-8');

                // Remove C1 control characters (U+0080–U+009F) that are never valid in
                // HTML or JavaScript and can cause SyntaxError in browsers.
                // Compatible with PHP 7.x and PHP 8.x.
                $cleaned = preg_replace('/[\x{0080}-\x{009F}]/u', '', $content);
                if ($cleaned !== null) {
                    $content = $cleaned;
                }

                //$this->print_log("############ content: #############");
                // $this->print_log($content);

            } else {
                $this->print_log("--- NOT extension_loaded('xml')");
            }
        }

        return $content;
    }

    public function removeDuplicates($array, $key) {
        $this->print_log("* removeDuplicates()");
        $tempArray = [];
        $resultArray = [];

        foreach ($array as $item) {
            $value = $item[$key];
            if (!in_array($value, $tempArray)) {
                $tempArray[] = $value;
                $resultArray[] = $item;
            }
        }

        return $resultArray;
    }

    public function filterSegments($segments) {
        $this->print_log("* filterSegments()");
        $res = [];
        if ($segments && is_array($segments)) {
            foreach ($segments as $segment) {
                if (preg_match('/\p{L}/u', $segment)) {
                    // Skip segments that consist entirely of template variables
                    $stripped = preg_replace('/\{\{\{?.+?\}?\}\}/', '', $segment);
                    if (trim($stripped) === '') {
                        continue;
                    }
                    $res[] = $this->protectTemplateVars($segment);
                }
            }
        }
        return $res;
    }

    /**
     * Replace {{ ... }} and {{{ ... }}} template expressions with deterministic
     * placeholders so they are not sent for translation.
     * Compatible with PHP 7.x and PHP 8.x.
     */
    private function protectTemplateVars($text) {
        return preg_replace_callback('/\{\{\{?.+?\}?\}\}/', function ($matches) {
            $original = $matches[0];
            $placeholder = 'CTXNTP_' . substr(md5($original), 0, 10);
            $this->templateVarMap[$placeholder] = $original;
            return $placeholder;
        }, $text);
    }

    /**
     * Restore template-variable placeholders back to their original {{ ... }} form.
     */
    private function restoreTemplateVars($text) {
        if (!empty($this->templateVarMap)) {
            $text = str_replace(
                array_keys($this->templateVarMap),
                array_values($this->templateVarMap),
                $text
            );
        }
        return $text;
    }

    public function allowCache($items) {
        $this->print_log("* allowCache()");
        return count($items) == count($this->variables->segments) ? true : false;
    }

    public function comparisonSegments($response_value, $segments_value) {
        // $this->print_log("* comparisonSegments()");
        $source_text = html_entity_decode($segments_value);
        $source_text = trim(preg_replace("/\<!--(.*?)\-->/", "", $source_text));
        $source_text2 = html_entity_decode($response_value);
        $trimmedResponse = trim($source_text2);
        if (strcmp($source_text, $trimmedResponse) === 0) {
            return true;
        }
        if (
            $this->isRootRelativeWpContentSegment($source_text)
            || $this->isRootRelativeWpContentSegment($trimmedResponse)
        ) {
            return false;
        }
        if (!extension_loaded('mbstring')) {
            $sourceLower = iconv('UTF-8', 'utf-8//TRANSLIT//IGNORE', $source_text);
        } else {
            $sourceLower = mb_strtolower($source_text, 'UTF-8');
        }
        $source_text = trim($sourceLower);
        $source_text2 = html_entity_decode($response_value);
        if (!extension_loaded('mbstring')) {
            $source2Lower = iconv('UTF-8', 'utf-8//TRANSLIT//IGNORE', $source_text2);
        } else {
            $source2Lower = mb_strtolower($source_text2, 'UTF-8');
        }
        if (strcmp($source_text, trim($source2Lower)) === 0) {
            return true;
        }
        $source_text2 = html_entity_decode($response_value);
        if (!extension_loaded('mbstring')) {
            $source2Lower = iconv('UTF-8', 'utf-8//TRANSLIT//IGNORE', $source_text2);
        } else {
            $source2Lower = mb_strtolower($source_text2, 'UTF-8');
        }
        if (strcmp($source_text, wp_strip_all_tags($source2Lower)) === 0) {
            return true;
        }

        return false;
    }

    // ======================================================================
    // SEO Translation Quality helpers
    // ======================================================================

    /**
     * Partition $this->variables->segments by declared content_type.
     * Unknown/untagged segments fall into 'regular'.
     */
    private function splitSegmentsByType(string $type): array {
        $out = [];
        foreach ($this->variables->segments as $segment) {
            $segmentType = $this->variables->segment_meta[$segment]['content_type'] ?? 'regular';
            if ($segmentType === $type) {
                $out[$segment] = $segment;
            }
        }
        return $out;
    }

    /**
     * Build the wire-format payload for seo_meta_segments — an array of
     * {text, meta_field} objects so the API can apply per-field length limits.
     */
    private function buildSeoMetaPayload(array $segments): array {
        $out = [];
        foreach ($segments as $segment) {
            $meta = $this->variables->segment_meta[$segment] ?? [];
            $out[] = [
                'text'       => $segment,
                'meta_field' => $meta['meta_field'] ?? 'description',
            ];
        }
        return $out;
    }

    /**
     * Defensive post-translation length enforcement for meta fields.
     * Word-boundary-aware truncation with a minimum-ratio guard to avoid catastrophic cuts.
     * Records a quality flag when truncation fires.
     */
    private function enforceMetaLengthLimits(string $original, string $translated, string $metaField): array {
        if (!$this->variables->seo_enforce_length) {
            return ['text' => $translated, 'flagged' => false];
        }

        $limits = apply_filters('conveythis_seo_meta_max_length', [
            'description'         => 160,
            'og:description'      => 200,
            'twitter:description' => 200,
            'title'               => 60,
            'og:title'            => 70,
            'twitter:title'       => 70,
            'keywords'            => 255,
            // Twitter label/data: spec does not hard-cap, 70 matches twitter:title
            // and keeps render graceful on narrow previews.
            'twitter:label1'      => 70,
            'twitter:data1'       => 70,
            'twitter:label2'      => 70,
            'twitter:data2'       => 70,
        ], $this->variables->language_code);

        $limit = isset($limits[$metaField]) ? (int) $limits[$metaField] : null;
        if ($limit === null || mb_strlen($translated, 'UTF-8') <= $limit) {
            return ['text' => $translated, 'flagged' => false];
        }

        $candidate = mb_substr($translated, 0, $limit, 'UTF-8');
        $lastSpace = mb_strrpos($candidate, ' ', 0, 'UTF-8');
        $minRatio = (float) apply_filters('conveythis_seo_truncation_min_ratio', 0.6);
        if ($lastSpace !== false && $lastSpace > $limit * $minRatio) {
            $candidate = mb_substr($candidate, 0, $lastSpace, 'UTF-8');
        }
        $candidate = rtrim($candidate, " ,;:.\xC2\xA0") . '…';

        $this->logSeoQualityFlag([
            'type'       => 'meta_length_truncated',
            'meta_field' => $metaField,
            'lang'       => $this->variables->language_code,
            'src_len'    => mb_strlen($original, 'UTF-8'),
            'out_len'    => mb_strlen($translated, 'UTF-8'),
            'limit'      => $limit,
            'truncated'  => mb_strlen($candidate, 'UTF-8'),
            'ts'         => time(),
        ]);

        return ['text' => $candidate, 'flagged' => true];
    }

    /**
     * Task 12 (rev 2026-04-17): O(1) ring-buffer write into the quality-flags transient.
     * Advisory data — read-modify-write race tolerated, not transactional.
     * Each new entry is tagged with the pre-increment head counter as a stable `id`
     * for the dismiss action; pre-upgrade entries without `id` render without action
     * buttons and age out naturally.
     */
    private function logSeoQualityFlag(array $entry) {
        $key  = 'conveythis_seo_quality_flags';
        $max  = (int) apply_filters('conveythis_seo_quality_flags_max', 1000);
        if ($max <= 0) $max = 1000;
        $ttl  = (int) apply_filters('conveythis_seo_quality_flags_ttl', 7 * DAY_IN_SECONDS);

        $state = get_transient($key);
        if (!is_array($state) || !isset($state['head'], $state['buf']) || !is_array($state['buf'])) {
            $state = ['head' => 0, 'buf' => array_fill(0, $max, null)];
        }
        if (count($state['buf']) !== $max) {
            $state['buf'] = array_fill(0, $max, null);
            $state['head'] = 0;
        }

        $entry['id'] = (int) $state['head'];
        $state['buf'][$state['head'] % $max] = $entry;
        $state['head']++;
        if ($state['head'] > 1000000) $state['head'] = $max;

        set_transient($key, $state, $ttl);
    }

    /**
     * Task 12 (rev 2026-04-17): last-24h aggregate for the settings UI + admin-notice trigger.
     * Dismissed entries are skipped — dismissing a fallback can make the admin notice disappear.
     */
    public function getSeoQualityStats(): array {
        $state = get_transient('conveythis_seo_quality_flags');
        $stats = ['translations' => 0, 'fallbacks' => 0, 'truncations' => 0];
        if (!is_array($state) || empty($state['buf'])) return $stats;

        $cutoff = time() - DAY_IN_SECONDS;
        foreach ($state['buf'] as $entry) {
            if (!is_array($entry) || (int) ($entry['ts'] ?? 0) < $cutoff) continue;
            if (!empty($entry['dismissed'])) continue;
            $stats['translations']++;
            $t = $entry['type'] ?? '';
            if      ($t === 'jsonld_fallback')         $stats['fallbacks']++;
            elseif  ($t === 'meta_length_truncated')   $stats['truncations']++;
        }
        return $stats;
    }

    /**
     * Task 12 (rev 2026-04-17): read the quality-flags ring buffer into a
     * newest-first, filter-able array for the review panel. Pure read — no side effects.
     *
     * Filter options:
     *   - 'type'             => string (e.g. 'jsonld_fallback')
     *   - 'lang'             => string
     *   - 'since'            => int (unix ts)
     *   - 'include_dismissed'=> bool (default false)
     */
    public function getSeoQualityEvents(array $filter = []): array {
        $state = get_transient('conveythis_seo_quality_flags');
        if (!is_array($state) || empty($state['buf'])) return [];

        $includeDismissed = !empty($filter['include_dismissed']);
        $type  = isset($filter['type'])  ? (string) $filter['type']  : null;
        $lang  = isset($filter['lang'])  ? (string) $filter['lang']  : null;
        $since = isset($filter['since']) ? (int)    $filter['since'] : null;

        $out = [];
        foreach ($state['buf'] as $entry) {
            if (!is_array($entry)) continue;
            if (!$includeDismissed && !empty($entry['dismissed'])) continue;
            if ($type !== null && ($entry['type'] ?? '') !== $type) continue;
            if ($lang !== null && ($entry['lang'] ?? '') !== $lang) continue;
            if ($since !== null && (int) ($entry['ts'] ?? 0) < $since) continue;
            $out[] = $entry;
        }
        usort($out, static fn($a, $b) => ((int) ($b['ts'] ?? 0)) <=> ((int) ($a['ts'] ?? 0)));
        return $out;
    }

    /**
     * Task 12 (rev 2026-04-17): mark a ring-buffer entry as dismissed by id.
     * Idempotent; returns true if the entry was found and dismissed, false otherwise.
     */
    public function dismissSeoQualityEvent(int $id): bool {
        $key = 'conveythis_seo_quality_flags';
        $state = get_transient($key);
        if (!is_array($state) || empty($state['buf'])) return false;

        $ttl = (int) apply_filters('conveythis_seo_quality_flags_ttl', 7 * DAY_IN_SECONDS);
        $found = false;
        foreach ($state['buf'] as $i => $entry) {
            if (is_array($entry) && isset($entry['id']) && (int) $entry['id'] === $id) {
                $state['buf'][$i]['dismissed'] = true;
                $found = true;
                break;
            }
        }
        if ($found) set_transient($key, $state, $ttl);
        return $found;
    }

    /**
     * Task 12 (rev 2026-04-17): append a term to the conveythis_seo_glossary option.
     * Re-reads before write to tolerate races with the main settings form.
     * Returns:
     *   'added'   — appended successfully
     *   'empty'   — term was empty after trim
     *   'dup'     — term already present (case-insensitive)
     */
    public function appendSeoGlossaryTerm(string $term): string {
        $term = trim(wp_strip_all_tags($term));
        if ($term === '') return 'empty';
        $term = mb_substr($term, 0, 200);

        $raw = get_option('conveythis_seo_glossary', '');
        $existing = is_array($raw)
            ? $raw
            : array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $raw))));

        foreach ($existing as $t) {
            if (mb_strtolower($t) === mb_strtolower($term)) return 'dup';
        }

        $existing[] = $term;
        update_option('conveythis_seo_glossary', implode("\n", $existing));
        return 'added';
    }

    /**
     * Single-pass JSON-LD post-validator.
     * Returns true if the translated tree is safe to ship; false triggers fallback.
     */
    private function validateTranslatedJsonLd(array $translatedTree, array $originalTree, $scriptKey): bool {
        $encoded = json_encode($translatedTree, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $this->logJsonLdFallback($scriptKey, 'json_encode_failed', null);
            return false;
        }

        $ctx = $translatedTree['@context'] ?? null;
        if (!$this->isValidSchemaOrgContext($ctx)) {
            $this->logJsonLdFallback($scriptKey, 'missing_or_invalid_context', null);
            return false;
        }

        if (!isset($translatedTree['@type'])) {
            $this->logJsonLdFallback($scriptKey, 'missing_type', null);
            return false;
        }

        $diff = $this->diffJsonLdProtectedFields($originalTree, $translatedTree);
        if (!empty($diff)) {
            $this->logJsonLdFallback($scriptKey, 'protected_field_modified', $diff[0]);
            return false;
        }

        return true;
    }

    private function isValidSchemaOrgContext($ctx): bool {
        if (is_string($ctx)) {
            return strpos($ctx, 'schema.org') !== false;
        }
        if (is_array($ctx)) {
            foreach ($ctx as $entry) {
                if (is_string($entry) && strpos($entry, 'schema.org') !== false) return true;
            }
        }
        return false;
    }

    /**
     * Walk original + translated trees in parallel; return first
     * protected-field violation (short-circuit on first hit).
     */
    private function diffJsonLdProtectedFields($original, $translated, $type = null, $path = ''): array {
        if (is_array($original) && isset($original['@type'])) {
            $declaredType = is_array($original['@type']) ? ($original['@type'][0] ?? null) : $original['@type'];
            if (is_string($declaredType)) {
                $type = $declaredType;
            }
        }

        if (!is_array($original)) {
            return $original === $translated ? [] : [['path' => $path, 'orig' => $original, 'trans' => $translated]];
        }

        foreach ($original as $key => $oVal) {
            $childPath = $path === '' ? (string) $key : $path . '.' . $key;
            $tVal = is_array($translated) && array_key_exists($key, $translated) ? $translated[$key] : null;

            if (is_string($key) && isset($this->variables->NO_TRANSLATE_KEYS[$key]) && $oVal !== $tVal) {
                return [['path' => $childPath, 'orig' => $oVal, 'trans' => $tVal, 'reason' => 'NO_TRANSLATE_KEYS modified']];
            }

            if ($type !== null && $this->variables->isPathProtected($type, $childPath) && $oVal !== $tVal) {
                return [['path' => $childPath, 'orig' => $oVal, 'trans' => $tVal, 'reason' => 'type rule violated']];
            }
            if ($type !== null && $this->variables->isPathProtected($type, (string) $key) && $oVal !== $tVal) {
                return [['path' => $childPath, 'orig' => $oVal, 'trans' => $tVal, 'reason' => 'type rule violated (leaf)']];
            }

            if (is_array($oVal) && is_array($tVal)) {
                $sub = $this->diffJsonLdProtectedFields($oVal, $tVal, $type, $childPath);
                if (!empty($sub)) return $sub;
            }
        }
        return [];
    }

    private function logJsonLdFallback($scriptKey, string $reason, $diffEntry) {
        $this->logSeoQualityFlag([
            'type'        => 'jsonld_fallback',
            'script_key'  => $scriptKey,
            'reason'      => $reason,
            'path'        => is_array($diffEntry) ? ($diffEntry['path'] ?? null) : null,
            'original'    => (is_array($diffEntry) && is_string($diffEntry['orig'] ?? null))
                ? mb_substr($diffEntry['orig'], 0, 200) : null,
            'translated'  => (is_array($diffEntry) && is_string($diffEntry['trans'] ?? null))
                ? mb_substr($diffEntry['trans'], 0, 200) : null,
            'lang'        => $this->variables->language_code,
            'referrer'    => $this->variables->referrer ?? '',
            'ts'          => time(),
        ]);
    }

    /**
     * First-run brand autodetection from bloginfo + Yoast/RankMath/SEOPress.
     * Idempotent — runs once per site lifetime (gated by conveythis_seo_brand_autodetected).
     */
    private function autoDetectBrand() {
        if (get_option('conveythis_seo_brand_autodetected')) return;

        $brand = trim((string) get_bloginfo('name'));

        if (defined('WPSEO_VERSION')) {
            $opts = get_option('wpseo_titles');
            if (is_array($opts) && !empty($opts['company_name'])) {
                $brand = $opts['company_name'];
            }
        } elseif (defined('RANK_MATH_VERSION')) {
            $opts = get_option('rank-math-options-titles');
            if (is_array($opts) && !empty($opts['knowledgegraph_name'])) {
                $brand = $opts['knowledgegraph_name'];
            }
        } elseif (function_exists('seopress_titles_single_titles')) {
            $opt = get_option('seopress_social_option_name');
            if (is_array($opt) && !empty($opt['seopress_social_knowledge_name'])) {
                $brand = $opt['seopress_social_knowledge_name'];
            }
        }

        $brand = apply_filters('conveythis_seo_brand_name', $brand);

        if (!empty($brand)) {
            update_option('conveythis_seo_brand', $brand);
            update_option('conveythis_seo_brand_autodetected', time());
            $this->variables->seo_brand = $brand;
        }
    }

    /**
     * Discover the site's sitemap URL based on known SEO plugins or WP core.
     */
    private function discover_sitemap_url() {
        if (class_exists('WPSEO_Sitemaps_Router'))      return home_url('/sitemap_index.xml');
        if (class_exists('RankMath\\Sitemap\\Router'))  return home_url('/sitemap_index.xml');
        if (function_exists('seopress_get_service'))    return home_url('/sitemaps.xml');
        if (function_exists('get_sitemap_url'))         return get_sitemap_url('index');
        return null;
    }

    /**
     * Non-blocking hint to Google to re-crawl the sitemap after SEO pipeline upgrade.
     */
    private function ping_sitemap_to_google() {
        $sitemap = $this->discover_sitemap_url();
        if (empty($sitemap)) return;

        wp_remote_get(
            'https://www.google.com/ping?sitemap=' . urlencode($sitemap),
            ['blocking' => false, 'timeout' => 0.01, 'sslverify' => false]
        );
    }

    /**
     * One-time upgrade gate for the SEO v1 rollout.
     * Wipes stale cached JSON-LD translations, autodetects brand, pings sitemap.
     * Idempotent — flag set LAST so any crash re-runs the whole flow on next request.
     */
    public function maybe_run_seo_v1_upgrade() {
        if (get_option('conveythis_seo_v1_purged')) return;

        if ($this->ConveyThisCache && method_exists($this->ConveyThisCache, 'clear_cached_translations')) {
            $this->ConveyThisCache->clear_cached_translations(true); // L1 wipe
        }
        if (class_exists('ConveyThisCache') && method_exists('ConveyThisCache', 'flush_cache_on_activate')) {
            ConveyThisCache::flush_cache_on_activate(); // L2 wipe via existing page-cache integrations
        }

        $this->autoDetectBrand();
        $this->ping_sitemap_to_google();

        update_option('conveythis_seo_v1_purged', time());
        update_option('conveythis_seo_v1_pinged', time());
        update_option('conveythis_seo_v1_show_notice', 1);
    }

    /**
     * Admin notice shown once after the SEO v1 upgrade with a warm-up CTA.
     */
    public function show_seo_v1_upgrade_notice() {
        if (!get_option('conveythis_seo_v1_show_notice')) return;
        if (!current_user_can('manage_options')) return;
        if (get_option('conveythis_seo_v1_warmed')) return;

        $maxUrls = (int) apply_filters('conveythis_seo_warmup_max_urls', 50);

        echo '<div class="notice notice-info is-dismissible" data-conveythis-seo-v1>';
        echo '<p><strong>' . esc_html__('ConveyThis SEO update v1 deployed.', 'conveythis-translate') . '</strong> ';
        echo esc_html__('Translation cache has been refreshed; the next visit to each translated page will retranslate it using the new SEO-aware pipeline.', 'conveythis-translate');
        echo '</p><p>';
        echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=conveythis_seo_warmup'), 'conveythis_seo_warmup')) . '" class="button button-primary">';
        echo esc_html(sprintf(__('Warm top %d URLs in background', 'conveythis-translate'), (int) $maxUrls));
        echo '</a> &nbsp; ';
        echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=conveythis_seo_warmup_skip'), 'conveythis_seo_warmup_skip')) . '" class="button">';
        echo esc_html__('Skip', 'conveythis-translate');
        echo '</a></p></div>';
    }

    /**
     * Dismissible admin notice that nudges admins to the Translate Structured
     * Data (JSON-LD) toggle on 269.9+. Default remains OFF — this notice only
     * exists to close the discoverability gap. Self-hides once an admin either
     * dismisses explicitly or enables the setting.
     */
    public function show_structured_data_discovery_notice() {
        if (!current_user_can('manage_options')) return;
        if (get_option('translate_structured_data', '0') !== '0') return;
        if (get_option('conveythis_structured_data_notice_dismissed')) return;

        $settings_url = admin_url('admin.php?page=convey_this#translate_structured_data_yes');
        $learn_more   = 'https://www.conveythis.com/help/translate-structured-data';
        $dismiss_url  = wp_nonce_url(
            admin_url('admin-post.php?action=conveythis_dismiss_structured_data_notice'),
            'conveythis_dismiss_structured_data_notice'
        );

        echo '<div class="notice notice-info is-dismissible" data-conveythis-structured-data>';
        echo '<p><strong>' . esc_html__('Translate structured data (JSON-LD) — available in 269.9.', 'conveythis-translate') . '</strong> ';
        echo esc_html__('Your FAQ, Product, and Article rich results currently show English text on translated pages. Turn this on in ConveyThis → General → Translate Structured Data and the plugin will translate headline, description, and FAQ questions/answers while keeping URLs, SKUs, prices, and dates unchanged. Translations that fail validation fall back to the original — no risk of a broken rich result.', 'conveythis-translate');
        echo '</p><p>';
        echo '<a href="' . esc_url($settings_url) . '" class="button button-primary">' . esc_html__('Open settings', 'conveythis-translate') . '</a> &nbsp; ';
        echo '<a href="' . esc_url($learn_more) . '" class="button" target="_blank" rel="noopener">' . esc_html__('Learn more', 'conveythis-translate') . '</a> &nbsp; ';
        echo '<a href="' . esc_url($dismiss_url) . '" class="button-link">' . esc_html__('Dismiss', 'conveythis-translate') . '</a>';
        echo '</p></div>';
    }

    /**
     * admin-post.php handler that persists dismissal of the structured-data
     * discovery notice. Nonce-checked; requires manage_options.
     */
    public function handle_dismiss_structured_data_notice() {
        check_admin_referer('conveythis_dismiss_structured_data_notice');
        if (!current_user_can('manage_options')) wp_die('Forbidden', 403);

        update_option('conveythis_structured_data_notice_dismissed', time());

        $referer = wp_get_referer();
        if (!$referer) {
            $referer = admin_url();
        }
        wp_safe_redirect($referer);
        exit;
    }

    /**
     * Kick off background cache warm-up. Parses sitemap, ranks URLs, schedules cron tick.
     */
    public function handle_seo_warmup_start() {
        check_admin_referer('conveythis_seo_warmup');
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        $sitemap = $this->discover_sitemap_url();
        if (empty($sitemap)) {
            update_option('conveythis_seo_v1_warmed', time());
            wp_safe_redirect(admin_url('admin.php?page=convey_this&warmup=no_sitemap'));
            exit;
        }

        $slash_map = [];
        $errors = [];
        try {
            $this->parseSitemapRecursive($sitemap, $slash_map, $errors, 0);
        } catch (\Throwable $e) {
            $this->print_log('seo warmup parseSitemap failed: ' . $e->getMessage());
        }

        $maxUrls = (int) apply_filters('conveythis_seo_warmup_max_urls', 50);
        $urls = array_slice(array_keys($slash_map), 0, $maxUrls);
        $urls = apply_filters('conveythis_seo_warmup_url_ranker', $urls, $slash_map);

        if (empty($urls)) {
            update_option('conveythis_seo_v1_warmed', time());
            wp_safe_redirect(admin_url('admin.php?page=convey_this&warmup=no_urls'));
            exit;
        }

        set_transient('conveythis_seo_warmup_queue', array_values($urls), HOUR_IN_SECONDS * 6);
        update_option('conveythis_seo_v1_warmed', time());

        if (!wp_next_scheduled('conveythis_seo_warmup_tick')) {
            wp_schedule_single_event(time() + 5, 'conveythis_seo_warmup_tick');
        }

        wp_safe_redirect(admin_url('admin.php?page=convey_this&warmup=started'));
        exit;
    }

    public function handle_seo_warmup_skip() {
        check_admin_referer('conveythis_seo_warmup_skip');
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        update_option('conveythis_seo_v1_warmed', time());
        wp_safe_redirect(admin_url('admin.php?page=convey_this&warmup=skipped'));
        exit;
    }

    /**
     * Task 12 (rev 2026-04-17): clear the entire SEO quality ring buffer.
     * Destructive, nonce-guarded, manage_options only.
     */
    public function handle_clear_seo_quality_log() {
        check_admin_referer('conveythis_clear_seo_quality_log');
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        delete_transient('conveythis_seo_quality_flags');
        wp_safe_redirect(admin_url('admin.php?page=convey_this&seo_quality=cleared#seo-translation-quality'));
        exit;
    }

    public function handle_dismiss_seo_quality_event() {
        check_admin_referer('conveythis_dismiss_seo_quality_event');
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $id = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_POST['id']) ? (int) $_POST['id'] : -1);
        if ($id >= 0) $this->dismissSeoQualityEvent($id);
        wp_safe_redirect(admin_url('admin.php?page=convey_this&seo_quality=dismissed#seo-translation-quality'));
        exit;
    }

    public function handle_add_seo_glossary_term() {
        check_admin_referer('conveythis_add_seo_glossary_term');
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $term = '';
        if (isset($_GET['term']))       $term = (string) wp_unslash($_GET['term']);
        elseif (isset($_POST['term']))  $term = (string) wp_unslash($_POST['term']);
        $id = isset($_GET['id'])
            ? (int) $_GET['id']
            : (isset($_POST['id']) ? (int) $_POST['id'] : -1);
        $result = $this->appendSeoGlossaryTerm($term);
        if ($result === 'added' && $id >= 0) {
            $this->dismissSeoQualityEvent($id);
            $flag = 'term_added';
        } elseif ($result === 'empty') {
            $flag = 'term_empty';
        } elseif ($result === 'dup') {
            // Still dismiss — the user's intent to quiet this event is valid.
            if ($id >= 0) $this->dismissSeoQualityEvent($id);
            $flag = 'term_dup';
        } else {
            $flag = 'dismissed';
        }
        wp_safe_redirect(admin_url('admin.php?page=convey_this&seo_quality=' . $flag . '#seo-translation-quality'));
        exit;
    }

    public function process_seo_warmup_tick() {
        $queue = get_transient('conveythis_seo_warmup_queue');
        if (!is_array($queue) || empty($queue)) return;

        $batchSize = (int) apply_filters('conveythis_seo_warmup_batch_size', 5);
        $batch = array_splice($queue, 0, max(1, $batchSize));

        foreach ($batch as $url) {
            wp_remote_get($url, [
                'blocking'   => true,
                'timeout'    => 30,
                'user-agent' => 'ConveyThisWarmer/1.0',
                'sslverify'  => false,
            ]);
        }

        if (!empty($queue)) {
            set_transient('conveythis_seo_warmup_queue', array_values($queue), HOUR_IN_SECONDS * 6);
            $interval = (int) apply_filters('conveythis_seo_warmup_interval_seconds', 120);
            wp_schedule_single_event(time() + $interval, 'conveythis_seo_warmup_tick');
        } else {
            delete_transient('conveythis_seo_warmup_queue');
        }
    }

    /**
     * Dismissible admin notice when JSON-LD fallback rate exceeds threshold.
     */
    public function show_seo_quality_notice() {
        if (!current_user_can('manage_options')) return;
        $stats = $this->getSeoQualityStats();
        $threshold = (int) apply_filters('conveythis_seo_quality_notice_threshold', 10);
        if (($stats['fallbacks'] ?? 0) < $threshold) return;

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html__('ConveyThis SEO: high JSON-LD fallback rate.', 'conveythis-translate') . '</strong> ';
        echo esc_html(sprintf(
            __('In the last 24h, %d JSON-LD translations fell back to the original. Please review your structured data and translation quality.', 'conveythis-translate'),
            (int) $stats['fallbacks']
        ));
        echo ' <a href="' . esc_url(admin_url('admin.php?page=convey_this') . '#seo-translation-quality') . '">' . esc_html__('Open settings', 'conveythis-translate') . '</a></p>';
        echo '</div>';
    }

    /**
     * Task 12 (rev 2026-04-17): success flash after an SEO-quality action.
     * Reads the seo_quality query arg set by the action handlers and renders a
     * dismissible success notice.
     */
    public function show_seo_quality_flash_notice() {
        if (empty($_GET['page']) || $_GET['page'] !== 'convey_this') return;
        if (empty($_GET['seo_quality']) || !current_user_can('manage_options')) return;
        $map = [
            'cleared'     => __('SEO quality log cleared.', 'conveythis-translate'),
            'dismissed'   => __('Event dismissed.', 'conveythis-translate'),
            'term_added'  => __('Term added to the glossary and event dismissed.', 'conveythis-translate'),
            'term_empty'  => __('Cannot add an empty term to the glossary.', 'conveythis-translate'),
            'term_dup'    => __('Term is already in the glossary.', 'conveythis-translate'),
        ];
        $key = (string) $_GET['seo_quality'];
        if (!isset($map[$key])) return;
        $class = ($key === 'term_empty' || $key === 'term_dup') ? 'notice-warning' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($map[$key]) . '</p></div>';
    }

    /**
     * SEO: rewrite schema.org `inLanguage` to the active translated language.
     * Walks the decoded JSON-LD tree and replaces every `inLanguage` value
     * (string or `{ @type: Language, name|alternateName }`) with the BCP-47
     * code for $this->variables->language_code. Without this, Google sees a
     * mismatch between page URL (/<lang>/) and JSON-LD inLanguage on translated
     * pages — schema-trust drop, possible rich-snippet loss.
     */
    private function rewriteInLanguage(&$data, $langCode) {
        if (!is_array($data) || $langCode === '') return;
        foreach ($data as $key => &$val) {
            if ($key === 'inLanguage') {
                if (is_array($val) && isset($val['@type']) && $val['@type'] === 'Language') {
                    $val['name'] = $langCode;
                    if (isset($val['alternateName'])) {
                        $val['alternateName'] = $langCode;
                    }
                } else {
                    $val = $langCode;
                }
            } elseif (is_array($val)) {
                $this->rewriteInLanguage($val, $langCode);
            }
        }
    }

    // ======================================================================
    // End SEO helpers
    // ======================================================================

    public function recursiveReplaceLinks(&$data, $parentType = null) {
        $this->print_log("* recursiveReplaceLinks()");

        $currentType = is_array($data) && isset($data['@type'])
            ? (is_string($data['@type']) ? $data['@type'] : $parentType)
            : $parentType;

        foreach ($data as $key => &$val) {
            if (is_array($val)) {
                $this->recursiveReplaceLinks($val, $currentType);
                continue;
            }

            if (!is_string($val) || !filter_var($val, FILTER_VALIDATE_URL)) {
                continue;
            }

            // Skip URLs whose key is in NO_TRANSLATE_KEYS (sameAs, image, logo, etc.)
            if (is_string($key) && isset($this->variables->NO_TRANSLATE_KEYS[$key])) {
                continue;
            }

            // Skip URLs protected by per-schema-type rules (e.g. Product.gtin, Review.author.url)
            if ($currentType !== null && $this->variables->isUrlPathProtectedForType($currentType, $key)) {
                continue;
            }

            $replaced_url = $this->replaceLink($val, $this->variables->language_code);
            if ($replaced_url && $replaced_url !== $val) {
                $val = $replaced_url;
            }
        }

        return $data;
    }

    /**
     * SEO: Decide whether a JSON-LD leaf value should be sent to translation.
     * Filters out: URLs, emails, numerics, ISO dates, currency codes, units, phones,
     * single-char strings, strings without letters.
     *
     * Filterable via 'conveythis_seo_jsonld_translatable'.
     */
    public function isTranslatableJsonLdValue($value): bool {
        if (!is_string($value)) return false;
        $value = trim($value);
        if (mb_strlen($value, 'UTF-8') < 2) return false;
        if (!preg_match('/\p{L}/u', $value)) return false;
        if (filter_var($value, FILTER_VALIDATE_URL)) return false;
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) return false;
        if (is_numeric($value)) return false;
        if (preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:?\d{2})?)?$/', $value)) return false;

        if (preg_match('/^[A-Z]{3}$/', $value)) {
            static $currencies = ['USD','EUR','GBP','JPY','CHF','CAD','AUD','CNY','INR','BRL','RUB','MXN','SEK','NOK','DKK','NZD','KRW','SGD','HKD','TRY','ZAR','PLN','THB','IDR'];
            if (in_array($value, $currencies, true)) return false;
        }

        if (preg_match('/^[a-zA-Z]{1,4}$/', $value)) {
            static $units = ['kg','g','mg','lb','oz','ml','l','m','cm','mm','km','ft','in','yd','mi','sec','min','h','px','em','rem','pt','pc'];
            if (in_array(strtolower($value), $units, true)) return false;
        }

        if (preg_match('/^\+?[\d\s\-\(\)]{7,}$/', $value) && !preg_match('/[a-zA-Z]/', $value)) return false;

        return function_exists('apply_filters')
            ? apply_filters('conveythis_seo_jsonld_translatable', true, $value)
            : true;
    }

    public function recursiveAddTextValues(&$data, &$seen, &$NO_TRANSLATE_KEYS, $currentType = null, $pathPrefix = '') {
        $this->print_log("* recursiveAddTextValues()");

        if (is_array($data) && isset($data['@type'])) {
            $declaredType = is_array($data['@type']) ? ($data['@type'][0] ?? null) : $data['@type'];
            if (is_string($declaredType)) {
                $currentType = $declaredType;
            }
        }

        foreach ($data as $key => &$val) {
            $childPath = $pathPrefix === '' ? (string) $key : $pathPrefix . '.' . $key;

            if (is_array($val)) {
                if ($key !== '@type') {
                    $this->recursiveAddTextValues($val, $seen, $NO_TRANSLATE_KEYS, $currentType, $childPath);
                }
                continue;
            }

            if (!is_string($val) || isset($NO_TRANSLATE_KEYS[$key])) continue;

            // Per-type protected paths (dot-path and leaf-key forms)
            if ($currentType !== null
                && $this->variables->isPathProtected($currentType, $childPath)) {
                continue;
            }
            if ($currentType !== null
                && $this->variables->isPathProtected($currentType, (string) $key)) {
                continue;
            }

            $valDecoded = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $valTrimmed = trim($valDecoded);
            if ($valTrimmed === '' || !$this->isTranslatableJsonLdValue($valTrimmed)) continue;

            if (!isset($seen[$valTrimmed])) {
                $aiHint = ($currentType !== null && $this->variables->shouldRouteToAi($currentType, $childPath));

                $this->variables->segments[$valTrimmed] = $valTrimmed;
                $this->variables->segment_meta[$valTrimmed] = [
                    'content_type' => 'seo_structured_data',
                    'jsonld_type'  => $currentType,
                    'jsonld_path'  => $childPath,
                    'ai_hint'      => $aiHint,
                ];
                $seen[$valTrimmed] = true;
            } else {
                $this->variables->jsonld_flags[$valTrimmed] = true;
            }
        }
    }


    public function recursiveReplaceTranslations(&$data, $translations, &$NO_TRANSLATE_KEYS) {
        $this->print_log("* recursiveReplaceTranslations()");
        foreach ($data as $key => &$val) {
            if (is_array($val)) {
                $this->recursiveReplaceTranslations($val, $translations, $NO_TRANSLATE_KEYS);
            } elseif (is_string($val) && !isset($NO_TRANSLATE_KEYS[$key])) {
                $val_trimmed = trim($val);
                $val_trimmed = html_entity_decode($val_trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $val_protected = $this->protectTemplateVars($val_trimmed);
                if (isset($translations[$val_protected])) {
                    $val = $this->restoreTemplateVars($translations[$val_protected]);
                } elseif (isset($translations[$val_trimmed])) {
                    $val = $translations[$val_trimmed];
                }
            }
        }
    }

    function filterLdJsonScripts(array $ldscriptContainer): array {
        $this->print_log("* filterLdJsonScripts()");
        $ldJsonScripts = [];

        foreach ($ldscriptContainer as $key => $scriptContent) {
            $value = json_decode($scriptContent, true);
            if (is_array($value)) {
                $isLdJson = false;
                if (
                    (isset($value['@context']) && strpos($value['@context'], 'schema.org') !== false) ||
                    isset($value['@type']) ||
                    (isset($value[0]) && is_array($value[0]) && isset($value[0]['@type']))
                ) {
                    $isLdJson = true;
                }
                if ($isLdJson) {
                    $ldJsonScripts[$key] = $value;
                }
            }
        }

        return $ldJsonScripts;
    }

    /**
     * Remove glossary rows tied to a target language that is no longer selected for the site.
     *
     * @param array $rules Glossary rules from the form or API sync.
     * @return array
     */
    private function filter_glossary_to_active_target_languages($rules) {
        if (! is_array($rules)) {
            return $rules;
        }
        $raw = null;
        if (isset($_POST['settings']) && is_array($_POST['settings']) && isset($_POST['settings']['target_languages'])) {
            $raw = wp_unslash($_POST['settings']['target_languages']);
        } elseif (isset($_POST['target_languages'])) {
            $raw = wp_unslash($_POST['target_languages']);
        }
        if ($raw === null || $raw === '') {
            return $rules;
        }
        if (is_array($raw)) {
            $codes = array_values(array_filter(array_map('trim', $raw)));
        } else {
            $codes = array_filter(array_map('trim', explode(',', (string) $raw)));
        }
        if (empty($codes)) {
            return $rules;
        }
        $allowed = array_flip($codes);
        $out = [];
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $tl = isset($rule['target_language']) ? trim((string) $rule['target_language']) : '';
            if ($tl !== '' && ! isset($allowed[$tl])) {
                continue;
            }
            $out[] = $rule;
        }
        return array_values($out);
    }

    private function updateRules($rules, $type) {
        $this->print_log("* updateRules() type=" . $type);

        if (is_string($rules)) {
            $rules = json_decode($rules, true);
        }

        if ($type == 'exclusion') {
            $this->send('POST', '/admin/account/domain/pages/excluded/', array(
                'referrer' => '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                'rules' => $rules
            ));
        } elseif ($type == 'glossary') {
            if (is_array($rules)) {
                $rules = $this->filter_glossary_to_active_target_languages($rules);
            }
            $rules_count = is_array($rules) ? count($rules) : 0;
            $this->print_log('[Glossary Save] updateRules(glossary): sending ' . $rules_count . ' rules to API');
            $referrer = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
            $referrer = $this->normalizeReferrerForApi($referrer);
            $rules_for_api = is_array($rules) ? array_values($rules) : [];
            foreach ($rules_for_api as $i => $r) {
                if (isset($r['glossary_id']) && $r['glossary_id'] !== '') {
                    $rules_for_api[ $i ]['glossary_id'] = (int) $r['glossary_id'];
                }
                // API expects null for "all languages" / empty; empty string can break addGlossary or cause wrong duplicate check
                if (array_key_exists('target_language', $rules_for_api[ $i ]) && $rules_for_api[ $i ]['target_language'] === '') {
                    $rules_for_api[ $i ]['target_language'] = null;
                }
                if (array_key_exists('translate_text', $rules_for_api[ $i ]) && $rules_for_api[ $i ]['translate_text'] === '') {
                    $rules_for_api[ $i ]['translate_text'] = null;
                }
                // prevent = do not translate; translate_text is unused; target_language still scopes the rule (null/empty = all languages)
                if ( ! empty( $rules_for_api[ $i ]['rule'] ) && $rules_for_api[ $i ]['rule'] === 'prevent' ) {
                    $rules_for_api[ $i ]['translate_text'] = null;
                }
            }
            $payload = array(
                'referrer' => $referrer,
                'rules' => $rules_for_api
            );
            $body_json = json_encode($payload);
            $this->print_log('[Glossary Save] API request body length=' . strlen($body_json));
            if ($rules_count > 0 && is_array($rules)) {
                $this->print_log('[Glossary Save] First rule: ' . json_encode($rules[0]));
            }
            $api_result = $this->send('POST', '/admin/account/domain/pages/glossary/', $payload);
            $this->print_log('[Glossary Save] API response: ' . (is_array($api_result) ? json_encode($api_result) : gettype($api_result)));
            $debug = [
                'referrer'        => $referrer,
                'rules_count'     => count($rules_for_api),
                'rules'           => $rules_for_api,
                'body_length'     => strlen($body_json),
                'api_response'    => $api_result,
                'api_endpoint'    => 'POST /admin/account/domain/pages/glossary/',
            ];
            if ( ! empty( $this->last_glossary_response_raw ) ) {
                $debug['api_response_raw'] = strlen( $this->last_glossary_response_raw ) > 800 ? substr( $this->last_glossary_response_raw, 0, 800 ) . '...' : $this->last_glossary_response_raw;
            }
            return $debug;
        } elseif ($type == 'exclusion_blocks') {
            $this->send('POST', '/admin/account/domain/excluded/blocks/', array(
                'referrer' => '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                'blocks' => $rules
            ));
        }
    }

    private function send($request_method = 'GET', $request_uri = '', $query = [], $return_error = false) {
        $this->print_log("* send");
        $this->print_log("$request_uri");
        $headers = [
            'X-Api-Key' => $this->variables->api_key
        ];
        if (count($query)) {
            $headers['Content-Type'] = 'application/json; charset=UTF-8';
        }
        if (strpos($request_uri, '/admin/') === 0) {
            $headers['X-Auth-Token'] = API_AUTH_TOKEN;
        }

        $args = [
            'headers' => $headers,
            'body' => count($query) ? json_encode($query) : null,
            'method' => $request_method,
            'redirection' => '10',
            'httpversion' => '1.1',
            'blocking' => true,
            'cookies' => []
        ];

        $response = $this->httpRequest($request_uri, $args, true, $this->variables->select_region);

        if (!is_array($response)) {
            return [];
        }

        $body = $response['body'];
        $code = $response['response']['code'];

        if (strpos($request_uri, 'glossary') !== false) {
            $this->print_log('[Glossary API] request_uri=' . $request_uri . ' method=' . $request_method);
            $this->print_log('[Glossary API] response code=' . $code);
            $this->print_log('[Glossary API] response body (raw)=' . substr($body, 0, 2000) . (strlen($body) > 2000 ? '...' : ''));
            $this->last_glossary_response_raw = $body;
        }

        if (!empty($body)) {
            $data = json_decode($body, true);

            if (!empty($data)) {
                if ($data['status'] == 'success') {
                    return $data['data'];
                } else if ($data['status'] == 'error') {
                    if ($return_error) {
                        return ['error' => $data['message']];
                    }
                    return [];
                } else {
                    if (!empty($data['message'])) {

                        if (is_admin()) {
                            if (!function_exists('add_settings_error')) {
                                include_once(ABSPATH . 'wp-admin/includes/template.php');
                            }
                            $message = esc_html($data['message'], 'conveythis-translate');
                            if (strpos($message, '#')) {
                                $message = str_replace('#', '<a target="_blank" href="https://www.conveythis.com/dashboard/pricing/?utm_source=widget&utm_medium=wordpress">' . __('change plan', 'conveythis-translate') . '</a>', $message);
                            }
                            add_settings_error('conveythis-translate', '501', $message, 'error');
                        }
                    }
                }
            }
        }
        return null;
    }

    private static function httpRequest($url, $args = [], $proxy = true, $region = 'US') {
        // Glossary POST: use longer timeout on first attempt so body is not lost and we avoid double-send
        $is_glossary_post = ( strpos($url, 'glossary') !== false && ! empty($args['method']) && $args['method'] === 'POST' );
        $args['timeout'] = $is_glossary_post ? 30 : 1;
        $response = [];
        $proxyApiURL = ($region == 'EU' && !empty(CONVEYTHIS_API_PROXY_URL_FOR_EU)) ? CONVEYTHIS_API_PROXY_URL_FOR_EU : CONVEYTHIS_API_PROXY_URL;
        if ($proxy) {
            $response = wp_remote_request($proxyApiURL . $url, $args);
        }
        if (is_wp_error($response) || empty($response) || empty($response['body'])) {
            $args['timeout'] = 30;
            $response = wp_remote_request(CONVEYTHIS_API_URL . $url, $args);
        }
        return $response;
    }

    /**
     * Normalize referrer to match API's getHost (strip protocol and www) so domain lookup succeeds.
     *
     * @param string $value Host or URL (e.g. dev.conveythis.com or https://www.dev.conveythis.com).
     * @return string Normalized host (e.g. dev.conveythis.com).
     */
    private function normalizeReferrerForApi($value) {
        if ( ! is_string($value) || $value === '' ) {
            return '';
        }
        $domain = preg_replace('/^(\s)?(http(s)?)?(\:)?(\/\/)?/', '', $value);
        $host = parse_url('http://' . $domain, PHP_URL_HOST);
        if ( is_string($host) ) {
            $host = preg_replace('/^www\./', '', $host);
            return $host;
        }
        return $value;
    }

    /**
     * View page source (logged-in admin): confirms which API hosts the PHP plugin uses. JS widget uses CDN ApiService hosts separately.
     */
    public function output_conveythis_api_debug_comment() {
        if (is_admin() || !function_exists('current_user_can') || !current_user_can('manage_options')) {
            return;
        }
        if (!defined('CONVEYTHIS_API_URL') || !defined('CONVEYTHIS_API_PROXY_URL')) {
            return;
        }
        $app = defined('CONVEYTHIS_APP_URL') ? CONVEYTHIS_APP_URL : '';
        echo "\n<!-- ConveyThis PHP API=" . esc_html(CONVEYTHIS_API_URL)
            . ' proxy_US=' . esc_html(CONVEYTHIS_API_PROXY_URL)
            . ($app !== '' ? ' APP=' . esc_html($app) : '')
            . " -->\n";
    }

    /**
     * When internal REQUEST_URI rewrite missed (encoding mismatch, etc.) but ConveyThis can still
     * resolve the translated path to a source slug, redirect to the canonical translated URL from the API.
     * If find_translation returns nothing, falls back to source path under the same language prefix (e.g. /uk/family/).
     */
    public function maybe_redirect_translated_slug_404() {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (empty($this->variables->translate_links) || !function_exists('is_404') || !is_404()) {
            return;
        }
        if (!empty($this->variables->url_structure) && $this->variables->url_structure === 'subdomain') {
            return;
        }
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }

        $req = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        $path = parse_url($req, PHP_URL_PATH);
        if (!is_string($path) || $path === '' || $path === '/') {
            return;
        }
        $pathWithSlash = '/' . trim(str_replace('\\', '/', $path), '/');
        if (substr($pathWithSlash, -1) !== '/') {
            $pathWithSlash .= '/';
        }

        $tail = $this->stripLeadingLanguageSegmentFromPath($pathWithSlash);
        if ($tail === $pathWithSlash || $tail === '/') {
            return;
        }

        $parts = array_values(array_filter(explode('/', trim($pathWithSlash, '/'))));
        if ($parts === []) {
            return;
        }
        $urlLang = $parts[0];

        $apiLang = $urlLang;
        if (!empty($this->variables->target_languages_translations) && is_array($this->variables->target_languages_translations)) {
            $found = array_search(urldecode($urlLang), $this->variables->target_languages_translations, true);
            if ($found !== false) {
                $apiLang = $found;
            }
        }
        if (!empty($this->variables->source_language) && $apiLang === $this->variables->source_language) {
            return;
        }

        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        $referer = '//' . $host . $pathWithSlash;

        $original_slug = $this->find_original_slug($tail, $this->variables->source_language, $apiLang, $referer);
        if (!is_string($original_slug) || trim($original_slug) === '') {
            return;
        }

        $canonTail = $this->find_translation($original_slug, $this->variables->source_language, $apiLang, $referer);
        if (!is_string($canonTail) || trim($canonTail) === '') {
            $canonTail = $original_slug;
        }

        $tailNorm = '/' . trim($tail, '/') . '/';
        $canonNorm = '/' . trim($canonTail, '/') . '/';
        if ($tailNorm === $canonNorm) {
            return;
        }

        $canonInner = trim($canonTail, '/');
        if ($canonInner === '') {
            return;
        }

        $target = $this->applyTrailingSlash(home_url('/' . $urlLang . '/' . $canonInner . '/'));
        $currentPath = $pathWithSlash;
        $targetPath = wp_parse_url($target, PHP_URL_PATH);
        if (is_string($targetPath) && untrailingslashit($currentPath) === untrailingslashit($targetPath)) {
            return;
        }

        wp_safe_redirect($target, 302);
        exit;
    }

    /**
     * Swap translated URL tail for source slug in REQUEST_URI. $matchTail comes from the lang-prefix
     * regex; it may be percent-encoded while the path in REQUEST_URI is decoded (or the reverse),
     * so a single preg_quote($matchTail) replace often missed and left a 404.
     */
    private function rewriteRequestUriTranslatedTailToOriginal(string $matchTail, string $original_slug): void
    {
        $original_slug = trim($original_slug);
        if ($original_slug === '') {
            return;
        }
        $normalizedOriginal = '/' . trim($original_slug, '/');
        if ($normalizedOriginal !== '/' && substr($normalizedOriginal, -1) !== '/') {
            $normalizedOriginal .= '/';
        }

        $fullUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        $path = parse_url($fullUri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $fullUri;
        }
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');

        $tailRaw = trim($matchTail, '/');
        $tails = [$tailRaw, urldecode($tailRaw), rawurldecode($tailRaw)];
        $decodedForEnc = rawurldecode($tailRaw);
        if ($decodedForEnc !== '' && strpos($decodedForEnc, '/') !== false) {
            $segs = explode('/', $decodedForEnc);
            $encTail = implode('/', array_map('rawurlencode', $segs));
            if ($encTail !== '') {
                $tails[] = $encTail;
            }
        } elseif ($decodedForEnc !== '') {
            $one = rawurlencode($decodedForEnc);
            if ($one !== '') {
                $tails[] = $one;
            }
        }
        if (class_exists('Normalizer')) {
            foreach ($tails as $t) {
                if (!is_string($t) || $t === '') {
                    continue;
                }
                $n = Normalizer::normalize($t, Normalizer::FORM_C);
                if (is_string($n) && $n !== '' && $n !== $t) {
                    $tails[] = $n;
                }
            }
        }
        $tails = array_values(array_unique(array_filter($tails, function ($t) {
            return is_string($t) && $t !== '';
        })));

        $apChars = $this->apostropheLikeChars();
        $moreTails = [];
        foreach ($tails as $t) {
            $stripped = str_replace($apChars, '', $t);
            if ($stripped !== '' && $stripped !== $t) {
                $moreTails[] = $stripped;
            }
            $hyphen = str_replace($apChars, '-', $t);
            if ($hyphen !== '' && $hyphen !== $t) {
                $moreTails[] = $hyphen;
            }
        }
        if ($moreTails !== []) {
            $tails = array_values(array_unique(array_merge($tails, $moreTails)));
        }

        $newPath = $path;
        foreach ($tails as $t) {
            $q = preg_quote($t, '#');
            if (preg_match('#/' . $q . '/?$#u', $newPath)) {
                $newPath = preg_replace('#/' . $q . '/?$#u', rtrim($normalizedOriginal, '/') . '/', $newPath, 1);
                break;
            }
        }

        $query = parse_url($fullUri, PHP_URL_QUERY);
        $_SERVER['REQUEST_URI'] = $newPath . ($query ? '?' . $query : '');
    }

    private function find_translation($slug, $source_language, $target_language, $referer) {
        $this->print_log("* find_translation()");
        $response = $this->send('POST', '/website/find-translation/', array(
            'referrer' => $referer,
            'source_language' => $source_language,
            'target_language' => $target_language,
            'segments' => [$slug]
        ));
        if (count($response)) {
            return $response[0]['translate_text'];
        }
        return false;
    }

    /**
     * ASCII apostrophe, modifier letter apostrophe, curly quotes, prime, fullwidth apostrophe, etc.
     * Different browsers, editors, and MT outputs use different code points for the same word.
     */
    private function apostropheLikeChars() {
        return array(
            "'",
            '`',
            "\xC2\xB4",
            "\xE2\x80\x98",
            "\xE2\x80\x99",
            "\xE2\x80\x9A",
            "\xE2\x80\x9B",
            "\xCA\xBC",
            "\xE2\x80\xB2",
            "\xEF\xBC\x87",
            "\xCA\xBB",
        );
    }

    /**
     * Try several path shapes for find-translation-source: MT/custom slugs may include apostrophes
     * WordPress omits in post_name, or vice versa; Ukrainian letters may differ from stored key.
     */
    private function getOriginalSlugLookupCandidates($slug) {
        if (!is_string($slug) || $slug === '') {
            return [];
        }
        $seen = [];
        $out = [];
        $add = function ($p) use (&$seen, &$out) {
            if (!is_string($p) || $p === '') {
                return;
            }
            $p = trim($p);
            if ($p === '') {
                return;
            }
            if ($p[0] !== '/') {
                $p = '/' . $p;
            }
            $p = preg_replace('#/+#', '/', $p);
            $variants = [$p];
            if (strlen($p) > 1 && substr($p, -1) !== '/') {
                $variants[] = $p . '/';
            }
            foreach ($variants as $variant) {
                if ($variant !== '' && !isset($seen[$variant])) {
                    $seen[$variant] = true;
                    $out[] = $variant;
                }
            }
        };

        $base = trim($slug);
        if (class_exists('Normalizer')) {
            $nfc = Normalizer::normalize($base, Normalizer::FORM_C);
            if (is_string($nfc) && $nfc !== '') {
                $base = $nfc;
            }
        }
        $add($base);

        $apostrophe_chars = $this->apostropheLikeChars();
        $add(str_replace($apostrophe_chars, '', $base));
        $add(str_replace($apostrophe_chars, '-', $base));

        $inner = trim($base, '/');
        if ($inner !== '') {
            $parts = array_filter(explode('/', $inner), function ($s) {
                return $s !== '';
            });
            if ($parts !== []) {
                $sanitized = [];
                foreach ($parts as $part) {
                    $t = sanitize_title(rawurldecode($part));
                    if ($t !== '') {
                        $sanitized[] = $t;
                    }
                }
                if ($sanitized !== []) {
                    $add('/' . implode('/', $sanitized) . '/');
                }
            }
        }

        return $out;
    }

    private function find_original_slug($slug, $source_language, $target_language, $referer) {
        $this->print_log("* find_original_slug()");
        $candidates = $this->getOriginalSlugLookupCandidates($slug);
        foreach ($candidates as $candidate) {
            $original_slug = $this->ConveyThisCache->get_cached_slug($candidate, $target_language, $source_language);
            if ($original_slug) {
                return $original_slug;
            }
        }
        foreach ($candidates as $candidate) {
            $response = $this->send('POST', '/website/find-translation-source/', array(
                'referrer' => $referer,
                'source_language' => $source_language,
                'target_language' => $target_language,
                'segments' => [$candidate]
            ));
            if (count($response) && !empty($response[0]['source_text'])) {
                $original_slug = $response[0]['source_text'];
                $this->ConveyThisCache->save_cached_slug($candidate, $target_language, $source_language, $original_slug);
                return $original_slug;
            }
        }
        $wpPath = $this->findOriginalSlugViaWordPressPermalinkCandidates($candidates);
        if (is_string($wpPath) && $wpPath !== '') {
            return $wpPath;
        }
        return false;
    }

    /**
     * When ConveyThis has no reverse row, still resolve if WordPress already uses this slug (e.g. Cyrillic post_name).
     */
    private function findOriginalSlugViaWordPressPermalinkCandidates(array $candidates): ?string
    {
        if (!function_exists('url_to_postid') || !function_exists('home_url') || !function_exists('get_permalink')) {
            return null;
        }
        foreach ($candidates as $c) {
            if (!is_string($c)) {
                continue;
            }
            $inner = trim($c, '/');
            if ($inner === '') {
                continue;
            }
            $id = url_to_postid(home_url('/' . $inner . '/'));
            if ($id > 0) {
                $pp = wp_parse_url(get_permalink($id), PHP_URL_PATH);
                if (is_string($pp) && $pp !== '') {
                    $out = '/' . trim($pp, '/') . '/';
                    return $out;
                }
            }
        }
        return null;
    }

    private function getTranslateSiteUrl($path, $targetLanguage = '') {
        $this->print_log("* getTranslateSiteUrl()");
        $translateUrl = '';
        if (strlen($path) > 0 && strlen($targetLanguage) > 0) {
            $pageUrl = trim($path);
            $pageUrl = str_replace($this->variables->site_url, '', $pageUrl);
            $pageUrl = str_replace($this->variables->site_prefix, '', $pageUrl);
            $pageUrl = str_replace($this->variables->site_host, '', $pageUrl);
            $pageUrl = str_replace('//', '/', $pageUrl);
            $translateUrl = $this->variables->site_url . '/' . $targetLanguage . $pageUrl;
        }
        return $translateUrl;
    }

    public function get_conveythis_shortcode() {
        $this->print_log("* get_conveythis_shortcode()");
        $widgetPlaceholder = '<div id="conveythis_widget_placeholder_' . $this->variables->shortcode_counter . '" class="conveythis_widget_placeholder"></div>';
        $this->variables->shortcode_counter++;
        return $widgetPlaceholder;
    }

    public static function Instance() {
        if (self::$instance === null) {
            self::$instance = new ConveyThis();
        }
        return self::$instance;
    }

    public static function getCurrentDomain() {
        // $this->print_log("* getCurrentDomain()");
        return str_ireplace('www.', '', parse_url(get_site_url(), PHP_URL_HOST));
    }

    public static function plugin_activate() {
        //$this->print_log("* plugin_activate()");
        $defaultTargetLng = 'en';
        $lng = explode("_", (get_locale()));

        if (is_array($lng) && isset($lng[0]) && strlen($lng[0]) == 2) {
            $defaultTargetLng = $lng[0];
        }

        add_option('api_key', '');
        add_option('conveythis_new_user', '1');
        add_option('is_translated', '0');
        add_option('source_language', $defaultTargetLng);
        add_option('target_languages', []);
        add_option('target_languages_translations', []);
        add_option('style_change_language', []);
        add_option('style_change_flag', []);
        add_option('style_flag', 'rect');
        add_option('style_text', 'full-text');
        add_option('style_position_vertical', 'bottom');
        add_option('style_position_horizontal', 'right');
        add_option('style_indenting_vertical', '0');
        add_option('style_indenting_horizontal', '24');
        add_option('auto_translate', '0');
        add_option('hide_conveythis_logo', '1');
        add_option('dynamic_translation', '0');
        add_option('translate_media', '1');
        add_option('translate_document', '0');
        add_option('translate_links', '0');
        add_option('translate_structured_data', '0');
        add_option('no_translate_element_id', '');
        add_option('no_translate_element_classes', '');
        add_option('change_direction', '0');
        add_option('alternate', '1');
        add_option('accept_language', '0');
        add_option('blockpages', []);
        add_option('show_javascript', '1');
        add_option('mb_admin_notice', []);
        add_option('style_position_type', 'fixed');
        add_option('style_position_vertical_custom', 'bottom');
        add_option('style_selector_id', '');
        add_option('conveythis_clear_cache', '0');
        add_option('conveythis_select_region', 'US');
        add_option('url_structure', 'regular');
        add_option('style_background_color', '#ffffff');
        add_option('style_hover_color', '#f6f6f6');
        add_option('style_border_color', '#e0e0e0');
        add_option('style_text_color', '#000000');
        add_option('style_corner_type', 'rect');
        add_option('custom_css_json', '');
        add_option('style_widget', 'dropdown');
        add_option('conveythis_system_links', []);
        add_option('use_trailing_slash', 0);

        self::sendEvent('activate');
    }

    public static function plugin_deactivate() {
        self::sendEvent('deactivate');
    }

    public static function plugin_uninstall() {
        delete_option('api_key');
        delete_option('conveythis_new_user');
        delete_option('is_translated', '0');
        delete_option('source_language');
        delete_option('target_languages');
        delete_option('target_languages_translations');
        delete_option('style_change_language');
        delete_option('style_change_flag');
        delete_option('style_flag');
        delete_option('style_text');
        delete_option('style_position_vertical');
        delete_option('style_position_horizontal');
        delete_option('style_indenting_vertical');
        delete_option('style_indenting_horizontal');
        delete_option('auto_translate');
        delete_option('hide_conveythis_logo');
        delete_option('dynamic_translation');
        delete_option('translate_media');
        delete_option('translate_document');
        delete_option('translate_links');
        delete_option('translate_structured_data');
        delete_option('no_translate_element_id');
        delete_option('no_translate_element_classes');
        delete_option('change_direction');
        delete_option('alternate');
        delete_option('accept_language');
        delete_option('blockpages');
        delete_option('show_javascript');
        delete_option('mb_admin_notice');
        delete_option('style_position_type');
        delete_option('style_position_vertical_custom');
        delete_option('style_selector_id');
        delete_option('url_structure');
        delete_option('conveythis_clear_cache');
        delete_option('conveythis_select_region');

        delete_option('style_background_color');
        delete_option('style_hover_color');
        delete_option('style_border_color');
        delete_option('style_text_color');
        delete_option('style_corner_type');
        delete_option('custom_css_json');
        delete_option('style_widget');
        delete_option('conveythis_system_links');
        delete_option('is_active_domain');
        delete_option('use_trailing_slash');

        self::sendEvent('uninstall');
    }

    static function plugin_update_option($optionName, $oldValue, $newValue) {
        //$this->print_log("* plugin_update_option()");
        self::optionPermalinkChanged($optionName, $oldValue, $newValue);

        $pluginOption = false;
        $eventName = 'updOption';
        if (!empty($optionName)) {
            if ($optionName == 'api_key') {
                $eventName .= self::getEventOptionName('ApiKey', $oldValue, $newValue);
                $pluginOption = true;
            }

            if ($optionName == 'source_language') {
                $eventName .= self::getEventOptionName('SourceLanguage', $oldValue, $newValue);
                $pluginOption = true;
            }

            if ($optionName == 'target_languages') {
                $eventName .= self::getEventOptionName('TargetLanguage', $oldValue, $newValue);
                $pluginOption = true;
            }
        }

        if ($pluginOption) {
            self::sendEvent($eventName);
        }
    }

    static function optionPermalinkChanged($option, $oldValue, $value) {
        //$this->print_log("* optionPermalinkChanged()");
        if ($option === 'permalink_structure') {
            delete_transient('convey_permalink_structure');
        }
    }

    static function getEventOptionName($name = '', $oldValue = '', $newValue = '') {
        //$this->print_log("* getEventOptionName()");
        $eventName = '';
        if (empty($oldValue) && !empty($newValue)) {
            $eventName .= 'First';
        }
        if (!empty($oldValue) && !empty($newValue)) {
            $eventName .= 'Update';
        }
        $eventName .= $name;

        return $eventName;
    }


    public static function show_activation_message() {
        //$this->print_log("* show_activation_message()");
        $is_set = get_option('api_key');
        if (!file_exists(CONVEYTHIS_VIEWS . '/activation_notice.php') || $is_set) {
            return;
        }
        include_once CONVEYTHIS_VIEWS . '/activation_notice.php';
    }

    public static function sendEvent($event = 'default', $message = '') {
        //$this->print_log("* sendEvent()");
        $key = get_option('api_key') ? get_option('api_key') : 'no_key';
        $response = self::httpRequest('/25/background/event/' . $key . '/' . base64_encode(self::getCurrentDomain()) . '/' . $event . '/');
    }

    function dismissNotice($function) {
        $this->print_log("* dismissNotice()");
        $metaName = 'convey_meta';
        $userMeta = get_user_meta(get_current_user_id(), $metaName, true);
        $userMeta = array_unique(array_filter(array_merge((array)$userMeta, [$function])));
        update_user_meta(get_current_user_id(), $metaName, $userMeta);
        delete_transient($function);
    }

    public function isDismiss($function) {
        $this->print_log("* isDismiss()");
        $isDismiss = false;
        if (!empty($function)) {
            $userMeta = get_user_meta(get_current_user_id(), 'convey_meta', true);
            if (in_array($function, (array)$userMeta, true)) {
                $isDismiss = true;
            }
        }
        return $isDismiss;
    }

    public function getWidgetStyles() {
        $this->print_log("* getWidgetStyles()");
        return $this->variables->widgetStyles;
    }

    public static function modify_admin_bar($wp_admin_bar) {
        //$this->print_log("* modify_admin_bar()");
        if (!is_admin_bar_showing()) {
            return;
        }
        if (!($wp_admin_bar instanceof WP_Admin_Bar)) {
            return;
        }
        $class_to_add = 'conveythis-no-translate';
        $nodes = $wp_admin_bar->get_nodes();
        if (empty($nodes)) {
            return;
        }
        foreach ($nodes as $node) {
            if (!is_object($node)) {
                continue;
            }
            $args = $node;
            if (is_array($args->meta)) {
                $args->meta['class'] = empty($args->meta['class'])
                    ? $class_to_add
                    : (strpos($args->meta['class'], $class_to_add) === false
                        ? $args->meta['class'] . ' ' . $class_to_add
                        : $args->meta['class']);
                try {
                    $wp_admin_bar->add_node($args);
                } catch (Exception $e) {
                    //  ConveyThis::customLogs("Function modify_admin_bar:\n" . $e);
                }
            }
        }
    }

    private function isPageExcluded($pageUrl, $rules) {
        $this->print_log("* isPageExcluded()");
        $this->print_log("^^^link: $pageUrl");
        if (!is_array($rules)) {
            return false;
        }
        $pageUrl = $this->getPageUrl($pageUrl);
        foreach ($rules as $rule) {
            $rowPageUrl = trim($rule['page_url']);
            if ($rule['rule'] == "start") {
                if (preg_match('~^' . $rowPageUrl . '~', $pageUrl)) {
                    return true;
                }
            } else if ($rule['rule'] == "end") {
                if (preg_match('~' . $rowPageUrl . '$~', $pageUrl)) {
                    return true;
                }
            } else if ($rule['rule'] == "contain") {
                if (preg_match('~' . $rowPageUrl . '~', $pageUrl)) {
                    return true;
                }
            } else if ($rule['rule'] == "equal") {
                $parsed_path = parse_url($rowPageUrl, PHP_URL_PATH);
                if ($parsed_path === null || $parsed_path === '') {
                    $parsed_path = '/';
                }
                $parsed_path = rtrim($parsed_path, '/');
                $pageUrl = rtrim($pageUrl, '/');
                if (strcasecmp($rule['page_url'], $pageUrl) == 0 || strcasecmp($parsed_path, $pageUrl) == 0) {
                    return true;
                }
            }
        }
        $this->print_log("***link: $pageUrl is not excluded");
        return false;
    }

    public function getVariables() {
        $this->print_log("* getVariables()");
        return $this->variables;
    }

    private function urlExists($url) {
        $this->print_log("* urlExists()");
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // error_log('$http_code === 200' . " " . print_r($http_code === 200, true));
        return ($http_code === 200);
    }

    function print_log($message, $clear = false) {
        $logFile = dirname(__DIR__) . '/print.log';
        $maxSize = 25 * 1024 * 1024; // 25 MB
        if (file_exists($logFile) && filesize($logFile) > $maxSize) {
            file_put_contents($logFile, ""); // Clear the log file
        }
        if ($clear == true) {
            file_put_contents($logFile, ""); // Clear the log file
        }
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }
        $dateTime = new DateTime("now", new DateTimeZone('America/New_York'));
        $formattedTime = $dateTime->format('Y-m-d H:i:s');
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $callingFile = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : ''; // php file name (which file is writing log currently)
        $delimiter = 'conveythis.com';
        if (strpos($callingFile, $delimiter) !== false) {
            $callingFile = strstr($callingFile, $delimiter);
            $callingFile = substr($callingFile, strlen($delimiter)); // keep file name only after "conveythis.com"
        }
        $logEntry = "[$formattedTime] [$callingFile] $message" . PHP_EOL;
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    function stringJsonToCSS($jsonString) {
        $styleObj = json_decode($jsonString, true);
        if (!is_array($styleObj)) {
            return '';
        }
        $css = '';
        foreach ($styleObj as $selector => $rulesString) {
            $css .= $selector . " {\n";
            $rules = array_filter(array_map('trim', explode(';', $rulesString)));
            foreach ($rules as $rule) {
                $css .= "  {$rule};\n";
            }
            $css .= "}\n";
        }
        return $css;
    }


}

