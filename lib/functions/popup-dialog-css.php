<?php
/**
 * Load Elementor's popup dialog stylesheet with the page.
 *
 * Elementor adds assets/css/conditionals/dialog.min.css (about 1.4 KB) to the
 * page only at the moment the first popup opens, and 250 ms later moves the
 * keyboard focus to the popup's close button. Until that stylesheet has
 * arrived the popup is not `position: fixed` yet: it sits in the normal flow
 * at the very end of the page. Two things follow for a popup that opens
 * without a click (page-load timer, scroll, exit intent):
 *  - when the stylesheet lands, the whole page shifts (measured layout shift
 *    0.25 to 0.3 on desktop, 0.9 to 1.0 on mobile);
 *  - when it takes longer than those 250 ms (any slow connection), the browser
 *    scrolls all the way down to the focused close button and the visitor is
 *    left at the footer with the popup on top.
 * Printing the same stylesheet with the page removes both: the popup is fixed
 * from the first frame.
 *
 * The URL is the one Elementor's own loader requests (same file, same version
 * query), and it is enqueued before Elementor's frontend stylesheet, which is
 * where Elementor inserts it, so the cascade does not change.
 *
 * Off by default (MetaHotels Settings › Other Settings). The filter
 * `metahotels_popup_dialog_css_enabled` lets a site override the switch.
 *
 * @package MetaHotels_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the dialog stylesheet should be printed on this request.
 *
 * @return bool
 */
function metahotels_popup_dialog_css_enabled() {
    if (is_admin() || is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    if (!did_action('elementor/loaded') || !defined('ELEMENTOR_ASSETS_URL') || !defined('ELEMENTOR_VERSION')) {
        return false;
    }
    // Never in the Elementor editor or its preview iframe.
    if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return false;
    }

    /**
     * Filters whether Elementor's popup dialog stylesheet is loaded with the page.
     *
     * @param bool $enabled Default: the saved option (off).
     */
    return (bool) apply_filters(
        'metahotels_popup_dialog_css_enabled',
        (bool) get_option('metahotels_enable_popup_dialog_css', false)
    );
}

/**
 * Enqueue the stylesheet early, so it is printed before Elementor's frontend CSS.
 */
function metahotels_popup_dialog_css_enqueue() {
    if (!metahotels_popup_dialog_css_enabled()) {
        return;
    }
    $suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
    wp_enqueue_style(
        'metahotels-e-dialog',
        ELEMENTOR_ASSETS_URL . 'css/conditionals/dialog' . $suffix . '.css',
        array(),
        ELEMENTOR_VERSION
    );
}
add_action('wp_enqueue_scripts', 'metahotels_popup_dialog_css_enqueue', 1);
