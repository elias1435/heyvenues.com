<?php
add_action('wp_enqueue_scripts', 'listeo_enqueue_styles');
function listeo_enqueue_styles()
{
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css', array('bootstrap', 'font-awesome-5', 'font-awesome-5-shims', 'simple-line-icons', 'listeo-woocommerce'));
}
function remove_parent_theme_features() {}
add_action('after_setup_theme', 'remove_parent_theme_features', 10);




function listeo_menu_items_shortcode()
{
    global $post;
    if (! $post) return '';
    $post_id = $post->ID;
    $menus   = get_post_meta($post_id, '_menu', true);

    if (empty($menus) || !is_array($menus)) {
        return '<p>No pricing items found.</p>';
    }
    $label_map = [
        'min_spend'  => 'Min Spend',
        'per_person' => 'Per Person',
        'per_hour'   => 'Per Hour',
    ];
    $currency_abbr   = get_option('listeo_currency');
    $currency_symbol = (class_exists('Listeo_Core_Listing') && method_exists('Listeo_Core_Listing', 'get_currency_symbol'))
        ? Listeo_Core_Listing::get_currency_symbol($currency_abbr)
        : '£';
    $decimals = (int) get_option('listeo_number_decimals', 0);
    $out = '<div class="venue-menu">';
    foreach ($menus as $menu_group) {
        $group_title = !empty($menu_group['menu_title']) ? $menu_group['menu_title'] : '';
        if ($group_title !== '') {
            $out .= '<h3>' . esc_html($group_title) . '</h3>';
        }
        if (!empty($menu_group['menu_elements']) && is_array($menu_group['menu_elements'])) {
            $out .= '<ul>';
            foreach ($menu_group['menu_elements'] as $item) {
                $raw_name = isset($item['name']) ? trim((string)$item['name']) : '';
                $price    = isset($item['price']) ? trim((string)$item['price']) : '';
                if ($raw_name === '' && $price === '') continue;
                $slug  = sanitize_key($raw_name);
                $label = $label_map[$slug] ?? ucwords(str_replace('_', ' ', $raw_name));
                $price_out = '';
                if ($price !== '') {
                    if (is_numeric($price)) {
                        $price_out = $currency_symbol . number_format((float)$price, $decimals);
                    } else {
                        $price_out = $price;
                    }
                }
                $out .= '<li>';
                $out .= '<span class="vm-label">' . esc_html($label) . '</span>';
                if ($price_out !== '') {
                    $out .= ' <span class="vm-price">' . esc_html($price_out) . '</span>';
                }
                $out .= '</li>';
            }
            $out .= '</ul>';
        }
    }
    $out .= '</div>';
    return $out;
}
add_shortcode('venue_menu', 'listeo_menu_items_shortcode');


// Replace "Owner" with "Venue Owner" everywhere
add_filter('gettext', 'replace_owner_text', 20, 3);
function replace_owner_text($translated_text, $untranslated_text, $domain)
{
    if ($untranslated_text === 'Owner') {
        $translated_text = 'Venue Owner';
    }
    return $translated_text;
}
// Hide headers depending on login state
add_action('wp_head', 'conditional_header_css');
function conditional_header_css()
{
    if (is_user_logged_in()) {
        // User is logged in
?>
        <style>
            .main-header .login {
                display: none !important;
            }
        </style>
    <?php
    } else {
        // User is logged out
    ?>
        <style>
            .main-header .dashboard {
                display: none !important;
            }
        </style>
    <?php
    }
}


/* MD Code Started */

/* JSON */

