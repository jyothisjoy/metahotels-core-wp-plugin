<?php
// Brevo Settings Page
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
// function metahotels_brevo_admin_menu() {
//    add_submenu_page(
//        'options-general.php',
//        'Brevo Settings',
//        'Brevo Settings',
//        'manage_options',
//        'metahotels-brevo-settings',
//        'metahotels_brevo_settings_page'
//    );
// }
// add_action('admin_menu', 'metahotels_brevo_admin_menu');

// Register settings
function metahotels_brevo_register_settings() {
    register_setting('metahotels_brevo_options', 'metahotels_brevo_api_key', array(
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_recaptcha_site_key', array(
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_recaptcha_secret_key', array(
        'sanitize_callback' => 'sanitize_text_field',
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_recaptcha_score_threshold', array(
        'sanitize_callback' => 'metahotels_sanitize_score_threshold',
        'default' => 0.5,
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_lists', array(
        'type'              => 'array',
        'sanitize_callback' => 'metahotels_sanitize_brevo_lists',
        'default'           => array(),
        'autoload'          => false, // Large JSON array; only needed in admin
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_senders', array(
        'type'              => 'array',
        'sanitize_callback' => 'metahotels_sanitize_brevo_senders',
        'default'           => array(),
        'autoload'          => false, // Large JSON array; only needed in admin
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_default_country', array(
        'sanitize_callback' => 'metahotels_sanitize_calling_code',
        'default' => '+91',
    ));

    register_setting('metahotels_brevo_options', 'metahotels_brevo_debug_mode', array(
        'sanitize_callback' => 'metahotels_sanitize_boolean',
        'default' => false,
    ));
    register_setting('metahotels_brevo_options', 'metahotels_ipapi_api_key', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_transactional_enabled', array(
        'type' => 'boolean',
        'sanitize_callback' => 'metahotels_sanitize_boolean',
        'default' => false
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_sender_name', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_sender_email', array(
        'sanitize_callback' => 'sanitize_email'
    ));
}
add_action('admin_init', 'metahotels_brevo_register_settings');

// Sanitize helpers for register_setting()
if (!function_exists('metahotels_sanitize_boolean')) {
    function metahotels_sanitize_boolean($value) {
        return (bool) $value;
    }
}

function metahotels_sanitize_score_threshold($value) {
    $value = floatval($value);
    return max(0.0, min(1.0, $value));
}

function metahotels_sanitize_brevo_lists($value) {
    if (!is_array($value)) {
        return array();
    }
    $clean = array();
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }
        $clean[] = array(
            'id'          => isset($item['id']) ? intval($item['id']) : 0,
            'name'        => isset($item['name']) ? sanitize_text_field($item['name']) : '',
            'subscribers' => isset($item['subscribers']) ? intval($item['subscribers']) : 0,
        );
    }
    return $clean;
}

function metahotels_sanitize_brevo_senders($value) {
    if (!is_array($value)) {
        return array();
    }
    $clean = array();
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }
        $clean[] = array(
            'id'     => isset($item['id']) ? intval($item['id']) : 0,
            'name'   => isset($item['name']) ? sanitize_text_field($item['name']) : '',
            'email'  => isset($item['email']) ? sanitize_email($item['email']) : '',
            'active' => isset($item['active']) ? (bool) $item['active'] : true,
        );
    }
    return $clean;
}

function metahotels_sanitize_calling_code($value) {
    $value = is_string($value) ? trim($value) : '';
    if ($value === '') {
        return '+91';
    }

    $value = preg_replace('/[^0-9+]/', '', $value);
    if ($value === null) {
        return '+91';
    }

    if (strpos($value, '+') !== 0) {
        $value = '+' . ltrim($value, '+');
    }

    if (!preg_match('/^\+\d{1,4}$/', $value)) {
        return '+91';
    }

    return $value;
}

function metahotels_brevo_debug_enabled() {
    return (bool) get_option('metahotels_brevo_debug_mode', false);
}

function metahotels_brevo_log($message, $context = array()) {
    if (!metahotels_brevo_debug_enabled()) {
        return;
    }

    if (!empty($context)) {
        error_log('[MetaHotels Brevo] ' . $message . ' ' . wp_json_encode($context));
        return;
    }

    error_log('[MetaHotels Brevo] ' . $message);
}

