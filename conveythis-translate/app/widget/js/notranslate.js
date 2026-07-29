/**
 * Suppress Chrome's Google Translate prompt / auto-translate.
 *
 * Do NOT add class "notranslate" (or translate="no") on document.body.
 * ConveyThis CDN Translate.js treats .notranslate ancestors as excluded, so a
 * body.notranslate mark disables our own client-side translation for the
 * whole page. Use the google notranslate meta instead (also injected by
 * conveythis-initializer).
 */
(function () {
    if (typeof document === 'undefined' || !document.head) {
        return;
    }
    if (document.querySelector('meta[name="google"][content="notranslate"]')) {
        return;
    }
    var meta = document.createElement('meta');
    meta.name = 'google';
    meta.content = 'notranslate';
    document.head.appendChild(meta);
})();
