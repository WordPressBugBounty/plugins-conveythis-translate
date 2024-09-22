<?php

    require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

    require_once("index.php");

    $ConveyThis = new ConveyThis();
    $variables = new Variables();
    $ConveyThisCache = new ConveyThisCache();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['api_key'] === $variables->api_key) { //phpcs:ignore
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