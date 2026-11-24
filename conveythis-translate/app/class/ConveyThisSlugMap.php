<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One translated address per page (Trello #2256).
 *
 * The ConveyThis API decides, per site and language, ONE slug for every source
 * path plus the older addresses of that page. This class keeps that list in a
 * single option, refreshed hourly by WP-Cron, and answers three questions:
 *
 *   forward($source, $lang)  the slug for links, canonical, hreflang, sitemap
 *   resolve($path, $lang)    an incoming /lang/... path: which source page it
 *                            opens, and where to redirect it if it is an old
 *                            address
 *   redirectStatus($lang)    302 or 301, decided by the API per site
 *
 * Off by default: the API answers "enabled: false" until ConveyThis turns the
 * site on, and then this class stores nothing and every caller falls back to
 * the behaviour of 271.4. On an API error the last list is kept, so an outage
 * never flips canonicals back and forth.
 */
class ConveyThisSlugMap
{
    const OPTION = 'conveythis_slug_map';
    const CRON_HOOK = 'ConveyThisSlugMapRefresh';
    const CRON_SCHEDULE = 'conveythis_hourly';

    /** @var callable fn(string $uri, array $body): array  the plugin's send() */
    private $send;

    /** @var array|null lang => ['version','status','fwd','rev','alias'] */
    private $map = null;

    public function __construct(callable $send)
    {
        $this->send = $send;
    }

    /** "/a/b/" and "/a/b" are one page; keys are decoded UTF-8. */
    public static function key(string $path): string
    {
        $path = rawurldecode($path);
        $path = '/' . ltrim($path, '/');
        $t = rtrim($path, '/');
        return $t === '' ? '/' : $t;
    }

    private function load(): array
    {
        if ($this->map === null) {
            $stored = get_option(self::OPTION, []);
            $this->map = is_array($stored) ? $stored : [];
        }
        return $this->map;
    }

    /** The stored list's key for a language, given an API or a URL code. */
    private function langKey(string $lang, array $urlCodes = []): ?string
    {
        $map = $this->load();
        if (isset($map[$lang])) {
            return $lang;
        }
        $code = array_search($lang, $urlCodes, true);
        if (is_string($code) && isset($map[$code])) {
            return $code;
        }
        if (isset($urlCodes[$lang]) && isset($map[$urlCodes[$lang]])) {
            return $urlCodes[$lang];
        }
        return null;
    }

    public function isActive(string $lang, array $urlCodes = []): bool
    {
        return $this->langKey($lang, $urlCodes) !== null;
    }

    /** Slug for a source path, or null (caller keeps its own behaviour). */
    public function forward(string $sourcePath, string $lang, array $urlCodes = []): ?string
    {
        $l = $this->langKey($lang, $urlCodes);
        if ($l === null) {
            return null;
        }
        $slug = $this->map[$l]['fwd'][self::key($sourcePath)] ?? null;
        return is_string($slug) && $slug !== '' ? $slug : null;
    }

    /**
     * An incoming path (without the /lang prefix):
     *   null                                   not in the list, caller decides
     *   ['source' => s, 'redirect' => null]    the page's slug, open source s
     *   ['source' => s, 'redirect' => slug]    an old address: send to slug
     */
    public function resolve(string $path, string $lang, array $urlCodes = []): ?array
    {
        $l = $this->langKey($lang, $urlCodes);
        if ($l === null) {
            return null;
        }
        $k = self::key($path);
        $m = $this->map[$l];
        if (isset($m['rev'][$k])) {
            return ['source' => $m['rev'][$k], 'redirect' => null];
        }
        $source = $m['alias'][$k] ?? null;
        if ($source === null && isset($m['fwd'][$k])) {
            $source = $m['src'][$k] ?? $k;   // the source path itself
        }
        if ($source === null) {
            return null;
        }
        $slug = $m['fwd'][self::key($source)] ?? null;
        if (!is_string($slug) || self::key($slug) === $k) {
            return ['source' => $source, 'redirect' => null];
        }
        return ['source' => $source, 'redirect' => $slug];
    }

    public function redirectStatus(string $lang, array $urlCodes = []): int
    {
        $l = $this->langKey($lang, $urlCodes);
        $s = $l === null ? 302 : (int) ($this->map[$l]['status'] ?? 302);
        return $s === 301 ? 301 : 302;
    }

    /** Build the lookup tables of one language from the API rows. */
    public static function index(array $rows, string $version, int $status): array
    {
        $fwd = $rev = $alias = $src = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['source'], $row['slug'])) {
                continue;
            }
            $source = '/' . ltrim((string) $row['source'], '/');
            $slug = '/' . ltrim((string) $row['slug'], '/');
            $sk = self::key($source);
            $fwd[$sk] = $slug;
            $src[$sk] = $source;
            $rev[self::key($slug)] = $source;
        }
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['source'])) {
                continue;
            }
            $source = '/' . ltrim((string) $row['source'], '/');
            foreach ((array) ($row['aliases'] ?? []) as $a) {
                $ak = self::key(html_entity_decode((string) $a, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                // Never let an old address shadow another page's slug or path.
                if (!isset($rev[$ak]) && !isset($fwd[$ak])) {
                    $alias[$ak] = $source;
                }
            }
        }
        // A source path that is another page's slug is that page's address.
        foreach ($fwd as $sk => $slug) {
            if (isset($rev[$sk]) && $rev[$sk] !== $src[$sk]) {
                unset($fwd[$sk]);
            }
        }
        return ['version' => $version, 'status' => $status === 301 ? 301 : 302,
                'fwd' => $fwd, 'rev' => $rev, 'alias' => $alias, 'src' => $src];
    }

    /**
     * Ask the API for every target language. Returns true when the stored
     * list changed (the caller then refreshes page caches).
     *
     * @param string[] $languages API language codes
     */
    public function refresh(string $referrer, array $languages, bool $translateLinks): bool
    {
        $old = $this->load();
        if (!$translateLinks || $referrer === '') {
            $new = [];
        } else {
            $new = $old;
            foreach ($languages as $lang) {
                $lang = (string) $lang;
                if ($lang === '') {
                    continue;
                }
                $answer = call_user_func($this->send, '/website/slugs-for-site/', [
                    'referrer'        => $referrer,
                    'target_language' => $lang,
                    'version'         => (string) ($old[$lang]['version'] ?? ''),
                ]);
                if (!is_array($answer) || !array_key_exists('enabled', $answer)) {
                    continue;                 // error or no answer: keep what we have
                }
                if (empty($answer['enabled'])) {
                    unset($new[$lang]);
                    continue;
                }
                $status = (int) ($answer['redirect_status'] ?? 302);
                if (!empty($answer['unchanged']) && isset($old[$lang])) {
                    $new[$lang]['status'] = $status === 301 ? 301 : 302;
                    continue;
                }
                if (isset($answer['slugs']) && is_array($answer['slugs'])) {
                    $new[$lang] = self::index($answer['slugs'], (string) ($answer['version'] ?? ''), $status);
                    // Saved per language: on a site with many languages a cron run
                    // that times out still keeps what it already fetched.
                    update_option(self::OPTION, $new, false);
                }
            }
            // Languages the site no longer translates into.
            foreach (array_keys($new) as $lang) {
                if (!in_array((string) $lang, array_map('strval', $languages), true)) {
                    unset($new[$lang]);
                }
            }
        }
        if ($new === $old) {
            return false;
        }
        if ($new === []) {
            delete_option(self::OPTION);
        } else {
            update_option(self::OPTION, $new, false);
        }
        $this->map = $new;
        return true;
    }
}
