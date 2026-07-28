<?php
// Brevo Form Shortcode - Elementor Compatible Version
// Version: 2.9.0

if (!defined('ABSPATH')) {
    exit;
}

// Register shortcode
add_shortcode('brevo_form', 'metahotels_brevo_form_shortcode');

// Global flag to track if any Brevo form is used on this page
global $metahotels_brevo_form_used;
if (!isset($metahotels_brevo_form_used)) {
    $metahotels_brevo_form_used = false;
}

if (!function_exists('metahotels_brevo_shortcode_log')) {
    function metahotels_brevo_shortcode_log($message) {
        static $debug_enabled = null;
        if ($debug_enabled === null) {
            $debug_enabled = (bool) get_option('metahotels_brevo_debug_mode', false);
        }

        if (!$debug_enabled) {
            return;
        }

        if (function_exists('metahotels_brevo_log')) {
            metahotels_brevo_log('Form: ' . (string) $message);
            return;
        }

        error_log('[MetaHotels Brevo Form] ' . (string) $message);
    }
}

/**
 * Redact an IP address for safe logging (keeps only the network portion).
 * Prevents full visitor IPs (PII) from being written to the debug log.
 */
if (!function_exists('metahotels_brevo_mask_ip')) {
    function metahotels_brevo_mask_ip($ip) {
        $ip = (string) $ip;
        if ($ip === '') {
            return 'unknown';
        }
        if (strpos($ip, ':') !== false) {
            // IPv6: keep only the first hextet.
            $blocks = explode(':', $ip);
            return $blocks[0] . ':***';
        }
        $octets = explode('.', $ip);
        if (count($octets) === 4) {
            return $octets[0] . '.' . $octets[1] . '.x.x';
        }
        return '***';
    }
}

/**
 * HMAC secret used to sign the anti-bot form-render timestamp. Reuses the
 * site AUTH_KEY (same approach as the signed registration cookie).
 */
if (!function_exists('metahotels_brevo_form_ts_secret')) {
    function metahotels_brevo_form_ts_secret() {
        return defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    }
}

/**
 * Produce a signed "form rendered at" token embedded in the form. Combined
 * with a minimum-age check on submit, this cheaply raises the cost of scripted
 * submissions (bots that post the instant they scrape the form are rejected).
 */
if (!function_exists('metahotels_brevo_sign_form_ts')) {
    function metahotels_brevo_sign_form_ts() {
        $ts  = time();
        $sig = hash_hmac('sha256', 'brevo_form_ts|' . $ts, metahotels_brevo_form_ts_secret());
        return base64_encode($ts . '|' . $sig);
    }
}

/**
 * Verify the signed form timestamp. Returns true only when the signature is
 * valid AND the form was on screen for at least the minimum age and not longer
 * than the maximum age. Both bounds are filterable; the max is generous so
 * full-page caching does not reject legitimate visitors.
 */
if (!function_exists('metahotels_brevo_verify_form_ts')) {
    function metahotels_brevo_verify_form_ts($value) {
        if (!is_string($value) || $value === '') {
            return false;
        }
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }
        $parts = explode('|', $decoded);
        if (count($parts) !== 2) {
            return false;
        }
        list($ts, $sig) = $parts;
        $ts = (int) $ts;
        if ($ts <= 0) {
            return false;
        }
        $expected = hash_hmac('sha256', 'brevo_form_ts|' . $ts, metahotels_brevo_form_ts_secret());
        if (!hash_equals($expected, (string) $sig)) {
            return false;
        }
        $age     = time() - $ts;
        $min_age = (int) apply_filters('metahotels_brevo_min_form_age', 2);            // seconds
        $max_age = (int) apply_filters('metahotels_brevo_max_form_age', DAY_IN_SECONDS);
        if ($age < $min_age || $age > $max_age) {
            return false;
        }
        return true;
    }
}

/**
 * Sign the redirect URL the shortcode was configured with.
 *
 * The destination has to travel to the browser and back in a hidden field, so
 * the submitter can rewrite it before posting. The signature proves the value
 * came from the shortcode, which is what lets an author-chosen off-site target
 * (a booking engine on its own domain, say) be honoured without turning the
 * form into an open redirect.
 */
if (!function_exists('metahotels_brevo_sign_redirect')) {
    function metahotels_brevo_sign_redirect($url) {
        $url = (string) $url;
        if ($url === '') {
            return '';
        }
        $sig = hash_hmac('sha256', 'brevo_redirect|' . $url, metahotels_brevo_form_ts_secret());
        return base64_encode($url . '|' . $sig);
    }
}

/**
 * Recover the signed redirect URL, or '' when the value is missing, malformed
 * or tampered with.
 */
if (!function_exists('metahotels_brevo_verify_redirect')) {
    function metahotels_brevo_verify_redirect($token) {
        if (!is_string($token) || $token === '') {
            return '';
        }
        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return '';
        }
        // Split on the LAST separator: a query string may itself contain '|'.
        $pos = strrpos($decoded, '|');
        if ($pos === false) {
            return '';
        }
        $url      = substr($decoded, 0, $pos);
        $sig      = substr($decoded, $pos + 1);
        $expected = hash_hmac('sha256', 'brevo_redirect|' . $url, metahotels_brevo_form_ts_secret());
        if (!hash_equals($expected, $sig)) {
            return '';
        }
        return $url;
    }
}

/**
 * Turn the posted redirect field into a URL that is safe to send a visitor to.
 *
 * A valid signature authorises that URL's host for this request only, so
 * wp_validate_redirect() still runs and still enforces the scheme allowlist —
 * javascript:, data: and friends are rejected however well signed they are.
 * An unsigned value (a page cached before this change, or a tampered one) is
 * treated as untrusted and falls back to same-site-only.
 */
