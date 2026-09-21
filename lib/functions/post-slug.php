<?php
/**
 * Post permalink prefix.
 *
 * Adds a path segment in front of every blog post URL — example.com/blog/hello/
 * instead of example.com/hello/ — without touching Pages or any custom post
 * type, and without overwriting the site's own permalink structure in
 * Settings > Permalinks.
 *
 * Two hooks do the work:
 *   - pre_post_link      prefixes the structure used to BUILD post permalinks.
 *   - post_rewrite_rules prefixes the rules that RESOLVE them on the way in.
 *
 * Deliberately not implemented by rewriting the permalink_structure option:
 * WP_Rewrite derives $wp_rewrite->front from everything before the first %
 * token, so a prefix there would also land on every taxonomy and custom post
 * type registered with 'with_front' => true (in this plugin: Rooms,
 * Surroundings, Offers, Careers and Destinations).
 *
 * @package MetaHotels_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Normalise a user-entered slug into a clean path segment (or segments).
 *
 * Accepts a nested path such as "news/articles"; each segment is sanitised
 * separately so the slashes survive. Falls back to 'blog' when nothing usable
 * is left, so the field can never be saved as an empty prefix.
 *
 * @param mixed $value Raw option value.
 * @return string
 */
function metahotels_sanitize_post_slug($value) {
    $value = is_string($value) ? trim($value) : '';
    $value = trim($value, "/ \t\n\r\0\x0B");

    if ('' === $value) {
        return 'blog';
    }

    $parts = array();
    foreach (explode('/', $value) as $segment) {
        $segment = sanitize_title($segment);
        if ('' !== $segment) {
            $parts[] = $segment;
        }
    }

    return empty($parts) ? 'blog' : implode('/', $parts);
}

/**
 * The active prefix, or '' when the feature should not apply.
 *
 * @return string
 */
function metahotels_post_slug_prefix() {
    if (!get_option('metahotels_enable_post_slug', false)) {
        return '';
    }

    // A prefix is only expressible in a pretty permalink. Under plain
    // permalinks (?p=123) there is no path to prefix, and returning one would
    // hand get_permalink() a structure with no % tokens to replace.
    if (!get_option('permalink_structure')) {
        return '';
    }

    return metahotels_sanitize_post_slug(get_option('metahotels_post_slug', 'blog'));
}

/**
 * Prefix the permalink structure that posts are built from.
 *
 * Scope note: get_permalink() returns early for pages, attachments and every
 * custom post type, reaching this filter only for the built-in 'post' type.
 * That is what keeps the prefix off Hotels, Rooms, Offers and the rest — the
 * filter is never consulted for them.
 *
 * @param string $permalink Post permalink structure.
 * @return string
 */
function metahotels_post_slug_pre_post_link($permalink) {
    $prefix = metahotels_post_slug_prefix();

    if ('' === $prefix || !is_string($permalink) || '' === $permalink) {
        return $permalink;
    }

    return '/' . $prefix . '/' . ltrim($permalink, '/');
}
add_filter('pre_post_link', 'metahotels_post_slug_pre_post_link', 10, 1);

/**
 * Make the prefixed URLs resolve.
 *
 * Re-keys the post rules WordPress generated for the current permalink
 * structure, whatever that structure is, so this keeps working for
 * /%postname%/, /%year%/%monthnum%/%postname%/ and the rest alike.
 *
 * The originals are kept after the prefixed copies: links published before the
 * prefix was turned on still resolve, and redirect_canonical() then 301s them
 * to the prefixed permalink rather than leaving them dead.
 *
 * @param array $rules Generated post rewrite rules.
 * @return array
 */
function metahotels_post_slug_rewrite_rules($rules) {
    $prefix = metahotels_post_slug_prefix();

    if ('' === $prefix || !is_array($rules) || empty($rules)) {
        return $rules;
    }

    $prefixed = array();
    foreach ($rules as $regex => $query) {
        $prefixed[$prefix . '/' . ltrim($regex, '^/')] = $query;
    }

    // Prefixed rules first: a bare /%postname%/ structure generates a catch-all
    // that would otherwise match "blog/hello" and look for a post named "blog".
    return array_merge($prefixed, $rules);
}
add_filter('post_rewrite_rules', 'metahotels_post_slug_rewrite_rules');

/**
 * 301 an un-prefixed post URL to its prefixed permalink.
 *
 * The un-prefixed rules kept above keep old links alive, but WordPress will not
 * clean them up on its own: redirect_canonical() only rebuilds a permalink when
 * the request arrived as a query string ($_GET['p'], $_GET['name'], ...). A URL
 * that a rewrite rule matched is treated as authoritative, so /hello/ and
 * /blog/hello/ would both answer 200 and split the post between two addresses.
 *
 * Prepending the prefix to the requested path — rather than redirecting to
 * get_permalink() — keeps whatever trails the post: pagination (/2/), comment
 * pages, and the query string all survive the redirect.
 */
function metahotels_post_slug_redirect_unprefixed() {
    $prefix = metahotels_post_slug_prefix();

    if ('' === $prefix || !is_singular('post')) {
        return;
    }

    // Feeds, embeds, previews and trackbacks have their own URL shapes; leave
    // them to WordPress.
    if (is_feed() || is_embed() || is_preview() || is_trackback() || is_customize_preview()) {
        return;
    }

    $request = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    $path    = (string) parse_url($request, PHP_URL_PATH);

    if ('' === $path) {
        return;
    }

    // Strip the install's own directory, if WordPress lives in a subfolder.
    $home_path = (string) parse_url(home_url('/'), PHP_URL_PATH);
    $home_path = '/' . ltrim(trailingslashit($home_path), '/');

    if ('/' !== $home_path) {
        if (0 !== strpos($path, $home_path)) {
            return;
        }
        $path = substr($path, strlen($home_path) - 1);
    }

    $relative = ltrim($path, '/');

    // Plain permalinks (/?p=123) leave nothing to prefix; redirect_canonical()
    // already handles those.
    if ('' === $relative) {
        return;
    }

    // Already prefixed — nothing to do.
    if (0 === strpos($relative, $prefix . '/')) {
        return;
    }

    $target = home_url('/' . $prefix . '/' . $relative);

    $query = (string) parse_url($request, PHP_URL_QUERY);
    if ('' !== $query) {
        $target .= '?' . $query;
    }

    wp_safe_redirect($target, 301);
    exit;
}
add_action('template_redirect', 'metahotels_post_slug_redirect_unprefixed', 9);
