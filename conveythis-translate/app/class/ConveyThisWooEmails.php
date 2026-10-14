<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sends WooCommerce customer emails in the language the customer used at checkout.
 *
 * - The order language is saved as order meta at checkout (classic and block checkout).
 *   Checkout requests carry no /xx/ prefix, so it comes from the current request,
 *   the Referer (/ru/checkout/) or the conveythis_lang cookie, in that order.
 * - When WooCommerce sends a customer email for such an order, the subject and HTML
 *   body are translated through ConveyThis::translateEmail(). Customer data (names,
 *   address, email, phone, order number, dates, customer note) is replaced with
 *   {{ct_...}} placeholders first, so it never reaches the API and every order
 *   produces the same segments. Address blocks are left out of translation. A few
 *   untranslated per-order lines are accepted; a mostly untranslated email, or any
 *   failure, sends the original.
 * - Warm-up: every enabled customer email is rendered with a dummy order in the
 *   background (after an update, a settings change or an email text edit) and sent
 *   through the same path, so the first real email does not meet cold texts.
 * - The email segments live under the page /conveythis-emails/, which answers 200 so
 *   the API page checker keeps it visible (and editable) in the dashboard.
 */
class ConveyThisWooEmails
{
    const ORDER_META = '_conveythis_language';
    const COOKIE = 'conveythis_lang';

    /** @var ConveyThis */
    private $ct;

    public function __construct($ct) {
        $this->ct = $ct;

        add_action('woocommerce_checkout_create_order', array($this, 'capture_order_language'), 10, 1);
        add_action('woocommerce_store_api_checkout_order_processed', array($this, 'capture_order_language_and_save'), 10, 1);
        add_filter('woocommerce_mail_callback_params', array($this, 'translate_email'), 20, 2);
        add_action('wp_footer', array($this, 'print_language_cookie_script'), 20);
        add_action('template_redirect', array($this, 'serve_email_page'), 0);

        add_action(self::WARMUP_HOOK, array($this, 'warm_email'), 10, 2);
        add_action('admin_init', array($this, 'maybe_warm_after_update'));
        add_action('add_option_conveythis_translate_emails', array($this, 'warm_on_settings_change'));
        add_action('update_option_conveythis_translate_emails', array($this, 'warm_on_settings_change'));
        add_action('add_option_target_languages', array($this, 'warm_on_settings_change'));
        add_action('update_option_target_languages', array($this, 'warm_on_settings_change'));
        add_action('updated_option', array($this, 'warm_on_email_settings_change'), 10, 1);
    }

    /** Classic checkout: the order is saved by WooCommerce right after this hook. */
    public function capture_order_language($order) {
        if (!is_object($order) || !method_exists($order, 'update_meta_data') || $order->get_meta(self::ORDER_META)) {
            return;
        }
        $language = $this->detect_checkout_language();
        if ($language !== '') {
            $order->update_meta_data(self::ORDER_META, $language);
        }
    }

    /** Block checkout (Store API): the hook fires on an already saved order. */
    public function capture_order_language_and_save($order) {
        if (!is_object($order) || !method_exists($order, 'update_meta_data') || $order->get_meta(self::ORDER_META)) {
            return;
        }
        $language = $this->detect_checkout_language();
        if ($language !== '') {
            $order->update_meta_data(self::ORDER_META, $language);
            $order->save_meta_data();
        }
    }

    /** Target language the customer is checking out in, or '' for the source language. */
    public function detect_checkout_language() {
        $current = $this->ct->getCurrentLanguageCode();
        if ($this->ct->isActiveTargetLanguage($current)) {
            return $current;
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        if ($referer !== '') {
            $refererHost = wp_parse_url($referer, PHP_URL_HOST);
            $siteHost = wp_parse_url(home_url(), PHP_URL_HOST);
            if (is_string($refererHost) && is_string($siteHost)
                && strcasecmp(preg_replace('/^www\./i', '', $refererHost), preg_replace('/^www\./i', '', $siteHost)) === 0) {
                // A same-site Referer is authoritative, including for the source language.
                return $this->ct->languageCodeFromUrl($referer);
            }
        }

        $cookie = isset($_COOKIE[self::COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE])) : '';
        return $this->ct->isActiveTargetLanguage($cookie) ? $cookie : '';
    }