if (!function_exists('metahotels_brevo_resolve_redirect')) {
    function metahotels_brevo_resolve_redirect($posted) {
        if (!is_string($posted) || $posted === '') {
            return '';
        }

        $signed = metahotels_brevo_verify_redirect($posted);

        if ($signed === '') {
            // Unsigned: a page cached before this change, or tampering. Only
            // entertain something actually shaped like a URL — anything else
            // reads as a relative path and would land the visitor on a 404 here
            // rather than showing the thank-you message.
            if (!preg_match('#^(?:https?://|/)#i', $posted)) {
                return '';
            }
            return wp_validate_redirect(esc_url_raw($posted), '');
        }

        $url = esc_url_raw($signed);

        // A signature means the shortcode author picked this destination, so an
        // off-site one is honoured. Sites that would rather keep every redirect
        // on this domain can return false here.
        if (apply_filters('metahotels_brevo_allow_external_redirect', true, $url)) {
            $host = wp_parse_url($url, PHP_URL_HOST);
            if (!empty($host)) {
                // Also covers the non-AJAX wp_safe_redirect() branch further down.
                add_filter('allowed_redirect_hosts', function ($hosts) use ($host) {
                    $hosts[] = $host;
                    return $hosts;
                });
            }
        }

        return wp_validate_redirect($url, '');
    }
}

function metahotels_brevo_form_shortcode($atts) {
    global $metahotels_brevo_form_used;
    $metahotels_brevo_form_used = true;
    $debug_mode = (bool) get_option('metahotels_brevo_debug_mode', false);
    
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
        
        // Prefill country code from settings as a fallback.
        $country_code = get_option('metahotels_brevo_default_country', '+91');
        if (function_exists('metahotels_sanitize_calling_code')) {
            $country_code = metahotels_sanitize_calling_code($country_code);
        } else {
            $country_code = preg_match('/^\+\d{1,4}$/', (string) $country_code) ? (string) $country_code : '+91';
        }
        
        // Get redirect URL from shortcode attributes only
        $redirect_url = !empty($atts['redirect_url']) ? $atts['redirect_url'] : '';
        
        // reCAPTCHA v3 settings
        $recaptcha_site_key = get_option('metahotels_brevo_recaptcha_site_key', '');
        
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
        $output = '<div class="brevo-form-container" data-form-id="' . esc_attr($form_id) . '" data-debug-mode="' . ($debug_mode ? '1' : '0') . '" data-recaptcha-site-key="' . esc_attr($recaptcha_site_key) . '">';
        $output .= '<form id="' . esc_attr($form_id) . '" class="brevo-form" data-ajax-url="' . esc_url(admin_url('admin-ajax.php')) . '">';
        $output .= '<input type="hidden" name="action" value="metahotels_brevo_subscribe">';
        $output .= '<input type="hidden" name="list_id" value="' . esc_attr($atts['list_id']) . '">';
        // Signed, not raw: see metahotels_brevo_sign_redirect().
        $output .= '<input type="hidden" name="redirect_url" value="' . esc_attr(metahotels_brevo_sign_redirect($redirect_url)) . '">';
        $output .= '<input type="hidden" name="nonce" value="' . esc_attr(wp_create_nonce('metahotels_brevo_nonce')) . '">';
        // Signed render timestamp for the anti-bot minimum-age check on submit.
        $output .= '<input type="hidden" name="form_ts" value="' . esc_attr(metahotels_brevo_sign_form_ts()) . '">';

        // Honeypot field (hidden from users, visible to bots)
        $output .= '<div style="position: absolute; left: -5000px; top: -5000px; opacity: 0; pointer-events: none;">';
        $output .= '<label for="website_url_' . $form_id . '">Website</label>';
        $output .= '<input type="text" name="website_url" id="website_url_' . $form_id . '" tabindex="-1" autocomplete="off">';
        $output .= '</div>';
        
        // Email field
        $output .= '<div>';
        $output .= '<input type="email" name="email" id="email_' . $form_id . '" placeholder="Email Address *" required>';
        $output .= '</div>';
        
        // WhatsApp field
        $output .= '<div>';
        $output .= '<div class="whatsapp-group">';
        $output .= '<input type="text" name="country_code" id="country_code_' . $form_id . '" value="' . esc_attr($country_code) . '" placeholder="Code" class="country-code-input" maxlength="4" pattern="\+[0-9]{1,3}" required>';
        $output .= '<input type="tel" name="whatsapp" id="whatsapp_' . $form_id . '" placeholder="WhatsApp Number *" required>';
        $output .= '</div>';
        $output .= '</div>';
        
        // reCAPTCHA v3 token field (will be filled on submit if enabled)
        if (!empty($recaptcha_site_key)) {
            $output .= '<input type="hidden" name="g-recaptcha-response" value="">';
        }
        
        // Submit button
        $output .= '<button type="submit">' . esc_html($atts['button_text']) . '</button>';
        
        $output .= '</form>';
        $output .= '</div>';
        
        return $output;
        
    } catch (Exception $e) {
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('Brevo Form Shortcode Error: ' . $e->getMessage());
        }
        return '<p style="color: red;">Error: Unable to load form. Please try again later.</p>';
    }
}

// Add script to footer to avoid conflicts - with better guards
add_action('wp_footer', 'metahotels_brevo_form_scripts', 99);

