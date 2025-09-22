<?php
// Brevo Form Shortcode - Elementor Compatible Version
// 
// Changes made for Elementor compatibility:
// 1. Improved script enqueuing to work with dynamic content (popups, widgets, etc.)
// 2. Added global flag tracking to ensure script loads when shortcode is used
// 3. Enhanced initialization with multiple event listeners for dynamic content
// 4. Added conflict prevention for duplicate variable declarations
// 5. Improved CSS specificity to avoid styling conflicts
// 6. Added MutationObserver for DOM changes detection
//
// Version: 2.5.1 - Elementor Compatible

if (!defined('ABSPATH')) {
    exit;
}

// Register shortcode
add_shortcode('brevo_form', 'metahotels_brevo_form_shortcode');

// Global flag to track if any Brevo form is used
global $metahotels_brevo_form_used;
$metahotels_brevo_form_used = false;

function metahotels_brevo_form_shortcode($atts) {
    global $metahotels_brevo_form_used;
    $metahotels_brevo_form_used = true;
    
    try {
        $atts = shortcode_atts(array(
            'list_id' => '',
            'title' => 'Subscribe to Newsletter',
            'button_text' => 'Book Now',
            'redirect_url' => ''
        ), $atts);
        
        if (empty($atts['list_id'])) {
            return '<p style="color: red;">Error: List ID is required for Brevo form.</p>';
        }
        
        // Get country code for WhatsApp
        $country_code = metahotels_get_country_from_ip();
        
        // Get redirect URL from shortcode attributes only
        $redirect_url = !empty($atts['redirect_url']) ? $atts['redirect_url'] : '';
        
        // Check if bot protection is enabled
        $bot_protection = get_option('metahotels_brevo_bot_protection', true);
        $debug_mode = get_option('metahotels_brevo_debug_mode', false);
        
        // Generate honeypot field if bot protection is enabled
        $honeypot_field = null;
        if ($bot_protection) {
            try {
                $honeypot_field = metahotels_generate_honeypot_field();
            } catch (Exception $e) {
                // If honeypot generation fails, disable bot protection for this form
                $bot_protection = false;
                if ($debug_mode) {
                    error_log('Brevo Form: Honeypot generation failed: ' . $e->getMessage());
                }
            }
        }
        
        // Generate CSRF token if bot protection is enabled
        $csrf_token_data = null;
        if ($bot_protection) {
            try {
                $csrf_token_data = metahotels_generate_csrf_token();
            } catch (Exception $e) {
                // If CSRF generation fails, disable bot protection for this form
                $bot_protection = false;
                if ($debug_mode) {
                    error_log('Brevo Form: CSRF generation failed: ' . $e->getMessage());
                }
            }
        }
        
        // Generate unique form ID
        $form_id = 'brevo_form_' . uniqid();
        
        // Enqueue necessary scripts and styles only once
        static $scripts_loaded = false;
        if (!$scripts_loaded) {
            wp_enqueue_script('jquery');
            wp_enqueue_style('metahotels-brevo-form', plugin_dir_url(__FILE__) . '../assets/brevo-form.css', array(), '1.0.2');
            $scripts_loaded = true;
        }
        
        // Return form HTML
        $output = '<div class="brevo-form-container" data-form-id="' . $form_id . '">';
        $output .= '<form id="' . $form_id . '" class="brevo-form" data-ajax-url="' . admin_url('admin-ajax.php') . '">';
        $output .= '<input type="hidden" name="action" value="metahotels_brevo_subscribe">';
        $output .= '<input type="hidden" name="list_id" value="' . esc_attr($atts['list_id']) . '">';
        $output .= '<input type="hidden" name="redirect_url" value="' . esc_attr($redirect_url) . '">';
        $output .= '<input type="hidden" name="nonce" value="' . wp_create_nonce('metahotels_brevo_nonce') . '">';
        
        // Email field
        $output .= '<div>';
        $output .= '<input type="email" name="email" id="email_' . $form_id . '" placeholder="Email Address *" required>';
        $output .= '</div>';
        
        // WhatsApp field
        $output .= '<div>';
        $output .= '<div class="whatsapp-group">';
        $output .= '<input type="text" name="country_code" id="country_code_' . $form_id . '" value="' . esc_attr($country_code) . '" placeholder="+" class="country-code-input">';
        $output .= '<input type="tel" name="whatsapp" id="whatsapp_' . $form_id . '" placeholder="WhatsApp Number *" required>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Honeypot field
        if ($bot_protection && $honeypot_field && isset($honeypot_field['name']) && isset($honeypot_field['id']) && isset($honeypot_field['hash'])) {
            $output .= '<div style="display: none;">';
            $output .= '<input type="text" name="' . esc_attr($honeypot_field['name']) . '" id="' . esc_attr($honeypot_field['id']) . '" placeholder="If you are human, leave this field blank.">';
            $output .= '<input type="hidden" name="honeypot_hash" value="' . esc_attr($honeypot_field['hash']) . '">';
            $output .= '</div>';
        }
        
        // CSRF token field
        if ($bot_protection && !empty($csrf_token_data)) {
            $output .= '<input type="hidden" name="csrf_token" value="' . esc_attr($csrf_token_data['token']) . '">';
        }
        
        // Submit button
        $output .= '<button type="submit">' . esc_html($atts['button_text']) . '</button>';
        
        $output .= '</form>';
        $output .= '</div>';
        
        return $output;
        
    } catch (Exception $e) {
        if ($debug_mode) {
            error_log('Brevo Form Shortcode Error: ' . $e->getMessage());
        }
        return '<p style="color: red;">Error: Unable to load form. Please try again later.</p>';
    }
}