    /**
     * Fallback for checkouts whose request has no usable Referer: remember the page
     * language in a cookie. Set client-side so full-page caches are not affected.
     */
    public function print_language_cookie_script() {
        if (is_admin() || !$this->is_available()) {
            return;
        }
        $language = $this->ct->getCurrentLanguageCode();
        $language = $this->ct->isActiveTargetLanguage($language) ? $language : '';
        $path = $this->ct->getSitePrefix();
        $cookie = self::COOKIE . '=' . rawurlencode($language) . ';path=' . $path . ';SameSite=Lax;max-age=' . ($language === '' ? 0 : 30 * DAY_IN_SECONDS);
        $script = 'document.cookie=' . wp_json_encode($cookie) . ';';
        if (function_exists('wp_print_inline_script_tag')) {
            wp_print_inline_script_tag($script);
        } else {
            echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
    }

    /**
     * woocommerce_mail_callback_params: array($to, $subject, $message, $headers, $attachments).
     */
    public function translate_email($params, $email = null) {
        if (!is_array($params) || count($params) < 3 || !is_string($params[1]) || !is_string($params[2])) {
            return $params;
        }
        if (!$this->is_available()) {
            return $params;
        }
        if (!is_object($email) || !method_exists($email, 'is_customer_email') || !$email->is_customer_email()) {
            return $params;
        }
        $order = isset($email->object) ? $email->object : null;
        if (!is_object($order) || !is_a($order, 'WC_Order')) {
            return $params;
        }
        $language = (string) $order->get_meta(self::ORDER_META);
        if (!$this->ct->isActiveTargetLanguage($language)) {
            return $params;
        }
        if (method_exists($email, 'get_content_type') && $email->get_content_type() === 'text/plain') {
            return $params;
        }
        if (!apply_filters('conveythis_translate_email', true, $email, $language)) {
            return $params;
        }

        $started = microtime(true);
        $result = $this->translate_rendered($params[1], $params[2], $order, $email, $language, (float) apply_filters('conveythis_email_translate_timeout', 5));
        $this->record_result($email, $order, $language, $result['error'], $started, $result['missing']);
        if ($result['error'] !== '') {
            return $params;
        }
        $params[1] = $result['subject'];
        $params[2] = $result['html'];
        return $params;
    }

    private function is_available() {
        return $this->ct->isTranslateEmailsEnabled() && !defined('ICL_SITEPRESS_VERSION') && !defined('POLYLANG_VERSION');
    }

    /**
     * Mask, translate, verify and restore one rendered email. Shared by real sends and the
     * warm-up, so both produce exactly the same segments.
     *
     * @return array ['subject', 'html', 'error' ('' on success), 'missing']
     */
    private function translate_rendered($subject, $html, $order, $email, $language, $timeout) {
        $masks = $this->build_masks($order, $email);
        $maskedSubject = $this->mask_text($subject, $masks);
        $links = array();
        $maskedHtml = $this->mask_html($this->mask_links($this->exclude_addresses($html), $links), $masks);

        $result = $this->ct->translateEmail($maskedHtml, $maskedSubject, $language, $timeout);
        if (!is_array($result)) {
            return array('subject' => $subject, 'html' => $html, 'error' => (string) $this->ct->getLastEmailError(), 'missing' => 0);
        }

        // A placeholder the translation lost or duplicated would drop or repeat customer data.
        $tokens = array_keys($links);
        foreach ($masks as $mask) {
            $tokens[] = $mask['token'];
        }
        foreach ($tokens as $token) {
            if (substr_count($maskedSubject . $maskedHtml, $token) !== substr_count($result['subject'] . $result['html'], $token)) {
                return array('subject' => $subject, 'html' => $html, 'error' => 'placeholder_lost', 'missing' => 0);
            }
        }

        $restore = $this->localize_dates($masks, $language);
        return array(
            'subject' => $this->unmask($result['subject'], $restore, false),
            'html'    => $this->remove_address_marker(strtr($this->unmask($result['html'], $restore, true), $links)),
            'error'   => '',
            'missing' => (int) $this->ct->getLastEmailMissing(),
        );
    }

    /**
     * The address blocks are customer data plus a country name, and they differ for every
     * country and set of filled-in fields. Keeping them out of translation keeps the email
     * segments the same for every order (the warm-up can then cover them).
     */
    public function exclude_addresses($html) {
        $result = preg_replace_callback('/<address\b([^>]*)>/i', function ($m) {
            $attributes = $m[1];
            if (preg_match('/\sclass\s*=\s*(["\'])(.*?)\1/is', $attributes, $c)) {
                $attributes = str_replace($c[0], ' class=' . $c[1] . trim($c[2] . ' conveythis-no-translate') . $c[1], $attributes);
            } else {
                $attributes .= ' class="conveythis-no-translate"';
            }
            return '<address' . $attributes . '>';
        }, $html);
        return $result === null ? $html : $result;
    }

    /** Undo exclude_addresses() in the email that is sent. */
    private function remove_address_marker($html) {
        return str_replace(array(' class="conveythis-no-translate"', ' conveythis-no-translate'), '', $html);
    }

    const LAST_RESULT_OPTION = 'conveythis_email_last_result';
    const WARMUP_OPTION = 'conveythis_email_warmup';
    const WARMUP_VERSION_OPTION = 'conveythis_email_warmup_version';
    const WARMUP_HOOK = 'conveythis_warm_email';

    /** Outcome of the last customer email, shown under the setting so a fallback can be explained. */
    private function record_result($email, $order, $language, $error, $started, $missing = 0) {
        update_option(self::LAST_RESULT_OPTION, array(
            'time'     => time(),
            'email'    => isset($email->id) ? (string) $email->id : '',
            'order'    => is_callable(array($order, 'get_id')) ? (int) $order->get_id() : 0,
            'language' => $language,
            'error'    => (string) $error,
            'missing'  => (int) $missing,
            'seconds'  => round(microtime(true) - $started, 1),
        ), false);
    }

    /** Lines for the settings page (last email, last warm-up); empty when nothing happened yet. */
    public static function describe_last_result() {
        $lines = array();
        $last = get_option(self::LAST_RESULT_OPTION);
        if (is_array($last) && !empty($last['time'])) {
            $what = sprintf('%s (order #%d, %s, %ss)', $last['email'], $last['order'], $last['language'], $last['seconds']);
            $error = (string) $last['error'];
            if ($error === '') {
                $note = !empty($last['missing']) ? sprintf(' (%d short line(s) not translated yet)', $last['missing']) : '';
                $lines[] = sprintf('Last email %s: translated%s. %s', self::format_time($last['time']), $note, $what);
            } else {
                $reasons = array(
                    'no_response'          => 'no answer from the translation server in time',
                    'incomplete'           => 'the translation was not complete yet',
                    'placeholder_lost'     => 'customer data could not be restored safely',
                    'not_configured'       => 'the plugin is not fully configured',
                    'nothing_to_translate' => 'nothing to translate',
                    'api_error'            => 'the translation server returned an error',
                );
                $key = strtok($error, ':');
                $reason = isset($reasons[$key]) ? $reasons[$key] : $error;
                if ($key === 'incomplete' || $key === 'api_error') {
                    $reason .= ' (' . $error . ')';
                }
                $lines[] = sprintf('Last email %s: sent in the original language, %s. %s', self::format_time($last['time']), $reason, $what);
            }
        }
        $warm = get_option(self::WARMUP_OPTION);
        if (is_array($warm) && !empty($warm['time'])) {
            $lines[] = sprintf('Email templates prepared %s: %d of %d translated%s.', self::format_time($warm['time']),
                $warm['done'], $warm['done'] + $warm['failed'], $warm['last_error'] !== '' ? ', last problem: ' . $warm['last_error'] : '');
        }
        return implode("\n", $lines);
    }

    private static function format_time($time) {
        return function_exists('wp_date') ? wp_date('Y-m-d H:i', (int) $time) : gmdate('Y-m-d H:i', (int) $time);
    }

    /*
     * Warm-up. A customer email whose texts were never translated is "cold": the API
     * needs up to ~20 s, far over the checkout timeout, so it goes out untranslated. The
     * warm-up renders every enabled customer email with a dummy order in the background
     * and sends it through the same path, so the first real email finds its texts ready.
     */

    /** Queue a warm-up for these email IDs (all customer emails when empty) in every target language. */
    public function schedule_warmup($emailIds = array()) {
        if (!$this->is_available() || !function_exists('WC')) {
            return;
        }
        $languages = $this->ct->getTargetLanguages();
        if (empty($languages)) {
            return;
        }
        if (empty($emailIds)) {
            $emailIds = array();
            foreach ($this->customer_emails() as $email) {
                $emailIds[] = $email->id;
            }
        }
        $delay = 0;
        foreach ($emailIds as $emailId) {
            foreach ($languages as $language) {
                $args = array((string) $emailId, (string) $language);
                if (function_exists('as_enqueue_async_action')) {
                    if (!function_exists('as_has_scheduled_action') || !as_has_scheduled_action(self::WARMUP_HOOK, $args, 'conveythis')) {
                        as_enqueue_async_action(self::WARMUP_HOOK, $args, 'conveythis');
                    }
                } elseif (!wp_next_scheduled(self::WARMUP_HOOK, $args)) {
                    wp_schedule_single_event(time() + $delay, self::WARMUP_HOOK, $args);
                    $delay += 30;
                }
            }
        }
    }

    /**
     * @return WC_Email[] enabled customer emails about an order (the only ones translated),
     *                    keyed by their ID. Order emails declare an {order_number} placeholder;
     *                    account emails (password reset, new account) do not.
     */
    private function customer_emails() {
        $emails = array();
        if (!function_exists('WC') || !WC()->mailer()) {
            return $emails;
        }
        foreach (WC()->mailer()->get_emails() as $email) {
            if (is_object($email) && !empty($email->id) && method_exists($email, 'is_customer_email')
                && $email->is_customer_email()
                // The invoice is sent by hand from the order screen, so it counts as disabled.
                && ($email->is_enabled() || $email->id === 'customer_invoice')
                && is_array($email->placeholders) && array_key_exists('{order_number}', $email->placeholders)) {
                $emails[$email->id] = $email;
            }
        }
        return $emails;
    }

    /** Action Scheduler / WP-Cron callback: render one email with a dummy order and translate it. */
    public function warm_email($emailId, $language) {
        if (!$this->is_available() || !$this->ct->isActiveTargetLanguage($language)) {
            return;
        }
        $emails = $this->customer_emails();
        if (!isset($emails[$emailId])) {
            return;
        }
        $email = $emails[$emailId];
        if (method_exists($email, 'get_content_type') && $email->get_content_type() === 'text/plain') {
            return;
        }
        $error = '';
        try {
            require_once __DIR__ . '/ConveyThisWooEmailsDummyOrder.php';
            $order = ConveyThisWooEmailsDummyOrder::build();
            if (method_exists($email, 'set_object')) {
                $email->set_object($order);
            } else {
                $email->object = $order;
            }
            if (property_exists($email, 'customer_note')) {
                $email->customer_note = 'Your parcel has been handed over to the courier.';
            }
            if (property_exists($email, 'partial_refund')) {
                $email->partial_refund = false;
            }
            $email->placeholders = array_merge((array) $email->placeholders, array(
                '{order_date}'              => wc_format_datetime($order->get_date_created()),
                '{order_number}'            => $order->get_order_number(),
                '{order_billing_full_name}' => $order->get_formatted_billing_full_name(),
            ));
            $html = $email->style_inline($email->get_content());
            $subject = function_exists('wp_specialchars_decode') ? wp_specialchars_decode($email->get_subject()) : $email->get_subject();
            // Background job: wait as long as a page translation does.
            $result = $this->translate_rendered($subject, $html, $order, $email, $language, 25);
            $error = $result['error'];
        } catch (\Throwable $e) {
            // Third-party email classes may not cope with a dummy order; skip them.
            $error = 'exception: ' . $e->getMessage();
        }

        $warm = get_option(self::WARMUP_OPTION);
        if (!is_array($warm) || empty($warm['time']) || time() - (int) $warm['time'] > HOUR_IN_SECONDS) {
            $warm = array('time' => time(), 'done' => 0, 'failed' => 0, 'last_error' => '');
        }
        $warm['time'] = time();
        if ($error === '') {
            $warm['done']++;
        } else {
            $warm['failed']++;
            $warm['last_error'] = $emailId . ' / ' . $language . ': ' . $error;
        }
        update_option(self::WARMUP_OPTION, $warm, false);
    }

    /** Warm up once per plugin version: covers existing shops right after an update. */
    public function maybe_warm_after_update() {
        $version = defined('CONVEYTHIS_PLUGIN_VERSION') ? CONVEYTHIS_PLUGIN_VERSION : '';
        if (get_option(self::WARMUP_VERSION_OPTION) === $version) {
            return;
        }
        update_option(self::WARMUP_VERSION_OPTION, $version, false);
        $this->schedule_warmup();
    }

    /**
     * The setting was switched on or the target languages changed. The hooks fire in the
     * middle of the settings save, before the plugin reloads its settings, so the warm-up
     * is queued at the end of the request, with the new languages.
     */
    public function warm_on_settings_change() {
        if (!has_action('shutdown', array($this, 'schedule_full_warmup'))) {
            add_action('shutdown', array($this, 'schedule_full_warmup'));
        }
    }

    public function schedule_full_warmup() {
        $this->schedule_warmup();
    }

    /** A WooCommerce email's subject, heading or text was edited: warm that email again. */
    public function warm_on_email_settings_change($option) {
        if (!is_string($option) || !preg_match('/^woocommerce_(.+)_settings$/', $option, $m)) {
            return;
        }
        if (array_key_exists($m[1], $this->customer_emails())) {
            $this->schedule_warmup(array($m[1]));
        }
    }

    /**
     * Links carry customer data: tel:/mailto: hold the phone and email, order links hold
     * the order ID and key (order-pay/123/?key=wc_order_...). Segments with inline markup
     * are sent with their attributes, so every href is swapped for a #ct_href_N anchor and
     * restored afterwards. Links therefore keep their original (source-language) URLs.
     *
     * @param array $links filled with ['#ct_href_N' => 'original href value']
     */
    public function mask_links($html, &$links) {
        $result = preg_replace_callback('/(\shref\s*=\s*)(["\'])([^"\']*)\2/i', function ($m) use (&$links) {
            $anchor = '#ct_href_' . count($links);
            $links[$anchor] = $m[3];
            return $m[1] . $m[2] . $anchor . $m[2];
        }, $html);
        return $result === null ? $html : $result;
    }

    /**
     * Customer values to hide, each with a placeholder named after its field (not its
     * value), so the segments are the same for every order.
     *
     * @return array list of ['token' => '{{ct_x}}', 'value' => 'raw', 'html' => 'escaped'], longest first
     */
    public function build_masks($order, $email = null) {
        $values = array();
        foreach (array('billing', 'shipping') as $type) {
            foreach (array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'phone') as $field) {
                $getter = 'get_' . $type . '_' . $field;
                if (is_callable(array($order, $getter))) {
                    $values[$type . '_' . $field] = $order->$getter();
                }
            }
            $first = is_callable(array($order, 'get_' . $type . '_first_name')) ? $order->{'get_' . $type . '_first_name'}() : '';
            $last = is_callable(array($order, 'get_' . $type . '_last_name')) ? $order->{'get_' . $type . '_last_name'}() : '';
            $values[$type . '_full_name'] = trim($first . ' ' . $last);
        }
        $values['billing_email'] = is_callable(array($order, 'get_billing_email')) ? $order->get_billing_email() : '';
        $values['order_number'] = is_callable(array($order, 'get_order_number')) ? $order->get_order_number() : '';
        $values['customer_note'] = is_callable(array($order, 'get_customer_note')) ? $order->get_customer_note() : '';
        $dates = array();
        if (function_exists('wc_format_datetime')) {
            foreach (array('created', 'paid', 'completed') as $date) {
                $getter = 'get_date_' . $date;
                if (is_callable(array($order, $getter)) && $order->$getter()) {
                    $values['date_' . $date] = wc_format_datetime($order->$getter());
                    $dates['date_' . $date] = $order->$getter();
                }
            }
        }

        $masks = array();
        $seen = array();
        foreach ($values as $field => $value) {
            $value = trim((string) $value);
            // Two characters is enough for a name; a single one would mask stray letters.
            // Order numbers 1-9 are still masked: masking is lossless, and an unmasked
            // number would make every such order a new segment.
            $minLength = $field === 'order_number' ? 1 : 2;
            if ((function_exists('mb_strlen') ? mb_strlen($value) : strlen($value)) < $minLength) {
                continue;
            }
            if (isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            $masks[] = array(
                'token' => '{{ct_' . $field . '}}',
                'value' => $value,
                'html'  => esc_html($value),
                'date'  => isset($dates[$field]) ? $dates[$field] : null,
            );
            // Address formats print some fields in capitals ({city_upper} for FR, {state_upper}, ...).
            $upper = function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
            if ($upper !== $value && !isset($seen[$upper])) {
                $seen[$upper] = true;
                $masks[] = array(
                    'token' => '{{ct_' . $field . '_upper}}',
                    'value' => $upper,
                    'html'  => esc_html($upper),
                );
            }
        }
        usort($masks, function ($a, $b) {
            return strlen($b['value']) - strlen($a['value']);
        });
        return $masks;
    }

    /** Mask plain text (the subject). */
    public function mask_text($text, $masks) {
        foreach ($masks as $mask) {
            $text = $this->replace_whole($text, $mask['value'], $mask['token']);
        }
        return $text;
    }

    /**
     * Mask the text between tags only. Tag attributes (hrefs, styles) stay untouched: a
     * placeholder inside a URL could be re-encoded by link rewriting and never restored.
     */
    public function mask_html($html, $masks) {
        $parts = preg_split('/(<!--.*?-->|<style\b.*?<\/style>|<[^>]*>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $html;
        }
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<') {
                continue;
            }
            foreach ($masks as $mask) {
                // Both forms (O&#039;Brien and O'Brien) get the same placeholder, so the
                // segment does not depend on whether the name needs escaping.
                if ($mask['html'] !== $mask['value']) {
                    $part = $this->replace_whole($part, $mask['html'], $mask['token']);
                }
                $part = $this->replace_whole($part, $mask['value'], $mask['token']);
            }
            $parts[$i] = $part;
        }
        return implode('', $parts);
    }

    /**
     * Dates are masked (they differ per order) and restored in the email's language:
     * "October 14, 2026" becomes "14 октября 2026 г.". Needs PHP intl; without it, or for
     * a locale intl does not know, the date stays as WooCommerce formatted it.
     */
    public function localize_dates($masks, $language) {
        if (!class_exists('IntlDateFormatter')) {
            return $masks;
        }
        $parts = explode('-', strtolower((string) $language));
        $locale = $parts[0] . (isset($parts[1]) ? '_' . strtoupper($parts[1]) : '');
        foreach ($masks as $i => $mask) {
            if (empty($mask['date']) || !($mask['date'] instanceof DateTimeInterface)) {
                continue;
            }
            $formatted = IntlDateFormatter::formatObject($mask['date'], array(IntlDateFormatter::LONG, IntlDateFormatter::NONE), $locale);
            if (is_string($formatted) && $formatted !== '') {
                $masks[$i]['value'] = $formatted;
                $masks[$i]['html'] = esc_html($formatted);
            }
        }
        return $masks;
    }

    /** Restore placeholders: escaped values in HTML, raw values in plain text (the subject). */
    public function unmask($text, $masks, $html) {
        foreach ($masks as $mask) {
            $text = str_replace($mask['token'], $html ? $mask['html'] : $mask['value'], $text);
        }
        return $text;
    }

    /** Replace $needle where it is not part of a longer word, number or an earlier {{ct_...}} placeholder. */
    private function replace_whole($haystack, $needle, $replacement) {
        if ($needle === '' || strpos($haystack, $needle) === false) {
            return $haystack;
        }
        $result = preg_replace('/(?<![\p{L}\p{N}_{])' . preg_quote($needle, '/') . '(?![\p{L}\p{N}_}])/u', str_replace(array('\\', '$'), array('\\\\', '\\$'), $replacement), $haystack);
        return $result === null ? $haystack : $result;
    }

    /**
     * /conveythis-emails/ is not a real page. Answer 200 (noindex) so the API page checker
     * does not mark it 404 and hide the email translations from the dashboard.
     */
    public function serve_email_page() {
        $path = isset($_SERVER['REQUEST_URI']) ? wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
        if (!is_string($path) || rtrim($path, '/') !== rtrim($this->ct->getSitePrefix() . ConveyThis::EMAIL_PAGE_PATH, '/')) {
            return;
        }
        status_header(200);
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="robots" content="noindex, nofollow"><title>ConveyThis</title></head>'
            . '<body><p>' . esc_html__('This address stores ConveyThis translations of WooCommerce emails. It is not a page for visitors.', 'conveythis-translate') . '</p></body></html>';
        exit;
    }
}