function metahotels_brevo_form_scripts() {
    global $metahotels_brevo_form_used;
    
    // Load reCAPTCHA v3 site key
    $recaptcha_site_key = get_option('metahotels_brevo_recaptcha_site_key', '');
    
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
    
    // If no forms detected anywhere, bail - do not load scripts or reCAPTCHA SDK.
    // For Elementor popup support: place the [brevo_form] shortcode somewhere on
    // the page (e.g. a hidden section) or use a page-level template that ensures
    // $metahotels_brevo_form_used is set before wp_footer fires.
    if (!$has_brevo_form) {
        return;
    }
    
    // Mark as loaded to prevent multiple executions
    $script_loaded = true;
    
    // Output reCAPTCHA script and badge styling if configured
    if (!empty($recaptcha_site_key)) {
        ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr($recaptcha_site_key); ?>"></script>
        <style>
            /* Move reCAPTCHA v3 badge inline under the Brevo form */
            .brevo-form-container .grecaptcha-badge {
                position: static !important;
                margin-top: 10px;
                box-shadow: none !important;
                -webkit-box-shadow: none !important;
            }
        </style>
        <?php
    }
    
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
            return;
        }
        
        // Mark as initialized immediately to prevent multiple executions
        window.metahotelsBrevoInitialized = true;
        
        var recaptchaSiteKey = '<?php echo esc_js($recaptcha_site_key); ?>';
        var globalDebugMode = $('.brevo-form-container[data-debug-mode="1"]').length > 0;
        var initTimer = null;

        function scheduleInit(delay) {
            if (initTimer) {
                clearTimeout(initTimer);
            }
            initTimer = setTimeout(function() {
                initTimer = null;
                initBrevoForms();
            }, delay || 100);
        }
        
        // IP-based country calling code detection using ipapi (via server-side AJAX)
        var brevoCountryCode = null;
        var brevoCountryCodeRequestStarted = false;
        var brevoCountryCodeRequestFailed = false;
        
        function loadBrevoCountryCode(callback) {
            // If we already have a code, return it immediately
            if (brevoCountryCode !== null) {
                if (typeof callback === 'function') {
                    callback(brevoCountryCode);
                }
                return;
            }
            
            // If a previous request failed, don't retry immediately
            if (brevoCountryCodeRequestFailed) {
                if (typeof callback === 'function') {
                    callback(null);
                }
                return;
            }
            
            // If another request is already in progress, wait for it
            if (brevoCountryCodeRequestStarted) {
                var checkInterval = setInterval(function() {
                    if (brevoCountryCode !== null || brevoCountryCodeRequestFailed) {
                        clearInterval(checkInterval);
                        if (typeof callback === 'function') {
                            callback(brevoCountryCode);
                        }
                    }
                }, 200);
                
                // Timeout after 5 seconds
                setTimeout(function() {
                    clearInterval(checkInterval);
                    if (typeof callback === 'function') {
                        callback(null);
                    }
                }, 5000);
                return;
            }
            
            brevoCountryCodeRequestStarted = true;
            
            // Get debug mode from container
            var $container = $('.brevo-form-container').first();
            var debugMode = $container.length > 0 && ($container.data('debug-mode') === 1 || $container.data('debug-mode') === '1');
            
            var nonce = $container.find('input[name="nonce"]').val();
            
            if (debugMode) {
                console.log('MetaHotels Brevo: Loading country code from IP...');
            }
            
            // Call our server-side AJAX handler which will get IP and call ipapi.com
            $.ajax({
                url: '<?php echo esc_js(esc_url_raw(admin_url('admin-ajax.php'))); ?>',
                type: 'POST',
                data: {
                    action: 'metahotels_brevo_get_country_code',
                    nonce: nonce
                },
                dataType: 'json',
                timeout: 10000 // 10 second timeout
            }).done(function(response) {
                brevoCountryCodeRequestStarted = false;
                
                if (response && response.success && response.data && response.data.country_code) {
                    brevoCountryCode = response.data.country_code;
                    if (debugMode) {
                        console.log('MetaHotels Brevo: Country code detected: ' + brevoCountryCode);
                    }
                } else {
                    // API key not configured or other error
                    var errorMsg = (response && response.data && response.data.message) ? response.data.message : 'Country code detection unavailable';
                    if (debugMode) {
                        console.warn('MetaHotels Brevo: Country code detection failed:', errorMsg);
                    }
                    brevoCountryCodeRequestFailed = true;
                }
                
                if (typeof callback === 'function') {
                    callback(brevoCountryCode);
                }
            }).fail(function(xhr, status, error) {
                brevoCountryCodeRequestStarted = false;
                brevoCountryCodeRequestFailed = true;
                
                var errorMessage = 'Failed to detect country code';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMessage = xhr.responseJSON.data.message;
                } else if (status === 'timeout') {
                    errorMessage = 'Country code detection timed out';
                } else if (status === 'error') {
                    errorMessage = 'Network error while detecting country code';
                }
                
                if (debugMode) {
                    console.warn('MetaHotels Brevo: Country code detection failed:', {
                        status: status,
                        error: error,
                        message: errorMessage,
                        response: xhr.responseText
                    });
                } else {
                    // Only log warning in non-debug mode, don't spam console
                    console.warn('MetaHotels Brevo: ' + errorMessage + '. Form will still work - user can enter country code manually.');
                }
                
                if (typeof callback === 'function') {
                    callback(null);
                }
            });
        }
        
        function applyBrevoCountryCodeToForm($form) {
            var $countryInput = $form.find('input[name="country_code"]');
            if ($countryInput.length === 0) {
                return;
            }
            
            // Do not overwrite if user already typed something
            if ($countryInput.val() && $countryInput.val().trim() !== '') {
                return;
            }
            
            // Get debug mode from container
            var $container = $form.closest('.brevo-form-container');
            var debugMode = $container.length > 0 && ($container.data('debug-mode') === 1 || $container.data('debug-mode') === '1');
            
            loadBrevoCountryCode(function(code) {
                if (!code) {
                    // Country code detection failed, but form still works
                    // User can manually enter their country code
                    if (debugMode) {
                        console.log('MetaHotels Brevo: Country code not auto-filled. User can enter manually.');
                    }
                    return;
                }
                
                // Only set if still empty (user might have typed while we were loading)
                if (!$countryInput.val() || $countryInput.val().trim() === '') {
                    $countryInput.val(code);
                    if (debugMode) {
                        console.log('MetaHotels Brevo: Auto-filled country code: ' + code);
                    }
                } else if (debugMode) {
                    console.log('MetaHotels Brevo: Country code field already has value, not overwriting.');
                }
            });
        }
        
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
                
                // Auto-format country code field and restrict input
                $form.find('input[name="country_code"]').on('input keypress', function(e) {
                    var value = $(this).val();
                    
                    // Block any non-numeric characters except + at the beginning
                    if (e.type === 'keypress') {
                        var char = String.fromCharCode(e.which);
                        // Allow + only at the beginning, numbers anywhere
                        if (char === '+' && value.length === 0) {
                            return true; // Allow + at the beginning
                        } else if (/[0-9]/.test(char)) {
                            return true; // Allow numbers
                        } else {
                            e.preventDefault(); // Block everything else
                            return false;
                        }
                    }
                    
                    // Auto-format: If user types without +, add it automatically
                    if (value && !value.startsWith('+') && /^\d/.test(value)) {
                        $(this).val('+' + value);
                    }
                    
                    // Enforce maximum length (4 characters: + and up to 3 digits)
                    if (value.length > 4) {
                        $(this).val(value.substring(0, 4));
                    }
                });
                
                // Auto-fill country code using ipapi (if empty)
                applyBrevoCountryCodeToForm($form);
                
                $form.on('submit', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Check if debug mode is enabled
                    var $container = $form.closest('.brevo-form-container');
                    var debugMode = $container.data('debug-mode') === 1 || $container.data('debug-mode') === '1';
                    
                    var email = $form.find('input[name="email"]').val();
                    var whatsapp = $form.find('input[name="whatsapp"]').val();
                    var countryCode = $form.find('input[name="country_code"]').val();
                    
                    if (debugMode) {
                        console.log('=== Brevo Form Submission Debug ===');
                        console.log('Form Data:', {
                            email: email,
                            whatsapp: whatsapp,
                            countryCode: countryCode,
                            listId: $form.find('input[name="list_id"]').val(),
                            redirectUrl: $form.find('input[name="redirect_url"]').val()
                        });
                    }
                    
                    if (!email || !whatsapp) {
                        if (debugMode) {
                            console.error('Brevo Form Error: Missing required fields', { email: email, whatsapp: whatsapp });
                        }
                        alert("Please fill in all required fields.");
                        return false;
                    }
                    
                    $submitBtn.text("Registering...").prop('disabled', true);
                    
                    function submitBrevoAjax() {
                        var formData = new FormData($form[0]);
                        
                        if (debugMode) {
                            console.log('Sending AJAX request to:', $form.data('ajax-url'));
                            var formDataObj = {};
                            for (var pair of formData.entries()) {
                                formDataObj[pair[0]] = pair[0] === 'nonce' ? '[HIDDEN]' : pair[1];
                            }
                            console.log('Form Data (sanitized):', formDataObj);
                        }
                        
                        $.ajax({
                            url: $form.data('ajax-url'),
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            success: function(response) {
                                if (debugMode) {
                                    console.log('Brevo AJAX Response:', response);
                                }
                                
                                if (response.success) {
                                    if (debugMode) {
                                        console.log('Brevo Form: Success', response.data);
                                    }
                                    if (response.data && response.data.redirect_url) {
                                        if (debugMode) {
                                            console.log('Redirecting to:', response.data.redirect_url);
                                        }
                                        window.location.href = response.data.redirect_url;
                                    } else {
                                        $form.html('<div style="background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px;"><h3>Thank you!</h3><p>You have been successfully subscribed to our newsletter.</p></div>');
                                    }
                                } else {
                                    var errorMessage = "Something went wrong. Please try again.";
                                    
                                    // Handle response.data - it could be a string or an object with a message property
                                    var errorData = response.data;
                                    var errorText = '';
                                    
                                    if (errorData) {
                                        if (typeof errorData === 'string') {
                                            errorText = errorData;
                                        } else if (typeof errorData === 'object' && errorData.message) {
                                            errorText = errorData.message;
                                        } else if (typeof errorData === 'object') {
                                            errorText = JSON.stringify(errorData);
                                        }
                                        
                                        if (debugMode) {
                                            console.error('Brevo Form Error:', errorData);
                                        }

                                        // Ensure errorText is always a string before using .includes()
                                        if (typeof errorText !== 'string') {
                                            errorText = String(errorText || '');
                                        }
                                        
                                        // Check error message content (errorText is now guaranteed to be a string)
                                        if (errorText.includes("Country code is required")) {
                                            errorMessage = "Please enter your country code (e.g., +1, +91, +44).";
                                        } else if (errorText.includes("Invalid country code format")) {
                                            errorMessage = "Invalid country code format. Please enter a valid country code (e.g., +1, +91, +44).";
                                        } else if (errorText.includes("Brevo")) {
                                            errorMessage = "Unable to save your information. Please try again later.";
                                        } else if (errorText.includes("Invalid email")) {
                                            errorMessage = "Please enter a valid email address.";
                                        } else if (errorText.includes("WhatsApp number is required")) {
                                            errorMessage = "Please enter your WhatsApp number.";
                                        } else if (errorText.includes("Security check failed")) {
                                            errorMessage = "Security verification failed. Please refresh the page and try again.";
                                        } else if (errorText.includes("API key not configured") || errorText.includes("Service configuration error")) {
                                            errorMessage = "Service temporarily unavailable. Please try again later.";
                                        } else if (errorText.includes("WHATSAPP is already associated")) {
                                            errorMessage = "This WhatsApp number is already registered. Please use a different number or contact support.";
                                        } else if (errorText.trim() !== '') {
                                            errorMessage = errorText;
                                        }
                                    }
                                    
                                    // Always restore button and show alert
                                    $submitBtn.text(originalButtonText).prop('disabled', false);
                                    alert(errorMessage);
                                }
                            },
                            error: function(xhr, status, error) {
                                if (debugMode) {
                                    console.error('Brevo AJAX Request Failed:', {
                                        status: status,
                                        error: error,
                                        statusCode: xhr.status,
                                        responseText: xhr.responseText,
                                        xhr: xhr
                                    });
                                }
                                
                                // Try to parse error response if available
                                var errorMessage = "Connection error. Please check your internet connection and try again.";
                                try {
                                    if (xhr.responseText) {
                                        var errorResponse = JSON.parse(xhr.responseText);
                                        if (errorResponse && errorResponse.data) {
                                            if (typeof errorResponse.data === 'string') {
                                                errorMessage = errorResponse.data;
                                            } else if (errorResponse.data.message) {
                                                errorMessage = errorResponse.data.message;
                                            }
                                        }
                                    }
                                } catch (e) {
                                    // If parsing fails, use default message
                                }
                                
                                // Always restore button and show alert
                                $submitBtn.text(originalButtonText).prop('disabled', false);
                                alert(errorMessage);
                            }
                        });
                    }
                    
                    // If reCAPTCHA is configured and available, execute it before submitting
                    if (recaptchaSiteKey && typeof grecaptcha !== 'undefined') {
                        if (debugMode) {
                            console.log('Executing reCAPTCHA v3 for Brevo form...');
                        }
                        grecaptcha.ready(function() {
                            grecaptcha.execute(recaptchaSiteKey, { action: 'brevo_form_submit' }).then(function(token) {
                                if (debugMode) {
                                    console.log('reCAPTCHA v3 token received');
                                }
                                var $tokenField = $form.find('input[name="g-recaptcha-response"]');
                                if ($tokenField.length === 0) {
                                    $tokenField = $('<input type="hidden" name="g-recaptcha-response" />').appendTo($form);
                                }
                                $tokenField.val(token);
                                submitBrevoAjax();
                            }).catch(function(error) {
                                if (debugMode) {
                                    console.error('reCAPTCHA v3 execution failed:', error);
                                }
                                $submitBtn.text(originalButtonText).prop('disabled', false);
                                alert("Security verification failed. Please refresh the page and try again.");
                            });
                        });
                    } else {
                        // Fallback: submit without reCAPTCHA
                        if (debugMode && recaptchaSiteKey) {
                            console.warn('reCAPTCHA site key configured but grecaptcha is not available.');
                        }
                        submitBrevoAjax();
                    }
                });
                
                $form.data('initialized', true);
            });
            
            if (newFormsFound > 0 && globalDebugMode) {
                console.log('MetaHotels Brevo: Successfully initialized ' + newFormsFound + ' new forms (total: ' + formsFound + ')');
            }
        }
        
        // Initialize on document ready
        $(document).ready(function() {
            scheduleInit(100);
        });
        
        // Initialize on Elementor frontend init (for popups) - with proper checks
        function initElementorHooks() {
            if (typeof elementorFrontend !== 'undefined' && elementorFrontend.hooks) {
                try {
                    elementorFrontend.hooks.addAction('frontend/element_ready/global', function() {
                        scheduleInit(100);
                    });
                    if (globalDebugMode) {
                        console.log('MetaHotels Brevo: Elementor hooks initialized successfully');
                    }
                } catch (error) {
                    if (globalDebugMode) {
                        console.warn('MetaHotels Brevo: Elementor hooks initialization failed:', error);
                    }
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
            scheduleInit(100);
        });
        
        // Initialize on any dynamic content changes
        $(document).on('elementor/frontend/init', function() {
            scheduleInit(100);
        });
        
        // Initialize on AJAX content load
        $(document).on('ajaxComplete', function() {
            scheduleInit(100);
        });
        
        // Initialize on any DOM changes (for dynamic content)
        var observer = new MutationObserver(function(mutations) {
            var shouldInit = false;
            mutations.forEach(function(mutation) {
                if (shouldInit || mutation.type !== 'childList' || mutation.addedNodes.length === 0) {
                    return;
                }

                for (var i = 0; i < mutation.addedNodes.length; i++) {
                    var node = mutation.addedNodes[i];
                    if (!node || node.nodeType !== 1) {
                        continue;
                    }

                    if (node.classList && node.classList.contains('brevo-form')) {
                        shouldInit = true;
                        break;
                    }

                    if (node.querySelector && node.querySelector('.brevo-form')) {
                        shouldInit = true;
                        break;
                    }
                }
            });

            if (shouldInit) {
                scheduleInit(100);
            }
        });
        
        // Start observing
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Add a global function for manual initialization if needed
        window.initMetaHotelsBrevoForms = initBrevoForms;
        
        // Initial debug logging (only once)
        if (globalDebugMode) {
            console.log('MetaHotels Brevo: Script loaded successfully');
            console.log('MetaHotels Brevo: Found ' + $('.brevo-form').length + ' forms on page');
        }
        
        // Additional Elementor compatibility - wait for Elementor to be fully loaded
        if (globalDebugMode) {
            if (typeof elementorFrontend !== 'undefined') {
                console.log('MetaHotels Brevo: Elementor Frontend detected');
            } else {
                console.log('MetaHotels Brevo: Elementor Frontend not detected (this is normal if not using Elementor)');
            }
        }
        
    })(jQuery);
    </script>
    <?php
}