// Add script to footer to avoid conflicts - with better guards
add_action('wp_footer', 'metahotels_brevo_form_scripts', 99);

function metahotels_brevo_form_scripts() {
    global $metahotels_brevo_form_used;
    
    // Prevent multiple executions
    static $script_loaded = false;
    if ($script_loaded) {
        return;
    }
    
    // Check if we have Brevo forms on the page using multiple methods
    $has_brevo_form = false;
    
    // Check global flag first (most reliable)
    if ($metahotels_brevo_form_used) {
        $has_brevo_form = true;
    }
    
    // Check main content
    if (!$has_brevo_form && (has_shortcode(get_the_content(), 'brevo_form') || has_shortcode(get_the_excerpt(), 'brevo_form'))) {
        $has_brevo_form = true;
    }
    
    // Check widgets and other areas
    if (!$has_brevo_form) {
        global $wp_query;
        if (isset($wp_query->posts) && is_array($wp_query->posts)) {
            foreach ($wp_query->posts as $post) {
                if (has_shortcode($post->post_content, 'brevo_form') || has_shortcode($post->post_excerpt, 'brevo_form')) {
                    $has_brevo_form = true;
                    break;
                }
            }
        }
    }
    
    // Check if we're on a page that might have dynamic content (like Elementor pages)
    if (!$has_brevo_form) {
        // Check if Elementor is active and we're on a page that might have popups
        if (class_exists('Elementor\Plugin') && is_page()) {
            $has_brevo_form = true; // Load script on all pages when Elementor is active
        }
    }
    
    // If no forms detected, don't load the script
    if (!$has_brevo_form) {
        // But if Elementor is active, load the script anyway to handle dynamic content
        if (class_exists('Elementor\Plugin')) {
            $has_brevo_form = true;
        } else {
            return;
        }
    }
    
    // Mark as loaded to prevent multiple executions
    $script_loaded = true;
    
    ?>
    <script type="text/javascript">
    (function($) {
        'use strict';
        
        // Ensure jQuery is available
        if (typeof $ === 'undefined') {
            console.error('MetaHotels Brevo: jQuery is not available');
            return;
        }
        
        // Prevent multiple initializations
        if (window.metahotelsBrevoInitialized) {
            console.log('MetaHotels Brevo: Already initialized, skipping...');
            return;
        }
        
        // Conflict resolution - prevent duplicate variable declarations
        if (typeof window.lazyloadRunObserver !== 'undefined') {
            console.warn('MetaHotels Brevo: Detected existing lazyloadRunObserver, skipping initialization');
            return;
        }
        
        // Mark as initialized immediately to prevent multiple executions
        window.metahotelsBrevoInitialized = true;
        
        function initBrevoForms() {
            var formsFound = 0;
            var newFormsFound = 0;
            
            $('.brevo-form').each(function() {
                var $form = $(this);
                var $submitBtn = $form.find('button[type="submit"]');
                
                // Prevent multiple event bindings
                if ($form.data('initialized')) {
                    formsFound++;
                    return;
                }
                
                if ($submitBtn.length === 0) {
                    console.warn('MetaHotels Brevo: Submit button not found in form');
                    return;
                }
                
                var originalButtonText = $submitBtn.text();
                formsFound++;
                newFormsFound++;
                
                $form.on('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var email = $form.find('input[name="email"]').val();
                    var whatsapp = $form.find('input[name="whatsapp"]').val();
                    
                    if (!email || !whatsapp) {
                        alert("Please fill in all required fields.");
                        return false;
                    }
                    
                    $submitBtn.text("Registering...").prop('disabled', true);
                    
                    var formData = new FormData(this);
                    
                    $.ajax({
                        url: $form.data('ajax-url'),
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                if (response.data && response.data.redirect_url) {
                                    window.location.href = response.data.redirect_url;
                                } else {
                                    $form.html('<div style="background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px;"><h3>Thank you!</h3><p>You have been successfully subscribed to our newsletter.</p></div>');
                                }
                            } else {
                                var errorMessage = "Something went wrong. Please try again.";
                                if (response.data) {
                                    if (response.data.includes("Brevo")) {
                                        errorMessage = "Unable to save your information. Please try again later.";
                                    } else if (response.data.includes("Invalid email")) {
                                        errorMessage = "Please enter a valid email address.";
                                    } else if (response.data.includes("WhatsApp number is required")) {
                                        errorMessage = "Please enter your WhatsApp number.";
                                    } else if (response.data.includes("Security check failed")) {
                                        errorMessage = "Security verification failed. Please refresh the page and try again.";
                                    } else if (response.data.includes("API key not configured")) {
                                        errorMessage = "Service temporarily unavailable. Please try again later.";
                                    } else if (response.data.includes("WHATSAPP is already associated")) {
                                        errorMessage = "This WhatsApp number is already registered. Please use a different number or contact support.";
                                    } else {
                                        errorMessage = "Unable to process your request. Please try again.";
                                    }
                                }
                                alert(errorMessage);
                                $submitBtn.text(originalButtonText).prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert("Connection error. Please check your internet connection and try again.");
                            $submitBtn.text(originalButtonText).prop('disabled', false);
                        }
                    });
                });
                
                $form.data('initialized', true);
            });
            
            if (newFormsFound > 0) {
                console.log('MetaHotels Brevo: Successfully initialized ' + newFormsFound + ' new forms (total: ' + formsFound + ')');
            }
        }
        
        // Initialize on document ready
        $(document).ready(function() {
            console.log('MetaHotels Brevo: DOM ready, initializing forms...');
            setTimeout(initBrevoForms, 100);
        });
        
        // Initialize on Elementor frontend init (for popups) - with proper checks
        function initElementorHooks() {
            if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks) {
                try {
                    elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                        setTimeout(initBrevoForms, 100);
                    });
                    console.log('MetaHotels Brevo: Elementor hooks initialized successfully');
                } catch (error) {
                    console.warn('MetaHotels Brevo: Elementor hooks initialization failed:', error);
                }
            } else {
                // Retry after a short delay if Elementor isn't ready yet
                setTimeout(initElementorHooks, 500);
            }
        }
        
        // Start Elementor hooks initialization
        initElementorHooks();
        
        // Initialize on dynamic content load
        $(document).on('elementor/popup/show', function() {
            setTimeout(initBrevoForms, 100);
        });
        
        // Initialize on any dynamic content changes
        $(document).on('elementor/frontend/init', function() {
            setTimeout(initBrevoForms, 100);
        });
        
        // Fallback for Elementor popups - check periodically for new forms
        setInterval(function() {
            var newForms = $('.brevo-form').not('[data-initialized]');
            if (newForms.length > 0) {
                console.log('MetaHotels Brevo: Found ' + newForms.length + ' new forms via periodic check, initializing...');
                initBrevoForms();
            }
        }, 5000); // Increased interval to reduce spam
        
        // Initialize on AJAX content load
        $(document).on('ajaxComplete', function() {
            setTimeout(initBrevoForms, 100);
        });
        
        // Initialize on any DOM changes (for dynamic content)
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    setTimeout(initBrevoForms, 100);
                }
            });
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Add a global function for manual initialization if needed
        window.initMetaHotelsBrevoForms = initBrevoForms;
        
        // Initial debug logging (only once)
        console.log('MetaHotels Brevo: Script loaded successfully');
        console.log('MetaHotels Brevo: Found ' + $('.brevo-form').length + ' forms on page');
        
        // Additional Elementor compatibility - wait for Elementor to be fully loaded
        if (typeof elementorFrontend !== 'undefined') {
            console.log('MetaHotels Brevo: Elementor Frontend detected');
        } else {
            console.log('MetaHotels Brevo: Elementor Frontend not detected (this is normal if not using Elementor)');
        }
        
    })(jQuery);
    </script>
    <?php
}

