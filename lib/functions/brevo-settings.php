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
    $recaptcha_score_threshold = get_option('metahotels_brevo_recaptcha_score_threshold', 0.5);
    $lists = get_option('metahotels_brevo_lists', array());
    $default_country = get_option('metahotels_brevo_default_country', '+91');

    $debug_mode = get_option('metahotels_brevo_debug_mode', false);
    $ipapi_api_key = get_option('metahotels_ipapi_api_key', '');
    $transactional_enabled = get_option('metahotels_brevo_transactional_enabled', false);
    $sender_name = get_option('metahotels_brevo_sender_name', get_bloginfo('name'));
    $sender_email = get_option('metahotels_brevo_sender_email', get_option('admin_email'));
    $senders = get_option('metahotels_brevo_senders', array());
    
    // Handle API key update and fetch lists
    if (isset($_POST['submit'])) {
        check_admin_referer('metahotels_brevo_save', 'metahotels_brevo_nonce_field');
        $new_api_key = isset($_POST['metahotels_brevo_api_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_brevo_api_key'])) : $api_key;
        $new_recaptcha_site_key = isset($_POST['metahotels_brevo_recaptcha_site_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_brevo_recaptcha_site_key'])) : $recaptcha_site_key;
        $new_recaptcha_secret_key = isset($_POST['metahotels_brevo_recaptcha_secret_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_brevo_recaptcha_secret_key'])) : $recaptcha_secret_key;
        $new_recaptcha_score_threshold = isset($_POST['metahotels_brevo_recaptcha_score_threshold']) ? floatval(wp_unslash($_POST['metahotels_brevo_recaptcha_score_threshold'])) : $recaptcha_score_threshold;
        // Ensure threshold is between 0 and 1
        if ($new_recaptcha_score_threshold < 0) {
            $new_recaptcha_score_threshold = 0;
        } elseif ($new_recaptcha_score_threshold > 1) {
            $new_recaptcha_score_threshold = 1;
        }
        $new_default_country = isset($_POST['metahotels_brevo_default_country']) ? metahotels_sanitize_calling_code(wp_unslash($_POST['metahotels_brevo_default_country'])) : $default_country;

        $new_debug_mode = isset($_POST['metahotels_brevo_debug_mode']) && wp_unslash($_POST['metahotels_brevo_debug_mode']) === '1';
        $new_ipapi_api_key = isset($_POST['metahotels_ipapi_api_key']) ? sanitize_text_field(wp_unslash($_POST['metahotels_ipapi_api_key'])) : $ipapi_api_key;
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

        update_option('metahotels_brevo_debug_mode', $new_debug_mode);
        
        // Update local variables for display
        $debug_mode = $new_debug_mode;
        
        // Only fetch lists and senders if API key is provided
        if (!empty($new_api_key)) {
            $lists = metahotels_brevo_fetch_lists($new_api_key);
            update_option('metahotels_brevo_lists', $lists, false);

            $senders = metahotels_brevo_fetch_senders($new_api_key);
            update_option('metahotels_brevo_senders', $senders, false);

            echo '<div class="notice notice-success"><p>Settings updated and data fetched successfully!</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Settings updated successfully!</p></div>';
        }
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
                                   value="<?php echo esc_attr($api_key); ?>"
                                    class="metahotels-input"
                                   autocomplete="off"
                                   required />
                            <p class="metahotels-helper-text">Your Brevo API key from Settings > API Keys in your Brevo account.</p>
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
                                    <p class="metahotels-helper-text">No verified senders found. Save your Brevo API key above to load senders from your account.</p>
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
                                   value="<?php echo esc_attr($ipapi_api_key); ?>"
                                   class="metahotels-input"
                                   autocomplete="off" />
                            <p class="metahotels-helper-text">Get your free API key from <a href="https://ipapi.com/signup/" target="_blank" rel="noopener noreferrer">ipapi.com</a> for automatic country detection.</p>
                        </div>
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
                                       value="<?php echo esc_attr($recaptcha_secret_key); ?>"
                                       class="metahotels-input" autocomplete="off" />
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
            
            <?php if (!empty($lists)): ?>
            <div class="metahotels-card">
                 <div class="metahotels-card-header">
                    <h3 class="metahotels-card-title">Available Brevo Lists</h3>
                    <p class="metahotels-card-description">Lists fetched from your Brevo account.</p>
                </div>
                <div class="metahotels-card-content" style="padding: 0;">
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
                </div>
            </div>
            <?php endif; ?>
            
            <div style="margin-top: 2rem;">
                <?php
                wp_nonce_field('metahotels_brevo_save', 'metahotels_brevo_nonce_field');
                submit_button('Save Settings');
                ?>
            </div>
        </form>
        
        <h2>Shortcode Usage</h2>
        <p>Use the following shortcode to display a Brevo subscription form:</p>
        <code>[brevo_form list_id="YOUR_LIST_ID" redirect_url="https://example.com/thank-you" button_text="Book Now"]</code>
        <p><strong>Parameters:</strong></p>
        <ul>
            <li><code>list_id</code> - The Brevo list ID (required)</li>
            <li><code>redirect_url</code> - URL to redirect after successful subscription (optional)</li>
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