// AJAX handler for getting country calling code from IP
add_action('wp_ajax_metahotels_brevo_get_country_code', 'metahotels_brevo_get_country_code_handler');
add_action('wp_ajax_nopriv_metahotels_brevo_get_country_code', 'metahotels_brevo_get_country_code_handler');

function metahotels_brevo_get_country_code_handler() {
    // Verify nonce
    if (!check_ajax_referer('metahotels_brevo_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }

    // Rate limiting: max 20 country-code lookups per IP per minute
    $rate_ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'brevo_cc_rate_' . md5($rate_ip);
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 20) {
        wp_send_json_error(array('message' => 'Too many requests. Please wait a moment and try again.'));
        return;
    }
    set_transient($rate_key, $attempts + 1, 60);

    // Get debug mode setting
    $debug_mode = get_option('metahotels_brevo_debug_mode', false);
    
    // Get API key from WordPress settings
    $api_key = get_option('metahotels_ipapi_api_key', '');
    
    // Check if API key is configured
    if (empty($api_key)) {
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: API key not configured');
        }
        wp_send_json_error(array('message' => 'IPAPI API key not configured'));
        return;
    }
    
    // Use only the real TCP connection IP - spoofable headers (HTTP_CLIENT_IP,
    // HTTP_X_FORWARDED_FOR, etc.) are excluded because any client can forge them.
    $user_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    
    // Validate IP format
    if (empty($user_ip) || !filter_var($user_ip, FILTER_VALIDATE_IP)) {
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: Could not detect valid IP address');
        }
        wp_send_json_error(array('message' => 'Could not detect IP address'));
        return;
    }
    
    // For local development, use a test IP
    if ($user_ip === '127.0.0.1' || $user_ip === '::1') {
        $user_ip = '134.201.250.155'; // Los Angeles, US
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: Using test IP for localhost: ' . $user_ip);
        }
    }
    
    // Check cache first
    $cache_key = 'ipapi_country_code_' . md5($user_ip);
    $cached_code = get_transient($cache_key);
    
    if ($cached_code !== false) {
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: Using cached country code for IP: ' . metahotels_brevo_mask_ip($user_ip));
        }
        wp_send_json_success(array('country_code' => $cached_code));
        return;
    }
    
    // Global daily budget: protect the paid ipapi.com quota against rotating-IP
    // abuse. Once the per-day cap is reached, serve the configured default
    // country code instead of calling the API. Keyed per UTC day so it resets
    // automatically. Filterable; set to 0 to disable the cap.
    $daily_budget = (int) apply_filters('metahotels_brevo_ipapi_daily_budget', 500);
    $budget_key   = 'brevo_ipapi_budget_' . gmdate('Ymd');
    $daily_used   = (int) get_transient($budget_key);
    if ($daily_budget > 0 && $daily_used >= $daily_budget) {
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: daily budget reached; serving default country code');
        }
        $default_code = get_option('metahotels_brevo_default_country', '+91');
        wp_send_json_success(array('country_code' => $default_code, 'source' => 'default'));
        return;
    }

    // Call ipapi.com API. The access_key must travel in the query string (ipapi
    // has no header auth); TLS protects it in transit and the full URL is never
    // logged (only the masked client IP is, and only in debug mode).
    $api_url = 'https://api.ipapi.com/api/' . urlencode($user_ip) . '?access_key=' . urlencode($api_key);

    if ($debug_mode) {
        metahotels_brevo_shortcode_log('IPAPI: Making API request for IP: ' . metahotels_brevo_mask_ip($user_ip));
    }

    // Count this call against the daily budget.
    set_transient($budget_key, $daily_used + 1, DAY_IN_SECONDS + HOUR_IN_SECONDS);

    $response = wp_remote_get($api_url, array(
        'timeout' => 10,
        'redirection' => 3,
        'sslverify' => true
    ));
    
    if (is_wp_error($response)) {
        $error_message = 'Country code detection unavailable';
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI Error: ' . $response->get_error_message());
        }
        wp_send_json_error(array('message' => $error_message));
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    if ($debug_mode) {
        metahotels_brevo_shortcode_log('IPAPI Response Code: ' . $response_code);
    }
    
    // Handle rate limiting (429 Too Many Requests)
    if ($response_code === 429) {
        $error_message = 'API rate limit exceeded. Please try again later.';
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: Rate limit exceeded');
        }
        wp_send_json_error(array('message' => $error_message));
        return;
    }
    
    if ($response_code !== 200) {
        $error_message = 'Country code detection unavailable';
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: API returned error code: ' . $response_code);
        }
        wp_send_json_error(array('message' => $error_message));
        return;
    }
    
    $data = json_decode($response_body, true);
    
    // Check for API errors in response
    if (isset($data['success']) && $data['success'] === false) {
        $error_msg = isset($data['error']['info']) ? $data['error']['info'] : 'Unknown API error';
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI API Error: ' . $error_msg);
        }
        wp_send_json_error(array('message' => 'Country code detection unavailable'));
        return;
    }
    
    // Extract calling code from location object
    if (isset($data['location']['calling_code']) && !empty($data['location']['calling_code'])) {
        $calling_code = '+' . $data['location']['calling_code'];
        
        // Cache the result for 24 hours
        set_transient($cache_key, $calling_code, 86400);
        
        if ($debug_mode) {
            metahotels_brevo_shortcode_log('IPAPI: Successfully retrieved country code: ' . $calling_code . ' for IP: ' . metahotels_brevo_mask_ip($user_ip));
        }

        wp_send_json_success(array('country_code' => $calling_code));
    } else {
        $error_message = 'Calling code not found in API response';
        if ($debug_mode) {
            // Log only that extraction failed; never dump the full geo payload (PII).
            metahotels_brevo_shortcode_log('IPAPI: ' . $error_message);
        }
        wp_send_json_error(array('message' => 'Country code detection unavailable'));
    }
}

