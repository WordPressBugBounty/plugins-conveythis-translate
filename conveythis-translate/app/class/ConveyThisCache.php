<?php

require_once(ABSPATH . 'wp-admin/includes/file.php');

class ConveyThisCache
{

    public function __construct()
    {

    }

    public static function checkCachePlugin()
    {
        $installCachePlugin = false;
        if (function_exists('w3tc_flush_all')
            || class_exists('LiteSpeed_Cache')
            || function_exists('wp_cache_clear_cache')
            || function_exists('wpfc_clear_all_cache')
            || function_exists('rocket_clean_domain')
            || function_exists('hyper_cache_clean')
            || function_exists('sc_cache_flush')
            || class_exists('Cache_Enabler') || has_action('cachify_flush_cache')) {
            $installCachePlugin = true;
        }
        return $installCachePlugin;
    }

    /*
     * Clearing page cache by installed plugin
     */
    public static function clearPageCache($url = '', $page_id = null)
    {

        if (strlen($url) > 0) {
            if (function_exists('flush_url')) {
                flush_url($url);
            }

            if (class_exists('LiteSpeed_Cache')) {
                LiteSpeed_Cache::plugin()->purge_url($url);
            }

            if (function_exists('wp_cache_clear_cache') && !!$page_id) {
                wp_cache_clear_cache($page_id);
            }

            if (function_exists('wpfc_clear_all_cache') && !!$page_id) {
                wpfc_clear_post_cache($page_id);
            }

            if (function_exists('rocket_clean_files')) {
                rocket_clean_files($url);
            }

            if (function_exists('hyper_cache_clean') && !!$page_id) {
                hyper_cache_invalidate_post($page_id);
            }

            if (function_exists('sc_cache_flush') && !!$page_id) {

            }

            if (class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'clear_total_cache') && !!$page_id) {
                Cache_Enabler::clear_post_cache($page_id);
            }

            if (has_action('cachify_flush_cache') && !!$page_id) {
                do_action('cachify_flush_cache', $page_id);
            }

        }
    }

    public function clearAllCache()
    {
        if ($_POST['conveythis_clear_all_cache'] == true && $_POST['api_key'] == $this->variables->api_key) { //phpcs:ignore
            self::flush_cache_on_activate();
        }
        die( json_encode (['clear' => true]));
    }

    public static function flush_cache_on_activate(){
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        // LiteSpeed
        if (class_exists('LiteSpeed_Cache')) {
            LiteSpeed_Cache::plugin()->purge_all();
        }
        /*if (class_exists('LiteSpeed_Cache_API') && method_exists('LiteSpeed_Cache_API', 'purge_all')) {
            LiteSpeed_Cache_API::purge_all();
        }*/
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache();
        }
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache();
        }
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        if (function_exists('hyper_cache_clean')) {
            hyper_cache_clean();
        }
        // Simple Cache
        if (function_exists('sc_cache_flush')) {
            sc_cache_flush();
        }
        // W3 Total Cache : w3tc
        if (function_exists('w3tc_pgcache_flush')) {
            w3tc_pgcache_flush();
        }
        // WP Super Cache : wp-super-cache
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        // WP Fastest Cache
        if (function_exists('wpfc_clear_all_cache')) {
            wpfc_clear_all_cache(true);
        }
        // WPEngine
        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_varnish_cache')) {
            WpeCommon::purge_memcached();
            WpeCommon::clear_maxcdn_cache();
            WpeCommon::purge_varnish_cache();
        }
        // SG Optimizer by Siteground
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
        }
        // Cache Enabler
        if (class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'clear_total_cache')) {
            Cache_Enabler::clear_total_cache();
        }
        // Comet cache
        if (class_exists('comet_cache') && method_exists('comet_cache', 'clear')) {
            comet_cache::clear();
        }
        // Pagely
        if (class_exists('PagelyCachePurge') && method_exists('PagelyCachePurge', 'purgeAll')) {
            PagelyCachePurge::purgeAll();
        }
        // Autoptimize
        if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
            autoptimizeCache::clearall();
        }
        // Hummingbird Cache
        if (class_exists('\Hummingbird\WP_Hummingbird') && method_exists('\Hummingbird\WP_Hummingbird', 'flush_cache')) {
            \Hummingbird\WP_Hummingbird::flush_cache();
        }
        // Autoptimize
        if (has_action('cachify_flush_cache')) {
            do_action('cachify_flush_cache');
        }
        // ConveyThis forward-direction slug cache (sitemap/hreflang).
        // Bundled here so the existing 'Clear all cache' admin button is one
        // button that resets every cache the plugin owns.
        self::clear_fwd_slugs();
    }

    public function flush_cache($option){

        if ($option == 'target_languages_translations') {

            if (function_exists('w3tc_flush_all')) {
                w3tc_flush_all();
            }
            if (class_exists('LiteSpeed_Cache')) {
                LiteSpeed_Cache::plugin()->purge_all();
            }
            if (function_exists('wp_cache_clear_cache')) {
                wp_cache_clear_cache();
            }
            if (function_exists('wpfc_clear_all_cache')) {
                wpfc_clear_all_cache();
            }
            if ( function_exists( 'rocket_clean_domain' ) ) {
                rocket_clean_domain();
            }
            if (function_exists('hyper_cache_clean')) {
                hyper_cache_clean();
            }
            if (function_exists('sc_cache_flush')) {
                sc_cache_flush();
            }
            if (class_exists('Cache_Enabler') && method_exists('Cache_Enabler', 'clear_total_cache')) {
                Cache_Enabler::clear_total_cache();
            }
            if ( has_action('cachify_flush_cache') ) {
                do_action('cachify_flush_cache');
            }

        }

    }

    public function dismissAllCacheMessages()
    {
        if ($_POST['dismiss']) { //phpcs:ignore
            $this->dismissNotice('all_cache_notice');
        }
    }

    public function save_cached_slug( $slug, $source_language, $target_language, $value ) {

        if ( !file_exists( CONVEYTHIS_CACHE_PATH ) ) {
            mkdir( CONVEYTHIS_CACHE_PATH, 0777, true ); //phpcs:ignore
            $slug_list = array();
        }else {
            $slug_list = file_exists( CONVEYTHIS_CACHE_SLUG_PATH ) ? json_decode( file_get_contents( CONVEYTHIS_CACHE_SLUG_PATH ), true ) : []; //phpcs:ignore
            if ( empty($slug_list) ) {
                $slug_list = array();
            }
        }
        if ( !isset($slug_list[$source_language]) ) {
            $slug_list[$source_language] = array();
        }
        if ( !isset($slug_list[$source_language][$slug]) ) {
            $slug_list[$source_language][$slug] = array();
        }
        $slug_list[$source_language][$slug][$target_language] = $value;
        file_put_contents(CONVEYTHIS_CACHE_SLUG_PATH, json_encode($slug_list) ); //phpcs:ignore
    }

    public function get_cached_translations( $source_language, $target_language, $path, $cacheKey ) {
        $file = CONVEYTHIS_CACHE_TRANSLATIONS_PATH. $source_language. '_'. $target_language. '/'. md5($path). '.json';
        $cacheContent = [];
        if (file_exists($file)) {
            // If cache has been created/modified more than 3 days ago, delete it
            if (time() - filemtime($file) > 259200) {
                @unlink($file);
                return $cacheContent;
            }
            $fileContents = json_decode(file_get_contents($file), true); //phpcs:ignore
            if (isset($fileContents[$cacheKey]) && $fileContents[$cacheKey]) {
                $cacheContent = $fileContents[$cacheKey];
            }
        }
        return $cacheContent;
    }

    public function save_cached_translations($sourceLanguage, $targetLanguage, $path, $data, $cacheKey = 'cache_key')
    {
        $langDir = $this->getCacheLangDir($sourceLanguage, $targetLanguage);
        $cachePath = $langDir . $this->getCacheFileName($path);
        $cacheData[$cacheKey] = $data;
        if ($data) {
            if (!file_exists($langDir)) {
                mkdir($langDir, 0777, true); //phpcs:ignore
            }
            file_put_contents($cachePath, json_encode($cacheData)); //phpcs:ignore
        } elseif (file_exists($cachePath)) {
            unlink($cachePath);
        }
    }

    private function clearDir($dir = '')
    {
        $clearResult = false;

        if (strlen($dir) > 0) {
            $dir_iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);

            foreach (new \RecursiveIteratorIterator($dir_iterator, \RecursiveIteratorIterator::CHILD_FIRST) as $name => $item) {

                if (is_dir($name)) {
                    rmdir($name); //phpcs:ignore
                } else {
                    unlink($name);
                }
            }
            $clearResult = rmdir($dir); //phpcs:ignore
        }
        return $clearResult;
    }
    public function clear_cached_translations($all = false, $path = '', $sourceLanguage = '', $targetLanguage = '')
    {

        $result = false;
        if ($all) {
            if (file_exists(CONVEYTHIS_CACHE_TRANSLATIONS_PATH) && is_dir(CONVEYTHIS_CACHE_TRANSLATIONS_PATH)) {
                $result = $this->clearDir(CONVEYTHIS_CACHE_TRANSLATIONS_PATH);
            }
        } else {
            if (strlen($path) > 0) {
                $cachePath = $this->getCacheLangDir($sourceLanguage, $targetLanguage) . $this->getCacheFileName($path);
                if(file_exists($cachePath)) {
                    $result = unlink($cachePath);
                }
            }
        }

        return $result;
    }

    private function getCacheLangDir($sourceLanguage = '', $targetLanguage = '')
    {
        return CONVEYTHIS_CACHE_TRANSLATIONS_PATH . $sourceLanguage . '_' . $targetLanguage;
    }

    private function getCacheFileName($path)
    {
        return '/' . md5($path) . '.json';
    }

    public function get_cached_slug( $slug, $source_language, $target_language ) {
        if (file_exists( CONVEYTHIS_CACHE_SLUG_PATH )) {
            $slug_list = json_decode( file_get_contents(CONVEYTHIS_CACHE_SLUG_PATH), true ); //phpcs:ignore

            if ( !empty($slug_list) ) {
                if ( isset($slug_list[$source_language][$slug][$target_language]) ) {
                    return $slug_list[$source_language][$slug][$target_language];
                }
            }
        }
        return false;
    }

    /*
     * Forward-direction slug cache (source path -> translated path).
     *
     * Sharded one file per (source_language, target_language) pair under
     * CONVEYTHIS_CACHE_FWD_SLUGS_PATH. Backs ConveyThis::lookupTranslatedPathForHreflang
     * to keep sitemap/hreflang rendering off the API hot path.
     *
     * Spec: docs/superpowers/specs/2026-05-02-sitemap-slug-cache-design.md.
     *
     * Why a separate layout from get_cached_slug / save_cached_slug above:
     *   - Existing slug.json is a single global file shared across all language
     *     pairs; under concurrent FPM workers writes race and the loser's entries
     *     get clobbered. The reverse-direction caller (find_original_slug) is low
     *     traffic so the race is invisible there.
     *   - Sitemap rendering hits this path for hundreds of (path, lang) tuples in
     *     one request, with many workers in parallel. We need flock and shard
     *     isolation, not a global JSON blob.
     *   - We deliberately leave the legacy file alone so reverse-direction code
     *     keeps working unchanged.
     */

    private static function fwdShardPath($source_language, $target_language) {
        $src = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $source_language);
        $tgt = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $target_language);
        if ($src === '' || $tgt === '') {
            return null;
        }
        return CONVEYTHIS_CACHE_FWD_SLUGS_PATH . $src . '_' . $tgt . '.json';
    }

    private static function fwdEntryKey($source_path) {
        return sha1((string) $source_path);
    }

    private static function fwdEnsureDir() {
        if (!file_exists(CONVEYTHIS_CACHE_FWD_SLUGS_PATH)) {
            @mkdir(CONVEYTHIS_CACHE_FWD_SLUGS_PATH, 0777, true); //phpcs:ignore
        }
    }

    /*
     * Read-modify-write under flock(LOCK_EX) with atomic rename-into-place.
     * Mutator receives the decoded array and returns the new array (or null to
     * skip the write). Falls through silently on disk-full / permission errors —
     * callers degrade to live API lookups, which is the existing behavior.
     */
    private static function fwdWithShardLock($shardPath, callable $mutator) {
        if ($shardPath === null) {
            return;
        }
        self::fwdEnsureDir();
        $fp = @fopen($shardPath, 'c+'); //phpcs:ignore
        if (!$fp) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            $raw = stream_get_contents($fp);
            $data = ($raw === '' || $raw === false) ? null : json_decode($raw, true);
            if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
                $data = ['schema' => 1, 'entries' => []];
            }
            $next = $mutator($data);
            if ($next === null) {
                return;
            }
            $tmp = $shardPath . '.tmp.' . getmypid();
            $bytes = @file_put_contents($tmp, json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); //phpcs:ignore
            if ($bytes !== false) {
                @rename($tmp, $shardPath); //phpcs:ignore
            } elseif (file_exists($tmp)) {
                @unlink($tmp); //phpcs:ignore
            }
        } finally {
            @flock($fp, LOCK_UN); //phpcs:ignore
            @fclose($fp); //phpcs:ignore
        }
    }

    /*
     * Returns ['hit' => bool, 'value' => string|null, 'neg' => bool, 'expired' => bool].
     * 'hit' false        -> caller must fetch and call set_fwd_slug.
     * 'hit' true,!expired -> trust 'value' (or null when 'neg' is true).
     * 'hit' true, expired -> stale; caller refetches but a concurrent fetch is OK
     *                        because writes are flock-serialized.
     *
     * Read uses LOCK_SH so concurrent readers don't serialize, while still
     * waiting out an in-flight writer.
     */
    public function get_fwd_slug($source_path, $source_language, $target_language) {
        $miss = ['hit' => false, 'value' => null, 'neg' => false, 'expired' => false];
        $shard = self::fwdShardPath($source_language, $target_language);
        if ($shard === null || !file_exists($shard)) {
            return $miss;
        }
        $fp = @fopen($shard, 'r'); //phpcs:ignore
        if (!$fp) {
            return $miss;
        }
        try {
            if (!flock($fp, LOCK_SH)) {
                return $miss;
            }
            $raw = stream_get_contents($fp);
        } finally {
            @flock($fp, LOCK_UN); //phpcs:ignore
            @fclose($fp); //phpcs:ignore
        }
        $data = ($raw === '' || $raw === false) ? null : json_decode($raw, true);
        if (!is_array($data) || empty($data['entries'])) {
            return $miss;
        }
        $key = self::fwdEntryKey($source_path);
        if (!isset($data['entries'][$key])) {
            return $miss;
        }
        $row = $data['entries'][$key];
        $neg = !empty($row['neg']);
        $ts  = isset($row['ts']) ? (int) $row['ts'] : 0;
        $ttl = $neg ? CONVEYTHIS_FWD_SLUG_TTL_NEGATIVE : CONVEYTHIS_FWD_SLUG_TTL_POSITIVE;
        return [
            'hit'     => true,
            'value'   => $neg ? null : (isset($row['t']) ? (string) $row['t'] : null),
            'neg'     => $neg,
            'expired' => (time() - $ts) > $ttl,
        ];
    }

    /*
     * Write (or refresh) one entry. $negative=true records a negative cache
     * (no translation found) with a shorter TTL, so a slug translated later in
     * the dashboard becomes discoverable within negative TTL on next crawl.
     */
    public function set_fwd_slug($source_path, $source_language, $target_language, $translated_path, $negative = false) {
        $shard = self::fwdShardPath($source_language, $target_language);
        if ($shard === null) {
            return;
        }
        $key = self::fwdEntryKey($source_path);
        self::fwdWithShardLock($shard, function ($data) use ($key, $source_path, $translated_path, $negative) {
            $data['entries'][$key] = [
                'p'   => (string) $source_path,
                't'   => $negative ? null : (is_string($translated_path) ? $translated_path : null),
                'ts'  => time(),
                'neg' => (bool) $negative,
            ];
            return $data;
        });
    }

    public function delete_fwd_slug($source_path, $source_language, $target_language) {
        $shard = self::fwdShardPath($source_language, $target_language);
        if ($shard === null || !file_exists($shard)) {
            return;
        }
        $key = self::fwdEntryKey($source_path);
        self::fwdWithShardLock($shard, function ($data) use ($key) {
            if (!isset($data['entries'][$key])) {
                return null; // skip write; nothing to remove
            }
            unset($data['entries'][$key]);
            return $data;
        });
    }

    /*
     * Convenience for invalidate_on_slug_change / invalidate_on_post_delete:
     * scrub a single source path across every configured target language.
     * Bounded surface — no fan-out beyond target_languages.
     */
    public function delete_fwd_slug_for_path_all_langs($source_path, $source_language, array $target_languages) {
        foreach ($target_languages as $tgt) {
            $this->delete_fwd_slug($source_path, $source_language, $tgt);
        }
    }

    /*
     * Wipe every shard. Hooked into the existing 'Clear all cache' admin path so
     * users have one button that resets everything. Static so flush_cache_on_activate()
     * (also static) can call it without bootstrapping an instance.
     */
    public static function clear_fwd_slugs() {
        if (!file_exists(CONVEYTHIS_CACHE_FWD_SLUGS_PATH)) {
            return;
        }
        foreach (glob(CONVEYTHIS_CACHE_FWD_SLUGS_PATH . '*.json') ?: [] as $shard) {
            @unlink($shard); //phpcs:ignore
        }
    }
}