// [listing_meta_debug] — prints ALL post meta as JSON on single listing (admins only)
add_shortcode('listing_meta_debug', function () {
    if (!is_singular('listing') || !current_user_can('manage_options')) return '';

    $meta = get_post_meta(get_the_ID());
    $flat = [];
    foreach ($meta as $k => $v) {
        $flat[$k] = count($v) === 1 ? $v[0] : $v;
    }

    ob_start(); ?>
    <pre class="listeo-debug-meta"
        style="background:#111;color:#0f0;padding:12px;white-space:pre-wrap;z-index:9999;overflow:auto">
<?php echo esc_html(json_encode($flat, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
    </pre>
    <script>
        try {
            console.log("Listing meta", <?php echo wp_json_encode($flat); ?>);
        } catch (e) {}
    </script>
<?php
    return ob_get_clean();
});


/* end JSON */


// REPLACE your existing lc_render_venue_menu() with this one
function lc_render_venue_menu($post_id = null)
{
    $post_id = $post_id ?: get_the_ID();

    // Label mapper (always human-friendly)
    $label_map = [
        'min_spend'   => 'Min Spend',
        'hire_fee'    => 'Hire Fee',
        'per_person'  => 'Per Person',
        'per_hour'    => 'Per Hour',
        'per_day'     => 'Per Day',
    ];
    $map_label = function ($slug) use ($label_map) {
        $slug = trim((string)$slug);
        return $label_map[$slug] ?? ucwords(str_replace('_', ' ', $slug));
    };

    // Currency formatting via Listeo settings
    $format_price = function ($raw) {
        if ($raw === '' || $raw === null) return '';
        $abbr = get_option('listeo_currency');
        $pos  = get_option('listeo_currency_postion'); // (sic)
        $dec  = (int) get_option('listeo_number_decimals', 2);
        $sym  = class_exists('Listeo_Core_Listing') ? Listeo_Core_Listing::get_currency_symbol($abbr) : '';
        if (is_numeric($raw)) {
            $num = number_format((float)$raw, $dec);
            return ($pos === 'before') ? ($sym . $num) : ($num . $sym);
        }
        return esc_html($raw);
    };

    $price = '';
    $label = '';

    // Call the actual shortcode callback to get raw data (not HTML list)
    global $shortcode_tags;
    if (isset($shortcode_tags['venue_menu']) && is_callable($shortcode_tags['venue_menu'])) {
        $res = call_user_func($shortcode_tags['venue_menu'], ['id' => $post_id], null, 'venue_menu');

        // If it’s an array, pick the first row and build our line
        if (is_array($res) && !empty($res)) {
            $row = reset($res);
            if (isset($row['price'])) $price = $format_price($row['price']);
            if (isset($row['label'])) $label = $map_label($row['label']);
        }
        // If it’s HTML, try to extract price & label very loosely (optional)
        elseif (is_string($res) && $res !== '') {
            // Price: first currency-like token
            if (preg_match('~([£$€]\s?\d[\d.,]*)~u', $res, $m)) $price = $m[1];
            // Label slug in markup, e.g. "per_person" -> Per Person
            if (preg_match('~\b(min_spend|hire_fee|per_person|per_hour|per_day)\b~', $res, $m)) $label = $map_label($m[1]);
        }
    }

    // Fallbacks if shortcode didn’t provide
    if ($price === '') {
        $alt = get_post_meta($post_id, '_classifieds_price', true);
        if ($alt !== '' && $alt !== null) $price = $format_price($alt);
    }
    if ($label === '') $label = $map_label('min_spend');

    // Return EXACT inner markup you asked for
    ob_start(); ?>
    from <span class="c-room-card__price_val"><?php echo $price !== '' ? $price : '—'; ?></span><br>
    <?php echo esc_html($label); ?>
<?php
    return trim(ob_get_clean());
}



/* Display the capacity in grid start */

// Map capacity -> label, value key, and optional toggle key
function lc_capacity_fields_map()
{
    return [
        'standing'  => [
            'label' => 'Standing',
            'value' => '_max_standing_capacity',
            'toggle' => '_capacity_and_layouts_standing',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Standing.png',
        ],
        'dining'    => [
            'label' => 'Dining',
            'value' => '_max_dining_capacity',
            'toggle' => '_capacity_and_layouts_dining',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Dining.png',
        ],
        'cabaret'   => [
            'label' => 'Cabaret',
            'value' => '_max_cabaret_capacity',
            'toggle' => '_capacity_and_layouts_cabaret',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Cabaret.png',
        ],
        'classroom' => [
            'label' => 'Classroom',
            'value' => '_max_classroom_capacity',
            'toggle' => '_capacity_and_layouts_classroom',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Classroom.png',
        ],
        'theatre'   => [
            'label' => 'Theatre',
            'value' => '_max_theatre_capacity',
            'toggle' => '_capacity_and_layouts_theatre',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Theatre.png',
        ],
        'ushaped'   => [
            'label' => 'U-Shaped',
            'value' => '_max_u_shaped_capacity',
            'toggle' => '_capacity_and_layouts_u_shaped',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/U-Shaped.png',
        ],
        'boardroom' => [
            'label' => 'Boardroom',
            'value' => '_max_boardroom_capacity',
            'toggle' => '_capacity_and_layouts_boardroom',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Boardroom.png',
        ],

        // Optional (will only show if you later wire a meta key):
        'guest'     => [
            'label' => 'Guest',
            'value' => '', // no meta to read right now
            'toggle' => '',
            'img'   => 'https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Min-Capacity-Preference-.jpg',
        ],
    ];
}


/**
 * Render up to $limit capacity pills for a listing.
 * Usage: echo lc_render_capacity_pills( get_the_ID(), 3 );
 */
function lc_render_capacity_pills($post_id = null, $limit = 3)
{
    $post_id = $post_id ?: get_the_ID();
    $map = lc_capacity_fields_map();

    $items = [];
    foreach ($map as $key => $conf) {
        // Respect optional toggle if provided
        if (!empty($conf['toggle'])) {
            $enabled = get_post_meta($post_id, $conf['toggle'], true);
            if ($enabled !== '' && $enabled !== 'Yes' && $enabled !== '1') continue;
        }

        $val = 0;
        if (!empty($conf['value'])) {
            $raw = get_post_meta($post_id, $conf['value'], true);
            $val = is_numeric($raw) ? (int)$raw : 0;
        }
        if ($val > 0) {
            $items[] = [
                'label' => $conf['label'],
                'value' => number_format_i18n($val),
                'img'   => isset($conf['img']) ? $conf['img'] : '',
            ];
        }
    }

    if (empty($items)) return '';

    $items = array_slice($items, 0, max(1, (int)$limit));

    ob_start(); ?>
    <div class="listing-capacities">
        <?php foreach ($items as $it): ?>
            <span class="capacity-pill">
                <?php if (!empty($it['img'])): ?>
                    <img class="capacity-icon" src="<?php echo esc_url($it['img']); ?>"
                        alt="<?php echo esc_attr($it['label']); ?>" width="18" height="18" loading="lazy" decoding="async" />
                <?php endif; ?>
                <span class="capacity-text">
                    <span class="capacity-label"><?php echo esc_html($it['label']); ?>:</span>
                    <span class="capacity-value"><?php echo esc_html($it['value']); ?></span>
                </span>
            </span>
        <?php endforeach; ?>
    </div>
<?php
    return trim(ob_get_clean());
}


/* end display the capacity in grid */

/* search filter start */

/**
 * --------------------------------------------------------
 * Listeo — unified filters (capacity, price, features, flags)
 * - Works for: page load, shortcode queries, and AJAX (admin-ajax)
 * - Updates results in place via inline JS (template-split-map.php)
 * --------------------------------------------------------
 */

/* =========================
 * A) CONFIG + SMALL HELPERS
 * ========================= */

// Capacity dropdown -> meta key map
function lc_capacity_meta_keys_map()
{
    return [
        'dining'     => '_max_dining_capacity',
        'standing'   => '_max_standing_capacity',
        'cabaret'    => '_max_cabaret_capacity',
        'classroom'  => '_max_classroom_capacity',
        'theatre'    => '_max_theatre_capacity',
        'ushaped'    => '_max_u_shaped_capacity',
        'boardroom'  => '_max_boardroom_capacity',
    ];
}

// Map ?sort=... → WP_Query sort rules
function lc_sort_rules_from_param($sort)
{
    $sort = sanitize_key($sort);
    $map = [
        // Standing capacity sorts
        'standing_desc'  => ['meta_key' => '_max_standing_capacity',  'order' => 'DESC'],
        'standing_asc'   => ['meta_key' => '_max_standing_capacity',  'order' => 'ASC'],

        // (Optional) Add more capacity types:
        'dining_desc'    => ['meta_key' => '_max_dining_capacity',    'order' => 'DESC'],
        'dining_asc'     => ['meta_key' => '_max_dining_capacity',    'order' => 'ASC'],
        'cabaret_desc'   => ['meta_key' => '_max_cabaret_capacity',   'order' => 'DESC'],
        'cabaret_asc'    => ['meta_key' => '_max_cabaret_capacity',   'order' => 'ASC'],
        'classroom_desc' => ['meta_key' => '_max_classroom_capacity', 'order' => 'DESC'],
        'classroom_asc'  => ['meta_key' => '_max_classroom_capacity', 'order' => 'ASC'],
        'theatre_desc'   => ['meta_key' => '_max_theatre_capacity',   'order' => 'DESC'],
        'theatre_asc'    => ['meta_key' => '_max_theatre_capacity',   'order' => 'ASC'],
        'ushaped_desc'   => ['meta_key' => '_max_u_shaped_capacity',  'order' => 'DESC'],
        'ushaped_asc'    => ['meta_key' => '_max_u_shaped_capacity',  'order' => 'ASC'],
        'boardroom_desc' => ['meta_key' => '_max_boardroom_capacity', 'order' => 'DESC'],
        'boardroom_asc'  => ['meta_key' => '_max_boardroom_capacity', 'order' => 'ASC'],
    ];
    return $map[$sort] ?? null;
}


// Meta flags (Yes/No) -> Label map (for UI + validation)
function lc_meta_flag_keys_map()
{
    return [
        '_capacity_and_layouts_dining'        => 'Dining layout',
        '_capacity_and_layouts_standing'      => 'Standing layout',
        '_capacity_and_layouts_cabaret'       => 'Cabaret layout',
        '_capacity_and_layouts_classroom'     => 'Classroom layout',
        '_capacity_and_layouts_theatre'       => 'Theatre layout',
        '_capacity_and_layouts_u_shaped'      => 'U-Shaped layout',
        '_capacity_and_layouts_boardroom'     => 'Boardroom layout',
        '_wedding_licence'                    => 'Wedding licence',
        '_clients_can_play_their_own_music'   => 'Own music allowed',
        '_clients_can_bring_their_own_dj'     => 'Bring your own DJ',
        '_space_has_noise_restriction'        => 'Noise restriction',
        '_wheelchair_accessible'              => 'Wheelchair accessible',
        '_accessible_toilet'                  => 'Accessible toilet',
        '_step_free_guest_entrance'           => 'Step-free entrance',
        '_accessible_parking_spot'            => 'Accessible parking',
        '_lift_to_all_floors'                 => 'Lift to all floors',
        '_cargo_lift'                         => 'Cargo lift',
        '_accommodation_is_available_on_site' => 'On-site accommodation',
        '_wi_fi'                              => 'Wi-Fi',
        '_projector'                          => 'Projector',
        '_flatscreen_tv'                      => 'Flatscreen TV',
        '_whiteboard'                         => 'Whiteboard',
        '_flipchart'                          => 'Flipchart',
        '_pa_system___speakers'               => 'PA / Speakers',
        '_conference_call_facilities'         => 'Conference call',
        '_air_conditioning'                   => 'Air conditioning',
        '_natural_light'                      => 'Natural light',
        '_storage_space'                      => 'Storage space',
        '_quiet_space'                        => 'Quiet space',
    ];
}

// Read helpers
// function lc_req_array($key) {
//   if (!isset($_REQUEST[$key])) return [];
//   $v = $_REQUEST[$key];
//   if (is_array($v)) {
//     return array_values(array_filter($v, fn($x) => $x !== '' && $x !== null));
//   }
//   return [$v];
// }
function lc_req_array($key)
{
    if (!isset($_REQUEST[$key])) return [];
    $v = $_REQUEST[$key];

    if (is_array($v)) {
        // drop empty strings/nulls
        $v = array_values(array_filter($v, function ($x) {
            return !($x === '' || $x === null || (is_string($x) && trim($x) === ''));
        }));
        return $v;
    }

    // scalar → trim and drop if empty
    $s = trim((string)$v);
    return ($s === '') ? [] : [$s];
}

function lc_read_capacity_params()
{
    $src = $_REQUEST;
    $type = isset($src['capacity_type']) ? sanitize_key($src['capacity_type']) : '';
    $min  = isset($src['capacity_min'])  ? (int)$src['capacity_min'] : 0;
    return [$type, $min];
}
function lc_read_price_params()
{
    $src = $_REQUEST;
    $min = isset($src['price_min']) ? (int)$src['price_min'] : null;
    $max = isset($src['price_max']) ? (int)$src['price_max'] : null;
    if ($min !== null && $min < 0) $min = 0;
    if ($max !== null && $max < 0) $max = 0;
    return [$min, $max];
}

// Expose query vars (nice for pretty URLs, not strictly required)
// --- Query vars (single, merged) ---
add_filter('query_vars', function ($vars) {
    foreach (
        [
            'capacity_type',
            'capacity_min',
            'price_min',
            'price_max',
            'listing_feature',
            'mf',
            'listing_category',   // taxonomy slugs (array ok)
            'keywords',           // free-text input (only used when NO category chosen)
            'location',           // simple LIKE on _address
        ] as $v
    ) {
        $vars[] = $v;
    }
    return $vars;
});


// allow the param
add_filter('query_vars', function ($vars) {
    $vars[] = 'listing_category';
    $vars[] = 'location_term';
    $vars[] = 'location_tax';
    return $vars;
});


// mirror for Listeo’s arg-based queries (shortcodes)
add_filter('listeo_core_listings_query_args', function ($args) {
    if (empty($args['post_type'])) $args['post_type'] = 'listing';
    if (!empty($_REQUEST['listing_category'])) {
        $slugs = (array) $_REQUEST['listing_category'];
        $tq = isset($args['tax_query']) && is_array($args['tax_query']) ? $args['tax_query'] : [];
        $tq[] = [
            'taxonomy' => 'listing_category',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_title', $slugs),
            'operator' => 'IN',
        ];
        $args['tax_query'] = $tq;
    }
    return $args;
});
add_filter('listeo_listings_query_args',  fn($a) => apply_filters('listeo_core_listings_query_args', $a));
add_filter('listeo_search_wp_query_args', fn($a) => apply_filters('listeo_core_listings_query_args', $a));


function lc_location_meta_or_query(string $needle): array
{
    $needle = trim($needle);

    $keys = [
        '_address',
        '_search_address_line_1',
        '_search_address_line_2',
        '_search_town',
        '_search_city',
        '_search_postcode',
        '_search_county',
        '_search_country',
    ];

    $or = ['relation' => 'OR'];
    foreach ($keys as $k) {
        $or[] = [
            'key'     => $k,
            'value'   => $needle,
            'compare' => 'LIKE',
        ];
    }
    return $or;
}

/* ===========================================
 * B) Unified Listeo args filter (shortcodes / internal queries)
 * =========================================== */
function lc_apply_all_filters_to_args($args)
{
    if (empty($args['post_type'])) $args['post_type'] = 'listing';
    $mq = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
    $tq = isset($args['tax_query'])  && is_array($args['tax_query'])  ? $args['tax_query']  : [];

    // Capacity
    if (function_exists('lc_capacity_meta_keys_map')) {
        [$ctype, $cmin] = lc_read_capacity_params();
        if (!empty($ctype) && $cmin > 0) {
            $map = lc_capacity_meta_keys_map();
            if (!empty($map[$ctype])) {
                $mq[] = ['key' => $map[$ctype], 'value' => $cmin, 'compare' => '>=', 'type' => 'NUMERIC'];
            }
        }
    }

    // Price
    [$pmin, $pmax] = lc_read_price_params();
    if ($pmin !== null) $mq[] = ['key' => '_price_max', 'value' => $pmin, 'compare' => '>=', 'type' => 'NUMERIC'];
    if ($pmax !== null) $mq[] = ['key' => '_price_min', 'value' => $pmax, 'compare' => '<=', 'type' => 'NUMERIC'];

    // listing_feature taxonomy
    $features = lc_req_array('listing_feature');
    if (!empty($features)) {
        $tq[] = [
            'taxonomy' => 'listing_feature',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_title', $features),
            'operator' => 'AND',
        ];
    }

    // category taxonomy
    $cats = lc_req_array('listing_category');
    $has_category = !empty($cats);
    if ($has_category) {
        $tq[] = [
            'taxonomy' => 'listing_category',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_title', $cats),
            'operator' => 'IN',
        ];
    }

    // meta flags
    if (function_exists('lc_meta_flag_keys_map')) {
        $all_flags = lc_meta_flag_keys_map();
        $mf_keys   = lc_req_array('mf');
        foreach ($mf_keys as $key) {
            if (!isset($all_flags[$key])) continue;
            $mq[] = ['key' => $key, 'value' => 'Yes', 'compare' => '='];
        }
    }

    // keywords -> s only if NO category
    if (!$has_category && !empty($_REQUEST['keywords'])) {
        $args['s'] = sanitize_text_field($_REQUEST['keywords']);
    }

    // location
    if (!empty($_REQUEST['location'])) {
        $needle = sanitize_text_field($_REQUEST['location']);
        $mq[] = ['key' => '_address', 'value' => $needle, 'compare' => 'LIKE'];
    }

    if (!empty($mq)) $args['meta_query'] = $mq;
    if (!empty($tq)) $args['tax_query']  = $tq;
    return $args;
}
add_filter('listeo_core_listings_query_args', 'lc_apply_all_filters_to_args', 999);
add_filter('listeo_listings_query_args',      'lc_apply_all_filters_to_args', 999);
add_filter('listeo_search_wp_query_args',     'lc_apply_all_filters_to_args', 999);


/* ============================================================
 * C) Unified pre_get_posts for any listing query (main, shortcode, AJAX)
 * ============================================================ */
add_action('pre_get_posts', function (WP_Query $q) {
    // Allow admin-ajax (is_admin true there), but skip real wp-admin screens
    if (is_admin() && ! defined('DOING_AJAX')) return;

    // Only touch Listing queries
    $pt = $q->get('post_type');
    $is_listing_query =
        ($pt === 'listing')
        || (is_array($pt) && in_array('listing', $pt, true))
        || (!$pt && ($q->get('tax_query') || $q->get('listeo_query')));

    if (!$is_listing_query) return;

    // Start with whatever is already on the query
    $mq = $q->get('meta_query');
    if (!is_array($mq)) $mq = [];
    $tq = $q->get('tax_query');
    if (!is_array($tq)) $tq = [];

    // 1) Capacity >= N
    if (function_exists('lc_capacity_meta_keys_map')) {
        [$ctype, $cmin] = lc_read_capacity_params();
        if (!empty($ctype) && $cmin > 0) {
            $map = lc_capacity_meta_keys_map();
            if (!empty($map[$ctype])) {
                $mq[] = ['key' => $map[$ctype], 'value' => $cmin, 'compare' => '>=', 'type' => 'NUMERIC'];
            }
        }
    }

    // 2) Price overlap (send only if actually provided)
    [$pmin, $pmax] = lc_read_price_params();
    if ($pmin !== null) $mq[] = ['key' => '_price_max', 'value' => $pmin, 'compare' => '>=', 'type' => 'NUMERIC'];
    if ($pmax !== null) $mq[] = ['key' => '_price_min', 'value' => $pmax, 'compare' => '<=', 'type' => 'NUMERIC'];

    // 3) Taxonomy: listing_feature (checkboxes)
    $features = lc_req_array('listing_feature'); // slugs
    if (!empty($features)) {
        $tq[] = [
            'taxonomy' => 'listing_feature',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_title', $features),
            'operator' => 'AND', // require all checked features
        ];
    }

    // 4) Meta flags (Yes/No)
    if (function_exists('lc_meta_flag_keys_map')) {
        $all_flags = lc_meta_flag_keys_map();
        $mf_keys   = lc_req_array('mf');
        foreach ($mf_keys as $key) {
            if (!isset($all_flags[$key])) continue;
            $mq[] = ['key' => $key, 'value' => 'Yes', 'compare' => '='];
        }
    }

    // 5) Category (slugs). If present, we won't add 's' for keywords (next step)
    $cats = lc_req_array('listing_category');
    $has_category = !empty($cats);
    if ($has_category) {
        $tq[] = [
            'taxonomy' => 'listing_category',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_title', $cats),
            'operator' => 'IN',
        ];
    }

    // 6) Keywords -> s (ONLY if NO category is set)
    if (!$has_category && !empty($_REQUEST['keywords'])) {
        $q->set('s', sanitize_text_field($_REQUEST['keywords']));
    }

    // 7) Location LIKE match
    if (!empty($_REQUEST['location'])) {
        $needle = sanitize_text_field($_REQUEST['location']);
        $mq[] = ['key' => '_address', 'value' => $needle, 'compare' => 'LIKE'];
    }

    if (!empty($mq)) $q->set('meta_query', $mq);
    if (!empty($tq)) $q->set('tax_query',  $tq);
    if (!$pt)        $q->set('post_type', 'listing');
}, 999);

// Helper: are any filters active?
function lc_has_active_filters(): bool
{
    $r = $_REQUEST;

    // capacity
    if (!empty($r['capacity_type']) && (int)($r['capacity_min'] ?? 0) > 0) return true;

    // price (any bound present)
    if (isset($r['price_min']) && $r['price_min'] !== '') return true;
    if (isset($r['price_max']) && $r['price_max'] !== '') return true;

    // taxonomies / flags
    if (!empty($r['listing_feature'])) return true;
    if (!empty($r['mf'])) return true;
    if (!empty($r['listing_category'])) return true;

    // text filters
    if (!empty($r['keywords'])) return true;
    if (!empty($r['location'])) return true;

    return false;
}

add_action('pre_get_posts', function (WP_Query $q) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!$q->is_main_query()) return;

    $pt = $q->get('post_type');
    $is_listing_query =
        ($pt === 'listing') ||
        (is_array($pt) && in_array('listing', $pt, true)) ||
        (!$pt && ($q->get('tax_query') || $q->get('listeo_query')));
    if (!$is_listing_query) return;

    // ---- SORT: only when filters are active ----
    if (lc_has_active_filters()) {
        $sort_param = get_query_var('sort');
        if (!$sort_param && isset($_REQUEST['sort'])) {
            $sort_param = sanitize_key($_REQUEST['sort']);
        }

        // OPTIONAL: set a default sort when filtering but no ?sort= provided
        if (!$sort_param) {
            $sort_param = 'standing_desc'; // or null to require explicit sort
        }

        if ($sort_param) {
            $rule = lc_sort_rules_from_param($sort_param);
            if ($rule) {
                $q->set('meta_key', $rule['meta_key']);
                $q->set('orderby',  'meta_value_num');
                $q->set('order',    $rule['order']);

                // keep posts without that meta but push them after those with the meta
                $mq = $q->get('meta_query');
                if (!is_array($mq)) $mq = [];
                $mq[] = ['key' => $rule['meta_key'], 'compare' => 'EXISTS', 'type' => 'NUMERIC'];
                $q->set('meta_query', $mq);
            }
        }
    } else {
        // no filters → leave theme’s default ordering
        $q->set('orderby', $q->get('orderby') ?: 'date');
    }
}, 999);



