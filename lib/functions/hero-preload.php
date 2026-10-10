<?php
/**
 * Preload hero background image (LCP) for Elementor pages.
 *
 * When the first top-level Section/Container of an Elementor page paints its
 * background from CSS (classic background image, or the fallback image of a
 * background video) or from JavaScript (background slideshow), the browser
 * cannot discover that image until the stylesheet or the slideshow script has
 * run, so the hero — usually the Largest Contentful Paint element — starts
 * downloading late. This prints a
 * <link rel="preload" as="image" fetchpriority="high"> for that image early in
 * <head> so it downloads straight away.
 *
 * The preloaded URL must be byte-for-byte the one Elementor paints, otherwise
 * the browser downloads the image twice:
 *  - Slideshow: Elementor's background-slideshow handler builds each slide from
 *    the gallery item's stored `url` (no image-size option, no random order,
 *    no initial-slide option) and always shows the first gallery item first.
 *  - Classic: Elementor's Media control resolves the CSS URL from the
 *    attachment ID with wp_get_attachment_image_url( id, size ?: 'full' ), and
 *    falls back to the stored `url` only when there is no ID.
 *  - Video: the "Background Fallback" image is a Media control too, printed as
 *    a CSS background on every device (the control is not responsive). It is
 *    what the hero shows until the video plays, and instead of it on mobile.
 *
 * Off by default (MetaHotels Settings › Other Settings). Two filters let a
 * site override the switch or the image list:
 *  - metahotels_hero_preload_enabled
 *  - metahotels_hero_preload_images
 *
 * Safe when Elementor is inactive or its API changes: every failure simply
 * prints nothing.
 *
 * @package MetaHotels_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Print the hero preload tag(s) in <head>.
 *
 * Runs once per front-end request on wp_head. Cheap exits come first so pages
 * that do not qualify cost next to nothing.
 */
function metahotels_hero_preload_print() {
    if (is_admin() || is_feed() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return;
    }
    if (!is_singular() || is_preview()) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0 || post_password_required($post_id)) {
        return;
    }

    /**
     * Filters whether the hero background preload runs for the current page.
     *
     * Defaults to the "Preload hero background image" option in MetaHotels
     * Settings › Other Settings, so a site can force the feature on or off
     * (globally or per page) without touching the option.
     *
     * @param bool $enabled Whether to preload the hero image. Default: the saved option (off).
     * @param int  $post_id ID of the singular post being viewed.
     */
    $enabled = (bool) apply_filters(
        'metahotels_hero_preload_enabled',
        (bool) get_option('metahotels_enable_hero_preload', false),
        $post_id
    );
    if (!$enabled) {
        return;
    }

    try {
        $images = metahotels_hero_preload_get_images($post_id);
    } catch (\Throwable $e) {
        // Never break the page because Elementor's internals changed.
        return;
    }

    foreach ($images as $image) {
        $media = ('' !== $image['media']) ? ' media="' . esc_attr($image['media']) . '"' : '';
        echo '<link rel="preload" as="image" href="' . esc_url($image['url']) . '" fetchpriority="high"' . $media . ">\n";
    }
}
add_action('wp_head', 'metahotels_hero_preload_print', 2);

/**
 * Find the hero image(s) to preload for an Elementor-built post.
 *
 * @param int $post_id Post ID.
 * @return array[] List of array( 'url' => string, 'media' => string ), unique by URL.
 */
