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
    register_setting('metahotels_brevo_options', 'metahotels_brevo_api_key');
    register_setting('metahotels_brevo_options', 'metahotels_brevo_recaptcha_site_key');
    register_setting('metahotels_brevo_options', 'metahotels_brevo_recaptcha_secret_key');
    register_setting('metahotels_brevo_options', 'metahotels_brevo_recaptcha_score_threshold', array(
        'default' => 0.5
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_lists', array(
        'type' => 'array',
        'default' => array()
    ));
    register_setting('metahotels_brevo_options', 'metahotels_brevo_default_country', array(
        'default' => 'IN'
    ));

    register_setting('metahotels_brevo_options', 'metahotels_brevo_debug_mode', array(
        'default' => false
    ));
    register_setting('metahotels_brevo_options', 'metahotels_ipapi_api_key', array(
        'sanitize_callback' => 'sanitize_text_field'
    ));
}
add_action('admin_init', 'metahotels_brevo_register_settings');

// Settings page HTML
// Settings page HTML
function metahotels_brevo_render_content() {
    $api_key = get_option('metahotels_brevo_api_key', '');
    $recaptcha_site_key = get_option('metahotels_brevo_recaptcha_site_key', '');
    $recaptcha_secret_key = get_option('metahotels_brevo_recaptcha_secret_key', '');
    $recaptcha_score_threshold = get_option('metahotels_brevo_recaptcha_score_threshold', 0.5);
    $lists = get_option('metahotels_brevo_lists', array());
    $default_country = get_option('metahotels_brevo_default_country', 'IN');

    $debug_mode = get_option('metahotels_brevo_debug_mode', false);
    $ipapi_api_key = get_option('metahotels_ipapi_api_key', '');
    
    // Handle API key update and fetch lists
    if (isset($_POST['submit'])) {
        $new_api_key = isset($_POST['metahotels_brevo_api_key']) ? sanitize_text_field($_POST['metahotels_brevo_api_key']) : $api_key;
        $new_recaptcha_site_key = isset($_POST['metahotels_brevo_recaptcha_site_key']) ? sanitize_text_field($_POST['metahotels_brevo_recaptcha_site_key']) : $recaptcha_site_key;
        $new_recaptcha_secret_key = isset($_POST['metahotels_brevo_recaptcha_secret_key']) ? sanitize_text_field($_POST['metahotels_brevo_recaptcha_secret_key']) : $recaptcha_secret_key;
        $new_recaptcha_score_threshold = isset($_POST['metahotels_brevo_recaptcha_score_threshold']) ? floatval($_POST['metahotels_brevo_recaptcha_score_threshold']) : $recaptcha_score_threshold;
        // Ensure threshold is between 0 and 1
        if ($new_recaptcha_score_threshold < 0) {
            $new_recaptcha_score_threshold = 0;
        } elseif ($new_recaptcha_score_threshold > 1) {
            $new_recaptcha_score_threshold = 1;
        }
        $new_default_country = isset($_POST['metahotels_brevo_default_country']) ? sanitize_text_field($_POST['metahotels_brevo_default_country']) : $default_country;

        $new_debug_mode = isset($_POST['metahotels_brevo_debug_mode']) && $_POST['metahotels_brevo_debug_mode'] == '1' ? true : false;
        $new_ipapi_api_key = isset($_POST['metahotels_ipapi_api_key']) ? sanitize_text_field($_POST['metahotels_ipapi_api_key']) : $ipapi_api_key;
        
        update_option('metahotels_brevo_api_key', $new_api_key);
        update_option('metahotels_ipapi_api_key', $new_ipapi_api_key);
        update_option('metahotels_brevo_default_country', $new_default_country);
        update_option('metahotels_brevo_recaptcha_site_key', $new_recaptcha_site_key);
        update_option('metahotels_brevo_recaptcha_secret_key', $new_recaptcha_secret_key);
        update_option('metahotels_brevo_recaptcha_score_threshold', $new_recaptcha_score_threshold);

        update_option('metahotels_brevo_debug_mode', $new_debug_mode);
        
        // Update local variables for display
        $debug_mode = $new_debug_mode;
        
        // Only fetch lists if API key is provided
        if (!empty($new_api_key)) {
        
            // Fetch lists from Brevo
            $lists = metahotels_brevo_fetch_lists($new_api_key);
            update_option('metahotels_brevo_lists', $lists);
            
            echo '<div class="notice notice-success"><p>Settings updated and lists fetched successfully!</p></div>';
        } else {
            echo '<div class="notice notice-success"><p>Settings updated successfully!</p></div>';
        }
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
        <form method="post" action="">
            <div class="metahotels-section">
                <!-- API Configuration Card -->
                <div class="metahotels-card">
                    <div class="metahotels-card-header">
                        <h3 class="metahotels-card-title">API Configuration</h3>
                        <p class="metahotels-card-description">Configure connections to external services like Brevo and ipapi.com.</p>
                    </div>
                    <div class="metahotels-card-content">
                        <div class="metahotels-form-group">
                            <label class="metahotels-label" for="metahotels_brevo_api_key">Brevo API Key</label>
                            <input type="text" 
                                   id="metahotels_brevo_api_key" 
                                   name="metahotels_brevo_api_key" 
                                   value="<?php echo esc_attr($api_key); ?>" 
                                   class="metahotels-input"
                                   required />
                            <p class="metahotels-helper-text">Enter your Brevo API key to enable list management.</p>
                        </div>

                        <div class="metahotels-form-group">
                            <label class="metahotels-label" for="metahotels_ipapi_api_key">ipapi.com API Key</label>
                            <input type="text" 
                                   id="metahotels_ipapi_api_key" 
                                   name="metahotels_ipapi_api_key" 
                                   value="<?php echo esc_attr($ipapi_api_key); ?>" 
                                   class="metahotels-input" />
                            <p class="metahotels-helper-text">Get your free API key from <a href="https://ipapi.com/signup/" target="_blank">ipapi.com</a> for automatic country detection.</p>
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
                 <?php submit_button('Save Settings'); ?>
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
            // Verify and extract email from cryptographically signed cookie
            $email = metahotels_verify_brevo_cookie($_COOKIE['brevo_registered_email']);
            
            if ($email !== false) {
                // Delete user from Brevo
                $deleted = metahotels_delete_brevo_user($email);
                
                if ($deleted) {
                    // Clear the cookie
                    setcookie('brevo_registered_email', '', time() - 3600, '/');
                    error_log('Brevo User Deletion: User deleted from Brevo after booking - ' . $email);
                }
            } else {
                // Invalid or tampered cookie - clear it
                setcookie('brevo_registered_email', '', time() - 3600, '/');
                error_log('Brevo User Deletion: Invalid or tampered cookie detected');
            }
        }
    }
}
add_action('wp', 'metahotels_handle_booking_page_visit');

// Simple test function for debugging
function metahotels_brevo_test_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
        return;
    }

    if (isset($_POST['test_ajax']) && $_POST['test_ajax'] === 'test') {
        wp_send_json_success(array('message' => 'AJAX is working!', 'timestamp' => time()));
    }
}
add_action('wp_ajax_metahotels_brevo_test_ajax', 'metahotels_brevo_test_ajax');
add_action('wp_ajax_nopriv_metahotels_brevo_test_ajax', 'metahotels_brevo_test_ajax'); 