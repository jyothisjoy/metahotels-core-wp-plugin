<?php
/**
 * Video Background (R2) for Elementor Sections and Containers.
 *
 * Adds an externally-hosted (Cloudflare R2) video-background capability to
 * Elementor Sections and Containers as a native background property — not a
 * widget. Controls live in the section's own Style tab, directly below the
 * built-in Background controls. No markup is printed inside the section: the
 * feature travels to the frontend as a JSON data attribute on the wrapper and
 * the DOM layer is built client-side by vbg.js.
 *
 * Safe when Elementor is inactive: an admin notice is shown and every hook
 * below simply never fires.
 *
 * @package MetaHotels_Core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Asset cache-busting version for this feature's CSS/JS.
if (!defined('METAHOTELS_VBG_VER')) {
    define('METAHOTELS_VBG_VER', '1.2.0');
}

// Master switch: keep the entire feature dormant — no Elementor controls, no
// render attributes, and no CSS/JS enqueues — unless enabled in MetaHotels
// Settings › Other Settings. Off by default.
if (!get_option('metahotels_enable_video_background', false)) {
    return;
}

/**
 * Bail gracefully unless Elementor is loaded and recent enough.
 *
 * @return bool
 */
function metahotels_vbg_elementor_ready() {
    if (!did_action('elementor/loaded')) {
        return false;
    }
    if (!defined('ELEMENTOR_VERSION') || version_compare(ELEMENTOR_VERSION, '3.5.0', '<')) {
        return false;
    }
    return true;
}

/**
 * Admin notice when Elementor is missing, so the feature's absence is explained.
 */
function metahotels_vbg_admin_notice() {
    if (metahotels_vbg_elementor_ready()) {
        return;
    }
    if (!current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-warning is-dismissible"><p>';
    echo esc_html__('MetaHotels Video Background requires Elementor 3.5.0 or later to be active.', 'metahotels-core');
    echo '</p></div>';
}
add_action('admin_notices', 'metahotels_vbg_admin_notice');

/**
 * Whether the given Elementor element is a Section or Container.
 *
 * @param \Elementor\Element_Base $element
 * @return bool
 */
function metahotels_vbg_is_target_element($element) {
    if (!is_object($element) || !method_exists($element, 'get_name')) {
        return false;
    }
    $name = $element->get_name();
    return ('section' === $name || 'container' === $name);
}

/**
 * Reduce any Wistia reference to its bare hashed media ID.
 *
 * The admin field documents one input — the media page URL — but Wistia's
 * "Embed & Share" dialog also hands out two HTML+JS snippets, and users paste
 * what they have. Rather than store or echo that markup (which would inject
 * third-party <script> tags through the page), every accepted form is reduced
 * here to the ID alone and everything else is discarded. A value that matches
 * nothing yields '' and the section falls back to poster-only.
 *
 * @param string $input Raw field value.
 * @return string Hashed media ID, or '' when nothing matched.
 */
function metahotels_vbg_extract_wistia_id($input) {
    if (!is_string($input) || '' === $input) {
        return '';
    }
    $s = trim($input);

    // A bare ID is exactly 10 lowercase alphanumerics. Anchored so stray text
    // can never be mistaken for an ID.
    if (preg_match('/^[a-z0-9]{10}$/', $s)) {
        return $s;
    }

    // Context-anchored patterns are safe with a looser length, since the
    // surrounding syntax is unambiguous.
    $patterns = array(
        // https://account.wistia.com/medias/<id>
        '#wistia\.(?:com|net)/medias/([A-Za-z0-9]{6,20})#',
        // https://fast.wistia.net/embed/iframe/<id>, /embed/medias/<id>.jsonp, /embed/<id>.js
        '#wistia\.(?:com|net)/embed/(?:iframe/|medias/)?([A-Za-z0-9]{6,20})#',
        // <wistia-player media-id="<id>">
        '#media-id=["\']([A-Za-z0-9]{6,20})["\']#',
        // <div class="wistia_embed wistia_async_<id> …">
        '#wistia_async_([A-Za-z0-9]{6,20})#',
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $s, $m)) {
            return $m[1];
        }
    }

    return '';
}

/**
 * Inject the Video Background controls directly below the native Background
 * controls in the Style tab.
 *
 * @param \Elementor\Controls_Stack $element
 * @param string                    $section_id
 * @param array                     $args
 */