function metahotels_hero_preload_get_images($post_id) {
    if (!did_action('elementor/loaded') || !class_exists('\Elementor\Plugin')) {
        return array();
    }

    $plugin = \Elementor\Plugin::$instance;
    if (!is_object($plugin) || !isset($plugin->documents, $plugin->breakpoints)) {
        return array();
    }

    // Never in the Elementor editor or its preview iframe.
    if (isset($plugin->preview) && is_object($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
        return array();
    }
    if (isset($plugin->editor) && is_object($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
        return array();
    }

    $document = $plugin->documents->get($post_id);
    if (!is_object($document) || !method_exists($document, 'is_built_with_elementor') || !method_exists($document, 'get_elements_data')) {
        return array();
    }
    if (!$document->is_built_with_elementor()) {
        return array();
    }

    $elements = $document->get_elements_data();
    if (empty($elements) || !is_array($elements)) {
        return array();
    }

    $element = reset($elements);
    if (!is_array($element) || empty($element['elType']) || !in_array($element['elType'], array('container', 'section'), true)) {
        return array();
    }

    $devices = metahotels_hero_preload_get_devices($plugin->breakpoints);
    if (empty($devices)) {
        return array();
    }

    $images = metahotels_hero_preload_collect($element, $devices);

    /**
     * Filters the hero images preloaded in <head>.
     *
     * Return an empty array to print nothing. Each item is an array with a
     * `url` (the exact image URL the page will paint) and an optional `media`
     * query ('' = all viewports). Duplicate URLs are dropped (first wins).
     *
     * @param array[] $images  List of array( 'url' => string, 'media' => string ).
     * @param int     $post_id ID of the singular post being viewed.
     * @param array   $element Raw Elementor data of the page's first top-level element.
     */
    $images = apply_filters('metahotels_hero_preload_images', $images, $post_id, $element);

    return metahotels_hero_preload_normalize($images);
}

/**
 * Describe Elementor's active devices as viewport-width ranges.
 *
 * Ordered largest to smallest, matching how Elementor cascades responsive
 * values and the ranges its `elementor-hidden-{device}` classes use. A
 * `min`/`max` of null means unbounded on that side. `type` is 'min' for
 * min-width devices (widescreen), 'desktop', or 'max' for max-width devices.
 *
 * @param object $breakpoints Elementor breakpoints manager.
 * @return array[] List of array( 'name', 'type', 'min', 'max' ), or array() on failure.
 */
function metahotels_hero_preload_get_devices($breakpoints) {
    if (!is_object($breakpoints) || !method_exists($breakpoints, 'get_active_breakpoints')) {
        return array();
    }

    $min_device = null;
    $max_devices = array();
    foreach ((array) $breakpoints->get_active_breakpoints() as $name => $breakpoint) {
        if (!is_object($breakpoint) || !method_exists($breakpoint, 'get_value') || !method_exists($breakpoint, 'get_direction')) {
            return array();
        }
        $value = (int) $breakpoint->get_value();
        if ($value <= 0) {
            continue;
        }
        if ('min' === $breakpoint->get_direction()) {
            $min_device = array('name' => (string) $name, 'value' => $value);
        } else {
            $max_devices[] = array('name' => (string) $name, 'value' => $value);
        }
    }

    usort($max_devices, function ($a, $b) {
        return $b['value'] - $a['value'];
    });

    $devices = array();
    $desktop_max = null;
    if ($min_device) {
        $devices[] = array('name' => $min_device['name'], 'type' => 'min', 'min' => $min_device['value'], 'max' => null);
        $desktop_max = $min_device['value'] - 1;
    }
    $devices[] = array(
        'name' => 'desktop',
        'type' => 'desktop',
        'min'  => $max_devices ? $max_devices[0]['value'] + 1 : null,
        'max'  => $desktop_max,
    );
    foreach ($max_devices as $i => $device) {
        $devices[] = array(
            'name' => $device['name'],
            'type' => 'max',
            'min'  => isset($max_devices[$i + 1]) ? $max_devices[$i + 1]['value'] + 1 : null,
            'max'  => $device['value'],
        );
    }

    return $devices;
}

/**
 * Work out which image each device paints for an element, and group devices
 * that share an image into one preload with a matching media query.
 *
 * Devices hidden through Elementor's "Hide On {Device}" switches are left out,
 * so a viewport that never shows the element never preloads its image.
 *
 * @param array   $element Raw Elementor element data (container or section).
 * @param array[] $devices Output of metahotels_hero_preload_get_devices().
 * @return array[] List of array( 'url' => string, 'media' => string ).
 */
function metahotels_hero_preload_collect(array $element, array $devices) {
    $settings = (isset($element['settings']) && is_array($element['settings'])) ? $element['settings'] : array();
    $type = isset($settings['background_background']) ? $settings['background_background'] : '';

    // Dynamic-tag backgrounds are only known after Elementor renders them.
    if (!empty($settings['__dynamic__']) && is_array($settings['__dynamic__'])) {
        foreach (array_keys($settings['__dynamic__']) as $key) {
            if (0 === strpos($key, 'background_image') || 0 === strpos($key, 'background_slideshow_gallery') || 0 === strpos($key, 'background_video_fallback')) {
                return array();
            }
        }
    }

    $device_urls = array();

    if ('slideshow' === $type) {
        // Elementor's slideshow is not responsive and always starts at the
        // first gallery item, painted from the item's stored `url`.
        $gallery = isset($settings['background_slideshow_gallery']) ? $settings['background_slideshow_gallery'] : array();
        $first = (is_array($gallery) && $gallery) ? reset($gallery) : null;
        $url = (is_array($first) && !empty($first['url']) && is_string($first['url'])) ? $first['url'] : '';
        if ('' === $url) {
            return array();
        }
        foreach ($devices as $device) {
            $device_urls[$device['name']] = $url;
        }
    } elseif ('classic' === $type) {
        $values = array();
        $ids = array();
        foreach ($devices as $device) {
            $key = 'background_image' . ('desktop' === $device['name'] ? '' : '_' . $device['name']);
            $values[$device['name']] = isset($settings[$key]) ? $settings[$key] : null;
            if (is_array($values[$device['name']]) && !empty($values[$device['name']]['id'])) {
                $ids[] = (int) $values[$device['name']]['id'];
            }
        }
        // Load every referenced attachment in one go rather than one by one.
        if (count($ids) > 1 && function_exists('_prime_post_caches')) {
            _prime_post_caches(array_unique($ids), false, true);
        }

        $own = array();
        foreach ($values as $name => $value) {
            $own[$name] = metahotels_hero_preload_media_url($value);
        }

        // Mirror Elementor's CSS cascade: a device without its own image uses
        // the next larger device's; min-width devices fall back to desktop.
        $desktop = isset($own['desktop']) ? $own['desktop'] : '';
        $inherited = $desktop;
        foreach ($devices as $device) {
            $name = $device['name'];
            if ('desktop' === $device['type']) {
                $device_urls[$name] = $desktop;
            } elseif ('min' === $device['type']) {
                $device_urls[$name] = ('' !== $own[$name]) ? $own[$name] : $desktop;
            } else {
                if ('' !== $own[$name]) {
                    $inherited = $own[$name];
                }
                $device_urls[$name] = $inherited;
            }
        }
    } elseif ('video' === $type) {
        // The fallback image is not responsive: one CSS background for every device.
        $url = metahotels_hero_preload_media_url(isset($settings['background_video_fallback']) ? $settings['background_video_fallback'] : null);
        if ('' === $url) {
            return array();
        }
        foreach ($devices as $device) {
            $device_urls[$device['name']] = $url;
        }
    } else {
        return array();
    }

    // Group visible devices by URL, merging neighbouring width ranges.
    $groups = array();
    foreach ($devices as $device) {
        if (!empty($settings['hide_' . $device['name']])) {
            continue;
        }
        $url = isset($device_urls[$device['name']]) ? $device_urls[$device['name']] : '';
        if ('' === $url) {
            continue;
        }
        if (!isset($groups[$url])) {
            $groups[$url] = array();
        }
        $last = count($groups[$url]) - 1;
        if ($last >= 0 && null !== $groups[$url][$last]['min'] && null !== $device['max'] && $groups[$url][$last]['min'] === $device['max'] + 1) {
            $groups[$url][$last]['min'] = $device['min'];
        } else {
            $groups[$url][] = array('min' => $device['min'], 'max' => $device['max']);
        }
    }

    $images = array();
    foreach ($groups as $url => $ranges) {
        $queries = array();
        foreach ($ranges as $range) {
            $query = metahotels_hero_preload_media_query($range['min'], $range['max']);
            if ('' === $query) {
                // One range covers every viewport.
                $queries = array();
                break;
            }
            $queries[] = $query;
        }
        $images[] = array('url' => (string) $url, 'media' => implode(', ', $queries));
    }

    return $images;
}

/**
 * Resolve an Elementor Media control value to the URL its CSS uses.
 *
 * Mirrors \Elementor\Control_Media::get_style_value(): with an attachment ID
 * the URL comes from wp_get_attachment_image_url( id, size ?: 'full' ),
 * otherwise from the stored `url`.
 *
 * @param mixed $value Media control value (array with `url`, `id`, optional `size`).
 * @return string URL, or '' when the control is empty.
 */
function metahotels_hero_preload_media_url($value) {
    if (!is_array($value)) {
        return '';
    }
    if (!empty($value['id'])) {
        $size = (!empty($value['size']) && is_string($value['size'])) ? $value['size'] : 'full';
        $url = wp_get_attachment_image_url((int) $value['id'], $size);
        return is_string($url) ? $url : '';
    }
    return (!empty($value['url']) && is_string($value['url'])) ? $value['url'] : '';
}

/**
 * Build a media query for a viewport-width range.
 *
 * @param int|null $min Minimum width in px, or null for unbounded.
 * @param int|null $max Maximum width in px, or null for unbounded.
 * @return string Media query, or '' when the range is unbounded both ways.
 */
function metahotels_hero_preload_media_query($min, $max) {
    $parts = array();
    if (null !== $min) {
        $parts[] = '(min-width: ' . (int) $min . 'px)';
    }
    if (null !== $max) {
        $parts[] = '(max-width: ' . (int) $max . 'px)';
    }
    return implode(' and ', $parts);
}

/**
 * Validate a (possibly filtered) image list and drop duplicate URLs.
 *
 * @param mixed $images Image list.
 * @return array[] List of array( 'url' => string, 'media' => string ).
 */
function metahotels_hero_preload_normalize($images) {
    if (!is_array($images)) {
        return array();
    }
    $clean = array();
    foreach ($images as $image) {
        if (!is_array($image) || empty($image['url']) || !is_string($image['url'])) {
            continue;
        }
        $url = $image['url'];
        if (isset($clean[$url])) {
            continue;
        }
        $media = (isset($image['media']) && is_string($image['media'])) ? trim($image['media']) : '';
        $clean[$url] = array('url' => $url, 'media' => $media);
    }
    return array_values($clean);
}
