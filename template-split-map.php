<?php
/**
 * Template Name: Listing With Map - Split Page
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package Listeo
 */

$full_width_header = get_option('listeo_full_width_header');
if ($full_width_header == 'enable' || $full_width_header == 'true') {
  get_header('fullwidthnosearch');
} else {
  get_header('split');
}
?>
<div class="fs-container full-listing-container">
  <div class="fs-inner-container content">
    <div class="fs-content">

<!-- Search -->
<section class="search custom-search-bar">
  <a href="#" id="show-map-button" class="show-map-button"
     data-enabled="<?php esc_attr_e('Show Map ','listeo'); ?>"
     data-disabled="<?php esc_attr_e('Hide Map ','listeo'); ?>">
     <?php esc_html_e('Show Map ','listeo') ?>
  </a>

  <div class="row">
    <div class="col-md-12">

      <div class="lc-accordion">
        <form id="js-capacity-filter" class="listing-capacity-filter" action="" method="get">
          <?php
            // Current query
            $q_capacity_type = isset($_GET['capacity_type']) ? sanitize_key($_GET['capacity_type']) : '';
            $q_capacity_min  = isset($_GET['capacity_min'])  ? (int) $_GET['capacity_min'] : '';
            $q_keywords      = isset($_GET['keywords'])      ? sanitize_text_field($_GET['keywords']) : '';
            $q_location      = isset($_GET['location'])      ? sanitize_text_field($_GET['location']) : '';

            // Price slider setup
            $GLOBAL_MIN = 0;  $GLOBAL_MAX = 10000;  $STEP = 10;
            $cur_min = isset($_GET['price_min']) ? (int) $_GET['price_min'] : $GLOBAL_MIN;
            $cur_max = isset($_GET['price_max']) ? (int) $_GET['price_max'] : $GLOBAL_MAX;
            if ($cur_min < $GLOBAL_MIN) $cur_min = $GLOBAL_MIN;
            if ($cur_max > $GLOBAL_MAX) $cur_max = $GLOBAL_MAX;
            if ($cur_min > $cur_max) { $t=$cur_min; $cur_min=$cur_max; $cur_max=$t; }

            // More filters (modal)
            $selected_features = isset($_GET['listing_feature']) ? (array) $_GET['listing_feature'] : [];
            $terms = get_terms(['taxonomy'=>'listing_feature','hide_empty'=>false]);
            $meta_flags_map = function_exists('lc_meta_flag_keys_map') ? lc_meta_flag_keys_map() : [];
            $selected_mf = isset($_GET['mf']) ? (array) $_GET['mf'] : [];
          ?>

          <div class="acc-wrap">

            <!-- 1) No. of Guest -->
            <div class="acc-item" data-acc="guest">
              <button class="acc-summary" type="button" aria-expanded="false">
                <i class="fa fa-user" aria-hidden="true"></i>
                <span>No. of Guests</span>
                <i class="fa fa-chevron-down acc-caret" aria-hidden="true"></i>
              </button>
              <div class="acc-panel" hidden>
                <div class="ds-field">
                  <label class="ds-form-label" for="capacity_min">Minimum Guests</label>
                  <input id="capacity_min" name="capacity_min" type="number" min="0" step="1"
                         class="ds-text-field__input" placeholder="Number of people"
                         value="<?php echo esc_attr($q_capacity_min); ?>">
                </div>
              </div>
            </div>

            <!-- 2) Space Type -->
            <div class="acc-item" data-acc="space-type">
              <button class="acc-summary" type="button" aria-expanded="false">
                <img src="https://heyvenues.tbcserver16.com/wp-content/uploads/2025/09/Standing.png"
                     alt="" width="16" height="16" class="acc-img-icon">
                <span>Space Type</span>
                <i class="fa fa-chevron-down acc-caret" aria-hidden="true"></i>
              </button>
              <div class="acc-panel" hidden>
                <div class="ds-field">
                  <label class="ds-form-label" for="capacity_type">Space Type</label>
                  <select id="capacity_type" name="capacity_type" class="ds-select">
