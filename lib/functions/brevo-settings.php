<?php
// Brevo Settings Page
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
function metahotels_brevo_admin_menu() {
    add_submenu_page(
        'options-general.php',
        'Brevo Settings',
        'Brevo Settings',
        'manage_options',
        'metahotels-brevo-settings',
        'metahotels_brevo_settings_page'
    );
}
add_action('admin_menu', 'metahotels_brevo_admin_menu');

// Register settings
function metahotels_brevo_register_settings() {
    register_setting('metahotels_brevo_options', 'metahotels_brevo_api_key');
    register_setting('metahotels_brevo_options', 'metahotels_brevo_lists', array(
        'type' => 'array',
        'default' => array()
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_default_country', array(
        'default' => 'IN'
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_advanced_validation', array(
        'default' => false
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_bot_protection', array(
        'default' => true
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_rate_limit', array(
        'default' => 5
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_rate_timeframe', array(
        'default' => 3600
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_debug_mode', array(
        'default' => false
    ));
}
add_action('admin_init', 'metahotels_brevo_register_settings');

// Settings page HTML
function metahotels_brevo_settings_page() {
    $api_key = get_option('metahotels_brevo_api_key', '');
    $lists = get_option('metahotels_brevo_lists', array());
    $default_country = get_option('metahotels_brevo_default_country', 'IN');
    $advanced_validation = get_option('metahotels_brevo_advanced_validation', false);
    $bot_protection = get_option('metahotels_brevo_bot_protection', true);
    $rate_limit = get_option('metahotels_brevo_rate_limit', 5);
    $rate_timeframe = get_option('metahotels_brevo_rate_timeframe', 3600);
    $debug_mode = get_option('metahotels_brevo_debug_mode', false);
    
    // Handle API key update and fetch lists
    if (isset($_POST['submit']) && !empty($_POST['metahotels_brevo_api_key'])) {
        $new_api_key = sanitize_text_field($_POST['metahotels_brevo_api_key']);
        $new_default_country = sanitize_text_field($_POST['metahotels_brevo_default_country']);
        $new_advanced_validation = isset($_POST['metahotels_brevo_advanced_validation']) ? true : false;
        $new_bot_protection = isset($_POST['metahotels_brevo_bot_protection']) ? true : false;
        $new_rate_limit = intval($_POST['metahotels_brevo_rate_limit']);
        $new_rate_timeframe = intval($_POST['metahotels_brevo_rate_timeframe']);
        $new_debug_mode = isset($_POST['metahotels_brevo_debug_mode']) ? true : false;
        
        update_option('metahotels_brevo_api_key', $new_api_key);
        update_option('metahotels_brevo_default_country', $new_default_country);
        update_option('metahotels_brevo_advanced_validation', $new_advanced_validation);
        update_option('metahotels_brevo_bot_protection', $new_bot_protection);
        update_option('metahotels_brevo_rate_limit', $new_rate_limit);
        update_option('metahotels_brevo_rate_timeframe', $new_rate_timeframe);
        update_option('metahotels_brevo_debug_mode', $new_debug_mode);
        
        // Fetch lists from Brevo
        $lists = metahotels_brevo_fetch_lists($new_api_key);
        update_option('metahotels_brevo_lists', $lists);
        
        echo '<div class="notice notice-success"><p>Settings updated and lists fetched successfully!</p></div>';
    }
    
    // Handle test contact creation
    if (isset($_POST['test_contact']) && !empty($_POST['metahotels_brevo_api_key'])) {
        $api_key = sanitize_text_field($_POST['metahotels_brevo_api_key']);
        $lists = get_option('metahotels_brevo_lists', array());
        
        if (!empty($lists)) {
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
    <div class="wrap">
        <h1>Brevo Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="metahotels_brevo_api_key">Brevo API Key</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="metahotels_brevo_api_key" 
                               name="metahotels_brevo_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text"
                               required />
                        <p class="description">Enter your Brevo API key to enable list management.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="metahotels_brevo_default_country">Default Country for Phone Numbers</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="metahotels_brevo_default_country" 
                               name="metahotels_brevo_default_country" 
                               value="<?php echo esc_attr($default_country); ?>" 
                               class="regular-text"
                               required />
                        <p class="description">Enter the default country code (e.g., +1 for USA, +91 for India) for phone numbers in subscription forms. Users can manually change this code if needed.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="metahotels_brevo_advanced_validation">Enable Advanced WhatsApp Validation</label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="metahotels_brevo_advanced_validation" 
                               name="metahotels_brevo_advanced_validation" 
                               value="1" 
                               <?php checked($advanced_validation, true); ?> />
                        <p class="description">Enable this option to use advanced validation for WhatsApp numbers.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="metahotels_brevo_bot_protection">Enable Bot Protection</label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="metahotels_brevo_bot_protection" 
                               name="metahotels_brevo_bot_protection" 
                               value="1" 
                               <?php checked($bot_protection, true); ?> />
                        <p class="description">Enable honeypot fields, rate limiting, and other bot protection measures.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="metahotels_brevo_rate_limit">Rate Limit (submissions per hour)</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="metahotels_brevo_rate_limit" 
                               name="metahotels_brevo_rate_limit" 
                               value="<?php echo esc_attr($rate_limit); ?>" 
                               min="1" 
                               max="100" 
                               class="small-text" />
                        <p class="description">Maximum number of form submissions allowed per IP address per hour.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="metahotels_brevo_rate_timeframe">Rate Limit Timeframe (seconds)</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="metahotels_brevo_rate_timeframe" 
                               name="metahotels_brevo_rate_timeframe" 
                               value="<?php echo esc_attr($rate_timeframe); ?>" 
                               min="60" 
                               max="86400" 
                               class="small-text" />
                        <p class="description">Time window for rate limiting (3600 = 1 hour).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="metahotels_brevo_debug_mode">Enable Debug Mode</label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="metahotels_brevo_debug_mode" 
                               name="metahotels_brevo_debug_mode" 
                               value="1" 
                               <?php checked($debug_mode, true); ?> />
                        <p class="description">Enable this to see detailed error messages and debug information in the browser console.</p>
                    </td>
                </tr>
            </table>
            
            <?php if (!empty($lists)): ?>
            <h2>Available Lists</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>List Name</th>
                        <th>List ID</th>
                        <th>Subscribers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lists as $list): ?>
                    <tr>
                        <td><?php echo esc_html($list['name']); ?></td>
                        <td><?php echo esc_html($list['id']); ?></td>
                        <td><?php echo esc_html($list['subscribers']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php submit_button('Save API Key & Fetch Lists'); ?>
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
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'metahotels_brevo_test_ajax',
                        test_ajax: 'test'
                    },
                    success: function(response) {
                        result.html('<div style="color: green;"><strong>Success:</strong> ' + response.data.message + '</div>');
                        btn.text('Test AJAX').prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        result.html('<div style="color: red;"><strong>Error:</strong> ' + status + ' - ' + error + '<br>Response: ' + xhr.responseText + '</div>');
                        btn.text('Test AJAX').prop('disabled', false);
                    }
                });
            });
        });
        </script>
        
        <?php if (!empty($lists)): ?>
        <p><strong>Test Contact Creation:</strong></p>
        <form method="post" action="">
            <input type="hidden" name="test_contact" value="1">
            <input type="hidden" name="metahotels_brevo_api_key" value="<?php echo esc_attr($api_key); ?>">
            <p>This will create a test contact in the first available list:</p>
            <button type="submit" class="button button-secondary">Test Contact Creation</button>
        </form>
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
        )
    ));
    
    if (is_wp_error($response)) {
        return array();
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['lists']) && is_array($data['lists'])) {
        $lists = array();
        foreach ($data['lists'] as $list) {
            $lists[] = array(
                'id' => $list['id'],
                'name' => $list['name'],
                'subscribers' => isset($list['uniqueSubscribers']) ? $list['uniqueSubscribers'] : 0
            );
        }
        return $lists;
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
    
    $body = json_encode($contact_data);
    
    // Log the request for debugging
    error_log('Brevo Test Request: ' . $body);
    
    $response = wp_remote_post($url, array(
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => $body,
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Brevo Test Error: ' . $response->get_error_message());
        return array('success' => false, 'message' => $response->get_error_message());
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    // Log the response for debugging
    error_log('Brevo Test Response Code: ' . $response_code);
    error_log('Brevo Test Response Body: ' . $response_body);
    
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

// Check if contact exists and get contact info
function metahotels_brevo_get_contact($api_key, $email) {
    if (empty($api_key) || empty($email)) {
        return false;
    }
    
    $url = 'https://api.brevo.com/v3/contacts/' . urlencode($email);
    
    $response = wp_remote_get($url, array(
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        )
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

// Update existing contact
function metahotels_brevo_update_contact($api_key, $email, $contact_data) {
    if (empty($api_key) || empty($email) || !is_array($contact_data)) {
        return array('success' => false, 'message' => 'Invalid parameters');
    }
    
    $url = 'https://api.brevo.com/v3/contacts/' . urlencode($email);
    
    // Remove email from contact data for update
    unset($contact_data['email']);
    
    $response = wp_remote_put($url, array(
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($contact_data),
        'timeout' => 30
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

// Advanced WhatsApp validation using external service (optional)
function metahotels_advanced_whatsapp_validation($phone_number, $country_code) {
    
    $full_number = $country_code . $phone_number;

    return array(
        'valid' => true,
        'message' => 'Basic validation passed. For advanced validation, integrate with a phone validation service.'
    );
} 

// Bot protection functions
function metahotels_generate_honeypot_field() {
    $field_name = 'website_' . wp_rand(1000, 9999);
    $field_id = 'website_' . wp_rand(1000, 9999);
    
    return array(
        'name' => $field_name,
        'id' => $field_id,
        'hash' => wp_hash($field_name . 'honeypot')
    );
}

function metahotels_verify_honeypot($honeypot_data) {
    if (empty($honeypot_data['name']) || empty($honeypot_data['hash'])) {
        return false;
    }
    
    $expected_hash = wp_hash($honeypot_data['name'] . 'honeypot');
    return hash_equals($expected_hash, $honeypot_data['hash']);
}

function metahotels_check_rate_limit($ip_address, $limit = 5, $timeframe = 3600) {
    $transient_key = 'brevo_rate_limit_' . md5($ip_address);
    $attempts = get_transient($transient_key);
    
    if ($attempts === false) {
        set_transient($transient_key, 1, $timeframe);
        return true;
    }
    
    if ($attempts >= $limit) {
        return false;
    }
    
    set_transient($transient_key, $attempts + 1, $timeframe);
    return true;
}

function metahotels_validate_submission_time($min_time = 3, $max_time = 300) {
    if (!isset($_POST['form_start_time'])) {
        return false;
    }
    
    $start_time = intval($_POST['form_start_time']);
    $current_time = time();
    $submission_time = $current_time - $start_time;
    
    // Too fast (likely a bot)
    if ($submission_time < $min_time) {
        return false;
    }
    
    // Too slow (session expired)
    if ($submission_time > $max_time) {
        return false;
    }
    
    return true;
}

function metahotels_check_user_agent() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check for common bot user agents
    $bot_patterns = array(
        '/bot/i', '/crawler/i', '/spider/i', '/scraper/i',
        '/curl/i', '/wget/i', '/python/i', '/java/i',
        '/perl/i', '/ruby/i', '/php/i', '/go-http-client/i'
    );
    
    foreach ($bot_patterns as $pattern) {
        if (preg_match($pattern, $user_agent)) {
            return false;
        }
    }
    
    return true;
}

function metahotels_validate_referer() {
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $site_url = get_site_url();
    
    // Check if referer is from the same site
    if (empty($referer) || !strpos($referer, $site_url) === 0) {
        return false;
    }
    
    return true;
}

function metahotels_generate_csrf_token() {
    $token = wp_generate_password(32, false);
    $token_id = 'brevo_csrf_' . wp_rand(1000, 9999);
    
    // Store token in WordPress transients instead of sessions
    set_transient($token_id, $token, 3600); // 1 hour expiry
    
    return array(
        'token' => $token,
        'id' => $token_id
    );
}

function metahotels_verify_csrf_token($token_data) {
    if (empty($token_data) || !is_array($token_data) || empty($token_data['token']) || empty($token_data['id'])) {
        return false;
    }
    
    $stored_token = get_transient($token_data['id']);
    
    if ($stored_token === false) {
        return false; // Token expired or doesn't exist
    }
    
    $is_valid = hash_equals($stored_token, $token_data['token']);
    
    // Delete the token after verification (one-time use)
    if ($is_valid) {
        delete_transient($token_data['id']);
    }
    
    return $is_valid;
} 

// Delete user from Brevo by email
function metahotels_delete_brevo_user($email) {
    $api_key = get_option('metahotels_brevo_api_key');
    if (empty($api_key)) {
        error_log('Brevo User Deletion: API key not configured');
        return false;
    }
    
    // First, get the contact ID by email
    $response = wp_remote_get("https://api.brevo.com/v3/contacts/{$email}", array(
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Brevo User Deletion: Failed to get contact - ' . $response->get_error_message());
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code === 404) {
        error_log('Brevo User Deletion: Contact not found - ' . $email);
        return true; // Contact doesn't exist, consider it deleted
    }
    
    if ($response_code !== 200) {
        error_log('Brevo User Deletion: Failed to get contact - HTTP ' . $response_code);
        return false;
    }
    
    // Delete the contact
    $delete_response = wp_remote_request("https://api.brevo.com/v3/contacts/{$email}", array(
        'method' => 'DELETE',
        'headers' => array(
            'api-key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($delete_response)) {
        error_log('Brevo User Deletion: Failed to delete contact - ' . $delete_response->get_error_message());
        return false;
    }
    
    $delete_code = wp_remote_retrieve_response_code($delete_response);
    if ($delete_code === 204 || $delete_code === 200) {
        error_log('Brevo User Deletion: Successfully deleted contact - ' . $email);
        return true;
    } else {
        error_log('Brevo User Deletion: Failed to delete contact - HTTP ' . $delete_code);
        return false;
    }
}

// Handle booking page visits - delete user from Brevo
function metahotels_handle_booking_page_visit() {
    // Check if we're on the booking confirmation page
    if (is_page('my-booking') || strpos($_SERVER['REQUEST_URI'], '/my-booking/') !== false) {
        // Check if user has a Brevo session (from popup registration)
        if (isset($_COOKIE['brevo_registered_email'])) {
            $email = sanitize_email($_COOKIE['brevo_registered_email']);
            
            if (is_email($email)) {
                // Delete user from Brevo
                $deleted = metahotels_delete_brevo_user($email);
                
                if ($deleted) {
                    // Clear the cookie
                    setcookie('brevo_registered_email', '', time() - 3600, '/');
                    error_log('Brevo User Deletion: User deleted from Brevo after booking - ' . $email);
                }
            }
        }
    }
}
add_action('wp', 'metahotels_handle_booking_page_visit');

// Simple test function for debugging
function metahotels_brevo_test_ajax() {
    if (isset($_POST['test_ajax']) && $_POST['test_ajax'] === 'test') {
        wp_send_json_success(array('message' => 'AJAX is working!', 'timestamp' => time()));
    }
}
add_action('wp_ajax_metahotels_brevo_test_ajax', 'metahotels_brevo_test_ajax');
add_action('wp_ajax_nopriv_metahotels_brevo_test_ajax', 'metahotels_brevo_test_ajax'); 