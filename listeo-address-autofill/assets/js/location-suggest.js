(function () {
  function esc(s){
    return String(s||'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
  }

  async function fetchSuggest(q){
    const url = (window.hvAjaxUrl || '/wp-admin/admin-ajax.php')
      + '?action=lc_location_suggest&q=' + encodeURIComponent(q);

    const res = await fetch(url, { credentials: 'same-origin' });
    const data = await res.json().catch(()=>null);
    return (data && data.success && data.data && data.data.items) ? data.data.items : [];
  }

  function ensureSuggestBox(input){
    // Create a sibling dropdown for THIS input (not global)
    let box = input.parentElement && input.parentElement.querySelector('.hv-location-suggest');
    if (box) return box;

    box = document.createElement('div');
    box.className = 'hv-location-suggest';
    box.style.border = '1px solid #ddd';
    box.style.borderRadius = '10px';
    box.style.marginTop = '8px';
    box.style.display = 'none';
    box.style.overflow = 'hidden';
    box.style.background = '#fff';
    box.style.position = 'absolute';
    box.style.top = '55px';
    box.style.zIndex = '9999';

    input.insertAdjacentElement('afterend', box);
    return box;
  }

  function render(box, items){
    if (!items || !items.length){
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }

    box.innerHTML = items.map(it => {
    //   const thumb = it.thumb
    //     ? `<img src="${esc(it.thumb)}" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:8px;flex:0 0 42px;">`
    //     : '';
    
      const thumb = '';
      const title = '';
      const addr  = esc(it.address || it.value || '');
      const value = esc(it.value || it.address || it.title || '');

      return `
        <div class="hv-loc-item" data-value="${value}"
             style="display:flex;gap:10px;align-items:center;padding:10px 12px;cursor:pointer;border-bottom:1px solid #eee;">
          ${thumb}
          <div style="min-width:0;">
            <div style="font-weight:600;line-height:1.2;">${title}</div>
            <div style="font-size:14px;opacity:.8;line-height:1.2;margin-top:3px;">${addr}</div>
          </div>
        </div>
      `;
    }).join('');

    const last = box.querySelector('.hv-loc-item:last-child');
    if (last) last.style.borderBottom = 'none';

    box.style.display = 'block';
  }

  function bindOne(input){
    if (!input || input.dataset.hvSuggestBound === '1') return;
    input.dataset.hvSuggestBound = '1';

    const box = ensureSuggestBox(input);
    let timer = null;

    input.addEventListener('input', function(){
      const q = input.value.trim();
      if (q.length < 2) { render(box, []); return; }

      clearTimeout(timer);
      timer = setTimeout(async () => {
        const items = await fetchSuggest(q);
        render(box, items);
      }, 250);
    });

    box.addEventListener('click', function(e){
      const item = e.target.closest('.hv-loc-item');
      if (!item) return;
      input.value = item.getAttribute('data-value') || '';
      render(box, []);
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });

    document.addEventListener('click', function(e){
      if (e.target === input) return;
      if (box.contains(e.target)) return;
      render(box, []);
    });
  }

  function bindAll(){
    // 1) Your existing header field
    const hv = document.getElementById('hv-location');
    if (hv) bindOne(hv);

    // 2) Search-result / filter field(s)
    document.querySelectorAll('input[name="location"]').forEach(bindOne);
  }

  function boot(){
    bindAll();

    // Pages like search-result often render filters dynamically
    const obs = new MutationObserver(bindAll);
    obs.observe(document.body, { childList: true, subtree: true });
    setTimeout(() => obs.disconnect(), 60000);
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