// AJAX handler for form submission
add_action('wp_ajax_metahotels_brevo_subscribe', 'metahotels_brevo_subscribe_handler');
add_action('wp_ajax_nopriv_metahotels_brevo_subscribe', 'metahotels_brevo_subscribe_handler');

function metahotels_brevo_subscribe_handler() {
    try {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'metahotels_brevo_nonce')) {
            if (wp_doing_ajax()) {
                wp_send_json_error('Security check failed');
            } else {
                wp_die('Security check failed');
            }
        }
        
        // Get and validate data
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $whatsapp = isset($_POST['whatsapp']) ? sanitize_text_field($_POST['whatsapp']) : '';
        $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : '';
        $list_id = isset($_POST['list_id']) ? intval($_POST['list_id']) : 0;
        $redirect_url = isset($_POST['redirect_url']) ? sanitize_text_field($_POST['redirect_url']) : '';
        
        if (!is_email($email)) {
            $error_message = 'Invalid email address';
            if (wp_doing_ajax()) {
                wp_send_json_error($error_message);
            } else {
                wp_die($error_message);
            }
        }
        
        if (empty($whatsapp)) {
            $error_message = 'WhatsApp number is required';
            if (wp_doing_ajax()) {
                wp_send_json_error($error_message);
            } else {
                wp_die($error_message);
            }
        }
        
        if (empty($list_id)) {
            $error_message = 'Invalid list ID';
            if (wp_doing_ajax()) {
                wp_send_json_error($error_message);
            } else {
                wp_die($error_message);
            }
        }
        
        // Get API key
        $api_key = get_option('metahotels_brevo_api_key');
        if (empty($api_key)) {
            $error_message = 'Service configuration error';
            if (wp_doing_ajax()) {
                wp_send_json_error($error_message);
            } else {
                wp_die($error_message);
            }
        }
        
        // Prepare contact data
        $contact_data = array(
            'email' => $email,
            'listIds' => array($list_id),
            'updateEnabled' => true,
            'emailBlacklisted' => false,
            'smsBlacklisted' => false
        );
        
        // Add WhatsApp (now required)
        // Use the country code from the form, fallback to auto-detection if empty
        if (empty($country_code)) {
            $country_code = metahotels_get_country_from_ip();
        }
        
        // Clean and format the country code
        $country_code = trim($country_code);
        if (!preg_match('/^\+\d+$/', $country_code)) {
            $country_code = '+' . ltrim($country_code, '+');
        }
        
        // Clean and format the phone number
        $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
        
        $full_whatsapp = $country_code . $whatsapp;
        $contact_data['attributes'] = array(
            'WHATSAPP' => $full_whatsapp
        );
        
        // Send to Brevo API
        $response = wp_remote_post('https://api.brevo.com/v3/contacts', array(
            'headers' => array(
                'api-key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($contact_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $error_message = 'Connection error';
            if (wp_doing_ajax()) {
                wp_send_json_error($error_message);
            } else {
                wp_die($error_message);
            }
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 201 || $response_code === 200 || $response_code === 204) {
            $response_data = array('message' => 'Contact added successfully');
            if (!empty($redirect_url)) {
                $response_data['redirect_url'] = $redirect_url;
            }
            
            if (wp_doing_ajax()) {
                wp_send_json_success($response_data);
            } else {
                // For regular form submission, redirect or show success message
                if (!empty($redirect_url)) {
                    wp_redirect($redirect_url);
                    exit;
                } else {
                    wp_die('Contact added successfully!');
                }
            }
        } else {
            $error_data = json_decode($response_body, true);
            
            // Provide user-friendly error messages
            $error_message = 'Service temporarily unavailable';
            if ($error_data && is_array($error_data)) {
                if (isset($error_data['message'])) {
                    $api_message = $error_data['message'];
                    // Check for specific error types and provide user-friendly messages
                    if (strpos($api_message, 'WHATSAPP is already associated') !== false) {
                        $error_message = 'WHATSAPP is already associated with another Contact';
                    } elseif (strpos($api_message, 'Invalid email') !== false) {
                        $error_message = 'Invalid email format';
                    } elseif (strpos($api_message, 'duplicate') !== false) {
                        $error_message = 'This email is already registered';
                    } else {
                        $error_message = 'Unable to save your information';
                    }
                } elseif (isset($error_data['error'])) {
                    $error_message = 'Service error';
                } elseif (isset($error_data['code'])) {
                    $error_message = 'Service error (Code: ' . $error_data['code'] . ')';
                }
            } else {
                $error_message = 'Service temporarily unavailable';
            }
            
            if (wp_doing_ajax()) {
                wp_send_json_error($error_message);
            } else {
                wp_die($error_message);
            }
        }
        
    } catch (Exception $e) {
        $debug_mode = get_option('metahotels_brevo_debug_mode', false);
        if ($debug_mode) {
            error_log('Brevo AJAX Handler Error: ' . $e->getMessage());
        }
        
        $error_message = 'An unexpected error occurred. Please try again.';
        if (wp_doing_ajax()) {
            wp_send_json_error($error_message);
        } else {
            wp_die($error_message);
        }
    }
} 