/* ============================================================
 * D) APPLY TO: Listeo's internal arg filters (belt & braces)
 * ============================================================ */
// add_filter('listeo_core_listings_query_args', 'lc_apply_all_filters_to_args');
// add_filter('listeo_listings_query_args',      'lc_apply_all_filters_to_args');
// add_filter('listeo_search_wp_query_args',     'lc_apply_all_filters_to_args');

/* ============================================================
 * E) Also hook Listeo’s arg filters for shortcode-powered loops
 * ============================================================ */
add_filter('listeo_core_listings_query_args', function ($args) {
    if (empty($args['post_type'])) $args['post_type'] = 'listing';

    if (!empty($_REQUEST['keywords'])) {
        $args['s'] = sanitize_text_field($_REQUEST['keywords']);
    }
    if (!empty($_REQUEST['location'])) {
        $needle = sanitize_text_field($_REQUEST['location']);
        $mq = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];
        $mq[] = ['key' => '_address', 'value' => $needle, 'compare' => 'LIKE'];
        $args['meta_query'] = $mq;
    }

    // --- SORTING (Listeo args) ---
    if (!empty($_REQUEST['sort'])) {
        $rule = lc_sort_rules_from_param($_REQUEST['sort']);
        if ($rule) {
            // Do NOT add EXISTS meta_query; just set meta_key+orderby
            $args['meta_key'] = $rule['meta_key'];
            $args['orderby']  = 'meta_value_num';
            $args['order']    = $rule['order'];
        }
    }
    return $args;
});
add_filter('listeo_listings_query_args',  function ($a) {
    return apply_filters('listeo_core_listings_query_args', $a);
});
add_filter('listeo_search_wp_query_args', function ($a) {
    return apply_filters('listeo_core_listings_query_args', $a);
});

/* ============================================================
 * F) AJAX: suggest terms for listing_category
 * ============================================================ */
add_action('wp_ajax_nopriv_lc_term_suggest', 'lc_term_suggest');
add_action('wp_ajax_lc_term_suggest',        'lc_term_suggest');
function lc_term_suggest()
{
    // Only allow whitelisted taxonomies
    $tax = isset($_GET['tax']) ? sanitize_key($_GET['tax']) : 'listing_category';
    if ($tax !== 'listing_category') {
        wp_send_json_error(['message' => 'Invalid taxonomy'], 400);
    }

    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    if ($q === '') {
        wp_send_json_success(['items' => []]); // empty query → empty suggestions
    }

    $terms = get_terms([
        'taxonomy'   => $tax,
        'hide_empty' => false,
        'number'     => 10,
        'search'     => $q,           // partial match on name
    ]);

    if (is_wp_error($terms)) {
        wp_send_json_error(['message' => $terms->get_error_message()], 500);
    }

    $items = array_map(function ($t) {
        return [
            'id'    => $t->term_id,
            'slug'  => $t->slug,
            'name'  => $t->name,
            'count' => (int) $t->count,
            'url'   => get_term_link($t),
        ];
    }, $terms);

    wp_send_json_success(['items' => $items]);
}

// Suggest locations from taxonomy terms (e.g., 'location' or 'listing_location')
add_action('wp_ajax_nopriv_lc_location_term_suggest', 'lc_location_term_suggest');
add_action('wp_ajax_lc_location_term_suggest',        'lc_location_term_suggest');
function lc_location_term_suggest()
{
    // Try both common tax names; stop at the first that exists
    $taxes = ['location', 'listing_location'];
    $tax   = null;
    foreach ($taxes as $t) {
        if (taxonomy_exists($t)) {
            $tax = $t;
            break;
        }
    }
    if (!$tax) {
        wp_send_json_error(['message' => 'Location taxonomy not found'], 500);
    }

    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    if ($q === '') {
        wp_send_json_success(['items' => []]);
    }

    $terms = get_terms([
        'taxonomy'   => $tax,
        'hide_empty' => false,
        'number'     => 12,
        'search'     => $q,
    ]);
    if (is_wp_error($terms)) {
        wp_send_json_error(['message' => $terms->get_error_message()], 500);
    }

    $items = array_map(function ($t) use ($tax) {
        return [
            'id'    => $t->term_id,
            'slug'  => $t->slug,
            'name'  => $t->name,
            'tax'   => $tax,
            'count' => (int) $t->count,
            'url'   => get_term_link($t),
        ];
    }, $terms);

    wp_send_json_success(['items' => $items]);
}


// Location suggestions from existing listing addresses
add_action('wp_ajax_nopriv_lc_location_suggest', 'lc_location_suggest');
add_action('wp_ajax_lc_location_suggest',        'lc_location_suggest');
function lc_location_suggest()
{
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    if ($q === '') {
        wp_send_json_success(['items' => []]);
    }

    // Find matching listings (by address parts + original _address)
    $args = [
        'post_type'      => 'listing',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'fields'         => 'ids',
        'meta_query'     => [
            // requires the helper you added earlier: lc_location_meta_or_query($needle)
            lc_location_meta_or_query($q),
        ],
    ];

    $ids = get_posts($args);

    $items = [];
    foreach ($ids as $pid) {
        $title = get_the_title($pid);
        $url   = get_permalink($pid);

        // Prefer original _address, else build from your custom fields
        $addr = trim((string) get_post_meta($pid, '_address', true));

        if ($addr === '') {
            $parts = [
                get_post_meta($pid, '_search_address_line_1', true),
                get_post_meta($pid, '_search_town', true),
                get_post_meta($pid, '_search_city', true),
                get_post_meta($pid, '_search_postcode', true),
                get_post_meta($pid, '_search_county', true),
                get_post_meta($pid, '_search_country', true),
            ];
            $parts = array_values(array_filter(array_map('trim', $parts)));
            $addr = implode(', ', $parts);
        }

        $thumb = get_the_post_thumbnail_url($pid, 'thumbnail');
        if (!$thumb) $thumb = ''; // keep empty if none

        // value = what will be inserted into the search input when clicked
        $value = $addr !== '' ? $addr : $title;

        $items[] = [
            'id'      => $pid,
            'title'   => $title,
            'address' => $addr,
            'url'     => $url,
            'thumb'   => $thumb,
            'value'   => $value,
        ];
    }

    wp_send_json_success(['items' => $items]);
}

/* ============================================================
 * G) INLINE JS for Split Map template (AJAX + Modal + Arrays)
 * ============================================================ */