// Fetch lists from Brevo API
function metahotels_brevo_fetch_lists($api_key) {
    $url = 'https://api.brevo.com/v3/contacts/lists';
    
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
        return array();
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        return array();
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['lists']) && is_array($data['lists'])) {
        $lists = array();
        foreach ($data['lists'] as $list) {
            $lists[] = array(
                'id' => isset($list['id']) ? intval($list['id']) : 0,
                'name' => isset($list['name']) ? sanitize_text_field($list['name']) : '',
                'subscribers' => isset($list['uniqueSubscribers']) ? intval($list['uniqueSubscribers']) : 0
            );
        }
        return $lists;
    }
    
    return array();
}

// Fetch verified senders from Brevo API
function metahotels_brevo_fetch_senders($api_key) {
    $response = wp_remote_get('https://api.brevo.com/v3/senders', array(
        'headers' => array(
            'api-key'      => $api_key,
            'Content-Type' => 'application/json',
        ),
        'timeout' => 15,
        'redirection' => 3,
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        return array();
    }

    if (wp_remote_retrieve_response_code($response) !== 200) {
        return array();
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (!isset($body['senders']) || !is_array($body['senders'])) {
        return array();
    }

    $senders = array();
    foreach ($body['senders'] as $s) {
        if (!empty($s['email'])) {
            $senders[] = array(
                'id'     => isset($s['id']) ? intval($s['id']) : 0,
                'name'   => isset($s['name']) ? sanitize_text_field($s['name']) : sanitize_email($s['email']),
                'email'  => sanitize_email($s['email']),
                'active' => isset($s['active']) ? (bool) $s['active'] : true,
            );
        }
    }

    return $senders;
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


// Validate WhatsApp number
function metahotels_validate_whatsapp_number($phone_number, $country_code) {
    $errors = array();
    
    // Clean the phone number
    $clean_number = preg_replace('/[^0-9]/', '', $phone_number);
    
    // Basic validation
    if (empty($clean_number)) {
        $errors[] = 'Phone number is required';
        return $errors;
    }
    
    // Check minimum length (most countries require 7-15 digits)
    if (strlen($clean_number) < 7) {
        $errors[] = 'Phone number is too short';
    }
    
    if (strlen($clean_number) > 15) {
        $errors[] = 'Phone number is too long';
    }
    
    // Country-specific validation
    $country_validations = array(
        'IN' => array('min' => 10, 'max' => 10, 'pattern' => '/^[6-9]\d{9}$/'), // India
        'US' => array('min' => 10, 'max' => 10, 'pattern' => '/^\d{10}$/'), // USA
        'GB' => array('min' => 10, 'max' => 11, 'pattern' => '/^[1-9]\d{9,10}$/'), // UK
        'DE' => array('min' => 10, 'max' => 12, 'pattern' => '/^[1-9]\d{9,11}$/'), // Germany
        'FR' => array('min' => 10, 'max' => 10, 'pattern' => '/^[1-9]\d{8}$/'), // France
        'AU' => array('min' => 9, 'max' => 9, 'pattern' => '/^[2-9]\d{8}$/'), // Australia
        'CA' => array('min' => 10, 'max' => 10, 'pattern' => '/^\d{10}$/'), // Canada
        'BR' => array('min' => 10, 'max' => 11, 'pattern' => '/^[1-9]\d{9,10}$/'), // Brazil
        'MX' => array('min' => 10, 'max' => 10, 'pattern' => '/^[1-9]\d{9}$/'), // Mexico
        'JP' => array('min' => 10, 'max' => 11, 'pattern' => '/^[1-9]\d{9,10}$/'), // Japan
        'KR' => array('min' => 10, 'max' => 11, 'pattern' => '/^[1-9]\d{9,10}$/'), // South Korea
        'CN' => array('min' => 11, 'max' => 11, 'pattern' => '/^1[3-9]\d{9}$/'), // China
    );
    
    // Get country code without +
    $country = str_replace('+', '', $country_code);
    $country_map = array(
        '91' => 'IN', '1' => 'US', '44' => 'GB', '49' => 'DE', '33' => 'FR',
        '61' => 'AU', '55' => 'BR', '52' => 'MX', '81' => 'JP', '82' => 'KR', '86' => 'CN'
    );
    
    $country_code_clean = $country_map[$country] ?? 'IN'; // Default to India
    
    if (isset($country_validations[$country_code_clean])) {
        $validation = $country_validations[$country_code_clean];
        
        // Check length
        if (strlen($clean_number) < $validation['min']) {
            $errors[] = 'Phone number is too short for ' . $country_code_clean . ' format';
        }
        
        if (strlen($clean_number) > $validation['max']) {
            $errors[] = 'Phone number is too long for ' . $country_code_clean . ' format';
        }
        
        // Check pattern
        if (!preg_match($validation['pattern'], $clean_number)) {
            $errors[] = 'Phone number format is invalid for ' . $country_code_clean;
        }
    }
    
    // Check for common invalid patterns
    if (preg_match('/^0+$/', $clean_number)) {
        $errors[] = 'Phone number cannot be all zeros';
    }
    
    if (preg_match('/^1+$/', $clean_number)) {
        $errors[] = 'Phone number cannot be all ones';
    }
    
    // Check for sequential numbers (likely fake)
    if (preg_match('/^(.)\1{5,}$/', $clean_number)) {
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
