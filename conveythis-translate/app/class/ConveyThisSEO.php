<?php

require_once 'Variables.php';

class ConveyThisSEO
{
    private $variables;

    function __construct()
    {
        $this->variables = new Variables();
    }

    public function sp_serve_custom_sitemaps()
    {
        /*SEOPress*/
        foreach ($this->variables->target_languages as $language) {
            if (get_query_var("post-{$language}-sitemap")) {
                header('Content-Type: application/xml; charset=utf-8');
                echo '<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="' . esc_url(home_url("sitemaps_xsl.xsl")) . '"?>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is properly escaped within the function.
                echo $this->generate_custom_sitemap_content('post', $language);
                exit;
            }
            if (get_query_var("page-{$language}-sitemap")) {
                header('Content-Type: application/xml; charset=utf-8');
                echo '<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="' . esc_url(home_url("sitemaps_xsl.xsl")) . '"?>';
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is properly escaped within the function.
                echo $this->generate_custom_sitemap_content('page', $language);
                exit;
            }
        }
    }

    public function sp_add_query_vars_filter($vars)
    {
        /*SEOPress*/
        foreach ($this->variables->target_languages as $language) {
            $vars[] = "post-{$language}-sitemap";
            $vars[] = "page-{$language}-sitemap";
        }

        return $vars;
    }

    public function sp_custom_sitemaps_rewrite_rule()
    {
        /* Adding rules for custom sitemaps SEOPress */
        foreach ($this->variables->target_languages as $language) {
            add_rewrite_rule("^post-{$language}-sitemap(\d+)\.xml$", "index.php?post-{$language}-sitemap=\$matches[1]", 'top');
            add_rewrite_rule("^page-{$language}-sitemap(\d+)\.xml$", "index.php?page-{$language}-sitemap=\$matches[1]", 'top');
        }
        flush_rewrite_rules();
    }

    public function sp_sitemaps_xml_index($xml)
    {
        /* Adding custom sitemaps by post and page type SEOPress */
        $xml = str_replace('</sitemapindex>', '', $xml);

        $pattern = '/<loc>(.*?(?:post-|page-).*?)<\/loc>/s';
        preg_match_all($pattern, $xml, $matches);
        $urls = $matches[1];

        $sitemap_custom_items = '';

        foreach ($this->variables->target_languages as $language) {
            foreach ($urls as $url) {
                $modifiedUrl = preg_replace('/(post|page)-sitemap(\d*)\.xml$/', '$1-' . $language . '-sitemap$2.xml', $url);

                $sitemap_custom_items .= "<sitemap>\n<loc>{$modifiedUrl}</loc>\n</sitemap>\n";
            }
        }

        $sitemap_custom_items .= '</sitemapindex>';

        return $xml . $sitemap_custom_items;
    }

    public function rm_enable_custom_sitemap()
    {
        /*RankMath*/
        $languages = $this->variables->target_languages;
        foreach ($languages as $language) {
            add_filter('rank_math/sitemap/index', function ($sitemap) use ($language) {
                return $this->rm_add_custom_sitemaps($sitemap, $language);
            }, 10, 2);

            add_filter("rank_math/sitemap/post-{$language}/content", function () use ($language) {
                return $this->generate_custom_sitemap_content('post', $language);
            });

            add_filter("rank_math/sitemap/page-{$language}/content", function () use ($language) {
                return $this->generate_custom_sitemap_content('page', $language);
            });
        }
    }

    public function rm_add_custom_sitemaps($sitemap, $language)
    {
        /* Adding custom sitemaps by post and page type RankMath*/
        if (class_exists('RankMath')) {
            $rank = new RankMath\Settings();
            $settings = $rank->all();
            $is_post_included = $settings["sitemap"]["pt_post_sitemap"];
            $is_page_included = $settings["sitemap"]["pt_page_sitemap"];

            if ($is_post_included) {
                $sitemap .= "
    <sitemap>
        <loc>" . home_url() . "/post-{$language}-sitemap.xml" . "</loc>
        <lastmod>" . gmdate('c') . "</lastmod>
    </sitemap>";
            }

            if ($is_page_included) {
                $sitemap .= "
    <sitemap>
        <loc>" . home_url() . "/page-{$language}-sitemap.xml" . "</loc>
        <lastmod>" . gmdate('c') . "</lastmod>
    </sitemap>";
            }
        }

        return $sitemap;
    }


    function yo_add_custom_sitemaps($sitemaps)
    {
        $custom_sitemaps = '';

        foreach ($this->variables->target_languages as $language) {
            foreach ($sitemaps as $idx => $sitemap) {
                $modifiedUrl = preg_replace('/(post|page)-sitemap(\d*)\.xml$/', '$1-' . $language . '-sitemap$2.xml', $sitemap['loc']);
                $lastmod = gmdate('c', strtotime($sitemap['lastmod']));

                $custom_sitemaps .= "
    <sitemap>
        <loc>" . $modifiedUrl . "</loc>
        <lastmod>" . $lastmod . "</lastmod>
    </sitemap>";
            }
        }

        return $custom_sitemaps;
    }

