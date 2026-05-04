<?php

class ConveyThisCron
{

    public function __construct()
    {

    }

    // Custom array time
    public static function ConveyThisСustomСronSchedule($schedules) {

        $variables = new Variables();
        $clear_cache = ( is_numeric($variables->clear_cache) && $variables->clear_cache > 0 ) ? $variables->clear_cache : 0;

        $schedules = Array(
            'every_ten_seconds' => Array(
                'interval' => 10,
                'display'  => __('Every Ten Seconds', 'text-domain')
            ),
            'every_24_hours' => Array(
                'interval' => 24 * 60 * 60,
                'display'  => __( 'Every 24 Hours' )
            ),
            'custome_time' => Array(
                'interval' => $clear_cache * 60 * 60,
                'display'  => __( 'Custome Time' )
            )
        );
        return $schedules;
    }

    // Start cron method
    public static function ConveyThisActivationCron() {

        if (!wp_next_scheduled('ConveyThisClearCache')) {
            $variables = new Variables();
            $clear_cache = ( is_numeric($variables->clear_cache) && $variables->clear_cache > 0 ) ? $variables->clear_cache : 0;

            // Cron method
            wp_schedule_event(time(), $clear_cache > 0 ? 'custome_time' : 'every_24_hours', 'ConveyThisClearCache');
        }

    }

    public static function ConveyThisDeactivationCron() {
        wp_clear_scheduled_hook('ConveyThisClearCache');
    }

    public static function ClearCache() {

        try {
            $directoryIterator = new RecursiveDirectoryIterator(CONVEYTHIS_CACHE_TRANSLATIONS_PATH, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

            $currentTime = time();

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->isFile()) {
                    $fileTime = $fileInfo->getMTime();
                    if (($currentTime - $fileTime) > 24*3600) {
                        unlink($fileInfo->getRealPath());
                    }
                }
            }
        } catch (UnexpectedValueException $e) {

        } catch (Exception $e) {

        }

        self::PruneFwdSlugShards();

    }

    /*
     * Per-entry TTL sweep over the forward-direction slug shards.
     * Whole-file mtime is unreliable here because every write refreshes the
     * file mtime even for rows that are already old, so we walk entries and
     * prune by their own 'ts' against positive/negative TTL constants.
     *
     * Spec: docs/superpowers/specs/2026-05-02-sitemap-slug-cache-design.md.
     */
    public static function PruneFwdSlugShards() {
        if (!defined('CONVEYTHIS_CACHE_FWD_SLUGS_PATH') || !file_exists(CONVEYTHIS_CACHE_FWD_SLUGS_PATH)) {
            return;
        }
        $now    = time();
        $posTtl = defined('CONVEYTHIS_FWD_SLUG_TTL_POSITIVE') ? (int) CONVEYTHIS_FWD_SLUG_TTL_POSITIVE : 7 * DAY_IN_SECONDS;
        $negTtl = defined('CONVEYTHIS_FWD_SLUG_TTL_NEGATIVE') ? (int) CONVEYTHIS_FWD_SLUG_TTL_NEGATIVE : 6 * HOUR_IN_SECONDS;

        foreach (glob(CONVEYTHIS_CACHE_FWD_SLUGS_PATH . '*.json') ?: [] as $shard) {
            $fp = @fopen($shard, 'c+'); //phpcs:ignore
            if (!$fp) {
                continue;
            }
            try {
                if (!flock($fp, LOCK_EX)) {
                    continue;
                }
                $raw = stream_get_contents($fp);
                $data = ($raw === '' || $raw === false) ? null : json_decode($raw, true);
                if (!is_array($data) || empty($data['entries']) || !is_array($data['entries'])) {
                    continue;
                }
                $changed = false;
                foreach ($data['entries'] as $key => $row) {
                    $ts  = isset($row['ts']) ? (int) $row['ts'] : 0;
                    $ttl = !empty($row['neg']) ? $negTtl : $posTtl;
                    if (($now - $ts) > $ttl) {
                        unset($data['entries'][$key]);
                        $changed = true;
                    }
                }
                if ($changed) {
                    $tmp = $shard . '.tmp.' . getmypid();
                    $bytes = @file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); //phpcs:ignore
                    if ($bytes !== false) {
                        @rename($tmp, $shard); //phpcs:ignore
                    } elseif (file_exists($tmp)) {
                        @unlink($tmp); //phpcs:ignore
                    }
                }
            } finally {
                @flock($fp, LOCK_UN); //phpcs:ignore
                @fclose($fp); //phpcs:ignore
            }
        }
    }
}

new ConveyThisCron();



