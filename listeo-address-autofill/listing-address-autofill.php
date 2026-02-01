<?php
if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {

    // Only enqueue on the add-listing page (adjust if your slug differs)
    if (!is_page('add-listing')) return;

    $google_key = defined('GOOGLE_MAPS_API_KEY') ? (string) GOOGLE_MAPS_API_KEY : '';
    $google_key = trim($google_key);

    // If Listeo already enqueues Google Places, you can skip this block.
    // Leaving it in is fine, but avoid double-loading if you notice issues.
    if ($google_key !== '') {
        wp_enqueue_script(
            'scc-google-maps-places',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($google_key) . '&libraries=places',
            [],
            null,
            true
        );
    }

    wp_enqueue_script(
        'scc-listeo-address-autofill',
        get_stylesheet_directory_uri() . '/listeo-address-autofill/assets/js/frontend-autofill.js',
        $google_key !== '' ? ['scc-google-maps-places'] : [],
        filemtime(get_stylesheet_directory() . '/listeo-address-autofill/assets/js/frontend-autofill.js'),
        true
    );
});