    public function generate_custom_sitemap_content($type, $language = '')
    {
        /* Build xml content of custom sitemap (RankMath/SEOPress) */
        $args = [
            'post_type' => $type,
            'posts_per_page' => -1,
        ];
        $query = new WP_Query($args);

        $sitemap_content = '';

        while ($query->have_posts()) {
            $query->the_post();
            $url = array(
                "loc" => get_permalink(),
                "mod" => $query->post->post_modified_gmt,
            );
            $sitemap_items = "
    <url>
        <loc>" . get_permalink() . "</loc>
        <lastmod>" . $query->post->post_modified_gmt . "</lastmod>
    </url>";

            $sitemap_content .= $this->sitemap_add_translated_urls($sitemap_items, $url, $language);
        }
        wp_reset_postdata();

        $sitemap_xml = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . $sitemap_content . '</urlset>';
        return $sitemap_xml;
    }

    public function yo_enable_custom_sitemap()
    {
        /* Yoast */
        global $wpseo_sitemaps;

        if (isset($wpseo_sitemaps) && !empty($wpseo_sitemaps) && is_object($wpseo_sitemaps)) {
            foreach ($wpseo_sitemaps->providers as $provider) {
                if ($provider->handles_type('post')) {
                    $sitemaps = $provider->get_index_links(400);
                }
            }

            $filtered_sitemaps = array_filter($sitemaps, function ($sitemap) {
                return strpos($sitemap['loc'], 'post-sitemap') !== false || strpos($sitemap['loc'], 'page-sitemap') !== false;
            });

            add_filter("wpseo_sitemap_index", function () use ($filtered_sitemaps) {
                return $this->yo_add_custom_sitemaps($filtered_sitemaps);
            }, 10, 2);

            foreach ($this->variables->target_languages as $lang) {

                $wpseo_sitemaps->register_sitemap("post-{$lang}", function () use ($lang) {
                    $this->yo_create_custom_sitemap('post');
                });

                $wpseo_sitemaps->register_sitemap("page-{$lang}", function () use ($lang) {
                    $this->yo_create_custom_sitemap('page');
                });
            }
        }
    }

    public function yo_create_custom_sitemap($type)
    {
        /* Build xml content of custom sitemap Yoast*/
        global $wpseo_sitemaps;

        if (isset($wpseo_sitemaps) && !empty($wpseo_sitemaps) && is_object($wpseo_sitemaps)) {
            $args = [
                    'post_type' => $type,
                    'posts_per_page' => -1,
                ];
            $query = new WP_Query($args);
            $urls = array();
            while ($query->have_posts()) {
                $query->the_post();

                $urls[] = $wpseo_sitemaps->renderer->sitemap_url(array(
                    "loc" => get_permalink(),
                    "mod" => get_the_modified_date('c'),
                ));
            }
            wp_reset_postdata();

            $sitemap_body = '
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
' . implode("", $urls) . '
</urlset>';

            $wpseo_sitemaps->set_sitemap($sitemap_body);
        }
    }

    public function rank_math_sitemap_init()
    {
        global $wp_query;
        if (!empty($wp_query)) {
            $type = get_query_var('sitemap', '');
            add_filter('rank_math/sitemap/enable_caching', '__return_false');
            add_filter("rank_math/sitemap/{$type}_urlset", array($this, 'sitemap_add_xhtml_to_urlset'));
        }
    }

    public function wpseo_init_sitemap()
    {
        global $wp_query;
        if (!empty($wp_query)) {
            $type = get_query_var('sitemap', '');
            add_filter("wpseo_sitemap_{$type}_urlset", array($this, 'sitemap_add_xhtml_to_urlset'));
        }
    }

    public function sitemap_add_xhtml_to_urlset($urlset)
    {
        $urlset = str_replace('<urlset', '<urlset xmlns:xhtml="http://www.w3.org/1999/xhtml" ', $urlset);
        return $urlset;
    }

