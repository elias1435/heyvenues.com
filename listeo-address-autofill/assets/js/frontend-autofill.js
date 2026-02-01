(function () {
  function byId(id) { return document.getElementById(id); }

  function setVal(id, val) {
    const el = byId(id);
    if (!el) return;
    el.value = val || "";
    el.dispatchEvent(new Event("input", { bubbles: true }));
    el.dispatchEvent(new Event("change", { bubbles: true }));
  }

  function getComponent(comps, type) {
    const c = comps.find(x => x.types && x.types.includes(type));
    return c ? c.long_name : "";
  }

  function fillFromComponents(comps, label) {
    const streetNumber = getComponent(comps, "street_number");
    const route        = getComponent(comps, "route");
    const line1        = [streetNumber, route].filter(Boolean).join(" ").trim();

    const subpremise   = getComponent(comps, "subpremise");
    const premise      = getComponent(comps, "premise");
    const neighborhood = getComponent(comps, "neighborhood");
    const line2        = [subpremise || premise, neighborhood].filter(Boolean).join(", ").trim();

    const postalTown   = getComponent(comps, "postal_town");
    const sublocality  = getComponent(comps, "sublocality") || getComponent(comps, "sublocality_level_1");
    const town         = postalTown || sublocality;

    const city         = getComponent(comps, "locality");
    const county       = getComponent(comps, "administrative_area_level_2");
    const postcode     = getComponent(comps, "postal_code");
    const country      = getComponent(comps, "country");

    setVal("_search_address_line_1", line1 || label || "");
    setVal("_search_address_line_2", line2);
    setVal("_search_town", town);
    setVal("_search_city", city);
    setVal("_search_postcode", postcode);
    setVal("_search_county", county);
    setVal("_search_country", country);
  }

  function geocode(value) {
    value = (value || "").trim();
    if (!value) return;
    if (!google?.maps?.Geocoder) return;

    const geocoder = new google.maps.Geocoder();
    geocoder.geocode({ address: value }, (results, status) => {
      if (status !== "OK" || !results?.[0]?.address_components) return;
      const r = results[0];
      fillFromComponents(r.address_components, r.formatted_address || value);
    });
  }

  let boundTo = null;
  let ac = null;

  function bind() {
    const addr = byId("_address");
    const targetExists = byId("_search_address_line_1"); // ensures your custom fields exist
    if (!addr || !targetExists) return false;

    // If Listeo re-rendered the input, rebind
    if (boundTo === addr) return true;
    boundTo = addr;

    if (google?.maps?.places?.Autocomplete) {
      ac = new google.maps.places.Autocomplete(addr, {
        types: ["geocode"],
        fields: ["address_components", "formatted_address", "name"]
      });

      ac.addListener("place_changed", () => {
        const place = ac.getPlace();
        if (!place?.address_components) return;
        fillFromComponents(place.address_components, place.name || place.formatted_address || addr.value);
        if (place.formatted_address) addr.value = place.formatted_address;
      });
    }

    // Fallback for typed input (no dropdown selection)
    addr.addEventListener("keydown", (e) => {
      if (e.key === "Enter") setTimeout(() => geocode(addr.value), 80);
    });
    addr.addEventListener("blur", () => setTimeout(() => geocode(addr.value), 50));
    addr.addEventListener("change", () => setTimeout(() => geocode(addr.value), 50));

    return true;
  }

  function boot() {
    if (bind()) return;

    const obs = new MutationObserver(() => {
      if (bind()) obs.disconnect();
    });
    obs.observe(document.body, { childList: true, subtree: true });
    setTimeout(() => obs.disconnect(), 30000);
  }

  document.addEventListener("DOMContentLoaded", boot);
})();