<!--                     <option value="">All</option> -->
                    <option value="standing"  <?php selected($q_capacity_type,'standing');  ?>>Standing</option>
                    <option value="dining"    <?php selected($q_capacity_type,'dining');    ?>>Dining</option>
                    <option value="cabaret"   <?php selected($q_capacity_type,'cabaret');   ?>>Cabaret</option>
                    <option value="classroom" <?php selected($q_capacity_type,'classroom'); ?>>Classroom</option>
                    <option value="theatre"   <?php selected($q_capacity_type,'theatre');   ?>>Theatre</option>
                    <option value="ushaped"   <?php selected($q_capacity_type,'ushaped');   ?>>U-Shaped</option>
                    <option value="boardroom" <?php selected($q_capacity_type,'boardroom'); ?>>Boardroom</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- 3) Keywords with terms -->
            <div class="acc-item" data-acc="keywords">
              <button class="acc-summary" type="button" aria-expanded="false">
                <i class="fa fa-search" aria-hidden="true"></i>
                <span>Keywords</span>
                <i class="fa fa-chevron-down acc-caret" aria-hidden="true"></i>
              </button>
              <div class="acc-panel" hidden>
                <div class="ds-field">
                    <label class="ds-form-label" for="keywords">Keywords</label>
                    <!--<input id="keywords" name="keywords" type="text" class="ds-text-field__input"-->
                    <!--     placeholder="e.g. festivals, birthday, rooftop"-->
                    <!--     value="<?php// echo esc_attr($q_keywords); ?>">-->
                         
                    <input type="text"
                         id="keywords"
                         name="keywords"
                         value="<?php echo esc_attr( $_GET['keywords'] ?? '' ); ?>"
                         placeholder="Keywords"
                         autocomplete="off" />
                         
                    <!-- this is the important part -->
                    <input type="hidden"
                         id="keywords_category"
                         name="listing_category"
                         value="<?php echo esc_attr( $_GET['listing_category'] ?? '' ); ?>" />
                         
                  <!--<input type="hidden" name="listing_category" id="keywords_category"
                    value="<?php// echo isset($_GET['listing_category']) ? esc_attr(is_array($_GET['listing_category']) ? reset($_GET['listing_category']) : $_GET['listing_category']) : ''; ?>">-->
                    
                    <!-- suggestion dropdown -->
                  <div id="keywords-suggest" class="lc-suggest" hidden></div>
                </div>
              </div>
            </div>

            <!-- 4) Location -->
            <div class="acc-item" data-acc="location">
              <button class="acc-summary" type="button" aria-expanded="false">
                <i class="fa fa-map-marker" aria-hidden="true"></i>
                <span>Location</span>
                <i class="fa fa-chevron-down acc-caret" aria-hidden="true"></i>
              </button>
              <div class="acc-panel" hidden>
                <div class="ds-field">
                  <label class="ds-form-label" for="location">Location</label>
                  <div id="autocomplete-container">
                    <input autocomplete="off" name="location" id="location" type="text"
                           placeholder="City, area or address"
                           value="<?php echo esc_attr($q_location); ?>" />
                    <div id="location-suggest" class="lc-suggest" hidden></div>
                  </div>
				<!--  <a href="#" class="lc-geolocate">
                    <i class="tooltip left fa fa-map-marker" title="<?php //esc_attr_e('Find My Location','listeo_core'); ?>"></i>
                  </a> -->
                  <input type="hidden" name="location_term" id="location_term"
                         value="<?php echo isset($_GET['location_term'])? esc_attr($_GET['location_term']) : ''; ?>">
                  <input type="hidden" name="location_tax" id="location_tax"
                         value="<?php echo isset($_GET['location_tax']) ? esc_attr($_GET['location_tax']) : 'location'; ?>">
                </div>
              </div>
            </div>

            <!-- 5) More Filters (chip that opens modal) -->
            <div class="acc-item" data-acc="more-filters">
              <button class="acc-summary" type="button" data-open="more-filters" aria-expanded="false">
                <i class="fa fa-sliders" aria-hidden="true"></i>
                <span>More Filters</span>
                <i class="fa fa-chevron-down acc-caret" aria-hidden="true"></i>
              </button>
            </div>

          <!-- Inline toolbar -->
          <div class="lc-toolbar">
            <!-- <button type="button" class="ds-button" data-open="more-filters">
              <i class="fa fa-sliders" aria-hidden="true"></i> More Filters
            </button> -->
            <button type="submit" class="ds-button ds-button--green brand-bg-color"><!-- <i class="fa fa-search" aria-hidden="true"></i> -->Search</button>
            <button type="button" id="js-capacity-clear" class="ds-button ds-button--inverse">Clear</button>
          </div>
          </div><!-- /.acc-wrap -->



          <!-- Modal (unchanged) -->
          <div id="more-filters-modal" class="mf-modal" aria-hidden="true">
            <div class="mf-dialog" role="dialog" aria-modal="true" aria-labelledby="mf-title">
              <div class="mf-header">
                <h3 id="mf-title">More Filters</h3>
                <button type="button" class="mf-close" data-close="more-filters" aria-label="Close">&times;</button>
              </div>

              <div class="mf-body">
                <div class="mf-section">
                  <h4 class="mf-section-title">Listing Features</h4>
                  <div class="mf-grid">
                    <?php if (!is_wp_error($terms) && $terms): foreach ($terms as $t): ?>
                      <label class="mf-check">
                        <input type="checkbox" name="listing_feature[]"
                               value="<?php echo esc_attr($t->slug); ?>"
                               <?php checked(in_array($t->slug, $selected_features, true)); ?>>
                        <span><?php echo esc_html($t->name); ?></span>
                      </label>
                    <?php endforeach; endif; ?>
                  </div>
                </div>

                <hr class="more--options__hr"/>

                <div class="mf-section">
                  <h4 class="mf-section-title">Amenities &amp; Options</h4>
                  <div class="mf-grid">
                    <?php foreach ($meta_flags_map as $key => $label): ?>
                      <label class="mf-check">
                        <input type="checkbox" name="mf[]" value="<?php echo esc_attr($key); ?>"
                               <?php checked(in_array($key, $selected_mf, true)); ?>>
                        <span><?php echo esc_html($label); ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

              <div class="mf-footer">
                <button type="button" class="ds-button ds-button--inverse" data-close="more-filters">Close</button>
                <button type="submit" class="ds-button ds-button--green" data-close-on-apply>
                  <i class="fa fa-search" aria-hidden="true"></i> Apply
                </button>
              </div>
            </div>
          </div>
          <!-- /Modal -->

        </form>
      </div>

      <style>
        /* Accordion chips */
        .acc-wrap{ display:flex; flex-wrap:wrap; gap:10px; align-items:flex-start; }
        .acc-item{ position:relative; display:inline-flex; flex-direction:column; width:auto; }
        .acc-summary{
          display:inline-flex; align-items:center; gap:8px;
          padding:8px 12px; background:#fafafa; border:1px solid #e5e7eb; border-radius:999px;
          cursor:pointer; font-weight:600; line-height:1.2; white-space:nowrap;
        }
        .acc-summary:focus-visible{ outline:2px solid #ad97c8; outline-offset:2px; }
        .acc-img-icon{ display:block; width:16px; height:16px; object-fit:contain; }
        .acc-caret{ transition: transform .15s ease; }
        .acc-item.is-open .acc-caret{ transform: rotate(180deg); } /* down -> up */

        /* Floating panel (desktop) */
        .acc-panel{
          position:absolute; top:calc(100% + 8px); left:0; width:min(92vw, 400px); max-width:400px;
          background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 24px rgba(0,0,0,.12);
          padding:12px; z-index:2000;
        }
        .acc-panel .ds-field{ margin:8px 0; }

        /* Mobile: dock panels */
        @media (max-width: 767.98px){
          .acc-item{ width:100%; }
          .acc-summary{ width:100%; justify-content:space-between; border-radius:10px; }
          .acc-panel{ position:static; width:100%; max-width:none; box-shadow:none; margin-top:8px; }
        }

        /* Toolbar */
.lc-toolbar{
  display:flex; align-items:center; gap:8px; flex-wrap:wrap;
}
.lc-toolbar .ds-button{
  display:inline-flex; align-items:center; gap:8px; border: 1px solid #ad97c8 !important;
  white-space:nowrap; /* keep labels on one line */
}

        /* Keep existing styles (from your sheet) */
        .search.custom-search-bar { padding: 13px 40px 0 !important; background-color: transparent !important; }
        .lc-suggest{ position:absolute; margin-top:4px; background:#fff; border:1px solid #e5e7eb; border-radius:8px;
          box-shadow:0 8px 24px rgba(0,0,0,.08); max-height:280px; overflow:auto; z-index:2147483646; width:94%; }
        .lc-suggest__item{ padding:8px 10px; cursor:pointer; display:flex; justify-content:space-between; gap:8px; }
        .lc-suggest__item[aria-selected="true"], .lc-suggest__item:hover{ background:#f3f4f6; }
        #keywords-suggest {margin-top:-10px;}
		.lc-suggest__count { display:none !important; }

        /* Modal basics preserved */
        html.mf-locked, html.mf-locked body { overflow:hidden!important; }
        .mf-modal{ position:fixed; inset:0; z-index:2147483647!important; display:none; align-items:center; justify-content:center;
          padding:20px; background:rgba(0,0,0,.45); }
        .mf-modal.is-open{ display:flex; }
        .mf-dialog{ display:flex; flex-direction:column; background:#fff; border-radius:12px; width:min(600px,96vw);
          max-height:90vh; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,.25); position:relative; z-index:1; }
        .mf-header,.mf-footer{ position:sticky; background:#fff; z-index:2; }
        .mf-header{ top:0; display:flex; justify-content:space-between; height:65px; padding:20px; border-bottom:1px solid #ddd; align-items:center; }
        .mf-footer{ bottom:0; display:flex; justify-content:space-between; height:65px; padding:20px; border-top:1px solid #ddd; align-items:center; }

        /* Buttons (keep your palette) */
        .mf-footer button, .custom-search-bar button{
          color:#ad97c8; border-color:#777 !important; border:1px solid; background:#fff; border-radius:30px;
          font-family:sans-serif; font-size:14px; min-width:75px; text-align:center; line-height:20px; font-weight:500;
          padding:9px; display:inline-flex; justify-content:center; gap:6px; align-items:center;
        }
        .mf-footer button:hover, .custom-search-bar button:hover{ color:#fff!important; background:#ad97c8!important; border: 1px solid #ad97c8 !important; }
		#js-capacity-clear {border: 1px solid #777 !important;}
		#js-capacity-clear:hover {border: 1px solid #ad97c8 !important;}
		  
@media (min-width: 1300px) and (max-width: 1400px) {		  
.mf-footer button,
.custom-search-bar button {font-size: 11px;}
}
	
@media (min-width: 1401px) and (max-width: 1500px) {		  
.mf-footer button,
.custom-search-bar button {font-size: 13px;}
}	
		 
@media (min-width: 1501px) and (max-width: 1600px) {		  
.mf-footer button,
.custom-search-bar button {font-size: 15px;}

}
@media (min-width: 1601px) and (max-width: 1645px) {
#listeo-listings-container.new-grid-layout-nl, .new-grid-layout-nl {
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
}	
}

@media (min-width: 100px) and (max-width: 1299px) {
		
#listeo-listings-container.new-grid-layout-nl, 
.new-grid-layout-nl {
    grid-template-columns: repeat(auto-fill, minmax(218px, 2fr)) !important;
}
	
}

		  
/* Modal */

html.mf-locked,
html.mf-locked body {
  overflow: hidden !important;
}
.mf-modal {
  position: fixed;
  inset: 0;
  z-index: 2147483647 !important;
  display: none;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(0, 0, 0, 0.45);
}
.mf-modal.is-open {
  display: flex;
}
.mf-dialog {
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  overflow: hidden;
  background: #fff;
  border-radius: 12px;
  width: min(600px, 96vw);
  max-height: 90vh;
  overflow: auto;
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
  position: relative;
  z-index: 1;
}
.mf-header h3 {
  margin: 0;
  font-size: 18px;
  font-weight: 600;
}
.mf-close {
  background: none;
  border: 0;
  font-size: 22px;
  cursor: pointer;
  line-height: 1;
}
.mf-body {
  flex: 1 1 auto;
  overflow: auto;
  padding: 30px;
}
.mf-header {
  top: 0;
  display: flex;
  justify-content: space-between;
  height: 65px;
  background: #fff;
  padding: 20px;
  border-bottom: 1px solid gray;
  align-items: center;
}
.mf-footer {
  bottom: 0;
  display: flex;
  justify-content: space-between;
  height: 65px;
  background: #fff;
  padding: 20px;
  border-top: 1px solid gray;
  align-items: center;
}
.mf-section-title {
  margin: 0 0 15px 0;
  font-size: 14px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: #000;
  font-weight: bold;
}
.mf-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 8px 16px;
}
.mf-check {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  margin-bottom: 0 !important;
}
.mf-header,
.mf-footer {
  position: sticky;
  background: #fff;
  z-index: 2;
}
.mf-header.is-scrolled {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.mf-footer.is-scrollable {
  box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.08);
}
.mf-body input[type="checkbox"] {
    display: inline;
    width: 23px;
    height: 23px;
	margin: 0;
}
		  
@media (min-width: 1025px) {
			  
.full-listing-container {
    display: flex;
    flex-direction: row-reverse;				  
}
.full-listing-container .map-fixed {
	left: 0;
}		  
			  
}
		  
		  
</style>

<script>
(function(){
  /* ---------- Accordion ---------- */
  function setOpen(item, open){
    const btn   = item.querySelector('.acc-summary');
    const panel = item.querySelector('.acc-panel');
    item.classList.toggle('is-open', !!open);
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (panel) panel.hidden = !open;
  }
  function closeOthers(except){
    document.querySelectorAll('.acc-item[data-acc]').forEach(it=>{
      if (it !== except && it.querySelector('.acc-panel')) setOpen(it, false);
    });
  }
  document.querySelectorAll('.acc-item[data-acc]').forEach(item=>{
    const btn = item.querySelector('.acc-summary');
    const pnl = item.querySelector('.acc-panel');
    if (pnl) pnl.hidden = true; // default closed
    if (!btn) return;
    btn.setAttribute('aria-expanded','false');
    btn.addEventListener('click', e=>{
      const willOpen = !item.classList.contains('is-open');
      closeOthers(item);
      setOpen(item, willOpen);
    });
  });
  document.addEventListener('mousedown', e=>{
    const inside = e.target.closest('.acc-item');
    if (!inside) closeOthers(null);
  });
  document.addEventListener('keydown', e=>{ if (e.key==='Escape') closeOthers(null); });

  /* ---------- Modal open/close ---------- */
  function openModal(){
    const m = document.getElementById('more-filters-modal');
    if (!m) return;
    m.classList.add('is-open'); m.setAttribute('aria-hidden','false');
    document.documentElement.classList.add('mf-locked');
  }
  function closeModal(){
    const m = document.getElementById('more-filters-modal');
    if (!m) return;
    m.classList.remove('is-open'); m.setAttribute('aria-hidden','true');
    document.documentElement.classList.remove('mf-locked');
  }
  document.addEventListener('click', function(e){
    const openBtn  = e.target.closest('[data-open="more-filters"]');
    const closeBtn = e.target.closest('[data-close="more-filters"]');
    if (openBtn){ e.preventDefault(); openModal(); }
    if (closeBtn){ e.preventDefault(); closeModal(); }
    const modal = document.getElementById('more-filters-modal');
    if (modal && e.target === modal) closeModal();
  });
  document.addEventListener('click', function(e){
    const applyBtn = e.target.closest('[data-close-on-apply]');
    if (applyBtn) closeModal();
  });

// When the keywords accordion opens, ensure the suggest box can appear
document.addEventListener('click', function(e){
  const btn = e.target.closest('.acc-item[data-acc="keywords"] .acc-summary');
  if (!btn) return;

  // After the panel toggles, if there are suggestions, force a refresh fetch
  setTimeout(function(){
    if (kwInput && kwInput.value) {
      // trigger suggest fetch again so UI redraws when panel becomes visible
      kwInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
  }, 50);
});


  /* ---------- Auto-apply with helper retry ---------- */
  const form = document.getElementById('js-capacity-filter');
  function debounce(fn, ms){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), ms); }; }

  // Try to call ajaxUpdate; if helpers not yet defined, retry a few times.
  function callAjaxWithRetry(params, keepExisting, attemptsLeft){
    if (typeof ajaxUpdate === 'function' &&
        typeof serializeFormToParams === 'function' &&
        typeof pruneDefaultPrice === 'function'){
      ajaxUpdate(params, keepExisting).catch(console.error);
      return;
    }
    if ((attemptsLeft||0) > 0){
      setTimeout(()=>callAjaxWithRetry(params, keepExisting, attemptsLeft-1), 250);
    }
  }

  if (form){
    const autoSubmit = debounce(function(){
      const modal = document.getElementById('more-filters-modal');
      if (modal && modal.classList.contains('is-open')) return;
      // Build params even if helpers aren't ready yet
      let params = {};
      try {
        const fd = new FormData(form);
        fd.forEach((v,k)=>{
          if (params[k] !== undefined){
            if (!Array.isArray(params[k])) params[k] = [params[k]];
            params[k].push(v);
          } else {
            params[k] = v;
          }
        });
      } catch(e){}
      // If helpers exist, prune defaults; otherwise send raw
      if (typeof pruneDefaultPrice === 'function'){
        params = pruneDefaultPrice(params);
      }
      callAjaxWithRetry(params, true, 12); // up to ~3s total
    }, 350);

    // live changes
    form.addEventListener('input', function(e){
      const el = e.target;
      if (!el) return;
      if (el.matches('#price_min_range, #price_max_range, #capacity_min, #keywords, #location')) autoSubmit();
    }, true);
    form.addEventListener('change', function(e){
      const el = e.target;
      if (!el) return;
      if (el.matches('#capacity_type')) autoSubmit();
    }, true);

    // Clear -> AJAX clean URL (with retry)
    const clearBtn = document.getElementById('js-capacity-clear');
    if (clearBtn){
      clearBtn.addEventListener('click', function(e){
        e.preventDefault();
        form.reset();
        callAjaxWithRetry({}, false, 12);
      });
    }
  }

  // Prevent Enter from submitting in text inputs (keeps accordion UX tidy)
  document.getElementById('keywords')?.addEventListener('keydown', e=>{ if (e.key==='Enter'){ e.preventDefault(); }});
  document.getElementById('location')?.addEventListener('keydown', e=>{ if (e.key==='Enter'){ e.preventDefault(); }});
})();
</script>

    </div>
  </div>
</section>
<!-- Search / End -->

      <section id="listeo-listings-container" class="listings-container margin-top-30">
        <div class="row fs-listings">
          <?php while ( have_posts() ) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('col-md-12'); ?>>
              <?php the_content(); // Your page content should include: [listings style="grid" per_page="6"] ?>
            </article>
          <?php endwhile; ?>
          <div class="col-md-12">
            <div class="copyrights margin-top-0">
              <?php
              $copyrights = get_option( 'pp_copyrights' , '&copy; Theme by Purethemes.net. All Rights Reserved.' );
              echo wp_kses($copyrights, ['a' => ['href'=>[],'title'=>[]],'br'=>[],'em'=>[],'strong'=>[]]);
              ?>
            </div>
          </div>
        </div>
      </section>

    </div>
  </div>

  <div class="fs-inner-container map-fixed">
    <!-- Map -->
    <div id="map-container" class="">
      <div id="map" class="split-map" data-map-zoom="<?php echo get_option('listeo_map_zoom_global',9); ?>" data-map-scroll="true"></div>
    </div>
  </div>
</div>
<div class="clearfix"></div>
<?php get_footer('empty'); ?>