add_action('wp_enqueue_scripts', function () {
    // Load on either template name you use
    if (! (is_page_template('template-split-map.php')
        || is_page_template('template-map-split.php')
        || is_page('search-result'))) {
        return;
    }

    // Register a handle (no src), then inject inline JS
    wp_register_script('lc-filters-ajax', false, [], null, true);

    // If you want a guaranteed ajaxurl in the frontend, uncomment the next line:
    wp_add_inline_script('lc-filters-ajax', 'window.ajaxurl = window.ajaxurl || "' . admin_url('admin-ajax.php') . '";', 'before');

    $js = <<<'JS'
(function(){
  //const RESULTS_SELECTOR = '#listeo-listings-container';
  const RESULTS_SELECTOR = '.row.fs-listings';
  const form  = document.getElementById('js-capacity-filter');
  const clear = document.getElementById('js-capacity-clear');
  const target = document.querySelector(RESULTS_SELECTOR);
  if (!form) return; // allow suggest wiring even if target isn't found yet

  // --- add these helpers near your other functions ---
  function getPriceDefaults(){
    const rmin = form.querySelector('#price_min_range');
    const rmax = form.querySelector('#price_max_range');
    const defMin = rmin ? parseInt(rmin.min ?? '0', 10) : 0;
    const defMax = rmax ? parseInt(rmax.max ?? '10000', 10) : 10000;
    return { min: defMin, max: defMax };
  }

  function pruneDefaultPrice(params){
    const defs = getPriceDefaults();
    if (!('price_min' in params) && !('price_max' in params)) return params;
    const pmin = params.price_min !== undefined && params.price_min !== '' ? parseInt(params.price_min, 10) : undefined;
    const pmax = params.price_max !== undefined && params.price_max !== '' ? parseInt(params.price_max, 10) : undefined;
    if (pmin === defs.min && pmax === defs.max) {
      delete params.price_min;
      delete params.price_max;
    }
    return params;
  }
  
function hasActiveFilters(){
  const f = form;

  // capacity
  if (f.capacity_type && f.capacity_min) {
    const t = (f.capacity_type.value || '').trim();
    const n = parseInt(f.capacity_min.value || '0', 10);
    if (t && n > 0) return true;
  }

  // price (different from defaults)
  const rmin = f.querySelector('#price_min_range');
  const rmax = f.querySelector('#price_max_range');
  if (rmin && rmax) {
    const defs = getPriceDefaults();
    const vmin = parseInt(rmin.value || '0', 10);
    const vmax = parseInt(rmax.value || '0', 10);
    if (vmin !== defs.min || vmax !== defs.max) return true;
  }
  // (also consider text inputs if you’re posting them)
  const imin = f.querySelector('#price_min');
  const imax = f.querySelector('#price_max');
  if ((imin && imin.value) || (imax && imax.value)) return true;

  // features / flags (any checked)
  if ([...f.querySelectorAll('[name="listing_feature[]"]:checked')].length) return true;
  if ([...f.querySelectorAll('[name="mf[]"]:checked')].length) return true;

  // category (single hidden or select)
  if (f['listing_category'] && String(f['listing_category'].value || '').trim()) return true;

  // text filters
  if (f.keywords && f.keywords.value.trim()) return true;
  if (f.location && f.location.value.trim()) return true;

  return false;
}

  /* ------------------------------
   * Keywords -> listing_category suggestions
   * ------------------------------ */
  const kwInput   = document.getElementById('keywords');
  const kwHidden  = document.getElementById('keywords_category');
  const kwSuggest = document.getElementById('keywords-suggest');

/* force select the suggestions */

// --- Mandatory selection mode (Search page) ---
function sp_hasValidSelection(){
  return kwHidden && String(kwHidden.value || '').trim() !== '';
}

function sp_clearKeyword(){
  if (kwInput) kwInput.value = '';
  if (kwHidden) kwHidden.value = '';
  if (kwSuggest) { kwSuggest.hidden = true; kwSuggest.innerHTML=''; }
}

// typing invalidates selection until user clicks suggestion
if (kwInput && kwHidden){
  kwInput.addEventListener('input', function(){
    kwHidden.value = '';
  });
}

// blur clears if user didn't select suggestion
if (kwInput){
  kwInput.addEventListener('blur', function(){
    setTimeout(function(){
      if (kwInput.value.trim() !== '' && !sp_hasValidSelection()){
        sp_clearKeyword();
      }
    }, 180);
  });
}



/* end force select the suggestions */


  function debounce(fn, ms){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), ms); }; }
  function ajax(url){ return fetch(url, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()); }

  function showSuggest(items){
    if (!kwSuggest) return;
    if (!items || !items.length){ kwSuggest.hidden = true; kwSuggest.innerHTML=''; return; }
    kwSuggest.innerHTML = items.map((it,i)=>`
      <div class="lc-suggest__item" data-slug="${it.slug}" role="option" aria-selected="${i===0?'true':'false'}">
        ${it.name} <span class="lc-suggest__count">(${it.count})</span>
      </div>`).join('');
    kwSuggest.hidden = false;
  }

  const fetchSuggest = debounce(function(term){
    const q = term.trim();
    if (!q){ showSuggest([]); return; }
    const url = `${window.ajaxurl || '/wp-admin/admin-ajax.php'}?action=lc_term_suggest&tax=listing_category&q=${encodeURIComponent(q)}`;
    ajax(url).then(res => {
      if (!res || !res.success) { showSuggest([]); return; }
      showSuggest(res.data.items || []);
    }).catch(()=> showSuggest([]));
  }, 180);

  function selectSuggestion(el){
    if (!el) return;
    const slug = el.getAttribute('data-slug') || '';
    const name = el.textContent.replace(/\(\d+\)\s*$/,'').trim();
    if (slug){
      kwHidden.value = slug;       // filter by category
      kwInput.value  = name;       // show selected term name
    }
    showSuggest([]);
  }

  if (kwInput && kwSuggest){
    kwInput.addEventListener('input', e => {
      kwHidden.value = '';
      fetchSuggest(e.target.value);
    });
    kwInput.addEventListener('focus', e => fetchSuggest(e.target.value));
    kwInput.addEventListener('blur', ()=> setTimeout(()=> showSuggest([]), 150));
    kwSuggest.addEventListener('mousedown', e => {
      const item = e.target.closest('.lc-suggest__item');
      if (item){ e.preventDefault(); selectSuggestion(item); }
    });
    kwInput.addEventListener('keydown', e => {
      if (kwSuggest.hidden) return;
      const items = Array.from(kwSuggest.querySelectorAll('.lc-suggest__item'));
      if (!items.length) return;
      let idx = items.findIndex(x => x.getAttribute('aria-selected') === 'true');
      if (e.key === 'ArrowDown'){ e.preventDefault(); idx = (idx+1) % items.length; }
      else if (e.key === 'ArrowUp'){ e.preventDefault(); idx = (idx-1+items.length) % items.length; }
      else if (e.key === 'Enter'){ e.preventDefault(); selectSuggestion(items[Math.max(idx,0)]); }
      else if (e.key === 'Escape'){ e.preventDefault(); showSuggest([]); return; }
      else return;
      items.forEach((x,i)=> x.setAttribute('aria-selected', i===idx ? 'true':'false'));
    });
  }


/* ------------------------------
 * Location suggestions (from existing listings' _address)
 * ------------------------------ */
const locInput   = document.getElementById('location');
const locSuggest = document.getElementById('location-suggest');

function debounce(fn, ms){ let t; return (...args)=>{ clearTimeout(t); t=setTimeout(()=>fn(...args), ms); }; }
function ajax(url){ return fetch(url, {credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json()); }

function escHtml(s){
  return String(s || '').replace(/[&<>"']/g, m => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
  }[m]));
}

function showLocSuggest(items){
  if (!locSuggest) return;
  if (!items || !items.length){ locSuggest.hidden = true; locSuggest.innerHTML=''; return; }

  locSuggest.innerHTML = items.map((it,i)=> {
    const value = escHtml(it.value || it.address || it.title || '');
    const title = escHtml(it.title || '');
    const addr  = escHtml(it.address || '');
    const url   = escHtml(it.url || '');
    const thumb = it.thumb ? `<img class="lc-suggest__thumb" src="${escHtml(it.thumb)}" alt="">` : '';

    return `
      <div class="lc-suggest__item" data-value="${value}" data-url="${url}"
           role="option" aria-selected="${i===0?'true':'false'}">
        ${thumb}
        <div class="lc-suggest__meta">
          <div class="lc-suggest__title">${title}</div>
          ${addr ? `<div class="lc-suggest__addr">${addr}</div>` : ``}
        </div>
      </div>
    `;
  }).join('');

  locSuggest.hidden = false;
}

const fetchLocSuggest = debounce(function(term){
  const q = term.trim();
  if (!q){ showLocSuggest([]); return; }
  const url = (window.ajaxurl || '/wp-admin/admin-ajax.php') +
              '?action=lc_location_suggest&q=' + encodeURIComponent(q);
  ajax(url).then(res => {
    if (!res || !res.success) { showLocSuggest([]); return; }
    showLocSuggest(res.data.items || []);
  }).catch(()=> showLocSuggest([]));
}, 180);

function selectLocSuggestion(el){
  if (!el) return;
  const val = el.getAttribute('data-value') || '';
  if (val) locInput.value = val;

  // Optional: open the listing in a new tab if user Ctrl/Cmd-clicks
  // const url = el.getAttribute('data-url') || '';
  // if (url && (window.event?.ctrlKey || window.event?.metaKey)) window.open(url, '_blank');

  showLocSuggest([]);
}

if (locInput && locSuggest){
  // typing
  locInput.addEventListener('input', e => fetchLocSuggest(e.target.value));
  // focus shows suggestions for current text
  locInput.addEventListener('focus', e => fetchLocSuggest(e.target.value));
  // blur hides after click time
  locInput.addEventListener('blur', ()=> setTimeout(()=> showLocSuggest([]), 150));

  // click to select
  locSuggest.addEventListener('mousedown', e => {
    const item = e.target.closest('.lc-suggest__item');
    if (item){ e.preventDefault(); selectLocSuggestion(item); }
  });

  // keyboard nav
  locInput.addEventListener('keydown', e => {
    if (locSuggest.hidden) return;
    const items = Array.from(locSuggest.querySelectorAll('.lc-suggest__item'));
    if (!items.length) return;
    let idx = items.findIndex(x => x.getAttribute('aria-selected') === 'true');
    if (e.key === 'ArrowDown'){ e.preventDefault(); idx = (idx+1) % items.length; }
    else if (e.key === 'ArrowUp'){ e.preventDefault(); idx = (idx-1+items.length) % items.length; }
    else if (e.key === 'Enter'){ e.preventDefault(); selectLocSuggestion(items[Math.max(idx,0)]); }
    else if (e.key === 'Escape'){ e.preventDefault(); showLocSuggest([]); return; }
    else return;
    items.forEach((x,i)=> x.setAttribute('aria-selected', i===idx ? 'true':'false'));
  });
}

// basic geolocate: just writes "lat, long" into the input
document.addEventListener('click', e => {
  const btn = e.target.closest('.lc-geolocate');
  if (!btn) return;
  e.preventDefault();
  if (!navigator.geolocation) return alert('Geolocation not supported.');
  navigator.geolocation.getCurrentPosition(function(pos){
    const { latitude, longitude } = pos.coords || {};
    if (typeof latitude === 'number' && typeof longitude === 'number') {
      locInput.value = latitude.toFixed(5) + ', ' + longitude.toFixed(5);
    }
  }, function(){ alert('Unable to get your location.'); }, { enableHighAccuracy:true, timeout:8000 });
});



  /* ------------------------------
   * URL + AJAX helpers
   * ------------------------------ */
  function buildUrlWithParams(base, params, keepExisting=true) {
    const url = new URL(base, location.origin);
    if (keepExisting) {
      const current = new URLSearchParams(location.search);
      current.forEach((v,k)=> url.searchParams.set(k,v));
    }
    Object.keys(params || {}).forEach(k => {
      url.searchParams.delete(k);
      const v = params[k];
      if (v == null || v === '' || (Array.isArray(v) && v.length === 0)) return;
      if (Array.isArray(v)) v.forEach(item => url.searchParams.append(k, item));
      else url.searchParams.set(k, v);
    });
    return url.toString();
  }

  function serializeFormToParams(f){
    const fd = new FormData(f);
    const out = {};
    fd.forEach((v,k)=>{
      if (out[k] !== undefined){
        if (!Array.isArray(out[k])) out[k] = [out[k]];
        out[k].push(v);
      } else {
        out[k] = v;
      }
    });
    return out;
  }

  async function ajaxUpdate(params, keepExisting=true) {
    if (!target) throw new Error('Results wrapper not found on page: ' + RESULTS_SELECTOR);
    const url = buildUrlWithParams(location.pathname, params, keepExisting);
    const res = await fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' }});
    if (!res.ok) throw new Error('Network ' + res.status);
    const html = await res.text();
    const dom = new DOMParser().parseFromString(html, 'text/html');
    const next = dom.querySelector(RESULTS_SELECTOR);
    if (!next) throw new Error('Results wrapper not found in response');
    target.innerHTML = next.innerHTML;
    history.pushState({}, '', url);
    document.dispatchEvent(new CustomEvent('lc:listings:updated', { detail: { url } }));
  }

  /* ------------------------------
   * Price slider sync
   * ------------------------------ */
  function syncPriceUI(){
    const rmin = form.querySelector('#price_min_range');
    const rmax = form.querySelector('#price_max_range');
    const imin = form.querySelector('#price_min');
    const imax = form.querySelector('#price_max');
    if (!rmin || !rmax || !imin || !imax) return;

    const vmin = parseInt(rmin.value||'0',10);
    const vmax = parseInt(rmax.value||'0',10);
    if (vmin > vmax) {
      if (document.activeElement === rmin) rmax.value = rmin.value;
      else rmin.value = rmax.value;
    }
    imin.value = rmin.value;
    imax.value = rmax.value;

    const outMin = form.querySelector('[data-role="price-min-out"]');
    const outMax = form.querySelector('[data-role="price-max-out"]');
    if (outMin) outMin.textContent = imin.value;
    if (outMax) outMax.textContent = imax.value;
  }
  function resetPriceUIToDefaults(){
    const rmin = form.querySelector('#price_min_range');
    const rmax = form.querySelector('#price_max_range');
    if (!rmin || !rmax) return;
    rmin.value = rmin.min ?? '0';
    rmax.value = rmax.max ?? '10000';
    syncPriceUI();
  }

  form.addEventListener('input', function(e){
    if (e.target && (e.target.id === 'price_min_range' || e.target.id === 'price_max_range')) {
      syncPriceUI();
    }
  });

  /* ------------------------------
   * Submit (AJAX)
   * ------------------------------ */
   


form.addEventListener('submit', function(e){

//alert('Please select a suggestion from the list.');

  e.preventDefault();
  closeModal();

  // Drop empty category param from this form
  var cat = form.querySelector('#keywords_category');
  var temporarilyDisabled = false;
  if (cat && !cat.value) { cat.disabled = true; temporarilyDisabled = true; }

  let params = serializeFormToParams(form);
  
// If keyword text exists but user didn't select a suggestion, stop.
if (params.keywords && String(params.keywords).trim() !== '' && !sp_hasValidSelection()){
  sp_clearKeyword();
  return; // do not run ajaxUpdate
}

// If selected, submit only listing_category (optional but recommended)
if (sp_hasValidSelection()){
  delete params.keywords;
}
 
 
  

  // Re-enable for the UI after serializing
  if (temporarilyDisabled) cat.disabled = false;

  params = pruneDefaultPrice(params);

  // If user is filtering and no sort is present, add it
  if (hasActiveFilters() && !('sort' in params)) {
    const sortControl = form.querySelector('[name="sort"], #sort');
    params.sort = (sortControl && sortControl.value) ? sortControl.value : 'standing_desc';
  }

  ajaxUpdate(params, /*keepExisting*/ true)
    .catch(err => { console.error(err); form.submit(); });
});



  /* ------------------------------
   * Clear → reset + soft reload (clean URL)
   * ------------------------------ */
  if (clear) {
    clear.addEventListener('click', function(e){
      e.preventDefault();
      form.reset();
      resetPriceUIToDefaults();
      closeModal();
      ajaxUpdate( {}, /*keepExisting*/false )
        .catch(()=>{ location.href = location.pathname; });
    });
  }

  /* ------------------------------
   * Modal (portal to <body> to fix z-index)
   * ------------------------------ */
  const modal = document.getElementById('more-filters-modal');

  function wireModalControlsToForm(){
    if (!modal) return;
    modal.querySelectorAll('input, select, textarea').forEach(el => {
      if (!el.name) return;
      el.setAttribute('form', 'js-capacity-filter');
    });
    modal.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
      btn.setAttribute('form', 'js-capacity-filter');
    });
  }

  function ensurePortal() {
    if (modal && modal.parentElement !== document.body) {
      document.body.appendChild(modal);
      modal.dataset.portaled = '1';
    }
    wireModalControlsToForm();
  }
  function openModal(){
    if (!modal) return;
    ensurePortal();
    document.documentElement.classList.add('mf-locked');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden','false');
    modal.style.zIndex = '2147483647';
  }
  function closeModal(){
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden','true');
    document.documentElement.classList.remove('mf-locked');
  }

  document.addEventListener('click', function(e){
    const open  = e.target.closest('[data-open="more-filters"]');
    const close = e.target.closest('[data-close="more-filters"]');
    if (open)  { e.preventDefault(); openModal(); }
    if (close) { e.preventDefault(); closeModal(); }
    if (modal && e.target === modal) { closeModal(); }
  });

  document.addEventListener('click', function(e){
    const submitApply = e.target.closest('[data-close-on-apply]');
    if (!submitApply) return;
    closeModal();
  });

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeModal();
  });

  wireModalControlsToForm();

  /* ------------------------------
   * Back/forward support
   * ------------------------------ */
  window.addEventListener('popstate', function(){
    ajaxUpdate({}, /*keepExisting*/true).catch(()=>{});
  });
})();
JS;

    wp_add_inline_script('lc-filters-ajax', $js);
    wp_enqueue_script('lc-filters-ajax');
});