// AJAX handler for form submission
add_action('wp_ajax_metahotels_brevo_subscribe', 'metahotels_brevo_subscribe_handler');
add_action('wp_ajax_nopriv_metahotels_brevo_subscribe', 'metahotels_brevo_subscribe_handler');

function metahotels_brevo_subscribe_handler() {
    // Rate limiting: max 5 form submissions per IP per minute
    $rate_ip  = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
    $rate_key = 'brevo_sub_rate_' . md5($rate_ip);
    $attempts = (int) get_transient($rate_key);
    if ($attempts >= 5) {
        if (wp_doing_ajax()) {
            wp_send_json_error(array('message' => 'Too many attempts. Please wait a moment and try again.'));
        } else {
            wp_die('Too many attempts. Please wait a moment and try again.');
        }
    }
    set_transient($rate_key, $attempts + 1, 60);

    // Global circuit breaker: caps total submissions per minute across ALL IPs
    // so a rotating-IP botnet cannot bypass the per-IP limit above. Filterable.
    $global_limit    = (int) apply_filters('metahotels_brevo_global_submit_limit', 60);
    if ($global_limit > 0) {
        $global_key      = 'brevo_sub_global_rate';
        $global_attempts = (int) get_transient($global_key);
        if ($global_attempts >= $global_limit) {
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => 'Service is busy. Please try again shortly.'));
            } else {
                wp_die('Service is busy. Please try again shortly.');
            }
        }
        set_transient($global_key, $global_attempts + 1, 60);
    }

    // Get debug mode setting
    $debug_mode = get_option('metahotels_brevo_debug_mode', false);
    
    // Prepare debug response data
    $debug_data = array();
    
    try {
        // reCAPTCHA settings (verification runs after nonce/honeypot checks).
        $recaptcha_secret_key = get_option('metahotels_brevo_recaptcha_secret_key', '');
        $recaptcha_score_threshold = floatval(get_option('metahotels_brevo_recaptcha_score_threshold', 0.5));
        if ($recaptcha_score_threshold < 0) {
            $recaptcha_score_threshold = 0;
        } elseif ($recaptcha_score_threshold > 1) {
            $recaptcha_score_threshold = 1;
        }
        
        // Log incoming request if debug mode is enabled
        if ($debug_mode) {
            $debug_data['request_time'] = current_time('mysql');
            // Log only field names to avoid exposing PII in the client-side response.
            $debug_data['post_data_keys'] = array_keys($_POST);
        }
        
        // Verify nonce
        if (!isset($_POST['nonce'])) {
            if ($debug_mode) {
                $debug_data['error'] = 'Security check failed - Nonce verification failed';
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => 'Security check failed'));
            } else {
                wp_die('Security check failed');
            }
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        if (!wp_verify_nonce($nonce, 'metahotels_brevo_nonce')) {
            if ($debug_mode) {
                $debug_data['error'] = 'Security check failed - Nonce verification failed';
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => 'Security check failed'));
            } else {
                wp_die('Security check failed');
            }
        }
        
        // Check Honeypot
        $honeypot = isset($_POST['website_url']) ? sanitize_text_field(wp_unslash($_POST['website_url'])) : '';
        if (!empty($honeypot)) {
            if ($debug_mode) {
                $debug_data['error'] = 'Bot detected: Honeypot field filled';
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            // Fail silently or with generic error to confuse bots
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => 'Submission failed'));
            } else {
                wp_die('Submission failed');
            }
        }

        // Anti-bot: reject submissions whose signed render timestamp is missing,
        // forged, or implausibly fast (see metahotels_brevo_verify_form_ts()).
        $form_ts = isset($_POST['form_ts']) ? sanitize_text_field(wp_unslash($_POST['form_ts'])) : '';
        if (!metahotels_brevo_verify_form_ts($form_ts)) {
            if ($debug_mode) {
                $debug_data['error'] = 'Form timing check failed';
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => 'Submission failed'));
            } else {
                wp_die('Submission failed');
            }
        }

        // Fail closed if the admin requires reCAPTCHA but no secret key is set,
        // so a misconfiguration can never silently disable bot protection.
        $recaptcha_required = (bool) get_option('metahotels_brevo_recaptcha_required', false);
        if ($recaptcha_required && empty($recaptcha_secret_key)) {
            if ($debug_mode) {
                $debug_data['error'] = 'reCAPTCHA required but secret key not configured';
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => 'Security check failed'));
            } else {
                wp_die('Security check failed');
            }
        }

        // Verify reCAPTCHA token if secret key is configured (always enforced
        // when a secret exists; additionally required when the toggle is on).
        if (!empty($recaptcha_secret_key)) {
            $recaptcha_token = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
            if ($debug_mode) {
                $debug_data['recaptcha'] = array(
                    'token_present' => !empty($recaptcha_token),
                    'score_threshold' => $recaptcha_score_threshold,
                );
            }
            if (empty($recaptcha_token)) {
                if ($debug_mode) {
                    $debug_data['recaptcha']['error'] = 'Missing reCAPTCHA token';
                    metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
                }
                if (wp_doing_ajax()) {
                    wp_send_json_error(array('message' => 'Security check failed'));
                } else {
                    wp_die('Security check failed');
                }
            }

            $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
            $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
                'body' => array(
                    'secret' => $recaptcha_secret_key,
                    'response' => $recaptcha_token,
                    'remoteip' => $remote_ip,
                ),
                'timeout' => 10,
                'redirection' => 3,
                'sslverify' => true,
            ));

            if (is_wp_error($verify_response)) {
                if ($debug_mode) {
                    $debug_data['recaptcha']['error'] = array(
                        'type' => 'WP_Error',
                        'message' => $verify_response->get_error_message(),
                        'code' => $verify_response->get_error_code(),
                    );
                    metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
                }
                if (wp_doing_ajax()) {
                    wp_send_json_error(array('message' => 'Security check failed'));
                } else {
                    wp_die('Security check failed');
                }
            }

            $verify_body = wp_remote_retrieve_body($verify_response);
            $verify_data = json_decode($verify_body, true);

            if ($debug_mode) {
                $debug_data['recaptcha']['response'] = $verify_data;
            }

            $recaptcha_success = isset($verify_data['success']) && $verify_data['success'];
            $recaptcha_score = isset($verify_data['score']) ? floatval($verify_data['score']) : 0;
            $recaptcha_action = isset($verify_data['action']) ? sanitize_text_field((string) $verify_data['action']) : '';
            $recaptcha_valid = $recaptcha_success && $recaptcha_score >= $recaptcha_score_threshold && $recaptcha_action === 'brevo_form_submit';

            if (!$recaptcha_valid) {
                if ($debug_mode) {
                    $debug_data['recaptcha']['validation'] = array(
                        'success' => $recaptcha_success,
                        'score' => $recaptcha_score,
                        'action' => $recaptcha_action,
                        'passed' => false,
                    );
                    metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
                }
                if (wp_doing_ajax()) {
                    wp_send_json_error(array('message' => 'Security check failed'));
                } else {
                    wp_die('Security check failed');
                }
            } elseif ($debug_mode) {
                $debug_data['recaptcha']['validation'] = array(
                    'success' => $recaptcha_success,
                    'score' => $recaptcha_score,
                    'action' => $recaptcha_action,
                    'passed' => true,
                );
            }
        }
        
        // Get and validate data
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $whatsapp = isset($_POST['whatsapp']) ? sanitize_text_field(wp_unslash($_POST['whatsapp'])) : '';
        $country_code = isset($_POST['country_code']) ? sanitize_text_field(wp_unslash($_POST['country_code'])) : '';
        $list_id = isset($_POST['list_id']) ? intval(wp_unslash($_POST['list_id'])) : 0;
        // Signed token, so sanitize_text_field rather than esc_url_raw here —
        // the latter would mangle base64. The open-redirect check lives in
        // metahotels_brevo_resolve_redirect().
        $raw_redirect   = isset($_POST['redirect_url']) ? sanitize_text_field(wp_unslash($_POST['redirect_url'])) : '';
        $redirect_url   = metahotels_brevo_resolve_redirect($raw_redirect);

        // A silently dropped redirect looks identical to no redirect at all,
        // which is exactly what made this hard to diagnose before.
        if ($debug_mode && $raw_redirect !== '' && $redirect_url === '') {
            metahotels_brevo_shortcode_log('Redirect dropped: signature invalid, or host not allowed and not same-site.');
        }

        if ($debug_mode) {
            $debug_data['form_data'] = array(
                'email_hash' => md5(strtolower($email)),
                'whatsapp_length' => strlen((string) $whatsapp),
                'country_code' => $country_code,
                'list_id' => $list_id
            );
        }
        
        if (!is_email($email)) {
            $error_message = 'Invalid email address';
            if ($debug_mode) {
                $debug_data['error'] = 'Invalid email address: ' . $email;
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }
        
        if (empty($whatsapp)) {
            $error_message = 'WhatsApp number is required';
            if ($debug_mode) {
                $debug_data['error'] = 'WhatsApp number is required';
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }
        
        // Validate list_id against the whitelist of lists saved in options.
        $allowed_lists = get_option('metahotels_brevo_lists', array());
        $allowed_ids   = array_map('intval', wp_list_pluck($allowed_lists, 'id'));
        if (empty($list_id) || !in_array($list_id, $allowed_ids, true)) {
            $error_message = 'Invalid list ID';
            if ($debug_mode) {
                $debug_data['error'] = 'Invalid list ID: ' . $list_id;
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }
        
        // Get API key
        $api_key = get_option('metahotels_brevo_api_key');
        if (empty($api_key)) {
            $error_message = 'Service configuration error';
            if ($debug_mode) {
                $debug_data['error'] = 'API key not configured';
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }
        
        if ($debug_mode) {
            $debug_data['api_key_status'] = empty($api_key) ? 'NOT SET' : 'SET (length: ' . strlen($api_key) . ')';
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
        // Validate country code - it's now required from user input
        if (empty($country_code)) {
            $error_message = 'Country code is required. Please enter your country code (e.g., +1, +91, +44).';
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }
        
        // Clean and format the country code
        $country_code = trim($country_code);
        if (!preg_match('/^\+\d+$/', $country_code)) {
            $country_code = '+' . ltrim($country_code, '+');
        }
        
        // Validate country code format
        if (!preg_match('/^\+\d{1,4}$/', $country_code)) {
            $error_message = 'Invalid country code format. Please enter a valid country code (e.g., +1, +91, +44).';
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }
        
        // Clean and format the phone number
        $whatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
        $whatsapp = is_string($whatsapp) ? $whatsapp : '';

        if (function_exists('metahotels_validate_whatsapp_number')) {
            $phone_errors = metahotels_validate_whatsapp_number($whatsapp, $country_code);
            if (!empty($phone_errors)) {
                $error_message = sanitize_text_field((string) $phone_errors[0]);
                if (wp_doing_ajax()) {
                    wp_send_json_error(array('message' => $error_message));
                } else {
                    wp_die($error_message);
                }
            }
        }
        
        $full_whatsapp = $country_code . $whatsapp;
        $contact_data['attributes'] = array(
            'WHATSAPP' => $full_whatsapp
        );
        
        if ($debug_mode) {
            $debug_data['contact_payload'] = array(
                'has_email' => !empty($contact_data['email']),
                'list_count' => count($contact_data['listIds']),
                'has_whatsapp' => !empty($full_whatsapp),
            );
        }
        
        // Send to Brevo API
        $api_url = 'https://api.brevo.com/v3/contacts';
        $request_body = wp_json_encode($contact_data);
        
        if ($debug_mode) {
            $debug_data['api_request'] = array(
                'url' => $api_url,
                'method' => 'POST',
                'payload_keys' => array_keys($contact_data)
            );
        }
        
        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'api-key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => $request_body,
            'timeout' => 20,
            'redirection' => 3,
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            $error_message = 'Connection error';
            if ($debug_mode) {
                $debug_data['api_error'] = array(
                    'type'    => 'WP_Error',
                    'message' => $response->get_error_message(),
                    'code'    => $response->get_error_code(),
                );
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($debug_mode) {
            // Log only the HTTP status code; never the raw Brevo response body
            // (it can contain contact PII).
            $debug_data['api_response'] = array(
                'code' => $response_code,
            );
        }

        if ($response_code === 201 || $response_code === 200 || $response_code === 204) {
            if ($debug_mode) {
                $debug_data['status'] = 'success';
            }
            
            if ($debug_mode) {
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            $response_data = array('message' => 'Contact added successfully');
            if (!empty($redirect_url)) {
                $response_data['redirect_url'] = $redirect_url;
            }
            
            // Set cryptographically signed cookie to track user for potential deletion on booking page
            $signed_cookie_value = metahotels_sign_brevo_cookie($email);
            if ($signed_cookie_value !== false) {
                setcookie('brevo_registered_email', $signed_cookie_value, array(
                    'expires'  => time() + (30 * 24 * 60 * 60),
                    'path'     => '/',
                    'secure'   => is_ssl(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ));
            }
            
            if (wp_doing_ajax()) {
                wp_send_json_success($response_data);
            } else {
                // For regular form submission, redirect or show success message
                if (!empty($redirect_url)) {
                    wp_safe_redirect($redirect_url);
                    exit;
                } else {
                    wp_die('Contact added successfully!');
                }
            }
        } else {
            $error_data = json_decode($response_body, true);

            if ($debug_mode) {
                $debug_data['status'] = 'error';
                // Keep only the Brevo error code for troubleshooting; omit the
                // message/body which may echo submitted contact details.
                $debug_data['api_error_code'] = (is_array($error_data) && isset($error_data['code'])) ? $error_data['code'] : null;
            }
            
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
                } elseif (isset($error_data['error']) || isset($error_data['code'])) {
                    // Do not reflect upstream provider error codes/details to the
                    // anonymous submitter; keep the message generic.
                    $error_message = 'Service temporarily unavailable';
                }
            } else {
                $error_message = 'Service temporarily unavailable';
            }
            
            if ($debug_mode) {
                metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
            }
            if (wp_doing_ajax()) {
                wp_send_json_error(array('message' => $error_message));
            } else {
                wp_die($error_message);
            }
        }

    } catch (Exception $e) {
        if ($debug_mode) {
            $debug_data['exception'] = array(
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
            );
            metahotels_brevo_shortcode_log('Brevo Subscribe Debug: ' . wp_json_encode($debug_data));
        }

        $error_message = 'An unexpected error occurred. Please try again.';

        if (wp_doing_ajax()) {
            wp_send_json_error(array('message' => $error_message));
        } else {
            wp_die($error_message);
        }
    }
} 
