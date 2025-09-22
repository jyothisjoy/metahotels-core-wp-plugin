<?php
// Register the countdown timer shortcode
add_shortcode('countdown_timer_25h', 'countdown_timer_25h_shortcode');

function countdown_timer_25h_shortcode() {
    // Set the reference reset timestamp (e.g., first start time)
    $start_reference = strtotime('2024-01-01 00:00:00'); // Fixed origin point
    $now = time();
    
    $cycle_seconds = 25 * 60 * 60; // 25 hours
    $time_since_start = $now - $start_reference;
    $time_until_next = $cycle_seconds - ($time_since_start % $cycle_seconds);
    
    // Break into h:m:s
    $hours = floor($time_until_next / 3600);
    $minutes = floor(($time_until_next % 3600) / 60);
    $seconds = $time_until_next % 60;

    // Output container with initial server values
    ob_start();
    ?>
    <div id="real-countdown" data-h="<?= $hours ?>" data-m="<?= $minutes ?>" data-s="<?= $seconds ?>" style="font-size: 24px; font-weight: bold;">Loading...</div>

    <script>
    (function(){
      const el = document.getElementById('real-countdown');
      let h = parseInt(el.dataset.h);
      let m = parseInt(el.dataset.m);
      let s = parseInt(el.dataset.s);

      function updateDisplay() {
        el.textContent = 
          `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
      }

      function tick() {
        if (--s < 0) {
          s = 59;
          if (--m < 0) {
            m = 59;
            if (--h < 0) {
              h = 0; m = 0; s = 0;
            }
          }
        }
        updateDisplay();
      }

      updateDisplay();
      setInterval(tick, 1000);
    })();
    </script>
    <?php
    return ob_get_clean();
} 