add_action('wp_footer', function () { ?>
    <script>
        jQuery(document).ready(function($) {
            function fixPreviewText() {
                $('body :not(script)').contents().each(function() {
                    if (this.nodeType === 3) { // Text node
                        const text = this.textContent;

                        // Replace "Notice!" with "Hey!"
                        if (text.includes('Notice!')) {
                            this.textContent = text.replace(/Notice!/i, 'Hey!');
                        }

                        // Fix preview text (previous functionality)
                        if (text.includes('preview of listing') && !text.includes('a preview')) {
                            this.textContent = text.replace(/preview of listing/i, 'a preview of listing');
                        }
                    }
                });
            }

            // Run on page load
            fixPreviewText();

            // Run after a short delay for dynamic content
            setTimeout(fixPreviewText, 500);
            setTimeout(fixPreviewText, 1000);
        });
    </script>
<?php });




/**
 * Clone listig button start
 */
add_action('rest_api_init', function () {
    register_rest_route('listing/v1', '/clone', [
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $req) {
            $src_id = absint($req->get_param('id'));
            if (!$src_id) return new WP_Error('bad_request', 'Missing id', ['status' => 400]);

            $src = get_post($src_id);
            if (!$src || $src->post_type !== 'listing') {
                return new WP_Error('not_found', 'Listing not found', ['status' => 404]);
            }

            if (!is_user_logged_in()) {
                return new WP_Error('forbidden', 'Login required', ['status' => 403]);
            }

            $current = wp_get_current_user();
            $is_owner = ((int)$src->post_author === (int)$current->ID);
            $can_edit_others = current_user_can('edit_others_posts'); // swap to custom caps if you use them
            if (!$is_owner && !$can_edit_others) {
                return new WP_Error('forbidden', 'You cannot new space', ['status' => 403]);
            }

            $new_id = wp_insert_post([
                'post_type'      => 'listing',
                'post_title'     => $src->post_title . ' (Copy)',
                'post_content'   => $src->post_content,
                'post_excerpt'   => $src->post_excerpt,
                'post_status'    => 'pending',
                'post_author'    => $src->post_author,
                'post_parent'    => 0,
                'menu_order'     => $src->menu_order,
                'comment_status' => $src->comment_status,
                'ping_status'    => $src->ping_status,
            ], true);
            if (is_wp_error($new_id)) return $new_id;

            // Copy taxonomies
            foreach (get_object_taxonomies('listing') as $tax) {
                $terms = wp_get_object_terms($src_id, $tax, ['fields' => 'ids']);
                if (!is_wp_error($terms)) wp_set_object_terms($new_id, $terms, $tax, false);
            }

            // Copy meta (skip volatile/protected keys)
            $protected = apply_filters('listing_cloner_protected_meta_keys', [
                '_edit_lock',
                '_edit_last',
                '_thumbnail_id',
                '_wp_old_slug',
                '_transient',
                '_transient_timeout'
            ]);
            $all_meta = get_post_meta($src_id);
            foreach ($all_meta as $key => $values) {
                $skip = false;
                foreach ($protected as $bad) {
                    if (strpos($key, $bad) === 0) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;
                foreach ((array)$values as $val) {
                    add_post_meta($new_id, $key, maybe_unserialize($val));
                }
            }

            if ($thumb_id = get_post_thumbnail_id($src_id)) {
                set_post_thumbnail($new_id, $thumb_id);
            }

            do_action('listing_cloned', $new_id, $src_id);

            return new WP_REST_Response([
                'id'        => $new_id,
                'permalink' => get_permalink($new_id),
                'status'    => get_post_status($new_id),
            ], 200);
        },
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ]);
});

/**
 * "Clone this listing" button
 */
add_action('wp_enqueue_scripts', function () {
    // if (!is_page('my-listings')) return;

    wp_register_script('listing-cloner-inline', '', [], null, true);
    wp_enqueue_script('listing-cloner-inline');

    wp_localize_script('listing-cloner-inline', 'ListingCloner', [
        'restUrl' => esc_url_raw(rest_url('listing/v1/clone')),
        'nonce'   => wp_create_nonce('wp_rest'),
        'redirect' => 'edit',
    ]);

    wp_add_inline_script('listing-cloner-inline', <<<JS
(function(){
  // Read ?status= from the current URL (default "active" if missing)
  var currentStatus = (new URLSearchParams(location.search).get('status') || 'active').toLowerCase();

  function getListingIdFromButtonsRight(container){
    var edit = container.querySelector('a.listeo_core-dashboard-action-edit[href*="listing_id="]');
    if(edit){
      try{
        var url = new URL(edit.href, window.location.origin);
        var id = parseInt(url.searchParams.get('listing_id'), 10);
        if(!isNaN(id)) return id;
      }catch(e){}
    }
    var fb = container.closest('li')?.querySelector('[id*="ical-import-dialog-"], [id*="ical-export-dialog-"]');
    if(fb){
      var m = fb.id.match(/-(\\d+)/);
      if(m) return parseInt(m[1], 10);
    }
    return null;
  }

  function makeButton(listingId){
    var a = document.createElement('a');
    a.href = '#';
    a.className = 'button gray js-clone-listing';
    a.setAttribute('data-post', String(listingId));
    a.innerHTML = '<i class="sl sl-icon-docs"></i> Add a space within this venue';
    return a;
  }

  function injectButtons(){
    // Add a status class on each LI in this view
    document.querySelectorAll('.dashboard-list-box ul > li').forEach(function(li){
      li.classList.add('listing-status-' + currentStatus);
      li.dataset.status = currentStatus; 
    });

    // Only add the Clone button on the "active" view
    if(currentStatus !== 'active') return;

    document.querySelectorAll('.buttons-to-right').forEach(function(container){
      if(container.querySelector('.js-clone-listing')) return;
      var listingId = getListingIdFromButtonsRight(container);
      if(!listingId) return;
      var btn = makeButton(listingId);
      container.prepend(btn); 
    });
  }

  document.addEventListener('click', async function(e){
    var btn = e.target.closest('.js-clone-listing');
    if(!btn) return;
    e.preventDefault();
    if(btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    var postId = parseInt(btn.getAttribute('data-post'), 10);
    var original = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-circle-o-notch fa-spin"></i> Cloning…';

    try{
      var res = await fetch(window.ListingCloner.restUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.ListingCloner.nonce
        },
        body: JSON.stringify({ id: postId })
      });
      if(!res.ok) throw new Error('HTTP ' + res.status);
      var data = await res.json();

      if(ListingCloner.redirect === 'edit' && data?.id){
        window.location.href = '/add-listing/?action=edit&listing_id=' + data.id;
      } else if(data?.permalink){
        window.location.href = data.permalink;
      } else {
        alert('Cloned. New ID: ' + (data?.id || '?'));
        window.location.reload();
      }
    } catch(err){
      console.error(err);
      alert('Sorry, cloning failed.');
      btn.innerHTML = original;
    } finally {
      btn.dataset.busy = '0';
    }
  }, false);

  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', injectButtons);
  } else {
    injectButtons();
  }

  window.injectCloneButtons = injectButtons;
}());
JS);
});

/**
 * Clone listig button end
 */


// min_spend Safer, direct reader for the _menu meta (no shortcode parsing)
if (! function_exists('lc_render_venue_menu_meta')) {
    function lc_render_venue_menu_meta($post_id = null)
    {
        $post_id = $post_id ?: get_the_ID();
        if (! $post_id) return '';

        // Fetch & unserialize _menu (WP can store it serialized)
        $raw  = get_post_meta($post_id, '_menu', true);
        if (empty($raw)) return '';

        if (function_exists('maybe_unserialize')) {
            $menu = maybe_unserialize($raw);
        } else {
            $menu = @unserialize($raw); // fallback
            if ($menu === false && $raw !== 'b:0;') $menu = $raw;
        }

        if (! is_array($menu)) return '';

        // Find first item (you said only one is used)
        // Expected shape: [0]['menu_elements'][0]['name'|'price' ...]
        $first_group = is_array($menu) ? reset($menu) : null;
        $elements    = is_array($first_group) && !empty($first_group['menu_elements']) ? $first_group['menu_elements'] : [];
        $first_item  = is_array($elements) ? reset($elements) : null;
        if (!is_array($first_item)) return '';

        $raw_name  = isset($first_item['name'])  ? trim((string)$first_item['name'])  : '';
        $raw_price = isset($first_item['price']) ? trim((string)$first_item['price']) : '';

        // Label map
        $label_map = [
            'min_spend'  => 'Min Spend',
            'per_person' => 'Per Person',
            'per_hour'   => 'Per Hour',
            'per_day'    => 'Per Day',
            'hire_fee'   => 'Hire Fee',
        ];
        $slug  = sanitize_key($raw_name);
        $label = $label_map[$slug] ?? ucwords(str_replace('_', ' ', $raw_name));

        // Currency formatting (uses Listeo settings)
        $abbr = get_option('listeo_currency');
        $pos  = get_option('listeo_currency_postion'); // (sic)
        $dec  = (int) get_option('listeo_number_decimals', 2);
        $sym  = class_exists('Listeo_Core_Listing') ? Listeo_Core_Listing::get_currency_symbol($abbr) : '£';
        if ($sym === '') $sym = '£';

        if ($raw_price === '') return ''; // nothing to show

        if (is_numeric($raw_price)) {
            $num   = number_format((float)$raw_price, $dec);
            $price = ($pos === 'before') ? ($sym . $num) : ($num . $sym);
        } else {
            $price = esc_html($raw_price);
        }

        // Final inner HTML you want
        $html  = 'from <span class="c-room-card__price_val">' . esc_html($price) . '</span><br>' . esc_html($label);
        return $html;
    }
}

// Allow ?sort=... in the URL (_max_standing_capacity)
add_filter('query_vars', function ($vars) {
    $vars[] = 'sort';
    return $vars;
});



/**
 * Lead Connect Embed meta box + shortcode
 */
add_action('add_meta_boxes', function () {
    add_meta_box(
        'lead_connect_embed_box',
        'Lead Connect Embed',
        'render_lead_connect_embed_box',
        'listing',
        'normal',
        'default'
    );
});
function render_lead_connect_embed_box($post)
{
    wp_nonce_field('save_lead_connect_embed', 'lead_connect_embed_nonce');
    $value = get_post_meta($post->ID, '_lead_connect_embed', true);
?>
    <p>Paste your Lead Connector iframe + script here:</p>
    <textarea
        name="lead_connect_embed"
        style="width:100%;min-height:200px;font-family:monospace;"><?php echo esc_textarea($value); ?></textarea>
<?php
}
add_action('save_post_listing', function ($post_id) {
    if (
        ! isset($_POST['lead_connect_embed_nonce']) ||
        ! wp_verify_nonce($_POST['lead_connect_embed_nonce'], 'save_lead_connect_embed')
    ) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (! current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['lead_connect_embed'])) {
        $embed_code = $_POST['lead_connect_embed'];
        update_post_meta($post_id, '_lead_connect_embed', $embed_code);
    }
});
function listing_lead_connect_shortcode($atts)
{
    $atts = shortcode_atts(
        [
            'id' => 0,
        ],
        $atts,
        'lead_connect_embed'
    );
    $post_id = intval($atts['id']);
    if (! $post_id) {
        $post_id = get_the_ID();
    }
    if (! $post_id) {
        return '';
    }
    $embed = get_post_meta($post_id, '_lead_connect_embed', true);
    if (! $embed) {
        return '';
    }
    return $embed;
}
add_shortcode('lead_connect_embed', 'listing_lead_connect_shortcode');



