<?php

    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

    require_once("index.php");

    $ConveyThis = new ConveyThis();
    $variables = new Variables();
    $ConveyThisCache = new ConveyThisCache();

    // Called by the ConveyThis dashboard to clear the cache after an edit. The key must
    // be set on this site and match exactly: comparing against an unset key let an
    // empty api_key through on sites that were installed but never connected.
    $posted_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash($_POST['api_key'])) : ''; //phpcs:ignore
    $site_key = is_string($variables->api_key) ? $variables->api_key : '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $site_key !== '' && $posted_key !== '' && hash_equals($site_key, $posted_key)) {
        $url = '//' . $_SERVER['HTTP_HOST'] . $_POST['url']; //phpcs:ignore
        $source = $_POST['source']; //phpcs:ignore
        $target = $_POST['target']; //phpcs:ignore

        $url_plugin = "/" . $target . $_POST['url']; //phpcs:ignore

        $page_id = null;
        $pages = get_posts($url_plugin);
        if ($pages) {
            $page_id = $pages[0]->ID;
        }

        $ConveyThisCache::clearPageCache($url_plugin, $page_id);

        $result = $ConveyThisCache->clear_cached_translations(false, $url, $source, $target);

        echo json_encode(["action" => "success"]);
    }
    else
    {
        echo json_encode(["action" => "error"]);
    }

?>