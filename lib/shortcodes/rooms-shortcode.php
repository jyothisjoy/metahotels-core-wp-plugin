<?php
// Register the shortcode
add_shortcode('metahotels_roomlist', 'metahotels_roomlist_shortcode');

// Shortcode function
function metahotels_roomlist_shortcode($atts) {
    // Extract the hotel ID from the shortcode attributes
    $hotel_id = isset($atts['hotel_id']) ? $atts['hotel_id'] : '';
    // Check if hotel ID is provided
    if (empty($hotel_id)) {
        return 'Please provide a hotel ID.';
    }

    // Generate a unique cache key based on hotel ID and date
    $currentDate = date('d/m/Y');
    $sanitizedDate = urlencode($currentDate);
    $cache_key = 'metahotels_rooms_' . $hotel_id . '_' . md5($currentDate);
    
    // Check for cached data
    $cached_output = get_transient($cache_key);
    if ($cached_output !== false) {
        return $cached_output;
    }

    // API URL with the hotel ID
    $api_url_room = 'https://api.mirai.com/MiraiWebService/roomInfo/' . $hotel_id;
    $api_url_price = 'https://api.mirai.com/MiraiWebService/availableRate/get?hotelId='. $hotel_id .'&checkin='. $sanitizedDate .'&nights=1&numAdults=2&numChildren=1';

    // Request headers
    $headers = array(
        'Accept' => '*/*',
        'Authorization' => 'Basic bWV0YW1vcnBob3NpczptM3Q0bW9ycGhvczFzNzZh',
    );

    // Perform the API request
    $response_api_room = wp_remote_get($api_url_room, array('headers' => $headers));
    $response_api_price = wp_remote_get($api_url_price, array('headers' => $headers));

    // Check for errors
    if (is_wp_error($response_api_room) || is_wp_error($response_api_price)) {
        return 'Error retrieving data.';
    }

    // Get the response body
    $body_api_room = wp_remote_retrieve_body($response_api_room);
    $body_api_price = wp_remote_retrieve_body($response_api_price);

    // Parse the JSON data
    $data_room = json_decode($body_api_room, true);
    $data_price = json_decode($body_api_price, true);

    // Check if the response contains rooms
    if (isset($data_room['rooms']) && is_array($data_room['rooms'])) {
        $output = '<section class="mh-room-section"><div class="mh-room-slider">';

        // Loop through each room
        foreach ($data_room['rooms'] as $room) {
            $output .= '<div class="mh-room">
            <div class="mh-room-image">
                <img src="' . esc_url($room['mainPhotoUrl']) . '" alt="' . esc_attr($room['title']) . '">
            </div>
            <div class="mh-room-content">
                <h4>'.esc_html($room['title']).'</h4><p>
				'.wp_kses_post($room['description']).'
				</p>';
                $netPrices = array(); // Initialize an empty array
                $currency = '';
                
                if (isset($data_price['availableRates'][$hotel_id]) && is_array($data_price['availableRates'][$hotel_id])) {
                    foreach ($data_price['availableRates'][$hotel_id] as $rate) {
                        if ($rate['roomTypeId'] == $room['roomId']) {
                            $netPrices[] = $rate['netPrice']; // Add the net price to the array
                        }
                        $currency = $rate['currency'];
                    }
                }
                
                if (!empty($netPrices)) {
                    $lowestRate = min($netPrices);
                    $highestRate = max($netPrices);
                
                    $output .= '<p class="price">Starting from <br>';
                    $output .= '<s>'.$highestRate.''.$currency.'</s><br>'.$lowestRate.' '.$currency;
                    $output .= '</p>';
                }
                $output .='<div class="mh-room-button-wrapper">
                            <a class="mh-room-button mi-be-book-btn" href="'. home_url() .'/bookingstep1.en.html">Book Now</a>
                        </div>
            </div>
        </div>';
        }

        $output .= '</div></section>';
        
        // Cache the output for 1 hour (3600 seconds)
        set_transient($cache_key, $output, 3600);
        
        return $output;
    }

    // If no rooms are found, return a message
    return 'No rooms available.';
}