function metahotels_vbg_register_controls($element, $section_id, $args) {
    if (!metahotels_vbg_is_target_element($element)) {
        return;
    }
    // Land right beneath the native Background controls group.
    if ('section_background' !== $section_id) {
        return;
    }

    $element->start_controls_section(
        'metahotels_vbg_section',
        array(
            'label' => esc_html__('Video Background (R2)', 'metahotels-core'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        )
    );

    $element->add_control(
        'vbg_enable',
        array(
            'label'        => esc_html__('Enable Video Background', 'metahotels-core'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => esc_html__('On', 'metahotels-core'),
            'label_off'    => esc_html__('Off', 'metahotels-core'),
            'return_value' => 'yes',
            'default'      => '',
        )
    );

    $element->add_control(
        'vbg_source',
        array(
            'label'     => esc_html__('Source', 'metahotels-core'),
            'type'      => \Elementor\Controls_Manager::SELECT,
            'default'   => 'file',
            'options'   => array(
                'file'    => esc_html__('Self-hosted / R2 (MP4)', 'metahotels-core'),
                'youtube' => esc_html__('YouTube', 'metahotels-core'),
                'wistia'  => esc_html__('Wistia', 'metahotels-core'),
            ),
            'condition' => array('vbg_enable' => 'yes'),
        )
    );

    $element->add_control(
        'vbg_desktop',
        array(
            'label'       => esc_html__('Video URL (Desktop)', 'metahotels-core'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'dynamic'     => array('active' => true),
            'label_block' => true,
            'placeholder' => 'https://cdn.example.com/video.mp4',
            'description' => esc_html__('Full URL to the externally hosted (R2) video file.', 'metahotels-core'),
            'condition'   => array(
                'vbg_enable' => 'yes',
                'vbg_source' => 'file',
            ),
        )
    );

    $element->add_control(
        'vbg_mobile',
        array(
            'label'       => esc_html__('Video URL (Mobile, optional)', 'metahotels-core'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'dynamic'     => array('active' => true),
            'label_block' => true,
            'placeholder' => 'https://cdn.example.com/video-720p.mp4',
            'description' => esc_html__('Optional lighter encode for small screens. Falls back to the desktop URL.', 'metahotels-core'),
            'condition'   => array(
                'vbg_enable' => 'yes',
                'vbg_source' => 'file',
            ),
        )
    );

    $element->add_control(
        'vbg_youtube',
        array(
            'label'       => esc_html__('YouTube URL or ID', 'metahotels-core'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'dynamic'     => array('active' => true),
            'label_block' => true,
            'placeholder' => 'https://www.youtube.com/watch?v=…',
            'description' => esc_html__('A YouTube watch, youtu.be, embed, or shorts URL — or a bare 11-character ID.', 'metahotels-core'),
            'condition'   => array(
                'vbg_enable' => 'yes',
                'vbg_source' => 'youtube',
            ),
        )
    );

    $element->add_control(
        'vbg_wistia',
        array(
            'label'       => esc_html__('Wistia Media URL', 'metahotels-core'),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'dynamic'     => array('active' => true),
            'label_block' => true,
            'placeholder' => 'https://youraccount.wistia.com/medias/0t727spqx5',
            'description' => esc_html__('Open the video in your Wistia library and copy the address bar URL. Only the media ID is used — any extra parameters are ignored.', 'metahotels-core'),
            'condition'   => array(
                'vbg_enable' => 'yes',
                'vbg_source' => 'wistia',
            ),
        )
    );

    $element->add_control(
        'vbg_wistia_track',
        array(
            'label'        => esc_html__('Wistia Analytics', 'metahotels-core'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => esc_html__('On', 'metahotels-core'),
            'label_off'    => esc_html__('Off', 'metahotels-core'),
            'return_value' => 'yes',
            'default'      => '',
            'description'  => esc_html__('Off by default: a background video autoplays on every page view, so tracking it records a play each time and inflates your Wistia stats.', 'metahotels-core'),
            'condition'    => array(
                'vbg_enable' => 'yes',
                'vbg_source' => 'wistia',
            ),
        )
    );

    $element->add_control(
        'vbg_start',
        array(
            'label'     => esc_html__('Loop Start (seconds)', 'metahotels-core'),
            'type'      => \Elementor\Controls_Manager::NUMBER,
            'default'   => 0,
            'min'       => 0,
            'condition' => array(
                'vbg_enable' => 'yes',
                'vbg_source' => array('youtube', 'wistia'),
            ),
        )
    );

    $element->add_control(
        'vbg_end',
        array(
            'label'     => esc_html__('Loop End (seconds, 0 = play to end)', 'metahotels-core'),
            'type'      => \Elementor\Controls_Manager::NUMBER,
            'default'   => 0,
            'min'       => 0,
            'condition' => array(
                'vbg_enable' => 'yes',
                'vbg_source' => array('youtube', 'wistia'),
            ),
        )
    );

    $element->add_control(
        'vbg_crop',
        array(
            'label'       => esc_html__('Top Crop (%)', 'metahotels-core'),
            'type'        => \Elementor\Controls_Manager::SLIDER,
            'size_units'  => array('%'),
            'range'       => array(
                '%' => array(
                    'min'  => 0,
                    'max'  => 30,
                    'step' => 1,
                ),
            ),
            'default'     => array(
                'unit' => '%',
                'size' => 15,
            ),
            'description' => esc_html__('Hides YouTube’s auto-showing title bar by pushing the frame up.', 'metahotels-core'),
            'condition'   => array(
                'vbg_enable' => 'yes',
                'vbg_source' => 'youtube',
            ),
        )
    );

    $element->add_control(
        'vbg_hold',
        array(
            'label'       => esc_html__('Poster Hold (seconds)', 'metahotels-core'),
            'type'        => \Elementor\Controls_Manager::NUMBER,
            'default'     => 0,
            'min'         => 0,
            'max'         => 8,
            'step'        => 0.5,
            'description' => esc_html__('Hold the poster longer before revealing the player, hiding the first moments of playback. On YouTube this is the alternative to Top Crop and pairs with crop 0.', 'metahotels-core'),
            'condition'   => array(
                'vbg_enable' => 'yes',
                'vbg_source' => array('youtube', 'wistia'),
            ),
        )
    );

    $element->add_control(
        'vbg_poster',
        array(
            'label'     => esc_html__('Poster Image', 'metahotels-core'),
            'type'      => \Elementor\Controls_Manager::MEDIA,
            'dynamic'   => array('active' => true),
            'condition' => array('vbg_enable' => 'yes'),
        )
    );

    $element->add_control(
        'vbg_mobile_behavior',
        array(
            'label'     => esc_html__('On Mobile', 'metahotels-core'),
            'type'      => \Elementor\Controls_Manager::SELECT,
            'default'   => 'video',
            'options'   => array(
                'video'  => esc_html__('Play video', 'metahotels-core'),
                'poster' => esc_html__('Poster image only', 'metahotels-core'),
            ),
            'condition' => array('vbg_enable' => 'yes'),
        )
    );

    $element->add_control(
        'vbg_scrim',
        array(
            'label'      => esc_html__('Scrim (dark overlay)', 'metahotels-core'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array('%'),
            'range'      => array(
                '%' => array(
                    'min'  => 0,
                    'max'  => 90,
                    'step' => 1,
                ),
            ),
            'default'    => array(
                'unit' => '%',
                'size' => 0,
            ),
            'selectors'  => array(
                '{{WRAPPER}} .vbg-layer__scrim' => 'background: rgba(0,0,0,{{SIZE}}%);',
            ),
            'condition'  => array('vbg_enable' => 'yes'),
        )
    );

    $element->add_control(
        'vbg_show_controls',
        array(
            'label'        => esc_html__('Show Sound / Play Controls', 'metahotels-core'),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'label_on'     => esc_html__('On', 'metahotels-core'),
            'label_off'    => esc_html__('Off', 'metahotels-core'),
            'return_value' => 'yes',
            'default'      => 'yes',
            'condition'    => array('vbg_enable' => 'yes'),
        )
    );

    $element->add_control(
        'vbg_label',
        array(
            'label'     => esc_html__('Control Label', 'metahotels-core'),
            'type'      => \Elementor\Controls_Manager::TEXT,
            'default'   => esc_html__('Volume', 'metahotels-core'),
            'condition' => array(
                'vbg_enable'        => 'yes',
                'vbg_show_controls' => 'yes',
            ),
        )
    );

    $element->add_control(
        'vbg_controls_position',
        array(
            'label'     => esc_html__('Controls Position', 'metahotels-core'),
            'type'      => \Elementor\Controls_Manager::SELECT,
            'default'   => 'br',
            'options'   => array(
                'br' => esc_html__('Bottom Right', 'metahotels-core'),
                'bl' => esc_html__('Bottom Left', 'metahotels-core'),
                'tr' => esc_html__('Top Right', 'metahotels-core'),
                'tl' => esc_html__('Top Left', 'metahotels-core'),
            ),
            'condition' => array(
                'vbg_enable'        => 'yes',
                'vbg_show_controls' => 'yes',
            ),
        )
    );

    $element->add_control(
        'vbg_controls_offset',
        array(
            'label'      => esc_html__('Controls Edge Spacing', 'metahotels-core'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range'      => array(
                'px' => array(
                    'min'  => 0,
                    'max'  => 120,
                    'step' => 1,
                ),
            ),
            'default'    => array(
                'unit' => 'px',
                'size' => 20,
            ),
            'selectors'  => array(
                '{{WRAPPER}} .vbg-layer' => '--vbg-offset: {{SIZE}}px;',
            ),
            'condition'  => array(
                'vbg_enable'        => 'yes',
                'vbg_show_controls' => 'yes',
            ),
        )
    );

    $element->end_controls_section();
}
add_action('elementor/element/after_section_end', 'metahotels_vbg_register_controls', 10, 3);

/**
 * Build the JSON config for an enabled element, or null when nothing to render.
 *
 * @param \Elementor\Element_Base $element
 * @return array|null
 */
function metahotels_vbg_build_config($element) {
    $settings = $element->get_settings_for_display();

    if (empty($settings['vbg_enable']) || 'yes' !== $settings['vbg_enable']) {
        return null;
    }

    $desktop = isset($settings['vbg_desktop']) ? esc_url_raw(trim($settings['vbg_desktop'])) : '';
    $mobile  = isset($settings['vbg_mobile']) ? esc_url_raw(trim($settings['vbg_mobile'])) : '';

    $poster = '';
    if (!empty($settings['vbg_poster']) && !empty($settings['vbg_poster']['url'])) {
        $poster = esc_url_raw($settings['vbg_poster']['url']);
    }

    $source = 'file';
    if (isset($settings['vbg_source'])
        && in_array($settings['vbg_source'], array('youtube', 'wistia'), true)) {
        $source = $settings['vbg_source'];
    }

    $youtube = isset($settings['vbg_youtube']) ? sanitize_text_field(trim($settings['vbg_youtube'])) : '';
    $start   = isset($settings['vbg_start']) ? absint($settings['vbg_start']) : 0;
    $end     = isset($settings['vbg_end']) ? absint($settings['vbg_end']) : 0;

    // Only the hashed ID survives — a pasted embed snippet never reaches the page.
    $wistia = isset($settings['vbg_wistia'])
        ? metahotels_vbg_extract_wistia_id($settings['vbg_wistia'])
        : '';
    $wistia_track = (isset($settings['vbg_wistia_track']) && 'yes' === $settings['vbg_wistia_track']);

    // Wistia serves a placeholder derived from the ID, so a section reads as
    // finished without anyone uploading a poster. An explicit poster still wins.
    if ('wistia' === $source && '' === $poster && '' !== $wistia) {
        $poster = 'https://fast.wistia.com/embed/medias/' . $wistia . '/swatch';
    }

    $crop = 15.0;
    if (isset($settings['vbg_crop']['size']) && '' !== $settings['vbg_crop']['size']) {
        $crop = floatval($settings['vbg_crop']['size']);
    }

    $hold = isset($settings['vbg_hold']) ? floatval($settings['vbg_hold']) : 0.0;

    // Nothing worth rendering — scoped per source.
    if ('file' === $source) {
        if ('' === $desktop && '' === $mobile && '' === $poster) {
            return null;
        }
    } elseif ('wistia' === $source) {
        // Wistia: bail when neither a media ID nor a poster is set.
        if ('' === $wistia && '' === $poster) {
            return null;
        }
    } else {
        // YouTube: bail when neither a video reference nor a poster is set.
        if ('' === $youtube && '' === $poster) {
            return null;
        }
    }

    $show_controls = (isset($settings['vbg_show_controls']) && 'yes' === $settings['vbg_show_controls']);

    $label = isset($settings['vbg_label']) ? wp_strip_all_tags($settings['vbg_label']) : '';
    if ('' === $label) {
        $label = esc_html__('Volume', 'metahotels-core');
    }

    $mobile_behavior = 'poster';
    if (!isset($settings['vbg_mobile_behavior']) || 'poster' !== $settings['vbg_mobile_behavior']) {
        $mobile_behavior = 'video';
    }

    $position = 'br';
    if (isset($settings['vbg_controls_position'])
        && in_array($settings['vbg_controls_position'], array('br', 'bl', 'tr', 'tl'), true)) {
        $position = $settings['vbg_controls_position'];
    }

    // Source-specific keys are emitted conditionally, and the file branch keeps
    // the exact original key order, so existing MP4 sections stay byte-identical
    // (the JS treats an absent 'source' as 'file').
    $config = array();

    if ('youtube' === $source) {
        $config['source']  = 'youtube';
        $config['youtube'] = $youtube;
        $config['start']   = $start;
        $config['end']     = $end;
        $config['crop']    = $crop;
        $config['hold']    = $hold;
    } elseif ('wistia' === $source) {
        $config['source'] = 'wistia';
        $config['wistia'] = $wistia;
        $config['start']  = $start;
        $config['end']    = $end;
        $config['hold']   = $hold;
        $config['track']  = $wistia_track;
    } else {
        $config['desktop'] = $desktop;
        $config['mobile']  = $mobile;
    }

    $config['poster']         = $poster;
    $config['mobileBehavior'] = $mobile_behavior;
    $config['showControls']   = $show_controls;
    $config['label']          = $label;
    $config['position']       = $position;
    // Translated strings travel with the config so the JS ships no English.
    // 'on'/'off' caption the sound pill and must reflect its current state.
    $config['i18n']           = array(
        'play'  => esc_html__('Play video', 'metahotels-core'),
        'pause' => esc_html__('Pause video', 'metahotels-core'),
        'on'    => esc_html__('ON', 'metahotels-core'),
        'off'   => esc_html__('OFF', 'metahotels-core'),
    );

    return $config;
}

/**
 * Attach the config to the wrapper and conditionally enqueue the script.
 * Prints no markup inside the section — the JS builds the DOM.
 *
 * @param \Elementor\Element_Base $element
 */
function metahotels_vbg_before_render($element) {
    if (!metahotels_vbg_is_target_element($element)) {
        return;
    }

    $config = metahotels_vbg_build_config($element);
    if (null === $config) {
        return;
    }

    $element->add_render_attribute(
        '_wrapper',
        array(
            'class'    => 'has-vbg',
            'data-vbg' => wp_json_encode($config),
        )
    );

    // Load the script only on pages that actually use the feature.
    if (!is_admin()) {
        if (!wp_script_is('metahotels-vbg', 'registered')) {
            metahotels_vbg_register_assets();
        }
        wp_enqueue_script('metahotels-vbg');
    }
}
add_action('elementor/frontend/before_render', 'metahotels_vbg_before_render');

/**
 * Register both assets. Script is enqueued conditionally; style is enqueued
 * site-wide (it is ~2KB) to avoid a footer-injected <link> and its flash.
 */
function metahotels_vbg_register_assets() {
    wp_register_style(
        'metahotels-vbg',
        plugins_url('../assets/vbg.css', __FILE__),
        array(),
        METAHOTELS_VBG_VER
    );

    wp_register_script(
        'metahotels-vbg',
        plugins_url('../assets/vbg.js', __FILE__),
        array(),
        METAHOTELS_VBG_VER,
        true
    );
}
add_action('elementor/frontend/after_register_scripts', 'metahotels_vbg_register_assets');

/**
 * Enqueue the stylesheet site-wide wherever Elementor renders.
 */
function metahotels_vbg_enqueue_style() {
    wp_enqueue_style('metahotels-vbg');
}
add_action('elementor/frontend/after_enqueue_styles', 'metahotels_vbg_enqueue_style');

/**
 * In the editor preview, both assets are always needed for live rebuilds.
 */
function metahotels_vbg_preview_assets() {
    // Ensure registration has happened even if the register hook order differs.
    if (!wp_script_is('metahotels-vbg', 'registered')) {
        metahotels_vbg_register_assets();
    }
    wp_enqueue_style('metahotels-vbg');
    wp_enqueue_script('metahotels-vbg');
}
add_action('elementor/preview/enqueue_scripts', 'metahotels_vbg_preview_assets');
