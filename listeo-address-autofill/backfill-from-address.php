<?php
if (!defined('ABSPATH')) exit;

/**
 * One-time backfill: fill _search_* fields from Google Geocoding using _address
 * Admin: Tools -> Backfill from _address
 * After finishing: remove require_once and delete this file.
 */

function hv_google_key(): string {
    $key = defined('GOOGLE_MAPS_API_KEY') ? (string) GOOGLE_MAPS_API_KEY : '';
    return trim($key);
}

function hv_comp(array $components, string $type, bool $short = false): string {
    foreach ($components as $c) {
        if (!empty($c['types']) && in_array($type, $c['types'], true)) {
            return $short ? ($c['short_name'] ?? '') : ($c['long_name'] ?? '');
        }
    }
    return '';
}

function hv_backfill_from_address(int $post_id, bool $force = false): array {
    if (get_post_type($post_id) !== 'listing') return ['ok'=>false,'msg'=>'Not listing'];

    $address = trim((string) get_post_meta($post_id, '_address', true));
    if ($address === '') return ['ok'=>false,'msg'=>'Missing _address'];

    $already = trim((string) get_post_meta($post_id, '_search_address_line_1', true));
    if (!$force && $already !== '') return ['ok'=>true,'msg'=>'Already filled; skipped'];

    $key = hv_google_key();
    if ($key === '') return ['ok'=>false,'msg'=>'Missing GOOGLE_MAPS_API_KEY'];

    $url = add_query_arg([
        'address' => $address,
        'key'     => $key,
        // Optional: bias results to UK
        // 'region'  => 'gb',
    ], 'https://maps.googleapis.com/maps/api/geocode/json');

    $res = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($res)) return ['ok'=>false,'msg'=>$res->get_error_message()];

    $body = json_decode((string) wp_remote_retrieve_body($res), true);
    $status = $body['status'] ?? '';
    if ($status !== 'OK' || empty($body['results'][0])) {
        return ['ok'=>false,'msg'=>'Geocode failed: ' . ($status ?: 'UNKNOWN')];
    }

    $r = $body['results'][0];
    $comps = $r['address_components'] ?? [];
    if (!is_array($comps)) $comps = [];

    $street_number = hv_comp($comps, 'street_number');
    $route         = hv_comp($comps, 'route');
    $line1         = trim(implode(' ', array_filter([$street_number, $route])));

    $subpremise    = hv_comp($comps, 'subpremise');
    $premise       = hv_comp($comps, 'premise');
    $neighborhood  = hv_comp($comps, 'neighborhood');
    $line2         = trim(implode(', ', array_filter([$subpremise ?: $premise, $neighborhood])));

    // UK-friendly “town”
    $town     = hv_comp($comps, 'postal_town')
             ?: hv_comp($comps, 'sublocality_level_1')
             ?: hv_comp($comps, 'sublocality');

    $city     = hv_comp($comps, 'locality');
    $county   = hv_comp($comps, 'administrative_area_level_2');
    $postcode = hv_comp($comps, 'postal_code');
    $country  = hv_comp($comps, 'country');

    $formatted = trim((string)($r['formatted_address'] ?? ''));

    // Fallback if street_number/route not provided
    if ($line1 === '') $line1 = $formatted ?: $address;

    update_post_meta($post_id, '_search_address_line_1', $line1);
    update_post_meta($post_id, '_search_address_line_2', $line2);
    update_post_meta($post_id, '_search_town', $town);
    update_post_meta($post_id, '_search_city', $city);
    update_post_meta($post_id, '_search_postcode', $postcode);
    update_post_meta($post_id, '_search_county', $county);
    update_post_meta($post_id, '_search_country', $country);

    // Optional: also store lat/lng
    $lat = $r['geometry']['location']['lat'] ?? null;
    $lng = $r['geometry']['location']['lng'] ?? null;
    if ($lat !== null && $lng !== null) {
        update_post_meta($post_id, '_geolocation_lat',  (string)$lat);
        update_post_meta($post_id, '_geolocation_long', (string)$lng);
    }

    return ['ok'=>true,'msg'=>'Filled'];
}

add_action('admin_menu', function () {
    add_management_page(
        'Backfill from _address',
        'Backfill from _address',
        'manage_options',
        'hv-backfill-address',
        'hv_backfill_address_page'
    );
});

function hv_backfill_address_page() {
    if (!current_user_can('manage_options')) return;

    $ran = false; $done = 0; $skipped = 0; $failed = 0;
    $messages = [];

    if (isset($_POST['hv_run']) && check_admin_referer('hv_backfill_address')) {
        $limit = max(1, min(200, (int) ($_POST['limit'] ?? 25)));
        $force = !empty($_POST['force']);

        $meta_query = [
            ['key' => '_address', 'compare' => 'EXISTS'],
        ];

        if (!$force) {
            // only those missing _search_address_line_1
            $meta_query[] = ['key' => '_search_address_line_1', 'compare' => 'NOT EXISTS'];
        }

        $q = new WP_Query([
            'post_type'      => 'listing',
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'meta_query'     => $meta_query,
        ]);

        foreach ($q->posts as $id) {
            $r = hv_backfill_from_address((int)$id, $force);
            if (!empty($r['ok'])) {
                if (($r['msg'] ?? '') === 'Already filled; skipped') $skipped++;
                else $done++;
            } else {
                $failed++;
                $messages[] = 'ID ' . (int)$id . ': ' . ($r['msg'] ?? 'failed');
            }
            // be nice to quota
            usleep(150000); // 150ms
        }

        $ran = true;
    }

    ?>
    <div class="wrap">
        <h1>Backfill from <code>_address</code></h1>
        <p>Reads <code>_address</code> for each listing, calls Google Geocoding, fills <code>_search_*</code> fields.</p>

        <?php if ($ran): ?>
            <div class="notice notice-success">
                <p>Updated: <strong><?php echo (int)$done; ?></strong> |
                   Skipped: <strong><?php echo (int)$skipped; ?></strong> |
                   Failed: <strong><?php echo (int)$failed; ?></strong></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($messages)): ?>
            <div class="notice notice-warning">
                <p><strong>Some failures:</strong></p>
                <ul style="margin-left:20px;list-style:disc;">
                    <?php foreach ($messages as $m): ?>
                        <li><?php echo esc_html($m); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post">
            <?php wp_nonce_field('hv_backfill_address'); ?>
            <table class="form-table">
                <tr>
                    <th>Batch size</th>
                    <td><input type="number" name="limit" value="25" min="1" max="200"></td>
                </tr>
                <tr>
                    <th>Force overwrite</th>
                    <td><label><input type="checkbox" name="force" value="1"> refill even if already filled</label></td>
                </tr>
            </table>
            <p><button class="button button-primary" type="submit" name="hv_run" value="1">Run batch</button></p>
        </form>

        <p><strong>Run multiple times</strong> until “Updated” becomes 0.</p>
    </div>
    <?php
}