// Settings page HTML
function metahotels_brevo_render_content() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $api_key = get_option('metahotels_brevo_api_key', '');
    $recaptcha_site_key = get_option('metahotels_brevo_recaptcha_site_key', '');
    $recaptcha_secret_key = get_option('metahotels_brevo_recaptcha_secret_key', '');
    $recaptcha_required = (bool) get_option('metahotels_brevo_recaptcha_required', false);
    $recaptcha_score_threshold = get_option('metahotels_brevo_recaptcha_score_threshold', 0.5);
    $lists = get_option('metahotels_brevo_lists', array());
    $default_country = get_option('metahotels_brevo_default_country', '+91');

    $debug_mode = get_option('metahotels_brevo_debug_mode', false);
    $ipapi_api_key = get_option('metahotels_ipapi_api_key', '');
    $transactional_enabled = get_option('metahotels_brevo_transactional_enabled', false);
    $sender_name = get_option('metahotels_brevo_sender_name', get_bloginfo('name'));
    $sender_email = get_option('metahotels_brevo_sender_email', get_option('admin_email'));
    $senders = get_option('metahotels_brevo_senders', array());
    $lists_fetch_error = get_option('metahotels_brevo_lists_last_error', '');
    $sender_fetch_error = get_option('metahotels_brevo_senders_last_error', '');
    
    // Handle API key update and fetch lists
    if (isset($_POST['submit'])) {
        check_admin_referer('metahotels_brevo_save', 'metahotels_brevo_nonce_field');
        // Secret fields use a write-only pattern: the stored value is never
        // rendered back into the form. A blank submission therefore means
        // "keep the existing key"; a non-empty submission replaces it.
        $submitted_api_key = isset($_POST['metahotels_brevo_api_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_brevo_api_key'])) : '';
        $new_api_key = ('' !== $submitted_api_key) ? $submitted_api_key : $api_key;
        $new_recaptcha_site_key = isset($_POST['metahotels_brevo_recaptcha_site_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_brevo_recaptcha_site_key'])) : $recaptcha_site_key;
        $submitted_recaptcha_secret_key = isset($_POST['metahotels_brevo_recaptcha_secret_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_brevo_recaptcha_secret_key'])) : '';
        $new_recaptcha_secret_key = ('' !== $submitted_recaptcha_secret_key) ? $submitted_recaptcha_secret_key : $recaptcha_secret_key;
        $new_recaptcha_score_threshold = isset($_POST['metahotels_brevo_recaptcha_score_threshold']) ? floatval(wp_unslash($_POST['metahotels_brevo_recaptcha_score_threshold'])) : $recaptcha_score_threshold;
        // Ensure threshold is between 0 and 1
        if ($new_recaptcha_score_threshold < 0) {
            $new_recaptcha_score_threshold = 0;
        } elseif ($new_recaptcha_score_threshold > 1) {
            $new_recaptcha_score_threshold = 1;
        }
        $new_default_country = isset($_POST['metahotels_brevo_default_country']) ? metahotels_sanitize_calling_code(wp_unslash($_POST['metahotels_brevo_default_country'])) : $default_country;

        $new_debug_mode = isset($_POST['metahotels_brevo_debug_mode']) && wp_unslash($_POST['metahotels_brevo_debug_mode']) === '1';
        $new_recaptcha_required = isset($_POST['metahotels_brevo_recaptcha_required']) && wp_unslash($_POST['metahotels_brevo_recaptcha_required']) === '1';
        $submitted_ipapi_api_key = isset($_POST['metahotels_ipapi_api_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_ipapi_api_key'])) : '';
        $new_ipapi_api_key = ('' !== $submitted_ipapi_api_key) ? $submitted_ipapi_api_key : $ipapi_api_key;
        $new_transactional_enabled = isset($_POST['metahotels_brevo_transactional_enabled']) && wp_unslash($_POST['metahotels_brevo_transactional_enabled']) === '1';
        $new_sender_name = isset($_POST['metahotels_brevo_sender_name']) ? sanitize_text_field(wp_unslash($_POST['metahotels_brevo_sender_name'])) : $sender_name;
        $new_sender_email = isset($_POST['metahotels_brevo_sender_email']) ? sanitize_email(wp_unslash($_POST['metahotels_brevo_sender_email'])) : $sender_email;

        update_option('metahotels_brevo_api_key', $new_api_key);
        update_option('metahotels_ipapi_api_key', $new_ipapi_api_key);
        update_option('metahotels_brevo_transactional_enabled', $new_transactional_enabled);
        update_option('metahotels_brevo_sender_name', $new_sender_name);
        update_option('metahotels_brevo_sender_email', $new_sender_email);

        $transactional_enabled = $new_transactional_enabled;
        $sender_name = $new_sender_name;
        $sender_email = $new_sender_email;
        update_option('metahotels_brevo_default_country', $new_default_country);
        update_option('metahotels_brevo_recaptcha_site_key', $new_recaptcha_site_key);
        update_option('metahotels_brevo_recaptcha_secret_key', $new_recaptcha_secret_key);
        update_option('metahotels_brevo_recaptcha_score_threshold', $new_recaptcha_score_threshold);
        update_option('metahotels_brevo_recaptcha_required', $new_recaptcha_required);

        update_option('metahotels_brevo_debug_mode', $new_debug_mode);

        // Update local variables for display in the same request.
        $api_key = $new_api_key;
        $recaptcha_site_key = $new_recaptcha_site_key;
        $recaptcha_secret_key = $new_recaptcha_secret_key;
        $recaptcha_required = $new_recaptcha_required;
        $recaptcha_score_threshold = $new_recaptcha_score_threshold;
        $default_country = $new_default_country;
        $debug_mode = $new_debug_mode;
        $ipapi_api_key = $new_ipapi_api_key;
        
        // Only fetch lists and senders if API key is provided
        if (!empty($new_api_key)) {
            // IMPORTANT: only overwrite the cached lists/senders when the fetch
            // actually succeeds. The cached lists back the front-end "Invalid list
            // ID" whitelist, so clobbering them with an empty array on a transient
            // API failure (e.g. HTTP 401, rate limiting) would break every live
            // subscription form. On failure we keep the previous cache and surface
            // the error instead.
            $lists_fetch_error = '';
            $fetched_lists = metahotels_brevo_fetch_lists($new_api_key, $lists_fetch_error);
            if (!empty($fetched_lists)) {
                $lists = $fetched_lists;
                update_option('metahotels_brevo_lists', $lists, false);
            }
            update_option('metahotels_brevo_lists_last_error', $lists_fetch_error, false);

            $sender_fetch_error = '';
            $fetched_senders = metahotels_brevo_fetch_senders($new_api_key, $sender_fetch_error);
            if (!empty($fetched_senders)) {
                $senders = $fetched_senders;
                update_option('metahotels_brevo_senders', $senders, false);
            }
            update_option('metahotels_brevo_senders_last_error', $sender_fetch_error, false);

            if (empty($lists_fetch_error) && empty($sender_fetch_error)) {
                echo '<div class="notice notice-success"><p>Settings updated and data fetched successfully!</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
            }
            if (!empty($lists_fetch_error)) {
                echo '<div class="notice notice-error"><p>Brevo lists fetch failed: ' . esc_html($lists_fetch_error) . ' &mdash; previously fetched lists were kept.</p></div>';
            }
            if (!empty($sender_fetch_error)) {
                echo '<div class="notice notice-error"><p>Brevo senders fetch failed: ' . esc_html($sender_fetch_error) . ' &mdash; previously fetched senders were kept.</p></div>';
            }
        } else {
            $lists = array();
            $senders = array();
            $lists_fetch_error = '';
            $sender_fetch_error = '';
            update_option('metahotels_brevo_lists', $lists, false);
            update_option('metahotels_brevo_senders', $senders, false);
            update_option('metahotels_brevo_lists_last_error', $lists_fetch_error, false);
            update_option('metahotels_brevo_senders_last_error', $sender_fetch_error, false);
            echo '<div class="notice notice-success"><p>Settings updated successfully!</p></div>';
        }
    }

    // If an API key already exists but list cache is empty, fetch once so list UI
    // can populate without requiring a dedicated re-save flow.
    if (!empty($api_key) && empty($lists)) {
        $lists_fetch_error = '';
        $fetched_lists = metahotels_brevo_fetch_lists($api_key, $lists_fetch_error);
        if (!empty($fetched_lists)) {
            $lists = $fetched_lists;
            update_option('metahotels_brevo_lists', $lists, false);
        }
        update_option('metahotels_brevo_lists_last_error', $lists_fetch_error, false);
    }

    // If an API key already exists but sender cache is empty, fetch once so the
    // transactional sender dropdown is immediately usable.
    if (!empty($api_key) && empty($senders)) {
        $sender_fetch_error = '';
        $fetched_senders = metahotels_brevo_fetch_senders($api_key, $sender_fetch_error);
        if (!empty($fetched_senders)) {
            $senders = $fetched_senders;
            update_option('metahotels_brevo_senders', $senders, false);
        }
        update_option('metahotels_brevo_senders_last_error', $sender_fetch_error, false);
    }
    
    // Handle test contact creation
    if (isset($_POST['test_contact'])) {
        check_admin_referer('metahotels_brevo_test_contact', 'metahotels_brevo_test_nonce_field');
        $api_key = get_option('metahotels_brevo_api_key', '');
        $lists = get_option('metahotels_brevo_lists', array());

        if (empty($api_key)) {
            echo '<div class="notice notice-error"><p>Brevo API key is not configured.</p></div>';
        } elseif (!empty($lists)) {
            $list_id = $lists[0]['id'];
            $test_result = metahotels_brevo_test_contact_creation($api_key, $list_id);
            
            if ($test_result['success']) {
                echo '<div class="notice notice-success"><p>Test contact created successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Test contact creation failed: ' . esc_html($test_result['message']) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>No lists available for testing.</p></div>';
        }
    }
    
    ?>
        <form method="post" action="">
            <div class="metahotels-section">
                <!-- Brevo API Card -->
                <div class="metahotels-card">
                    <div class="metahotels-card-header">
                        <h3 class="metahotels-card-title">Brevo Configuration</h3>
                        <p class="metahotels-card-description">Connect your Brevo account for contact list management and transactional email.</p>
                    </div>
                    <div class="metahotels-card-content">
                        <div class="metahotels-form-group">
                            <label class="metahotels-label" for="metahotels_brevo_api_key">Brevo API Key</label>
                            <input type="password"
                                   id="metahotels_brevo_api_key"
                                   name="metahotels_brevo_api_key"
                                   value=""
                                    class="metahotels-input"
                                   autocomplete="off"
                                   placeholder="<?php echo $api_key ? esc_attr__('••••••••  (saved — leave blank to keep)', 'metahotels-core') : esc_attr__('Enter your Brevo API key', 'metahotels-core'); ?>" />
                            <p class="metahotels-helper-text">Your Brevo API key from Settings > API Keys in your Brevo account. For security it is never displayed; leave blank to keep the saved key, or enter a new value to replace it.</p>
                        </div>
                    </div>
                </div>

                <!-- Brevo Transactional Email Card -->
                <div class="metahotels-card">
                    <div class="metahotels-card-header">
                        <h3 class="metahotels-card-title">Transactional Email</h3>
                        <p class="metahotels-card-description">Route all WordPress emails (notifications, password resets, etc.) through Brevo for reliable delivery.</p>
                    </div>
                    <div class="metahotels-card-content">
                        <div style="margin-bottom: 1.5rem;">
                            <div class="metahotels-switch-wrapper">
                                <div class="metahotels-switch-label">
                                    <span class="metahotels-switch-title">Enable Brevo for All WordPress Emails</span>
                                    <span class="metahotels-switch-desc">All emails sent via wp_mail() will be delivered through the Brevo API.</span>
                                </div>
                                <label class="metahotels-switch">
                                    <input type="checkbox"
                                           name="metahotels_brevo_transactional_enabled"
                                           value="1"
                                           id="metahotels_brevo_transactional_enabled"
                                           <?php checked($transactional_enabled, true); ?> />
                                    <span class="metahotels-slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="metahotels-grid">
                            <div class="metahotels-form-group">
                                <label class="metahotels-label" for="metahotels_brevo_sender_name">From Name</label>
                                <input type="text"
                                       id="metahotels_brevo_sender_name"
                                       name="metahotels_brevo_sender_name"
                                       value="<?php echo esc_attr($sender_name); ?>"
                                       class="metahotels-input" />
                                <p class="metahotels-helper-text">The name recipients see in the From field. Auto-fills when you pick a sender below, but you can override it.</p>
                            </div>
                            <div class="metahotels-form-group">
                                <label class="metahotels-label" for="metahotels_brevo_sender_email">From Email</label>
                                <?php if (!empty($senders)): ?>
                                    <select id="metahotels_brevo_sender_email"
                                            name="metahotels_brevo_sender_email"
                                            class="metahotels-input"
                                            onchange="metahotelsSyncSenderName(this)">
                                        <option value="">- Select a verified sender -</option>
                                        <?php foreach ($senders as $s): ?>
                                            <option value="<?php echo esc_attr($s['email']); ?>"
                                                    data-name="<?php echo esc_attr($s['name']); ?>"
                                                    <?php selected($sender_email, $s['email']); ?>>
                                                <?php echo esc_html($s['email']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="metahotels-helper-text">Verified senders fetched from your Brevo account.</p>
                                    <script>
                                    function metahotelsSyncSenderName(select) {
                                        var opt = select.options[select.selectedIndex];
                                        var nameField = document.getElementById('metahotels_brevo_sender_name');
                                        if (opt.getAttribute('data-name')) {
                                            nameField.value = opt.getAttribute('data-name');
                                        }
                                    }
                                    </script>
                                <?php else: ?>
                                    <input type="email"
                                           id="metahotels_brevo_sender_email"
                                           name="metahotels_brevo_sender_email"
                                           value="<?php echo esc_attr($sender_email); ?>"
                                           class="metahotels-input" />
                                    <?php
                                    $sender_error_text = '';
                                    if (!empty($sender_fetch_error)) {
                                        $sender_error_text = ' Last fetch error: ' . wp_html_excerpt($sender_fetch_error, 180, '...');
                                    }
                                    ?>
                                    <p class="metahotels-helper-text">No verified senders found. Save your Brevo API key above to load senders from your account.<?php echo esc_html($sender_error_text); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($sender_email) && !empty($api_key)): ?>
                <!-- Test Email Card -->
                <div class="metahotels-card">
                    <div class="metahotels-card-header">
                        <h3 class="metahotels-card-title">Test Email Connection</h3>
                        <p class="metahotels-card-description">Send a test email to verify your Brevo configuration is working correctly.</p>
                    </div>
                    <div class="metahotels-card-content">
                        <div class="metahotels-grid">
                            <div class="metahotels-form-group">
                                <label class="metahotels-label" for="metahotels_brevo_test_recipient">Send Test To</label>
                                <input type="email"
                                       id="metahotels_brevo_test_recipient"
                                       placeholder="recipient@example.com"
                                       class="metahotels-input" />
                                <p class="metahotels-helper-text">Will be sent from: <strong><?php echo esc_html($sender_name . ' <' . $sender_email . '>'); ?></strong></p>
                            </div>
                            <div class="metahotels-form-group" style="display:flex; align-items:flex-end;">
                                <button type="button"
                                        id="metahotels_brevo_test_btn"
                                        class="button button-secondary"
                                        onclick="metahotelsBrevoSendTest()">
                                    Send Test Email
                                </button>
                            </div>
                        </div>
                        <div id="metahotels_brevo_test_result" style="margin-top:1rem; display:none;"></div>
                    </div>
                </div>
                <script>
                function metahotelsBrevoSendTest() {
                    var recipient = document.getElementById('metahotels_brevo_test_recipient').value.trim();
                    var btn       = document.getElementById('metahotels_brevo_test_btn');
                    var result    = document.getElementById('metahotels_brevo_test_result');
                    var ajaxUrl   = '<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>';

                    function renderNotice(type, message) {
                        result.style.display = 'block';
                        result.textContent = '';

                        var notice = document.createElement('div');
                        notice.className = 'notice notice-' + type + ' inline';

                        var text = document.createElement('p');
                        text.textContent = String(message || '');
                        notice.appendChild(text);
                        result.appendChild(notice);
                    }

                    if (!recipient) {
                        renderNotice('warning', 'Please enter a recipient email address.');
                        return;
                    }

                    btn.textContent = 'Sending...';
                    btn.disabled    = true;
                    result.style.display = 'none';

                    var data = new FormData();
                    data.append('action', 'metahotels_brevo_send_test_email');
                    data.append('recipient', recipient);
                    data.append('nonce', '<?php echo esc_js(wp_create_nonce('metahotels_brevo_test_email')); ?>');

                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: data
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        if (resp.success) {
                            renderNotice('success', resp && resp.data ? resp.data.message : 'Success');
                        } else {
                            renderNotice('error', resp && resp.data ? (resp.data.message || resp.data) : 'Unknown error');
                        }
                    })
                    .catch(function(e) {
                        renderNotice('error', 'Request failed: ' + (e && e.message ? e.message : 'Unknown error'));
                    })
                    .finally(function() {
                        btn.textContent = 'Send Test Email';
                        btn.disabled    = false;
                    });
                }
                </script>
                <?php endif; ?>

                <!-- IPAPI Card -->
                <div class="metahotels-card">
                    <div class="metahotels-card-header">
                        <h3 class="metahotels-card-title">ipapi.com Configuration</h3>
                        <p class="metahotels-card-description">Enable automatic country detection for subscription forms.</p>
                    </div>
                    <div class="metahotels-card-content">
                        <div class="metahotels-form-group">
                            <label class="metahotels-label" for="metahotels_ipapi_api_key">ipapi.com API Key</label>
                            <input type="password"
                                   id="metahotels_ipapi_api_key"
                                   name="metahotels_ipapi_api_key"
                                   value=""
                                   class="metahotels-input"
                                   autocomplete="off"
                                   placeholder="<?php echo $ipapi_api_key ? esc_attr__('••••••••  (saved — leave blank to keep)', 'metahotels-core') : esc_attr__('Enter your ipapi.com API key', 'metahotels-core'); ?>" />
                            <p class="metahotels-helper-text">Get your free API key from <a href="https://ipapi.com/signup/" target="_blank" rel="noopener noreferrer">ipapi.com</a> for automatic country detection. For security it is never displayed; leave blank to keep the saved key.</p>
                        </div>

                        <hr style="margin: 1.25rem 0; border: 0; border-top: 1px solid #e2e8f0;" />

                        <?php if (!empty($ipapi_api_key)): ?>
                        <?php
                        /**
                         * On a local or proxied site WordPress only ever sees a loopback or
                         * edge address for the admin request, so the test asks the browser
                         * itself for its public IP. Filter to false to keep the test entirely
                         * server-side (no third-party request from the admin screen).
                         */
                        $ipapi_browser_lookup = (bool) apply_filters('metahotels_ipapi_test_browser_ip_lookup', true);
                        ?>
                        <div class="metahotels-form-group">
                            <label class="metahotels-label" for="metahotels_ipapi_test_ip"><?php esc_html_e('Test IP API Connection', 'metahotels-core'); ?></label>
                            <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
                                <input type="text"
                                       id="metahotels_ipapi_test_ip"
                                       class="metahotels-input"
                                       style="max-width:260px;"
                                       autocomplete="off"
                                       placeholder="<?php esc_attr_e('Optional IP (blank = your own IP)', 'metahotels-core'); ?>" />
                                <button type="button"
                                        id="metahotels_ipapi_test_btn"
                                        class="button button-secondary"
                                        onclick="metahotelsIpapiTestConnection()">
                                    <?php esc_html_e('Test Connection', 'metahotels-core'); ?>
                                </button>
                            </div>
                            <p class="metahotels-helper-text">
                                <?php esc_html_e('Runs a live lookup against api.ipapi.com with the saved key and reports the country and calling code it returns for your own IP — the same field live forms read.', 'metahotels-core'); ?>
                                <?php if ($ipapi_browser_lookup): ?>
                                    <?php esc_html_e('On a local site WordPress only sees 127.0.0.1, so when you click Test your browser asks Cloudflare (keyless, no data sent) for its public IP and that address is tested instead. It is also compared against what WordPress sees, which reveals a CDN or reverse proxy breaking visitor detection.', 'metahotels-core'); ?>
                                <?php endif; ?>
                                <?php esc_html_e('The test never touches the cache or quota counter used by live forms.', 'metahotels-core'); ?>
                            </p>
                        </div>
                        <div id="metahotels_ipapi_test_result" style="margin-top:1rem; display:none;"></div>
                        <script>
                        function metahotelsIpapiTestConnection() {
                            var ipField     = document.getElementById('metahotels_ipapi_test_ip');
                            var btn         = document.getElementById('metahotels_ipapi_test_btn');
                            var result      = document.getElementById('metahotels_ipapi_test_result');
                            var ajaxUrl     = '<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>';
                            var label       = '<?php echo esc_js(__('Test Connection', 'metahotels-core')); ?>';
                            var lookupOwnIp = <?php echo $ipapi_browser_lookup ? 'true' : 'false'; ?>;

                            function renderNotice(type, message, details) {
                                result.style.display = 'block';
                                result.textContent = '';

                                var notice = document.createElement('div');
                                notice.className = 'notice notice-' + type + ' inline';

                                var text = document.createElement('p');
                                text.textContent = String(message || '');
                                notice.appendChild(text);

                                if (details && details.length) {
                                    var list = document.createElement('ul');
                                    list.style.margin = '0 0 0.75rem 1.25rem';
                                    list.style.listStyle = 'disc';
                                    details.forEach(function(row) {
                                        var item = document.createElement('li');
                                        var name = document.createElement('strong');
                                        name.textContent = String(row[0]) + ': ';
                                        item.appendChild(name);
                                        item.appendChild(document.createTextNode(String(row[1])));
                                        list.appendChild(item);
                                    });
                                    notice.appendChild(list);
                                }

                                result.appendChild(notice);
                            }

                            function fetchWithTimeout(url, ms) {
                                if (typeof AbortController === 'undefined') {
                                    return fetch(url, { cache: 'no-store' });
                                }
                                var controller = new AbortController();
                                var timer = setTimeout(function() { controller.abort(); }, ms);
                                return fetch(url, { cache: 'no-store', signal: controller.signal })
                                    .finally(function() { clearTimeout(timer); });
                            }

                            // Ask an outside observer what this browser's public IP is. Nothing
                            // is sent to it beyond the request itself, and a failure here is not
                            // fatal - the server just falls back to the address it can see.
                            function resolveOwnIp() {
                                if (!lookupOwnIp) {
                                    return Promise.resolve({});
                                }
                                return fetchWithTimeout('https://www.cloudflare.com/cdn-cgi/trace', 4000)
                                    .then(function(r) { return r.text(); })
                                    .then(function(body) {
                                        var found = {};
                                        String(body).split('\n').forEach(function(line) {
                                            var split = line.indexOf('=');
                                            if (split < 1) { return; }
                                            var key = line.slice(0, split);
                                            var value = line.slice(split + 1).trim();
                                            if (key === 'ip') { found.ip = value; }
                                            if (key === 'loc') { found.loc = value; }
                                        });
                                        if (!found.ip) { throw new Error('no ip in trace'); }
                                        return found;
                                    })
                                    .catch(function() {
                                        return fetchWithTimeout('https://api.ipify.org?format=json', 4000)
                                            .then(function(r) { return r.json(); })
                                            .then(function(j) { return (j && j.ip) ? { ip: j.ip } : {}; })
                                            .catch(function() { return {}; });
                                    });
                            }

                            var manualIp = ipField.value.trim();

                            btn.textContent = '<?php echo esc_js(__('Testing...', 'metahotels-core')); ?>';
                            btn.disabled    = true;
                            result.style.display = 'none';

                            // An explicitly entered IP needs no self-lookup.
                            (manualIp ? Promise.resolve({}) : resolveOwnIp())
                            .then(function(own) {
                                var data = new FormData();
                                data.append('action', 'metahotels_ipapi_test_connection');
                                data.append('test_ip', manualIp);
                                data.append('browser_ip', own.ip || '');
                                data.append('browser_loc', own.loc || '');
                                data.append('nonce', '<?php echo esc_js(wp_create_nonce('metahotels_ipapi_test_connection')); ?>');

                                return fetch(ajaxUrl, {
                                    method: 'POST',
                                    body: data,
                                    credentials: 'same-origin'
                                });
                            })
                            .then(function(r) { return r.json(); })
                            .then(function(resp) {
                                var payload = resp && resp.data ? resp.data : {};
                                if (resp && resp.success) {
                                    renderNotice(payload.status === 'warning' ? 'warning' : 'success', payload.message, payload.details);
                                } else {
                                    renderNotice('error', payload.message || 'Unknown error', payload.details);
                                }
                            })
                            .catch(function(e) {
                                renderNotice('error', 'Request failed: ' + (e && e.message ? e.message : 'Unknown error'));
                            })
                            .finally(function() {
                                btn.textContent = label;
                                btn.disabled    = false;
                            });
                        }
                        </script>
                        <?php else: ?>
                        <p class="metahotels-helper-text"><em><?php esc_html_e('Save an ipapi.com API key above to enable the connection test.', 'metahotels-core'); ?></em></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Security Settings Card -->
                <div class="metahotels-card">
                    <div class="metahotels-card-header">
                        <h3 class="metahotels-card-title">Security Settings (reCAPTCHA v3)</h3>
                        <p class="metahotels-card-description">Protect your forms from spam and bots.</p>
                    </div>
                    <div class="metahotels-card-content">
                        <div class="metahotels-grid">
                            <div class="metahotels-form-group">
                                <label class="metahotels-label" for="metahotels_brevo_recaptcha_site_key">Site Key</label>
                                <input type="text"
                                       id="metahotels_brevo_recaptcha_site_key"
                                       name="metahotels_brevo_recaptcha_site_key"
                                       value="<?php echo esc_attr($recaptcha_site_key); ?>"
                                       class="metahotels-input" />
                            </div>
                            <div class="metahotels-form-group">
                                <label class="metahotels-label" for="metahotels_brevo_recaptcha_secret_key">Secret Key</label>
                                <input type="password"
                                       id="metahotels_brevo_recaptcha_secret_key"
                                       name="metahotels_brevo_recaptcha_secret_key"
                                       value=""
                                       class="metahotels-input" autocomplete="off"
                                       placeholder="<?php echo $recaptcha_secret_key ? esc_attr__('••••••••  (saved — leave blank to keep)', 'metahotels-core') : esc_attr__('Enter your reCAPTCHA secret key', 'metahotels-core'); ?>" />
                            </div>
                        </div>
                        <div class="metahotels-form-group" style="margin-top: 1rem;">
                            <label class="metahotels-label" for="metahotels_brevo_recaptcha_score_threshold">Score Threshold (0.0 - 1.0)</label>
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <input type="number"
                                       step="0.1"
                                       min="0"
                                       max="1"
                                       id="metahotels_brevo_recaptcha_score_threshold"
                                       name="metahotels_brevo_recaptcha_score_threshold"
                                       value="<?php echo esc_attr($recaptcha_score_threshold); ?>"
                                       class="metahotels-input" 
                                       style="width: 100px;" />
                                <span class="metahotels-helper-text" style="margin: 0;">Higher values are stricter (Default: 0.5)</span>
                            </div>
                        </div>
                        <div class="metahotels-switch-wrapper" style="margin-top: 1.5rem;">
                            <div class="metahotels-switch-label">
                                <span class="metahotels-switch-title">Require reCAPTCHA for form submissions</span>
                                <span class="metahotels-switch-desc">When enabled, subscription submissions are rejected unless a valid reCAPTCHA token verifies. Fails closed if the secret key above is missing. Leave off to allow captcha-free forms (honeypot and rate limiting still apply).</span>
                            </div>
                            <label class="metahotels-switch">
                                <input type="checkbox"
                                       name="metahotels_brevo_recaptcha_required"
                                       value="1"
                                       <?php checked($recaptcha_required, true); ?> />
                                <span class="metahotels-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Form Preferences Card -->
                <div class="metahotels-card">
                    <div class="metahotels-card-header">
                        <h3 class="metahotels-card-title">Form Preferences</h3>
                        <p class="metahotels-card-description">Customize the behavior of your subscription forms.</p>
                    </div>
                    <div class="metahotels-card-content">
                        <div class="metahotels-form-group">
                            <label class="metahotels-label" for="metahotels_brevo_default_country">Default Country Code</label>
                            <input type="text" 
                                   id="metahotels_brevo_default_country" 
                                   name="metahotels_brevo_default_country" 
                                   value="<?php echo esc_attr($default_country); ?>" 
                                   class="metahotels-input"
                                   style="max-width: 200px;"
                                   required />
                            <p class="metahotels-helper-text">E.g., +1 for USA, +91 for India.</p>
                        </div>

                        <div style="margin-top: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">

                            <div class="metahotels-switch-wrapper">
                                <div class="metahotels-switch-label">
                                    <span class="metahotels-switch-title">Debug Mode</span>
                                    <span class="metahotels-switch-desc">Log detailed errors to the browser console for troubleshooting.</span>
                                </div>
                                <label class="metahotels-switch">
                                    <input type="checkbox" 
                                           name="metahotels_brevo_debug_mode" 
                                           value="1" 
                                           <?php checked($debug_mode, true); ?> />
                                    <span class="metahotels-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="metahotels-card">
                 <div class="metahotels-card-header" style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; flex-wrap:wrap;">
                    <div>
                        <h3 class="metahotels-card-title">Available Brevo Lists</h3>
                        <p class="metahotels-card-description">Lists fetched from your Brevo account.</p>
                    </div>
                    <button type="button"
                            id="metahotels_brevo_refetch_btn"
                            class="button button-secondary"
                            onclick="metahotelsBrevoRefetchLists()"
                            style="white-space:nowrap; display:inline-flex; align-items:center; gap:0.35rem;">
                        <span class="dashicons dashicons-update" aria-hidden="true"></span> Refetch Lists
                    </button>
                </div>
                <div id="metahotels_brevo_refetch_notice" style="padding: 0 1rem;"></div>
                <div class="metahotels-card-content" id="metahotels_brevo_lists_content" style="padding: 0;">
                    <?php if (!empty($lists)): ?>
                    <table class="wp-list-table widefat fixed striped" style="border: none; box-shadow: none;">
                        <thead>
                            <tr>
                                <th style="padding: 1rem;">List Name</th>
                                <th style="padding: 1rem;">List ID</th>
                                <th style="padding: 1rem;">Subscribers</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lists as $list): ?>
                            <tr>
                                <td style="padding: 1rem;"><?php echo esc_html($list['name']); ?></td>
                                <td style="padding: 1rem;"><?php echo esc_html($list['id']); ?></td>
                                <td style="padding: 1rem;"><?php echo esc_html($list['subscribers']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="padding: 1rem;">
                        <?php
                        $lists_error_text = '';
                        if (!empty($lists_fetch_error)) {
                            $lists_error_text = ' Last fetch error: ' . wp_html_excerpt($lists_fetch_error, 180, '...');
                        }
                        ?>
                        <p class="metahotels-helper-text" style="margin:0;">No Brevo lists found. Save your Brevo API key above to load lists from your account.<?php echo esc_html($lists_error_text); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <script>
            function metahotelsBrevoRefetchLists() {
                var btn     = document.getElementById('metahotels_brevo_refetch_btn');
                var content = document.getElementById('metahotels_brevo_lists_content');
                var ajaxUrl = '<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>';
                var nonce   = '<?php echo esc_js(wp_create_nonce('metahotels_brevo_refetch_lists')); ?>';
                var originalHtml = btn.innerHTML;

                btn.disabled = true;
                btn.textContent = 'Refetching...';

                function showNotice(type, message) {
                    var holder = document.getElementById('metahotels_brevo_refetch_notice');
                    if (!holder) { return; }
                    holder.textContent = '';
                    var notice = document.createElement('div');
                    notice.className = 'notice notice-' + type + ' inline';
                    notice.style.margin = '0.5rem 0';
                    var p = document.createElement('p');
                    p.textContent = String(message || '');
                    notice.appendChild(p);
                    holder.appendChild(notice);
                }

                function renderLists(lists) {
                    content.textContent = '';
                    if (!lists || !lists.length) {
                        var wrap = document.createElement('div');
                        wrap.style.padding = '1rem';
                        var p = document.createElement('p');
                        p.className = 'metahotels-helper-text';
                        p.style.margin = '0';
                        p.textContent = 'No Brevo lists found.';
                        wrap.appendChild(p);
                        content.appendChild(wrap);
                        return;
                    }
                    var table = document.createElement('table');
                    table.className = 'wp-list-table widefat fixed striped';
                    table.style.border = 'none';
                    table.style.boxShadow = 'none';

                    var thead = document.createElement('thead');
                    var htr = document.createElement('tr');
                    ['List Name', 'List ID', 'Subscribers'].forEach(function(h) {
                        var th = document.createElement('th');
                        th.style.padding = '1rem';
                        th.textContent = h;
                        htr.appendChild(th);
                    });
                    thead.appendChild(htr);
                    table.appendChild(thead);

                    var tbody = document.createElement('tbody');
                    lists.forEach(function(l) {
                        var tr = document.createElement('tr');
                        [l.name, String(l.id), String(l.subscribers)].forEach(function(v) {
                            var td = document.createElement('td');
                            td.style.padding = '1rem';
                            td.textContent = v;
                            tr.appendChild(td);
                        });
                        tbody.appendChild(tr);
                    });
                    table.appendChild(tbody);
                    content.appendChild(table);
                }

                var data = new FormData();
                data.append('action', 'metahotels_brevo_refetch_lists');
                data.append('nonce', nonce);

                fetch(ajaxUrl, { method: 'POST', body: data })
                    .then(function(r) { return r.json(); })
                    .then(function(resp) {
                        if (resp && resp.success && resp.data && Array.isArray(resp.data.lists)) {
                            renderLists(resp.data.lists);
                            showNotice('success', resp.data.message || 'Lists refreshed.');
                        } else {
                            var msg = (resp && resp.data && (resp.data.message || resp.data)) ? (resp.data.message || resp.data) : 'Failed to refetch lists.';
                            if (resp && resp.data && Array.isArray(resp.data.lists)) {
                                renderLists(resp.data.lists);
                            }
                            showNotice('error', msg);
                        }
                    })
                    .catch(function(e) {
                        showNotice('error', 'Request failed: ' + (e && e.message ? e.message : 'Unknown error'));
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.innerHTML = originalHtml;
                    });
            }
            </script>

            <div style="margin-top: 2rem;">
                <?php
                wp_nonce_field('metahotels_brevo_save', 'metahotels_brevo_nonce_field');
                submit_button('Save Settings');
                ?>
            </div>
        </form>
        
        <h2>Shortcode Usage</h2>
        <p>Use the following shortcode to display a Brevo subscription form:</p>
        <code>[brevo_form list_id="YOUR_LIST_ID" redirect_url="/thank-you/" button_text="Book Now"]</code>
        <p><strong>Parameters:</strong></p>
        <ul>
            <li><code>list_id</code> - The Brevo list ID (required)</li>
            <li><code>redirect_url</code> - Where to send the visitor after a successful subscription (optional). Use a path on this site such as <code>/thank-you/</code>, or a full URL on another domain if you redirect to an external booking engine. Leave it out to show a thank-you message in place of the form instead.</li>
            <li><code>button_text</code> - Submit button text (optional, default: "Book Now")</li>
        </ul>
        
        <?php if (!empty($api_key)): ?>
        <h2>Debug Information</h2>
        <p><strong>API Key Status:</strong> <?php echo !empty($api_key) ? 'Configured' : 'Not configured'; ?></p>
        <p><strong>Lists Found:</strong> <?php echo count($lists); ?></p>
        
        <h3>AJAX Test</h3>
        <p>Click the button below to test if AJAX is working:</p>
        <button type="button" id="test-ajax-btn" class="button button-secondary">Test AJAX</button>
        <div id="ajax-test-result" style="margin-top: 10px;"></div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-ajax-btn').on('click', function() {
                var btn = $(this);
                var result = $('#ajax-test-result');
                
                btn.text('Testing...').prop('disabled', true);
                result.html('');
                
                $.ajax({
                    url: '<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>',
                    type: 'POST',
                    data: {
                        action: 'metahotels_brevo_test_ajax',
                        test_ajax: 'test',
                        nonce: '<?php echo esc_js(wp_create_nonce('metahotels_brevo_test_ajax')); ?>'
                    },
                    success: function(response) {
                        var msg = response && response.data && response.data.message ? response.data.message : 'AJAX call succeeded.';
                        result.text('Success: ' + msg).css('color', 'green');
                        btn.text('Test AJAX').prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        result.text('Error: ' + status + ' - ' + error).css('color', 'red');
                        btn.text('Test AJAX').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        
        <?php if (!empty($lists)): ?>
        <p><strong>Test Contact Creation:</strong></p>
        <form method="post" action="">
            <?php wp_nonce_field('metahotels_brevo_test_contact', 'metahotels_brevo_test_nonce_field'); ?>
            <input type="hidden" name="test_contact" value="1">
            <p>This will create a test contact in the first available list:</p>
            <button type="submit" class="button button-secondary">Test Contact Creation</button>
        </form>
        <?php endif; ?>
        
        <?php if ($debug_mode): ?>
        <h2>Debug Information</h2>
        <p><strong>Debug mode is enabled.</strong> All form submissions and API calls will be logged to your browser console.</p>
        <p><strong>To view debug logs:</strong></p>
        <ol>
            <li>Open your browser's Developer Tools (press <strong>F12</strong> or right-click and select "Inspect")</li>
            <li>Go to the <strong>Console</strong> tab</li>
            <li>Submit the Brevo form on your website</li>
            <li>Look for log entries prefixed with "=== Brevo Form Submission Debug ===" or "Brevo"</li>
        </ol>
        <p><strong>What you'll see in the console:</strong></p>
        <ul>
            <li>Form data being submitted</li>
            <li>AJAX request details</li>
            <li>API responses from Brevo</li>
            <li>Any errors with full details</li>
        </ul>
        <p><em>Note: Make sure to disable debug mode on production sites for security.</em></p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// Fetch lists from Brevo API.
//
// Brevo's GET /v3/contacts/lists endpoint returns a maximum of 10 lists per
// request by default (page size, newest-first). Fetching a single page silently
// dropped every list beyond the 10 newest, so older but still-active lists were
// missing from the whitelist and their forms failed with "Invalid list ID".
// We now page through the full account (limit=50 per request) until every list
// reported by the API's `count` field has been collected.
function metahotels_brevo_fetch_lists($api_key, &$error_message = '') {
    $error_message = '';
    if (empty($api_key)) {
        return array();
    }

    $base_url     = 'https://api.brevo.com/v3/contacts/lists';
    $limit        = 50;   // Maximum page size Brevo allows for this endpoint.
    $offset       = 0;
    $total        = null; // Discovered from the first response's `count`.
    $max_pages    = 200;  // Safety cap (200 * 50 = 10,000 lists).
    $lists        = array();

    for ($page = 0; $page < $max_pages; $page++) {
        $url = add_query_arg(
            array(
                'limit'  => $limit,
                'offset' => $offset,
                'sort'   => 'desc',
            ),
            $base_url
        );

        $response = wp_remote_get($url, array(
            'headers' => array(
                'api-key'      => $api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'timeout' => 20,
            'redirection' => 3,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            $error_message = sanitize_text_field($response->get_error_message());
            metahotels_brevo_log('Lists fetch WP_Error', array('error' => $error_message, 'offset' => $offset));
            // Return whatever was gathered on earlier pages rather than dropping it.
            return $lists;
        }

        $status = intval(wp_remote_retrieve_response_code($response));
        $body_raw = (string) wp_remote_retrieve_body($response);

        if ($status !== 200) {
            $detail = metahotels_brevo_extract_api_error($body_raw);
            $error_message = 'HTTP ' . $status . ($detail !== '' ? (' - ' . $detail) : '');
            metahotels_brevo_log('Lists fetch non-200 response', array('status' => $status, 'detail' => $detail, 'offset' => $offset));
            return $lists;
        }

        $data = json_decode($body_raw, true);
        if (!is_array($data)) {
            $error_message = 'Invalid JSON response';
            metahotels_brevo_log('Lists fetch invalid JSON');
            return $lists;
        }

        // Total list count is reported on every page; capture it once.
        if ($total === null && isset($data['count'])) {
            $total = intval($data['count']);
        }

        $raw_lists = array();
        if (isset($data['lists']) && is_array($data['lists'])) {
            $raw_lists = $data['lists'];
        } elseif (isset($data['data']['lists']) && is_array($data['data']['lists'])) {
            $raw_lists = $data['data']['lists'];
        } elseif (array_keys($data) === range(0, count($data) - 1)) {
            $raw_lists = $data;
        }

        if (empty($raw_lists)) {
            break; // No more lists to page through.
        }

        foreach ($raw_lists as $list) {
            if (!is_array($list)) {
                continue;
            }
            $lists[] = array(
                'id' => isset($list['id']) ? intval($list['id']) : 0,
                'name' => isset($list['name']) ? sanitize_text_field($list['name']) : '',
                'subscribers' => isset($list['uniqueSubscribers']) ? intval($list['uniqueSubscribers']) : 0
            );
        }

        $fetched = count($raw_lists);
        $offset += $fetched;

        // Stop when the API returned a short page (last page) or we have them all.
        if ($fetched < $limit) {
            break;
        }
        if ($total !== null && $offset >= $total) {
            break;
        }
    }

    if (empty($lists)) {
        $error_message = 'No lists returned by API';
    }

    return $lists;
}

// AJAX handler: manually refetch the Brevo lists from the "Available Brevo Lists"
// card without re-saving the whole settings form.
add_action('wp_ajax_metahotels_brevo_refetch_lists', 'metahotels_brevo_refetch_lists_handler');
function metahotels_brevo_refetch_lists_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to do this.'));
    }

    if (!check_ajax_referer('metahotels_brevo_refetch_lists', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed. Please refresh the page and try again.'));
    }

    $api_key = get_option('metahotels_brevo_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'Add and save your Brevo API key before refetching lists.'));
    }

    $error   = '';
    $fetched = metahotels_brevo_fetch_lists($api_key, $error);

    // Only overwrite the cached whitelist when the fetch actually returned lists,
    // so a transient failure never wipes the list that backs form validation.
    if (!empty($fetched)) {
        update_option('metahotels_brevo_lists', $fetched, false);
        update_option('metahotels_brevo_lists_last_error', $error, false);
        wp_send_json_success(array(
            'lists'   => $fetched,
            'message' => sprintf(
                /* translators: %d: number of Brevo lists fetched. */
                _n('%d list loaded from Brevo.', '%d lists loaded from Brevo.', count($fetched), 'metahotels-core'),
                count($fetched)
            ),
        ));
    }

    update_option('metahotels_brevo_lists_last_error', $error, false);
    $existing = get_option('metahotels_brevo_lists', array());
    wp_send_json_error(array(
        'message' => $error !== ''
            ? ('Brevo lists fetch failed: ' . $error . ' — previously fetched lists were kept.')
            : 'No lists returned by Brevo.',
        'lists'   => is_array($existing) ? $existing : array(),
    ));
}

/**
 * Force Brevo API calls over IPv4 when cURL transport is used.
 *
 * Local/dev environments often egress via temporary IPv6 addresses that fail
 * Brevo's IP allowlist checks even after manual authorization.
 */
function metahotels_brevo_force_ipv4_curl($handle, $request_args, $url) {
    if (!is_string($url) || strpos($url, 'api.brevo.com') === false) {
        return;
    }

    if (!defined('CURLOPT_IPRESOLVE') || !defined('CURL_IPRESOLVE_V4')) {
        return;
    }

    /**
     * Filter: allow disabling forced IPv4 for Brevo requests.
     *
     * Example usage:
     * add_filter('metahotels_brevo_force_ipv4_requests', '__return_false');
     */
    $force_ipv4 = (bool) apply_filters('metahotels_brevo_force_ipv4_requests', true);
    if (!$force_ipv4) {
        return;
    }

    curl_setopt($handle, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
}
add_action('http_api_curl', 'metahotels_brevo_force_ipv4_curl', 10, 3);

/**
 * Extract a readable error from a Brevo API response payload.
 */
function metahotels_brevo_extract_api_error($body_raw) {
    $decoded = json_decode((string) $body_raw, true);
    if (!is_array($decoded)) {
        return '';
    }

    foreach (array('message', 'error', 'detail', 'code') as $key) {
        if (isset($decoded[$key]) && is_scalar($decoded[$key]) && (string) $decoded[$key] !== '') {
            return sanitize_text_field((string) $decoded[$key]);
        }
    }

    if (isset($decoded['errors']) && is_array($decoded['errors'])) {
        $first = reset($decoded['errors']);
        if (is_scalar($first) && (string) $first !== '') {
            return sanitize_text_field((string) $first);
        }
        if (is_array($first)) {
            foreach (array('message', 'error', 'detail') as $key) {
                if (isset($first[$key]) && is_scalar($first[$key]) && (string) $first[$key] !== '') {
                    return sanitize_text_field((string) $first[$key]);
                }
            }
        }
    }

    return '';
}

/**
 * Normalize sender payloads returned by different Brevo sender endpoints.
 */
function metahotels_brevo_normalize_senders_payload($payload) {
    if (!is_array($payload)) {
        return array();
    }

    $collections = array();
    if (isset($payload['senders']) && is_array($payload['senders'])) {
        $collections[] = $payload['senders'];
    }
    if (isset($payload['smtp_senders']) && is_array($payload['smtp_senders'])) {
        $collections[] = $payload['smtp_senders'];
    }
    if (isset($payload['data']['senders']) && is_array($payload['data']['senders'])) {
        $collections[] = $payload['data']['senders'];
    }

    // Some endpoints may return an array of sender objects as the root payload.
    $is_root_list = array_keys($payload) === range(0, count($payload) - 1);
    if ($is_root_list) {
        $collections[] = $payload;
    }

    $senders = array();
    $seen = array();

    foreach ($collections as $items) {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $raw_email = '';
            if (!empty($item['email'])) {
                $raw_email = $item['email'];
            } elseif (!empty($item['fromEmail'])) {
                $raw_email = $item['fromEmail'];
            } elseif (isset($item['sender']) && is_array($item['sender']) && !empty($item['sender']['email'])) {
                $raw_email = $item['sender']['email'];
            }

            $email = sanitize_email((string) $raw_email);
            if (empty($email)) {
                continue;
            }

            $email_key = strtolower($email);
            if (isset($seen[$email_key])) {
                continue;
            }

            $raw_name = '';
            if (!empty($item['name'])) {
                $raw_name = $item['name'];
            } elseif (!empty($item['fromName'])) {
                $raw_name = $item['fromName'];
            } elseif (isset($item['sender']) && is_array($item['sender']) && !empty($item['sender']['name'])) {
                $raw_name = $item['sender']['name'];
            }

            $name = sanitize_text_field((string) $raw_name);
            if ($name === '') {
                $name = $email;
            }

            $active = true;
            if (isset($item['active'])) {
                $active = (bool) $item['active'];
            } elseif (isset($item['isActive'])) {
                $active = (bool) $item['isActive'];
            } elseif (isset($item['status'])) {
                $active = strtolower((string) $item['status']) !== 'inactive';
            }

            $id = 0;
            if (isset($item['id'])) {
                $id = intval($item['id']);
            } elseif (isset($item['senderId'])) {
                $id = intval($item['senderId']);
            }

            $senders[] = array(
                'id'     => $id,
                'name'   => $name,
                'email'  => $email,
                'active' => $active,
            );
            $seen[$email_key] = true;
        }
    }

    return $senders;
}

// Fetch verified senders from Brevo API.
function metahotels_brevo_fetch_senders($api_key, &$error_message = '') {
    $error_message = '';

    if (empty($api_key)) {
        return array();
    }

    $endpoints = array(
        'https://api.brevo.com/v3/senders',
        'https://api.brevo.com/v3/smtp/senders',
    );
    $errors = array();

    foreach ($endpoints as $url) {
        $response = wp_remote_get($url, array(
            'headers' => array(
                'api-key'      => $api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
            'timeout' => 20,
            'redirection' => 3,
            'sslverify' => true,
        ));

        $path = wp_parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : $url;

        if (is_wp_error($response)) {
            $errors[] = $path . ': ' . sanitize_text_field($response->get_error_message());
            continue;
        }

        $status = intval(wp_remote_retrieve_response_code($response));
        $raw_body = (string) wp_remote_retrieve_body($response);

        if ($status !== 200) {
            $detail = metahotels_brevo_extract_api_error($raw_body);
            $errors[] = $path . ': HTTP ' . $status . ($detail !== '' ? (' - ' . $detail) : '');
            continue;
        }

        $decoded = json_decode($raw_body, true);
        if (!is_array($decoded)) {
            $errors[] = $path . ': invalid JSON response';
            continue;
        }

        $senders = metahotels_brevo_normalize_senders_payload($decoded);
        if (!empty($senders)) {
            return $senders;
        }

        $errors[] = $path . ': no senders returned';
    }

    $error_message = implode(' | ', $errors);
    if ($error_message !== '') {
        metahotels_brevo_log('Sender fetch failed', array('error' => $error_message));
    }

    return array();
}

// Test contact creation
function metahotels_brevo_test_contact_creation($api_key, $list_id) {
    $url = 'https://api.brevo.com/v3/contacts';
    
    $contact_data = array(
        'email' => 'test@example.com',
        'attributes' => array(
            'FNAME' => 'Test',
            'LNAME' => 'User'
        ),
        'listIds' => array($list_id)
    );
    
    $body = wp_json_encode($contact_data);

    metahotels_brevo_log('Test contact request queued', array('list_id' => (int) $list_id));
    
    $response = wp_remote_post($url, array(
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => $body,
        'timeout' => 20,
        'redirection' => 3,
        'sslverify' => true,
    ));
    
    if (is_wp_error($response)) {
        metahotels_brevo_log('Test contact request failed', array('error' => $response->get_error_message()));
        return array('success' => false, 'message' => $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    metahotels_brevo_log('Test contact response received', array('status' => $response_code));
    
    $data = json_decode($response_body, true);
    
    if ($response_code === 201 || $response_code === 200 || $response_code === 204) {
        return array('success' => true, 'message' => 'Contact created successfully with ID: ' . (isset($data['id']) ? $data['id'] : 'Unknown'));
    }
    
    $error_message = 'HTTP ' . $response_code;
    if (isset($data['message'])) {
        $error_message .= ': ' . $data['message'];
    } elseif (isset($data['error'])) {
        $error_message .= ': ' . $data['error'];
    } else {
        $error_message .= ': ' . $response_body;
    }
    
    return array('success' => false, 'message' => $error_message);
}

// Public integration helper: fetch a Brevo contact by email.
function metahotels_brevo_get_contact($api_key, $email) {
    $email = sanitize_email($email);
    if (empty($api_key) || !is_email($email)) {
        return false;
    }
    
    $url = 'https://api.brevo.com/v3/contacts/' . rawurlencode($email);
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 15,
        'redirection' => 3,
        'sslverify' => true,
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    
    if ($response_code === 200) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        return is_array($data) ? $data : false;
    }
    
    return false;
}

// Public integration helper: update an existing Brevo contact.
function metahotels_brevo_update_contact($api_key, $email, $contact_data) {
    $email = sanitize_email($email);
    if (empty($api_key) || !is_email($email) || !is_array($contact_data)) {
        return array('success' => false, 'message' => 'Invalid parameters');
    }
    
    $url = 'https://api.brevo.com/v3/contacts/' . rawurlencode($email);
    
    // Remove email from contact data for update
    unset($contact_data['email']);
    
    $response = wp_remote_put($url, array(
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => wp_json_encode($contact_data),
        'timeout' => 20,
        'redirection' => 3,
        'sslverify' => true,
    ));
    
    if (is_wp_error($response)) {
        return array('success' => false, 'message' => $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($response_code === 200 || $response_code === 204) {
        return array('success' => true, 'message' => 'Contact updated successfully');
    } else {
        $error_data = json_decode($response_body, true);
        $error_message = 'Unknown error';
        if ($error_data && is_array($error_data) && isset($error_data['message'])) {
            $error_message = $error_data['message'];
        }
        return array('success' => false, 'message' => $error_message);
    }
}


/**
 * Drop a national trunk prefix (a single leading 0) so the E.164 number built
 * from calling code + national number is actually dialable.
 *
 * Guests write their number the way they dial it at home - 050 123 4567 - and
 * prefixing a calling code to that yields an unreachable number. WhatsApp
 * numbers are mobile, and no country's mobile number keeps a trunk 0 once an
 * international prefix is present, so this needs no country knowledge.
 */
function metahotels_brevo_normalize_national_number($phone_number) {
    $digits = preg_replace('/[^0-9]/', '', (string) $phone_number);

    if ('' === $digits || '0' !== $digits[0]) {
        return $digits;
    }

    $trimmed = substr($digits, 1);

    // Just "0" - leave it for validation to reject.
    return ('' === $trimmed) ? $digits : $trimmed;
}

/**
 * Validate a WhatsApp number: sanity checks only, deliberately.
 *
 * Per-country length and pattern rules used to live here and were removed. Such
 * a table is never complete, and an unlisted calling code fell back to Indian
 * rules - which silently rejected every valid UAE, Saudi, Qatar and Singapore
 * number. Brevo and WhatsApp both validate downstream, so accepting an unusual
 * number costs far less than turning away a real guest.
 *
 * 7-15 digits is the E.164 range. $country_code is unused but retained so
 * existing two-argument callers keep working.
 */
function metahotels_validate_whatsapp_number($phone_number, $country_code = '') {
    $errors = array();

    $clean_number = preg_replace('/[^0-9]/', '', (string) $phone_number);

    // Note: not empty(), which would treat the string "0" as absent.
    if ('' === $clean_number) {
        $errors[] = 'Phone number is required';
        return $errors;
    }

    if (strlen($clean_number) < 7) {
        $errors[] = 'Phone number is too short';
    } elseif (strlen($clean_number) > 15) {
        $errors[] = 'Phone number is too long';
    }

    // Obvious junk. Reported one at a time, since only the first error is shown.
    if (preg_match('/^0+$/', $clean_number)) {
        $errors[] = 'Phone number cannot be all zeros';
    } elseif (preg_match('/^1+$/', $clean_number)) {
        $errors[] = 'Phone number cannot be all ones';
    } elseif (preg_match('/^(.)\1{5,}$/', $clean_number)) {
        $errors[] = 'Phone number appears to be invalid (repeated digits)';
    }

    return $errors;
}
// Cryptographically sign cookie value to prevent tampering
function metahotels_sign_brevo_cookie($email) {
    if (empty($email) || !is_email($email)) {
        return false;
    }
    
    // Create a signed token with email and timestamp
    $timestamp = time();
    $data = $email . '|' . $timestamp;
    
    // Use WordPress's authentication key for signing
    $secret_key = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    $signature = hash_hmac('sha256', $data, $secret_key);
    
    // Return signed cookie value: email|timestamp|signature
    return base64_encode($data . '|' . $signature);
}

// Verify and extract email from signed cookie
function metahotels_verify_brevo_cookie($signed_value) {
    if (empty($signed_value)) {
        return false;
    }
    
    // Decode the base64 value
    $decoded = base64_decode($signed_value, true);
    if ($decoded === false) {
        return false;
    }
    
    // Split into parts: email|timestamp|signature
    $parts = explode('|', $decoded);
    if (count($parts) !== 3) {
        return false;
    }
    
    list($email, $timestamp, $signature) = $parts;
    
    // Validate email format
    if (!is_email($email)) {
        return false;
    }
    
    // Validate timestamp (cookie should not be older than 30 days)
    $timestamp = intval($timestamp);
    $max_age = 30 * 24 * 60 * 60; // 30 days in seconds
    if ($timestamp < (time() - $max_age) || $timestamp > time()) {
        return false; // Cookie expired or invalid timestamp
    }
    
    // Recreate the data and verify signature
    $data = $email . '|' . $timestamp;
    $secret_key = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    $expected_signature = hash_hmac('sha256', $data, $secret_key);
    
    // Use hash_equals for timing-safe comparison
    if (!hash_equals($expected_signature, $signature)) {
        return false; // Signature verification failed
    }
    
    return sanitize_email($email);
}

// Delete user from Brevo by email
function metahotels_delete_brevo_user($email) {
    $email = sanitize_email($email);
    if (!is_email($email)) {
        return false;
    }

    $api_key = get_option('metahotels_brevo_api_key');
    if (empty($api_key)) {
        metahotels_brevo_log('Delete contact skipped: API key missing');
        return false;
    }

    $delete_url = 'https://api.brevo.com/v3/contacts/' . rawurlencode($email);
    $delete_response = wp_remote_request($delete_url, array(
        'method' => 'DELETE',
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 15,
        'redirection' => 3,
        'sslverify' => true,
    ));

    if (is_wp_error($delete_response)) {
        metahotels_brevo_log('Delete contact request failed', array('error' => $delete_response->get_error_message()));
        return false;
    }

    $delete_code = wp_remote_retrieve_response_code($delete_response);
    if ($delete_code === 204 || $delete_code === 200 || $delete_code === 404) {
        metahotels_brevo_log('Delete contact completed', array('status' => $delete_code));
        return true;
    }

    metahotels_brevo_log('Delete contact failed', array('status' => $delete_code));
    return false;
}

// ============================================
// Brevo Transactional Email (wp_mail intercept)
// ============================================

/**
 * Intercept wp_mail() and send via Brevo transactional email API.
 * Requires WordPress 5.7+ (pre_wp_mail filter).
 *
 * Enable "Debug Mode" in Marketing Settings to trace every call in the PHP error log.
 */
function metahotels_brevo_intercept_wp_mail($return, $atts) {
    $debug = metahotels_brevo_debug_enabled();

    if (!get_option('metahotels_brevo_transactional_enabled', false)) {
        if ($debug) {
            metahotels_brevo_log('Mail passthrough: transactional email disabled');
        }
        return null;
    }

    $api_key = get_option('metahotels_brevo_api_key', '');
    if (empty($api_key)) {
        if ($debug) {
            metahotels_brevo_log('Mail passthrough: API key missing');
        }
        return null;
    }

    $sender_name  = sanitize_text_field(get_option('metahotels_brevo_sender_name', get_bloginfo('name')));
    $sender_email = get_option('metahotels_brevo_sender_email', '');

    // Fall back to admin email only if option was never explicitly saved
    if (empty($sender_email)) {
        $sender_email = get_option('admin_email');
    }

    if (!is_email($sender_email)) {
        if ($debug) {
            metahotels_brevo_log('Mail passthrough: sender email invalid');
        }
        return null;
    }

    $to          = isset($atts['to']) ? $atts['to'] : array();
    $subject     = isset($atts['subject']) ? $atts['subject'] : '(no subject)';
    $message     = isset($atts['message']) ? $atts['message'] : '';
    $headers     = isset($atts['headers']) ? $atts['headers'] : array();
    $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();

    // Normalise recipients - handle both string and array
    if (!is_array($to)) {
        $to = array_map('trim', explode(',', $to));
    }

    $recipients = array();
    foreach ($to as $recipient) {
        $recipient = trim($recipient);
        if (empty($recipient)) {
            continue;
        }
        if (preg_match('/^(.*?)\s*<(.+?)>$/', $recipient, $m)) {
            $recipient_email = sanitize_email(trim($m[2]));
            if (!is_email($recipient_email)) {
                continue;
            }

            $recipient_data = array('email' => $recipient_email);
            $recipient_name = sanitize_text_field(trim($m[1]));
            if ($recipient_name !== '') {
                $recipient_data['name'] = $recipient_name;
            }
            $recipients[] = $recipient_data;
        } else {
            $recipient_email = sanitize_email($recipient);
            if (!is_email($recipient_email)) {
                continue;
            }
            $recipients[] = array('email' => $recipient_email);
        }
    }

    if (empty($recipients)) {
        if ($debug) {
            metahotels_brevo_log('Mail passthrough: no valid recipients');
        }
        return null;
    }

    if ($debug) {
        metahotels_brevo_log('Mail intercepted', array(
            'recipients' => count($recipients),
            'subject_length' => strlen((string) $subject),
        ));
    }

    // Parse headers
    if (!is_array($headers)) {
        $headers = array_filter(array_map('trim', explode("\n", str_replace("\r\n", "\n", $headers))));
    }

    $is_html  = false;
    $cc       = array();
    $bcc      = array();
    $reply_to = null;

    foreach ($headers as $header) {
        if (strpos($header, ':') === false) {
            continue;
        }
        list($hname, $hvalue) = explode(':', $header, 2);
        $hname  = strtolower(trim($hname));
        $hvalue = trim($hvalue);

        switch ($hname) {
            case 'content-type':
                if (stripos($hvalue, 'text/html') !== false) {
                    $is_html = true;
                }
                break;
            case 'cc':
                foreach (array_map('trim', explode(',', $hvalue)) as $addr) {
                    if ($addr === '') {
                        continue;
                    }

                    $email = sanitize_email($addr);
                    if (is_email($email)) {
                        $cc[] = array('email' => $email);
                    }
                }
                break;
            case 'bcc':
                foreach (array_map('trim', explode(',', $hvalue)) as $addr) {
                    if ($addr === '') {
                        continue;
                    }

                    $email = sanitize_email($addr);
                    if (is_email($email)) {
                        $bcc[] = array('email' => $email);
                    }
                }
                break;
            case 'reply-to':
                if (preg_match('/^(.*?)\s*<(.+?)>\s*$/', $hvalue, $rtm)) {
                    $rt_email = sanitize_email(trim($rtm[2]));
                    $rt_name  = sanitize_text_field(trim($rtm[1]));
                } else {
                    $rt_email = sanitize_email(trim($hvalue));
                    $rt_name  = '';
                }
                if (is_email($rt_email)) {
                    $reply_to = array('email' => $rt_email);
                    if (!empty($rt_name)) {
                        $reply_to['name'] = $rt_name;
                    }
                }
                break;
        }
    }

    // Auto-detect HTML content if no explicit content-type header was set
    if (!$is_html && preg_match('/<[a-z][\s\S]*>/i', $message)) {
        $is_html = true;
        if ($debug) {
            metahotels_brevo_log('Mail content auto-detected as HTML');
        }
    }

    $payload = array(
        'sender'  => array('name' => $sender_name, 'email' => $sender_email),
        'to'      => $recipients,
        'subject' => $subject,
    );

    if ($is_html) {
        $payload['htmlContent'] = $message;
    } else {
        $payload['textContent'] = $message;
    }

    if (!empty($cc)) {
        $payload['cc'] = $cc;
    }
    if (!empty($bcc)) {
        $payload['bcc'] = $bcc;
    }
    if ($reply_to) {
        $payload['replyTo'] = $reply_to;
    }

    // Attachments: encode as base64 for Brevo API
    if (!empty($attachments)) {
        $max_attachment_bytes = (int) apply_filters('metahotels_brevo_max_attachment_size', 5 * MB_IN_BYTES);
        $max_attachment_count = (int) apply_filters('metahotels_brevo_max_attachment_count', 10);
        $brevo_attachments = array();
        foreach ((array) $attachments as $attachment) {
            if (!is_string($attachment) || !is_readable($attachment)) {
                continue;
            }

            if ($max_attachment_count > 0 && count($brevo_attachments) >= $max_attachment_count) {
                break;
            }

            $size = @filesize($attachment);
            if (is_int($size) && $size > 0 && $size > $max_attachment_bytes) {
                if ($debug) {
                    metahotels_brevo_log('Skipping oversized attachment', array(
                        'name' => basename($attachment),
                        'size' => $size,
                        'max' => $max_attachment_bytes,
                    ));
                }
                continue;
            }

            $content = file_get_contents($attachment);
            if (!is_string($content) || $content === '') {
                continue;
            }

            if (strlen($content) > $max_attachment_bytes) {
                if ($debug) {
                    metahotels_brevo_log('Skipping oversized attachment content', array(
                        'name' => basename($attachment),
                        'size' => strlen($content),
                        'max' => $max_attachment_bytes,
                    ));
                }
                continue;
            }

            $brevo_attachments[] = array(
                'name'    => sanitize_file_name(basename($attachment)),
                'content' => base64_encode($content),
            );
        }
        if (!empty($brevo_attachments)) {
            $payload['attachment'] = $brevo_attachments;
        }
    }

    if ($debug) {
        metahotels_brevo_log('Sending mail via Brevo API', array(
            'is_html' => $is_html,
            'payload_keys' => array_keys($payload),
        ));
    }

    $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', array(
        'headers' => array(
            'api-key'      => $api_key,
            'Content-Type' => 'application/json',
        ),
        'body'    => wp_json_encode($payload),
        'timeout' => 20,
        'redirection' => 3,
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        if ($debug) {
            metahotels_brevo_log('Mail send failed with WP_Error', array('error' => $response->get_error_message()));
        }
        return null;
    }

    $code = wp_remote_retrieve_response_code($response);

    if ($code === 201) {
        if ($debug) {
            metahotels_brevo_log('Mail queued successfully', array('status' => $code));
        }
        return true;
    }

    if ($debug) {
        metahotels_brevo_log('Mail send failed', array('status' => $code));
    }
    return null;
}
add_filter('pre_wp_mail', 'metahotels_brevo_intercept_wp_mail', 10, 2);

function metahotels_clear_brevo_cookie() {
    setcookie('brevo_registered_email', '', array(
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => true,
        'samesite' => 'Lax',
    ));
}

function metahotels_queue_brevo_deletion($email) {
    $email = sanitize_email($email);
    if (!is_email($email)) {
        return false;
    }

    if (wp_next_scheduled('metahotels_brevo_delete_contact_async', array($email))) {
        return true;
    }

    return (bool) wp_schedule_single_event(time() + 5, 'metahotels_brevo_delete_contact_async', array($email));
}

function metahotels_brevo_delete_contact_async_handler($email) {
    metahotels_delete_brevo_user($email);
}
add_action('metahotels_brevo_delete_contact_async', 'metahotels_brevo_delete_contact_async_handler');

// Handle booking page visits - queue asynchronous Brevo deletion.
function metahotels_handle_booking_page_visit() {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    if (!is_page('my-booking') && strpos($request_uri, '/my-booking/') === false) {
        return;
    }

    if (!isset($_COOKIE['brevo_registered_email'])) {
        return;
    }

    $signed_cookie = sanitize_text_field(wp_unslash($_COOKIE['brevo_registered_email']));
    $email = metahotels_verify_brevo_cookie($signed_cookie);

    if ($email === false) {
        metahotels_clear_brevo_cookie();
        metahotels_brevo_log('Deletion cookie rejected as invalid');
        return;
    }

    if (metahotels_queue_brevo_deletion($email)) {
        metahotels_brevo_log('Deletion queued', array('email_hash' => md5($email)));
    } else {
        metahotels_brevo_log('Failed to queue deletion');
    }

    // Always clear cookie once consumed.
    metahotels_clear_brevo_cookie();
}
add_action('wp', 'metahotels_handle_booking_page_visit');

// AJAX: send a test transactional email via Brevo
function metahotels_brevo_send_test_email_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    if (!check_ajax_referer('metahotels_brevo_test_email', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $recipient = isset($_POST['recipient']) ? sanitize_email(wp_unslash($_POST['recipient'])) : '';
    if (!is_email($recipient)) {
        wp_send_json_error(array('message' => 'Please enter a valid recipient email address.'));
        return;
    }

    $api_key      = get_option('metahotels_brevo_api_key', '');
    $sender_name  = sanitize_text_field(get_option('metahotels_brevo_sender_name', get_bloginfo('name')));
    $sender_email = get_option('metahotels_brevo_sender_email', '');

    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'Brevo API key is not configured.'));
        return;
    }

    if (empty($sender_email) || !is_email($sender_email)) {
        wp_send_json_error(array('message' => 'Sender email is not configured or invalid.'));
        return;
    }

    $payload = array(
        'sender'      => array('name' => $sender_name, 'email' => $sender_email),
        'to'          => array(array('email' => $recipient)),
        'subject'     => '[Test] Brevo Email Connection - ' . get_bloginfo('name'),
        'htmlContent' => '<p>This is a test email sent from <strong>' . esc_html(get_bloginfo('name')) . '</strong> to confirm your Brevo transactional email integration is working correctly.</p><p>Sent via the MetaHotels Core plugin.</p>',
    );

    $response = wp_remote_post('https://api.brevo.com/v3/smtp/email', array(
        'headers' => array(
            'api-key'      => $api_key,
            'Content-Type' => 'application/json',
        ),
        'body'    => wp_json_encode($payload),
        'timeout' => 20,
        'redirection' => 3,
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection error: ' . sanitize_text_field($response->get_error_message())));
        return;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code === 201) {
        wp_send_json_success(array('message' => 'Test email sent successfully to ' . esc_html($recipient) . '. Check your inbox!'));
        return;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $detail_raw = isset($body['message']) ? $body['message'] : ('HTTP ' . $code);
    $detail = sanitize_text_field(wp_strip_all_tags((string) $detail_raw));
    wp_send_json_error(array('message' => 'Brevo API error: ' . $detail));
}
add_action('wp_ajax_metahotels_brevo_send_test_email', 'metahotels_brevo_send_test_email_handler');

/**
 * AJAX: live connectivity test for the ipapi.com (IP API) integration.
 *
 * Deliberately side-effect free with respect to the front end: it neither reads
 * nor writes the per-IP country-code transient and does not touch the daily
 * budget counter, so testing can never poison the cache live forms read from,
 * nor exhaust their quota allowance. A per-admin rate limit keeps repeated
 * clicks from burning the paid ipapi.com quota.
 */
function metahotels_ipapi_test_connection_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    if (!check_ajax_referer('metahotels_ipapi_test_connection', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    $api_key = get_option('metahotels_ipapi_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error(array('message' => 'No ipapi.com API key is saved. Enter the key, save the settings, then run the test.'));
        return;
    }

    $rate_key = 'metahotels_ipapi_test_' . get_current_user_id();
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 10) {
        wp_send_json_error(array('message' => 'Too many tests in a row. Please wait a few minutes before testing again.'));
        return;
    }
    set_transient($rate_key, $attempts + 1, 5 * MINUTE_IN_SECONDS);

    $manual_ip   = isset($_POST['test_ip']) ? sanitize_text_field(wp_unslash($_POST['test_ip'])) : '';
    $browser_ip  = isset($_POST['browser_ip']) ? sanitize_text_field(wp_unslash($_POST['browser_ip'])) : '';
    $browser_loc = isset($_POST['browser_loc']) ? sanitize_text_field(wp_unslash($_POST['browser_loc'])) : '';
    $browser_loc = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $browser_loc), 0, 2));
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    $browser_ip = metahotels_ipapi_is_public_ip($browser_ip) ? $browser_ip : '';

    if ('' !== $manual_ip) {
        if (!filter_var($manual_ip, FILTER_VALIDATE_IP)) {
            wp_send_json_error(array('message' => 'That is not a valid IP address. Leave the field blank to test with your own IP.'));
            return;
        }
        if (!metahotels_ipapi_is_public_ip($manual_ip)) {
            wp_send_json_error(array('message' => 'That address is private or reserved, so ipapi.com holds no location data for it. Enter a public IP, or leave the field blank to test with your own.'));
            return;
        }
        $test_ip     = $manual_ip;
        $test_source = 'the IP you entered';
    } elseif (metahotels_ipapi_is_public_ip($remote_addr)) {
        // The best possible test: the exact address the front-end handler would
        // read for this request, so a pass here means live detection works.
        $test_ip     = $remote_addr;
        $test_source = 'your IP, exactly as WordPress sees it';
    } elseif ('' !== $browser_ip) {
        // WordPress sees only a loopback/LAN address (typical for Local or any
        // localhost install), so fall back to the public IP the browser reported.
        $test_ip     = $browser_ip;
        $test_source = 'your browser\'s public IP (WordPress sees only ' . ('' !== $remote_addr ? $remote_addr : 'a local address') . ' here)';
    } else {
        wp_send_json_error(array(
            'message' => 'WordPress only sees a local address (' . ('' !== $remote_addr ? $remote_addr : 'unknown') . ') for this request, and your browser\'s public IP could not be determined. Enter an IP address in the field to test the key directly.',
        ));
        return;
    }

    // The access_key must travel in the query string (ipapi has no header auth);
    // TLS protects it in transit and the full URL is never logged.
    $api_url = 'https://api.ipapi.com/api/' . urlencode($test_ip) . '?access_key=' . urlencode($api_key);

    metahotels_brevo_log('IPAPI connection test started', array(
        'ip'     => function_exists('metahotels_brevo_mask_ip') ? metahotels_brevo_mask_ip($test_ip) : '***',
        'source' => $test_source,
    ));

    $started  = microtime(true);
    $response = wp_remote_get($api_url, array(
        'timeout'     => 15,
        'redirection' => 3,
        'sslverify'   => true,
    ));
    $elapsed_ms = (int) round((microtime(true) - $started) * 1000);

    if (is_wp_error($response)) {
        metahotels_brevo_log('IPAPI connection test failed', array('error' => $response->get_error_message()));
        wp_send_json_error(array(
            'message' => 'Could not reach api.ipapi.com: ' . sanitize_text_field($response->get_error_message()),
        ));
        return;
    }

    $response_code = (int) wp_remote_retrieve_response_code($response);
    metahotels_brevo_log('IPAPI connection test response', array('status' => $response_code, 'ms' => $elapsed_ms));

    if (429 === $response_code) {
        wp_send_json_error(array('message' => 'ipapi.com rate limit exceeded (HTTP 429). Wait a moment and test again.'));
        return;
    }

    if (401 === $response_code || 403 === $response_code) {
        wp_send_json_error(array('message' => 'ipapi.com refused the request (HTTP ' . $response_code . '). The saved API key is most likely wrong or disabled.'));
        return;
    }

    if (200 !== $response_code) {
        wp_send_json_error(array('message' => 'ipapi.com returned an unexpected response (HTTP ' . $response_code . ').'));
        return;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) {
        wp_send_json_error(array('message' => 'ipapi.com returned a response that could not be read as JSON.'));
        return;
    }

    // ipapi reports application-level failures with HTTP 200 + success:false.
    if (isset($data['success']) && false === $data['success']) {
        $error_code = isset($data['error']['code']) ? (int) $data['error']['code'] : 0;
        $error_info = isset($data['error']['info']) ? sanitize_text_field(wp_strip_all_tags((string) $data['error']['info'])) : '';

        $hints = array(
            101 => 'The API key was rejected. Re-copy it from your ipapi.com dashboard and save the settings again.',
            102 => 'The ipapi.com account is inactive.',
            103 => 'The API endpoint this plugin calls does not exist on ipapi.com.',
            104 => 'The monthly request quota for this ipapi.com plan is used up.',
            105 => 'The current ipapi.com subscription plan does not permit this request — HTTPS access is restricted on the free plan.',
            106 => 'ipapi.com has no data for that IP address.',
        );

        $message = isset($hints[$error_code]) ? $hints[$error_code] : 'ipapi.com rejected the request.';
        if ('' !== $error_info) {
            $message .= ' (' . $error_info . ')';
        }

        metahotels_brevo_log('IPAPI connection test rejected', array('code' => $error_code));
        wp_send_json_error(array('message' => $message));
        return;
    }

    $country_name  = isset($data['country_name']) ? sanitize_text_field((string) $data['country_name']) : '';
    $country_code  = isset($data['country_code']) ? sanitize_text_field((string) $data['country_code']) : '';
    $city          = isset($data['city']) ? sanitize_text_field((string) $data['city']) : '';
    $region        = isset($data['region_name']) ? sanitize_text_field((string) $data['region_name']) : '';
    $raw_calling   = isset($data['location']['calling_code']) ? preg_replace('/[^0-9]/', '', (string) $data['location']['calling_code']) : '';
    $calling_code  = ('' !== $raw_calling) ? '+' . $raw_calling : '';

    $location = trim(implode(', ', array_filter(array($city, $region))));

    $details = array(
        array('IP tested', $test_ip . ' — ' . $test_source),
        array('Country', trim($country_name . ('' !== $country_code ? ' (' . $country_code . ')' : ''))),
        array('Calling code', '' !== $calling_code ? $calling_code : 'not returned'),
        array('Location', '' !== $location ? $location : 'not returned'),
        array('Response time', $elapsed_ms . ' ms'),
    );

    // Independent cross-check: the browser's own edge lookup already told us
    // which country it thinks this connection comes from, so a disagreement is
    // worth showing rather than silently trusting one source.
    if ('' === $manual_ip && '' !== $browser_loc && '' !== $country_code) {
        $agrees = ($browser_loc === strtoupper($country_code));
        $details[] = array(
            'Cross-check',
            $agrees
                ? 'your browser\'s edge lookup also reports ' . $browser_loc
                : 'your browser\'s edge lookup reports ' . $browser_loc . ', ipapi.com reports ' . strtoupper($country_code) . ' — a VPN or differing geo database can explain this',
        );
    }

    // A public REMOTE_ADDR that differs from the browser's real public IP means
    // something terminates connections in front of PHP (CDN, reverse proxy, load
    // balancer). The front-end handler trusts only REMOTE_ADDR, so every visitor
    // would be geolocated to that intermediary instead of themselves. Compared
    // only within the same address family, since a dual-stack client can legitimately
    // reach the edge over IPv6 while PHP records an IPv4 address.
    $remote_is_public = metahotels_ipapi_is_public_ip($remote_addr);
    $same_family      = (false !== strpos($remote_addr, ':')) === (false !== strpos($browser_ip, ':'));

    if ($remote_is_public && '' !== $browser_ip && $same_family && $remote_addr !== $browser_ip) {
        $details[] = array('Proxy detected', 'WordPress sees ' . $remote_addr . ', your browser\'s real public IP is ' . $browser_ip);
        wp_send_json_success(array(
            'status'  => 'warning',
            'message' => 'ipapi.com answered correctly, but a CDN or reverse proxy sits in front of this site: WordPress reads the intermediary\'s address, not the visitor\'s. Country detection on live forms will report the proxy\'s location for everyone.',
            'details' => $details,
        ));
        return;
    }

    if ('' === $calling_code) {
        wp_send_json_success(array(
            'status'  => 'warning',
            'message' => 'Connected to ipapi.com successfully, but the response carried no calling code for this IP. Forms will fall back to the default country code.',
            'details' => $details,
        ));
        return;
    }

    wp_send_json_success(array(
        'status'  => 'success',
        'message' => 'IP API connection is working. ipapi.com returned valid data for ' . $test_ip . '.',
        'details' => $details,
    ));
}

/**
 * True only for a routable public IP - the kind ipapi.com can actually geolocate.
 * Private, loopback and reserved ranges are rejected.
 */
function metahotels_ipapi_is_public_ip($ip) {
    if (!is_string($ip) || '' === $ip) {
        return false;
    }

    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}
add_action('wp_ajax_metahotels_ipapi_test_connection', 'metahotels_ipapi_test_connection_handler');

// Simple test function for debugging
function metahotels_brevo_test_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    if (!check_ajax_referer('metahotels_brevo_test_ajax', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    if (isset($_POST['test_ajax']) && sanitize_text_field(wp_unslash($_POST['test_ajax'])) === 'test') {
        wp_send_json_success(array('message' => 'AJAX is working!', 'timestamp' => time()));
        return;
    }

    wp_send_json_error(array('message' => 'Invalid test request.'));
}
add_action('wp_ajax_metahotels_brevo_test_ajax', 'metahotels_brevo_test_ajax');