    public function sitemap_add_translated_urls($output, $url, $language = '')
    {
        $actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        if (in_array($url['loc'], $this->variables->blockpages)) // no need to add translated url for blocked pages
            return $output;

        $alternate = "";
        $translatedOutputUrls = array();

        // add source language to alternate
	    $alternate .= "\t<xhtml:link hreflang='x-default' href='" . $url['loc'] . "' rel='alternate'/>\n\t";
	    $alternate .= "\t<xhtml:link hreflang='" . $this->variables->source_language . "' href='" . $url['loc'] . "' rel='alternate'/>\n\t";

        // Per-language sitemap callers (rm_enable_custom_sitemap, sp_serve_custom_sitemaps,
        // generate_custom_sitemap_content) pass the target $language so we render exactly
        // one row per post. The general /sitemap_index.xml hook (rank_math/sitemap/url,
        // wpseo_sitemap_url, seopress_sitemaps_url) has no per-language scope and passes
        // '' — we then iterate all configured languages, matching the original behavior
        // for that index path only.
        //
        // Without this guard, every per-language sitemap re-iterated all
        // target_languages because the loop variable shadowed the parameter,
        // causing 93x the API lookups and the FPM-pool exhaustion documented at
        // docs/superpowers/specs/2026-05-02-sitemap-slug-cache-design.md.
        $languages_to_render = ($language !== '' && is_string($language))
            ? [$language]
            : $this->variables->target_languages;

        foreach ($languages_to_render as $lang) {
            $modifiedResult = $this->modify_url($url, $lang);

            if (str_contains($actual_link, '-' . $lang . '-') === false) {
                $alternate .= $modifiedResult['alternate'];
                continue;
            } else {
                $translatedUrl = $modifiedResult['translatedUrl'];
                $translatedOutputUrls = $modifiedResult['translatedOutputUrls'];
                $alternate .= $modifiedResult['alternate'];
            }
        }

        //add alternate to translated url
        foreach ($translatedOutputUrls as &$value) {
            $value = str_replace("</url>", $alternate . "</url>", $value);
        }

        //add alternate to source url
        $newOutput = str_replace("</url>", $alternate . "</url>", $output);

        $translatedOutput = implode("", $translatedOutputUrls);

        return $translatedOutput ?: $newOutput . $translatedOutput;
    }

    function modify_url($url, $language)
    {

        $site_url = home_url();
        $translatedOutputUrls = array();

        $site_url = str_replace("https://", "", $site_url);
        $site_url = str_replace("http://", "", $site_url);

        if (!empty($this->variables->url_structure) && $this->variables->url_structure == "subdomain") {
            $translatedUrl = str_replace($site_url, $language . "." . $site_url, $url['loc']);
        } else {
            $translatedUrl = str_replace($site_url, $site_url . "/" . $language, $url['loc']);

            // SEO: sitemap URLs MUST match canonical/hreflang. Look up the translated slug
            // and substitute it in (default mode only — subdomain URLs preserve source path).
            // Without this, sitemap lists /<lang>/<source-slug> while the actual page is
            // /<lang>/<translated-slug>; Google then drops the hreflang annotation.
            $sourcePath = isset($url['loc']) ? parse_url($url['loc'], PHP_URL_PATH) : null;
            if (is_string($sourcePath) && $sourcePath !== '' && $sourcePath !== '/' && class_exists('ConveyThis')) {
                $convey = ConveyThis::Instance();
                if ($convey && method_exists($convey, 'lookupTranslatedPathForHreflang')) {
                    $translated = $convey->lookupTranslatedPathForHreflang($sourcePath, $language);
                    if (is_string($translated) && $translated !== '' && $translated !== $sourcePath) {
                        // Replace only the path portion, preserve scheme/host/query.
                        $parts = parse_url($translatedUrl);
                        if ($parts && isset($parts['scheme'], $parts['host'])) {
                            $rebuilt  = $parts['scheme'] . '://' . $parts['host'];
                            if (isset($parts['port']))  $rebuilt .= ':' . $parts['port'];
                            $rebuilt .= '/' . $language . '/' . ltrim($translated, '/');
                            if (isset($parts['query']))    $rebuilt .= '?' . $parts['query'];
                            if (isset($parts['fragment'])) $rebuilt .= '#' . $parts['fragment'];
                            $translatedUrl = $rebuilt;
                        }
                    }
                }
            }
        }


        $loc = "\t\t<loc>" . $translatedUrl . "</loc>\n";
        $lasmod = !empty($url['mod']) ? "\t\t<lastmod>" . gmdate('c', strtotime($url['mod'])) . "</lastmod>\n" : "";
        $images = "";
        if (isset($url['images']) && is_array($url['images'])) {
            foreach ($url['images'] as $image) {
                $images .= "\t\t<image:image><image:loc>" . $image['src'] . "</image:loc></image:image>\n";
            }
        }
        $translatedOutputUrls[] = "\t<url>\n" . $loc . $lasmod . $images . "\t</url>\n";
        $alternate = "\t<xhtml:link hreflang='" . $language . "' href='" . $translatedUrl . "' rel='alternate'/>\n\t";

        return array('translatedUrl' => $translatedUrl, 'translatedOutputUrls' => $translatedOutputUrls, 'alternate' => $alternate);
    }
}