/* -------------------------------------------------
 * HERO SEARCH SHORTCODE (Elementor friendly)
 * -----------------------------------------------*/
add_shortcode('hv_hero_search', function ($atts) {
    $atts = shortcode_atts([
        'action' => '/search-result/', // where to send the search
    ], $atts, 'hv_hero_search');

    ob_start(); ?>
    <style>
        #hv-keywords,
        #hv-location,
        #hv-guests,
        .hv-hero .bootstrap-select.btn-group button {
            margin: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }

        .hv-hero-wrap {
            position: relative;
            z-index: 10
        }

        .hv-hero {
            display: flex;
            gap: 16px;
            align-items: center;
            padding: 10px 20px;
            border-radius: 999px;
            background: #fff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
            overflow: visible
        }

        .hv-item {
            position: relative;
            flex: 1;
            display: flex;
            align-items: center;
            padding: 0;
        }

        .hv-item+.hv-item {
            border-left: 1px solid rgba(0, 0, 0, .08)
        }

        .hv-input {
            width: 100%;
            border: 0;
            background: transparent;
            outline: none;
            font: 16px/1.2 system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial
        }

        .hv-input::placeholder {
            color: #9aa0a6
        }

        .hv-loc-icon {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            opacity: .55
        }

        .hv-loc-icon svg {
            width: 18px;
            height: 18px;
            display: block
        }

        .hv-select,
        .hv-number {
            appearance: none;
            -webkit-appearance: none;
            width: 100%;
            border: 0;
            background: transparent;
            outline: none;
            font: 16px/1.2 system-ui
        }

        .hv-select {
            padding-right: 18px
        }

        .hv-number {
            padding-right: 4px
        }

        .hv-caret {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            opacity: .55
        }

        .hv-dd {
            position: relative;
        }

        .hv-dd__toggle {
            width: 100%;
            background: #fff;
            border: none !important;
            outline: none !important;
            padding: 12px 40px 12px 14px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            cursor: pointer;
            line-height: 1.2;
            color: #808080;
        }

        .hv-dd__toggle {
            border-color: transparent !important;
        }

        .hv-dd__chev {
            flex: 0 0 auto;
        }

        .hv-dd__menu {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 9999;
            margin: 0;
            padding: 6px;
            list-style: none;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .12);
            max-height: 260px;
            overflow: auto;
            border: none !important;
            outline: none !important;
        }

        .hv-dd__opt {
            padding: 10px 10px;
            border-radius: 8px;
            cursor: pointer;
            white-space: nowrap;
        }

        .hv-dd__opt:hover,
        .hv-dd__opt[aria-selected="true"] {
            background: #f5f7fb;
        }

        .hv-btn {
            flex: 0 0 auto;
            border: 0;
            border-radius: 999px;
            padding: 12px 22px;
            background: #9a83c3;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            width: 115px;
        }

        .hv-btn:hover {
            filter: brightness(.95)
        }

        .hv-suggest {
            position: absolute;
            left: 0;
            top: 100%;
            margin-top: 8px;
            width: 100%;
            max-height: 260px;
            overflow: auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 28px rgba(0, 0, 0, .15);
            border: 1px solid rgba(0, 0, 0, .06);
            z-index: 2147483647
        }

        .hv-suggest[hidden] {
            display: none !important
        }

        .hv-suggest__item {
            padding: 10px 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px
        }

        .hv-suggest__item[aria-selected="true"],
        .hv-suggest__item:hover {
            background: #f5f6f8
        }

        .hv-suggest__name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis
        }

        .hv-suggest__count {
            display: none
        }

        .elementor .hv-hero-wrap,
        .elementor .hv-hero {
            overflow: visible !important
        }

        @media (max-width: 767px) {
            .hv-hero {
                flex-wrap: wrap;
                border-radius: 18px;
                padding: 8px 20px;
            }

            .hv-item {
                flex: 1 1 100%;
                border-left: 0 !important;
                border-top: 1px solid rgba(0, 0, 0, .08)
            }

            .hv-item:first-child {
                border-top: 0
            }

            .hv-btn {
                width: 100%
            }
        }
    </style>

    <div class="hv-hero-wrap">
        <form class="hv-hero" action="<?php echo esc_url($atts['action']); ?>" method="get" id="hv-hero-form" role="search" novalidate>
            <!-- Keywords -->
            <div class="hv-item" data-field="keywords">
                <input type="text" class="hv-input" id="hv-keywords" name="keywords" autocomplete="off" placeholder="Event type (Start typing and select from dropdown)" />
                <input type="hidden" id="hv-keywords-category" name="listing_category" value="">
                <div class="hv-suggest" id="hv-kw-suggest" hidden></div>
            </div>

            <!-- Location (pin icon on right) -->
            <div class="hv-item" data-field="location">
                <input type="text" class="hv-input" id="hv-location" name="location" autocomplete="off" placeholder="Location" />
                <span class="hv-loc-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z" />
                    </svg>
                </span>
                <div class="hv-suggest" id="hv-loc-suggest" hidden></div>
            </div>

            <!-- Number of Guests -->
            <div class="hv-item" data-field="guests">
                <input type="text" id="hv-guests" name="capacity_min" class="hv-number" placeholder="Number of Guests" inputmode="numeric" pattern="[0-9]*" autocomplete="off" />
            </div>

            <!-- Space type (custom dropdown) -->
            <div class="hv-item hv-field hv-dd" data-name="capacity_type">
                <input type="hidden" name="capacity_type" id="hv-space" value="">

                <button type="button" class="hv-dd__toggle" aria-haspopup="listbox" aria-expanded="false">
                    <span class="hv-dd__label">Space type</span>
                    <svg class="hv-dd__chev" width="16" height="16" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 10l5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" />
                    </svg>
                </button>

                <ul class="hv-dd__menu" role="listbox" tabindex="-1" hidden>
                    <!-- set data-value to the meta key your filters expect -->
                    <!-- 		<li role="option" class="hv-dd__opt" data-value="">Space type</li> -->
                    <li role="option" class="hv-dd__opt" data-value="standing">Standing</li>
                    <li role="option" class="hv-dd__opt" data-value="dining">Dining</li>
                    <li role="option" class="hv-dd__opt" data-value="cabaret">Cabaret</li>
                    <li role="option" class="hv-dd__opt" data-value="classroom">Classroom</li>
                    <li role="option" class="hv-dd__opt" data-value="theatre">Theatre</li>
                    <li role="option" class="hv-dd__opt" data-value="ushaped">U-Shaped</li>
                    <li role="option" class="hv-dd__opt" data-value="boardroom">Boardroom</li>
                </ul>
            </div>

            <button class="hv-btn" type="submit">Search</button>
        </form>
    </div>

    <script>
        (function() {
            var form = document.getElementById('hv-hero-form');
            if (!form) return;

            // endpoints you already registered in PHP:
            var ajaxurl = (window.ajaxurl || '/wp-admin/admin-ajax.php');

            /* ------- UTILITIES ------- */
            function debounce(fn, ms) {
                var t;
                return function() {
                    var a = arguments,
                        ctx = this;
                    clearTimeout(t);
                    t = setTimeout(function() {
                        fn.apply(ctx, a);
                    }, ms || 180);
                };
            }

            function fetchJSON(url) {
                return fetch(url, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function(r) {
                    return r.json();
                });
            }

            // PORTAL: move dropdown to <body> so it's never clipped
            function portalize(listEl, inputEl) {
                if (!listEl || !inputEl) return;
                if (!listEl.dataset.portaled) {
                    listEl.dataset.portaled = '1';
                    listEl.style.position = 'fixed';
                    listEl.style.left = '0px';
                    listEl.style.top = '0px';
                    document.body.appendChild(listEl);
                }
                var r = inputEl.getBoundingClientRect();
                listEl.style.width = r.width + 'px';
                listEl.style.left = r.left + 'px';
                listEl.style.top = (r.bottom + 8) + 'px';
            }

            function attachPortalPosition(listEl, inputEl) {
                function pos() {
                    if (!listEl.hasAttribute('hidden')) portalize(listEl, inputEl);
                }
                window.addEventListener('resize', pos, {
                    passive: true
                });
                window.addEventListener('scroll', pos, true);
                return pos;
            }

            /* ------- KEYWORDS SUGGEST ------- */
            var kwInput = document.getElementById('hv-keywords');
            var kwHidden = document.getElementById('hv-keywords-category');
            var kwSuggest = document.getElementById('hv-kw-suggest');
            var kwPos = attachPortalPosition(kwSuggest, kwInput);

            /* force select the suggestions */

            // --- Mandatory selection mode (Home) ---
            function hv_hasValidSelection() {
                return kwHidden && String(kwHidden.value || '').trim() !== '';
            }

            function hv_clearKeyword() {
                if (kwInput) kwInput.value = '';
                if (kwHidden) kwHidden.value = '';
                if (kwSuggest) {
                    kwSuggest.setAttribute('hidden', '');
                    kwSuggest.innerHTML = '';
                }
            }

            // If user types anything manually, it is NOT valid until they pick a suggestion
            if (kwInput && kwHidden) {
                kwInput.addEventListener('input', function() {
                    kwHidden.value = ''; // invalidate selection as soon as user types
                });
            }

            // On blur: if no valid suggestion selected, clear the field
            if (kwInput) {
                kwInput.addEventListener('blur', function() {
                    setTimeout(function() { // allow click on suggestion first
                        if (kwInput.value.trim() !== '' && !hv_hasValidSelection()) {
                            hv_clearKeyword();
                        }
                    }, 180);
                });
            }

            // On submit: hard block keywords if not selected
            var heroForm = document.getElementById('hv-hero-form');
            if (heroForm) {
                heroForm.addEventListener('submit', function(e) {
                    var typed = kwInput ? kwInput.value.trim() : '';
                    if (typed !== '' && !hv_hasValidSelection()) {
                        // Not selected from suggestion -> clear & block submit
                        hv_clearKeyword();
                        e.preventDefault();
                        return false;
                    }
                }, true);
            }



            /* end force select the suggestions */




            function showKw(items) {
                if (!items || !items.length) {
                    kwSuggest.setAttribute('hidden', '');
                    kwSuggest.innerHTML = '';
                    return;
                }
                kwSuggest.innerHTML = items.map(function(it, i) {
                    return '<div class="hv-suggest__item" data-slug="' + (it.slug || '') + '" role="option" aria-selected="' + (i === 0 ? 'true' : 'false') + '">' +
                        '<span class="hv-suggest__name">' + (it.name || '') + '</span>' +
                        '<span class="hv-suggest__count">(' + (it.count || 0) + ')</span>' +
                        '</div>';
                }).join('');
                kwSuggest.removeAttribute('hidden');
                kwPos();
            }
            var fetchKw = debounce(function(term) {
                var q = (term || '').trim();
                if (!q) {
                    showKw([]);
                    return;
                }
                var url = ajaxurl + '?action=lc_term_suggest&tax=listing_category&q=' + encodeURIComponent(q);
                fetchJSON(url).then(function(res) {
                        showKw((res && res.success && res.data && res.data.items) || []);
                    })
                    .catch(function() {
                        showKw([]);
                    });
            }, 180);

            function selectKw(el) {
                if (!el) return;
                var slug = el.getAttribute('data-slug') || '';
                var name = (el.querySelector('.hv-suggest__name') || {}).textContent || '';
                if (slug) {
                    kwHidden.value = slug;
                    kwInput.value = name;
                }
                kwSuggest.setAttribute('hidden', '');
                kwSuggest.innerHTML = '';
            }

            // function selectKw(el){
            //   if(!el) return;
            //   var name = (el.querySelector('.hv-suggest__name') || {}).textContent || '';
            //   kwHidden.value = '';      // do NOT submit listing_category from keyword suggestions
            //   kwInput.value  = name;    // only fill keyword text
            //   kwSuggest.setAttribute('hidden',''); 
            //   kwSuggest.innerHTML='';
            // }


            kwInput.addEventListener('input', function(e) {
                kwHidden.value = '';
                fetchKw(e.target.value);
            });
            kwInput.addEventListener('focus', function(e) {
                fetchKw(e.target.value);
            });
            kwInput.addEventListener('blur', function() {
                setTimeout(function() {
                    kwSuggest.setAttribute('hidden', '');
                }, 150);
            });
            kwSuggest.addEventListener('mousedown', function(e) {
                var item = e.target.closest('.hv-suggest__item');
                if (!item) return;
                e.preventDefault();
                selectKw(item);
            });
            kwInput.addEventListener('keydown', function(e) {
                if (kwSuggest.hasAttribute('hidden')) return;
                var items = [].slice.call(kwSuggest.querySelectorAll('.hv-suggest__item'));
                if (!items.length) return;
                var idx = items.findIndex(function(x) {
                    return x.getAttribute('aria-selected') === 'true';
                });
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    idx = (idx + 1) % items.length;
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    idx = (idx - 1 + items.length) % items.length;
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    selectKw(items[Math.max(idx, 0)]);
                    return;
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    kwSuggest.setAttribute('hidden', '');
                    return;
                }
                items.forEach(function(x, i) {
                    x.setAttribute('aria-selected', i === idx ? 'true' : 'false');
                });
            });

            /* ------- LOCATION SUGGEST ------- */
            var locInput = document.getElementById('hv-location');
            var locSuggest = document.getElementById('hv-loc-suggest');
            var locPos = attachPortalPosition(locSuggest, locInput);

            function showLoc(items) {
                if (!items || !items.length) {
                    locSuggest.setAttribute('hidden', '');
                    locSuggest.innerHTML = '';
                    return;
                }
                locSuggest.innerHTML = items.map(function(it, i) {
                    var name = (it && (it.name || it.slug || '')) || '';
                    return '<div class="hv-suggest__item" data-value="' + name.replace(/"/g, '&quot;') + '" role="option" aria-selected="' + (i === 0 ? 'true' : 'false') + '">' +
                        '<span class="hv-suggest__name">' + name + '</span>' +
                        '</div>';
                }).join('');
                locSuggest.removeAttribute('hidden');
                locPos();
            }
            var fetchLoc = debounce(function(term) {
                var q = (term || '').trim();
                if (!q) {
                    showLoc([]);
                    return;
                }
                var url = ajaxurl + '?action=lc_location_suggest&q=' + encodeURIComponent(q);
                fetchJSON(url).then(function(res) {
                        showLoc((res && res.success && res.data && res.data.items) || []);
                    })
                    .catch(function() {
                        showLoc([]);
                    });
            }, 180);

            function selectLoc(el) {
                if (!el) return;
                var val = el.getAttribute('data-value') || '';
                if (val) locInput.value = val;
                locSuggest.setAttribute('hidden', '');
                locSuggest.innerHTML = '';
            }

            locInput.addEventListener('input', function(e) {
                fetchLoc(e.target.value);
            });
            locInput.addEventListener('focus', function(e) {
                fetchLoc(e.target.value);
            });
            locInput.addEventListener('blur', function() {
                setTimeout(function() {
                    locSuggest.setAttribute('hidden', '');
                }, 150);
            });
            locSuggest.addEventListener('mousedown', function(e) {
                var item = e.target.closest('.hv-suggest__item');
                if (!item) return;
                e.preventDefault();
                selectLoc(item);
            });
            locInput.addEventListener('keydown', function(e) {
                if (locSuggest.hasAttribute('hidden')) return;
                var items = [].slice.call(locSuggest.querySelectorAll('.hv-suggest__item'));
                if (!items.length) return;
                var idx = items.findIndex(function(x) {
                    return x.getAttribute('aria-selected') === 'true';
                });
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    idx = (idx + 1) % items.length;
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    idx = (idx - 1 + items.length) % items.length;
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    selectLoc(items[Math.max(idx, 0)]);
                    return;
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    locSuggest.setAttribute('hidden', '');
                    return;
                }
                items.forEach(function(x, i) {
                    x.setAttribute('aria-selected', i === idx ? 'true' : 'false');
                });
            });

            /* ------- SUBMIT ------- */
            form.addEventListener('submit', function(e) {
                // Nothing fancy: let the browser navigate to /search-result/?...
                // Params already named to match your existing filter code.
            });
        })();

        (function() {
            var hero = document.getElementById('hv-hero-form');
            if (!hero) return;

            hero.addEventListener('submit', function() {
                var cat = document.getElementById('hv-keywords-category');
                if (cat && !cat.value) cat.disabled = true; // disabled inputs aren't submitted
            });
        })();

        (function() {
            const ids = ['hv-guests', 'hv-space'];

            function fixInlineStyles() {
                ids.forEach(function(id) {
                    var el = document.getElementById(id);
                    if (!el) return;

                    // Prefer: remove any inline styles
                    el.removeAttribute('style');

                    // Optional fallback: force your border inline with !important
                    // el.style.setProperty('border', '1px solid #e3e6ea', 'important');
                    // el.style.setProperty('height', '48px', 'important');
                    // el.style.setProperty('border-radius', '10px', 'important');
                    // el.style.setProperty('padding', '0 14px', 'important');
                });
            }

            document.addEventListener('DOMContentLoaded', fixInlineStyles);

            // If Elementor or AJAX re-renders, keep cleaning
            new MutationObserver(fixInlineStyles).observe(document.documentElement, {
                childList: true,
                subtree: true
            });
        })();


        (function() {
            document.addEventListener('click', function(e) {
                const dd = e.target.closest('.hv-dd');
                const isToggle = e.target.closest('.hv-dd__toggle');

                // close others when clicking outside
                if (!dd) {
                    document.querySelectorAll('.hv-dd__menu:not([hidden])').forEach(m => m.hidden = true);
                    document.querySelectorAll('.hv-dd__toggle[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
                    return;
                }

                if (isToggle) {
                    const btn = dd.querySelector('.hv-dd__toggle');
                    const menu = dd.querySelector('.hv-dd__menu');
                    const open = btn.getAttribute('aria-expanded') === 'true';
                    btn.setAttribute('aria-expanded', String(!open));
                    menu.hidden = open;
                    if (!open) setTimeout(() => menu.focus(), 0);
                    return;
                }

                const opt = e.target.closest('.hv-dd__opt');
                if (opt) {
                    const v = opt.getAttribute('data-value') || '';
                    const label = opt.textContent.trim() || 'Space type';
                    dd.querySelector('input[type="hidden"]').value = v;
                    dd.querySelector('.hv-dd__label').textContent = label;
                    dd.querySelectorAll('.hv-dd__opt[aria-selected="true"]').forEach(li => li.removeAttribute('aria-selected'));
                    opt.setAttribute('aria-selected', 'true');
                    dd.querySelector('.hv-dd__menu').hidden = true;
                    dd.querySelector('.hv-dd__toggle').setAttribute('aria-expanded', 'false');
                }
            });

            // basic keyboard support
            document.addEventListener('keydown', function(e) {
                const openMenu = document.querySelector('.hv-dd__menu:not([hidden])');
                if (!openMenu) return;
                const items = Array.from(openMenu.querySelectorAll('.hv-dd__opt'));
                if (!items.length) return;

                let idx = items.findIndex(li => li.getAttribute('aria-selected') === 'true');
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    idx = (idx + 1 + items.length) % items.length;
                    items[idx].setAttribute('aria-selected', 'true');
                    items[(idx - 1 + items.length) % items.length]?.removeAttribute('aria-selected');
                    items[idx].scrollIntoView({
                        block: 'nearest'
                    });
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    idx = (idx - 1 + items.length) % items.length;
                    items[idx].setAttribute('aria-selected', 'true');
                    items[(idx + 1) % items.length]?.removeAttribute('aria-selected');
                    items[idx].scrollIntoView({
                        block: 'nearest'
                    });
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    items[Math.max(idx, 0)].click();
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    openMenu.hidden = true;
                    openMenu.closest('.hv-dd').querySelector('.hv-dd__toggle').setAttribute('aria-expanded', 'false');
                    openMenu.closest('.hv-dd').querySelector('.hv-dd__toggle').focus();
                }
            });
        })();
    </script>
    <script>
        // Space type default selection - first option
        (function() {
            const dd = document.querySelector('.hv-dd[data-name="capacity_type"]');
            if (!dd) return;

            const input = dd.querySelector('input[type="hidden"]'); // #hv-space
            const labelEl = dd.querySelector('.hv-dd__label');
            const firstOpt = dd.querySelector('.hv-dd__menu .hv-dd__opt'); // first LI (Standing)

            function setDefaultIfEmpty() {
                if (!input || input.value) return; // already set via query or user click
                if (!firstOpt) return;

                const v = firstOpt.getAttribute('data-value') || '';
                const label = firstOpt.textContent.trim() || 'Space type';

                input.value = v;
                if (labelEl) labelEl.textContent = label;

                // visual selection
                dd.querySelectorAll('.hv-dd__opt[aria-selected="true"]').forEach(li => li.removeAttribute('aria-selected'));
                firstOpt.setAttribute('aria-selected', 'true');
            }

            // 1) On load
            setDefaultIfEmpty();

            // 2) Ensure it before submit (just in case)
            const form = document.getElementById('hv-hero-form');
            if (form) form.addEventListener('submit', setDefaultIfEmpty);
        })();
    </script>


