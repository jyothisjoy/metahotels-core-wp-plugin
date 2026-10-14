<?php
/**
 * Start entrance animations above the fold at first paint.
 *
 * Elementor hides every element that has an entrance animation
 * (`.elementor-invisible`, `visibility: hidden`) until its own JavaScript has
 * loaded and started, and only then plays the animation. For an element at the
 * top of the page (a header row with the logo and menu, the hero heading) that
 * means it is missing from the first paint until all of Elementor's scripts
 * have arrived: a fraction of a second on a desktop, several seconds on a
 * phone. Measured on shamshotels.com (slow-4G phone, 2026-10-13): first paint
 * at 3 s, hero heading at 6.5 s; with this on, 2.8 s.
 *
 * For the header template and for the first section of the page content this
 * prints a small style block that starts the same animation from CSS, so it
 * plays as soon as the page paints. When Elementor's script arrives it swaps
 * `elementor-invisible` for `animated <name>`; the animation name does not
 * change, so the browser does not play it a second time. Everything further
 * down the page keeps animating on scroll as before. Visitors who ask for
 * reduced motion get the elements without animation, as Elementor does.
 *
 * Only the animations whose stylesheet (`e-animation-<name>`) Elementor has put
 * on the page are covered, so the block stays a few hundred bytes.
 *
 * Off by default (MetaHotels Settings › Other Settings). The filter
 * `metahotels_entrance_first_paint_enabled` lets a site override the switch.
 *
 * @package MetaHotels_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Whether the style block should be printed on this request.
 *
 * @return bool
 */
function metahotels_entrance_first_paint_enabled() {
    if (is_admin() || is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    if (!did_action('elementor/loaded')) {
        return false;
    }
    // Never in the Elementor editor or its preview iframe.
    if (isset($_GET['elementor-preview']) || isset($_GET['elementor_library'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return false;
    }

    /**
     * Filters whether entrance animations above the fold start at first paint.
     *
     * @param bool $enabled Default: the saved option (off).
     */
    return (bool) apply_filters(
        'metahotels_entrance_first_paint_enabled',
        (bool) get_option('metahotels_enable_entrance_first_paint', false)
    );
}

/**
 * Entrance animations whose stylesheet Elementor has enqueued for this page.
 *
 * @return string[] Animation names, e.g. array('fadeInUp').
 */
function metahotels_entrance_first_paint_names() {
    $names  = array();
    $styles = wp_styles();
    foreach (array_merge((array) $styles->done, (array) $styles->queue) as $handle) {
        if (preg_match('/^e-animation-([A-Za-z]+)$/', $handle, $m)) {
            $names[$m[1]] = true;
        }
    }

    /**
     * Filters the entrance animation names that are started at first paint.
     *
     * @param string[] $names Names found among the enqueued `e-animation-<name>` stylesheets.
     */
    return (array) apply_filters('metahotels_entrance_first_paint_names', array_keys($names));
}

/**
 * Build the style block for the given animation names.
 *
 * @param string[] $names Animation names.
 * @return string CSS, or '' when there is nothing to print.
 */
function metahotels_entrance_first_paint_css($names) {
    // The header template, and the first top-level element of the page content
    // (the element itself and everything inside it).
    $scopes = array('.elementor-location-header ');
    foreach (array('wp-page', 'wp-post', 'single-page', 'single-post') as $type) {
        $scopes[] = '[data-elementor-type="' . $type . '"]>.elementor-element:first-child ';
        $scopes[] = '[data-elementor-type="' . $type . '"]>.elementor-element:first-child';
    }

    $css = '';
    $all = array();
    foreach ($names as $name) {
        if (!is_string($name) || !preg_match('/^[A-Za-z]+$/', $name)) {
            continue;
        }
        $selectors = array();
        foreach ($scopes as $scope) {
            // Matches both "animation":"<name>" (containers) and "_animation":"<name>" (widgets).
            $selectors[] = $scope . '.elementor-invisible[data-settings*=\'animation":"' . $name . '"\']';
        }
        $selector = implode(',', $selectors);
        $all[]    = $selector;
        // 1.25s is the duration of Elementor's own `.animated` class.
        $css .= $selector . '{visibility:visible;animation-name:' . $name . ';animation-duration:1.25s}';
    }
    if ('' === $css) {
        return '';
    }

    return '@media (prefers-reduced-motion:no-preference){' . $css . '}'
        . '@media (prefers-reduced-motion:reduce){' . implode(',', $all) . '{visibility:visible}}';
}

/**
 * Print the style block late in the head, after Elementor's stylesheets were enqueued.
 */
function metahotels_entrance_first_paint_print() {
    if (!metahotels_entrance_first_paint_enabled()) {
        return;
    }
    $css = metahotels_entrance_first_paint_css(metahotels_entrance_first_paint_names());
    if ('' === $css) {
        return;
    }
    echo '<style id="metahotels-entrance-first-paint">' . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('wp_head', 'metahotels_entrance_first_paint_print', 20);