<?php
    return ob_get_clean();
});

function listeo_map_shortcode()
{
    // Get the map zoom option with a fallback
    $map_zoom = get_option('listeo_map_zoom_global', 9);

    ob_start();
?>
    <div id="map-container" class="">
        <div
            id="map"
            class="split-map"
            data-map-zoom="<?php echo esc_attr($map_zoom); ?>"
            data-map-scroll="true">
        </div>
    </div>
<?php
    return ob_get_clean();
}
add_shortcode('listeo_map', 'listeo_map_shortcode');


/**
 * Listeo Listing Filters – Shortcode ONLY
 * Usage: [listeo_listing_filters]
 */
add_shortcode('listeo_listing_filters', function () {

    ob_start();
?>

    <section class="search custom-search-bar">
        <div class="row">
            <div class="col-md-12">

                <div class="lc-accordion">
                    <form id="js-capacity-filter" class="listing-capacity-filter" method="get">

                        <?php
                        $q_capacity_type = isset($_GET['capacity_type']) ? sanitize_key($_GET['capacity_type']) : '';
                        $q_capacity_min  = isset($_GET['capacity_min']) ? (int) $_GET['capacity_min'] : '';
                        $q_keywords      = isset($_GET['keywords']) ? sanitize_text_field($_GET['keywords']) : '';
                        $q_location      = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';

                        $selected_features = isset($_GET['listing_feature']) ? (array) $_GET['listing_feature'] : [];
                        $terms = get_terms(['taxonomy' => 'listing_feature', 'hide_empty' => false]);

                        $meta_flags_map = function_exists('lc_meta_flag_keys_map') ? lc_meta_flag_keys_map() : [];
                        $selected_mf = isset($_GET['mf']) ? (array) $_GET['mf'] : [];
                        ?>

                        <div class="acc-wrap">

                            <!-- Guests -->
                            <div class="acc-item" data-acc="guest">
                                <button class="acc-summary" type="button">
                                    <i class="fa fa-user"></i><span>No. of Guests</span>
                                    <i class="fa fa-chevron-down acc-caret"></i>
                                </button>
                                <div class="acc-panel" hidden>
                                    <input name="capacity_min" type="number"
                                        value="<?php echo esc_attr($q_capacity_min); ?>"
                                        class="ds-text-field__input"
                                        placeholder="Number of people">
                                </div>
                            </div>

                            <!-- Space Type -->
                            <div class="acc-item" data-acc="space-type">
                                <button class="acc-summary" type="button">
                                    <span>Space Type</span>
                                    <i class="fa fa-chevron-down acc-caret"></i>
                                </button>
                                <div class="acc-panel" hidden>
                                    <select name="capacity_type" class="ds-select">
                                        <option value="standing" <?php selected($q_capacity_type, 'standing'); ?>>Standing</option>
                                        <option value="dining" <?php selected($q_capacity_type, 'dining'); ?>>Dining</option>
                                        <option value="cabaret" <?php selected($q_capacity_type, 'cabaret'); ?>>Cabaret</option>
                                        <option value="classroom" <?php selected($q_capacity_type, 'classroom'); ?>>Classroom</option>
                                        <option value="theatre" <?php selected($q_capacity_type, 'theatre'); ?>>Theatre</option>
                                        <option value="ushaped" <?php selected($q_capacity_type, 'ushaped'); ?>>U-Shaped</option>
                                        <option value="boardroom" <?php selected($q_capacity_type, 'boardroom'); ?>>Boardroom</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Keywords -->
                            <div class="acc-item" data-acc="keywords">
                                <button class="acc-summary" type="button">
                                    <i class="fa fa-search"></i><span>Event Type</span>
                                    <i class="fa fa-chevron-down acc-caret"></i>
                                </button>
                                <div class="acc-panel" hidden>
                                    <!--                 <input name="keywords" type="text"
                       value="<? php // echo esc_attr($q_keywords); 
                                ?>"
                       class="ds-text-field__input"
                       placeholder="e.g. rooftop, birthday"> -->


                                    <input type="text"
                                        id="keywords"
                                        name="keywords"
                                        value="<?php echo esc_attr($_GET['keywords'] ?? ''); ?>"
                                        placeholder="Please start typing and select from dropdown"
                                        autocomplete="off" />

                                    <!-- this is the important part -->
                                    <input type="hidden"
                                        id="keywords_category"
                                        name="listing_category"
                                        value="<?php echo esc_attr($_GET['listing_category'] ?? ''); ?>" />

                                    <!-- suggestion dropdown -->
                                    <div id="keywords-suggest" class="lc-suggest" hidden></div>


                                </div>
                            </div>


                            <!-- Location -->
                            <div class="acc-item" data-acc="location">
                                <button class="acc-summary" type="button">
                                    <i class="fa fa-map-marker"></i><span>Location</span>
                                    <i class="fa fa-chevron-down acc-caret"></i>
                                </button>
                                <div class="acc-panel" hidden>
                                    <input name="location" type="text"
                                        value="<?php echo esc_attr($q_location); ?>"
                                        class="ds-text-field__input"
                                        placeholder="City or area">
                                </div>
                            </div>

                            <!-- More Filters -->
                            <div class="acc-item">
                                <button class="acc-summary" type="button" data-open="more-filters">
                                    <i class="fa fa-sliders"></i><span>More Filters</span>
                                </button>
                            </div>

                            <!-- Buttons -->
                            <div class="lc-toolbar">
                                <button type="submit" class="ds-button ds-button--green">Search</button>
                                <button type="button" id="js-capacity-clear" class="ds-button ds-button--inverse">Clear</button>
                            </div>

                        </div>

                        <!-- Modal -->
                        <div id="more-filters-modal" class="mf-modal">
                            <div class="mf-dialog">
                                <div class="mf-header">
                                    <h3>More Filters</h3>
                                    <button data-close="more-filters">&times;</button>
                                </div>

                                <div class="mf-body">
                                    <h4>Listing Features</h4>
                                    <?php foreach ($terms as $t): ?>
                                        <label class="mf-check">
                                            <input type="checkbox" name="listing_feature[]"
                                                value="<?php echo esc_attr($t->slug); ?>"
                                                <?php checked(in_array($t->slug, $selected_features, true)); ?>>
                                            <?php echo esc_html($t->name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mf-footer">
                                    <button data-close="more-filters">Close</button>
                                    <button type="submit">Apply</button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>

            </div>
        </div>
    </section>

    <style>
        .acc-wrap {
            display: flex;
            gap: 10px;
            flex-wrap: wrap
        }

        .acc-item {
            position: relative
        }

        .acc-summary {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 999px;
            background: #fafafa;
            cursor: pointer
        }

        .acc-panel {
            position: absolute;
            top: 100%;
            background: #fff;
            padding: 12px;
            border: 1px solid #ddd;
            z-index: 99
        }

        .mf-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .5)
        }

        .mf-modal.is-open {
            display: flex;
            align-items: center;
            justify-content: center
        }

        .mf-dialog {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            width: 500px
        }

        .lc-toolbar {
            display: flex;
            gap: 10px
        }
    </style>

    <script>
        // (function(){
        //   document.querySelectorAll('.acc-summary').forEach(btn=>{
        //     btn.addEventListener('click',()=>{
        //       const panel = btn.nextElementSibling;
        //       if(panel) panel.hidden = !panel.hidden;
        //     });
        //   });

        //   document.querySelector('[data-open="more-filters"]')?.addEventListener('click',()=>{
        //     document.getElementById('more-filters-modal')?.classList.add('is-open');
        //   });

        //   document.querySelectorAll('[data-close="more-filters"]').forEach(b=>{
        //     b.addEventListener('click',()=>{
        //       document.getElementById('more-filters-modal')?.classList.remove('is-open');
        //     });
        //   });

        //   document.getElementById('js-capacity-clear')?.addEventListener('click',()=>{
        //     document.getElementById('js-capacity-filter').reset();
        //   });
        // })();
    </script>

    <script>
        (function() {
            // Accordion: only one open at a time
            document.querySelectorAll('.acc-wrap').forEach(function(wrap) {

                function closeAll(exceptPanel) {
                    wrap.querySelectorAll('.acc-panel').forEach(function(p) {
                        if (exceptPanel && p === exceptPanel) return;
                        p.hidden = true;
                    });
                }

                // Search page Keywords suggestion
                document.addEventListener('DOMContentLoaded', function() {
                    var f = document.getElementById('js-capacity-filter');
                    if (!f) return;

                    f.addEventListener('submit', function() {
                        var cat = document.getElementById('keywords_category');
                        if (cat && !cat.value) cat.disabled = true; // disabled inputs don't submit
                    });
                });


                wrap.querySelectorAll('.acc-summary').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();

                        var panel = btn.nextElementSibling;
                        // Only handle items that actually have a panel
                        if (!panel || !panel.classList.contains('acc-panel')) return;

                        var willOpen = panel.hidden; // if hidden now, it will open
                        closeAll(); // close others
                        panel.hidden = !willOpen; // toggle this one
                    });
                });

                // Close panels when clicking outside the wrap
                document.addEventListener('click', function(e) {
                    if (!wrap.contains(e.target)) closeAll();
                });
            });

            // Modal open/close (your existing code)
            document.querySelector('[data-open="more-filters"]')?.addEventListener('click', function() {
                document.getElementById('more-filters-modal')?.classList.add('is-open');
            });

            document.querySelectorAll('[data-close="more-filters"]').forEach(function(b) {
                b.addEventListener('click', function() {
                    document.getElementById('more-filters-modal')?.classList.remove('is-open');
                });
            });

            document.getElementById('js-capacity-clear')?.addEventListener('click', function() {
                var f = document.getElementById('js-capacity-filter');
                if (!f) return;

                f.reset();

                // Also close any open accordion panels after clearing
                f.querySelectorAll('.acc-panel').forEach(function(p) {
                    p.hidden = true;
                });
            });
        })();
    </script>


<?php
    return ob_get_clean();
});

/**
 * Replace "Bookmark(s)" with "Favourite(s)" in translated strings.
 */
add_filter('gettext', function ($translated, $text, $domain) {

    // Only replace exact matches (safer than replacing every occurrence)
    $map = [
        'Bookmark'  => 'Favourite',
        'Bookmarks' => 'Favourites',
    ];

    return $map[$text] ?? $translated;
}, 20, 3);



// Search by Location updates
require_once get_stylesheet_directory() . '/listeo-address-autofill/listing-address-autofill.php';

add_action('wp_enqueue_scripts', function () {
    $rel  = '/listeo-address-autofill/assets/js/location-suggest.js';
    $path = get_stylesheet_directory() . $rel;
    $ver  = file_exists($path) ? filemtime($path) : time();

    wp_enqueue_script(
        'hv-location-suggest',
        get_stylesheet_directory_uri() . $rel,
        [],
        $ver,
        true
    );

    wp_add_inline_script(
        'hv-location-suggest',
        'window.hvAjaxUrl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';',
        'before'
    );
});



// Listing auto filled - one time seeding
//require_once get_stylesheet_directory() . '/listeo-address-autofill/backfill-from-address.php';



// long + lan finder

/**
 * Shortcode: [hv_geo_report]
 * Admin-only report: counts + lists listings with missing/blank _geolocation_lat/_geolocation_long.
 *
 * Optional attributes:
 * - limit="200" (default 200)
 * - mode="either" | "both" | "lat" | "long" (default "either")
 */
add_shortcode('hv_geo_report', function ($atts) {
    if (!is_user_logged_in() || !current_user_can('manage_options')) {
        return '<p>Not allowed.</p>';
    }

    $atts = shortcode_atts([
        'limit' => 200,
        'mode'  => 'either', // either|both|lat|long
    ], $atts, 'hv_geo_report');

    $limit = max(1, (int) $atts['limit']);
    $mode  = sanitize_key($atts['mode']);

    // Helper meta "is missing or empty"
    $missing_lat = [
        'relation' => 'OR',
        ['key' => '_geolocation_lat',  'compare' => 'NOT EXISTS'],
        ['key' => '_geolocation_lat',  'value' => '', 'compare' => '='],
    ];
    $missing_lng = [
        'relation' => 'OR',
        ['key' => '_geolocation_long', 'compare' => 'NOT EXISTS'],
        ['key' => '_geolocation_long', 'value' => '', 'compare' => '='],
    ];

    // Build filter for the list
    if ($mode === 'both') {
        $meta_query = ['relation' => 'AND', $missing_lat, $missing_lng];
    } elseif ($mode === 'lat') {
        $meta_query = $missing_lat;
    } elseif ($mode === 'long') {
        $meta_query = $missing_lng;
    } else { // either
        $meta_query = ['relation' => 'OR', $missing_lat, $missing_lng];
    }

    // Counts (fast enough; separate lightweight queries)
    $count_total = (int) (new WP_Query([
        'post_type'      => 'listing',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]))->found_posts;

    $count_missing_lat = (int) (new WP_Query([
        'post_type'      => 'listing',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => $missing_lat,
    ]))->found_posts;

    $count_missing_lng = (int) (new WP_Query([
        'post_type'      => 'listing',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => $missing_lng,
    ]))->found_posts;

    $count_missing_both = (int) (new WP_Query([
        'post_type'      => 'listing',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => ['relation' => 'AND', $missing_lat, $missing_lng],
    ]))->found_posts;

    $count_missing_either = (int) (new WP_Query([
        'post_type'      => 'listing',
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_query'     => ['relation' => 'OR', $missing_lat, $missing_lng],
    ]))->found_posts;

    // Listing table
    $q = new WP_Query([
        'post_type'      => 'listing',
        'post_status'    => 'any',
        'posts_per_page' => $limit,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => $meta_query,
    ]);

    ob_start();
?>
    <div class="hv-geo-report">
        <h3>Geolocation Report (Listings)</h3>

        <table class="widefat striped" style="max-width:1000px;">
            <tbody>
                <tr>
                    <td><strong>Total listings</strong></td>
                    <td><?php echo esc_html($count_total); ?></td>
                </tr>
                <tr>
                    <td><strong>Missing/blank lat</strong></td>
                    <td><?php echo esc_html($count_missing_lat); ?></td>
                </tr>
                <tr>
                    <td><strong>Missing/blank long</strong></td>
                    <td><?php echo esc_html($count_missing_lng); ?></td>
                </tr>
                <tr>
                    <td><strong>Missing/blank BOTH</strong></td>
                    <td><?php echo esc_html($count_missing_both); ?></td>
                </tr>
                <tr>
                    <td><strong>Missing/blank EITHER</strong></td>
                    <td><?php echo esc_html($count_missing_either); ?></td>
                </tr>
            </tbody>
        </table>

        <p style="margin-top:12px;">
            <strong>Mode:</strong> <?php echo esc_html($mode); ?> |
            <strong>Showing:</strong> <?php echo esc_html(min($limit, (int)$q->found_posts)); ?> of <?php echo esc_html((int)$q->found_posts); ?>
        </p>

        <table class="widefat striped" style="max-width:1000px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Latitude</th>
                    <th>Longitude</th>
                    <th>Address</th>
                    <th>Edit</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($q->have_posts()): while ($q->have_posts()): $q->the_post();
                        $id   = get_the_ID();
                        $lat  = get_post_meta($id, '_geolocation_lat', true);
                        $lng  = get_post_meta($id, '_geolocation_long', true);
                        $addr = get_post_meta($id, '_address', true);
                ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html($id); ?>
                                </a>
                            </td>

                            <td>
                                <a href="<?php echo esc_url(get_permalink($id)); ?>" target="_blank" rel="noopener">
                                    <?php echo esc_html(get_the_title()); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($lat); ?></td>
                            <td><?php echo esc_html($lng); ?></td>
                            <td><?php echo esc_html($addr); ?></td>
                            <td><a href="<?php echo esc_url(get_edit_post_link($id)); ?>" target="_blank" rel="noopener">Edit</a></td>
                        </tr>
                    <?php endwhile;
                    wp_reset_postdata();
                else: ?>
                    <tr>
                        <td colspan="6">No results.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
    return ob_get_clean();
});




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