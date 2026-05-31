<?php
/**
 * components/property_card.php
 * Card vizual pentru o proprietate.
 *
 * Utilizare:
 *   $property = [...] // array cu datele proprietății
 *   include 'components/property_card.php';
 *
 * SAU via render_property_card($property) - funcție helper de mai jos.
 */

if (!function_exists('render_property_card')) {
    /**
     * Randează HTML-ul unui card de proprietate și îl returnează ca string.
     *
     * @param array  $property
     * @param string $check_in
     * @param string $check_out
     * @param string $guests
     * @return string
     */
    function render_property_card(array $property, string $check_in = '', string $check_out = '', string $guests = ''): string
    {
        $slug       = !empty($property['slug']) ? $property['slug'] : null;
        $identifier = $slug ? urlencode($slug) : (int)$property['id'];
        $param      = $slug ? 'slug' : 'id';

        $query_params = array_filter([
            $param      => $identifier,
            'check_in'  => $check_in,
            'check_out' => $check_out,
            'guests'    => $guests,
        ]);

        $url = lh_public_url('property-details.php?' . http_build_query($query_params));

        $images      = !empty($property['image_name']) ? explode(',', $property['image_name']) : [];
        $pid         = (int) ($property['id'] ?? 0);
        $slide_urls  = [];
        foreach ($images as $raw_name) {
            $bn = trim((string) $raw_name);
            if ($bn === '') {
                continue;
            }
            $slide_urls[] = lh_property_image_url($pid, $bn, 'thumb');
        }
        $first_image = !empty($images[0]) ? trim($images[0]) : null;
        $img_src     = $first_image
            ? lh_property_image_url($pid, $first_image, 'thumb')
            : 'https://placehold.co/600x400/e2e8f0/94a3b8?text=Fara+imagine';
        $slide_urls_json = $slide_urls !== []
            ? htmlspecialchars(
                json_encode($slide_urls, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ENT_QUOTES,
                'UTF-8',
            )
            : '';

        $title = htmlspecialchars($property['title'] ?? 'Proprietate', ENT_QUOTES, 'UTF-8');

        $city_raw     = trim((string) ($property['city'] ?? ''));
        $district_raw = trim((string) ($property['district'] ?? ''));
        $location_raw = trim((string) ($property['location'] ?? ''));

        $loc_parts = [];
        if ($city_raw !== '') {
            $loc_parts[] = htmlspecialchars($city_raw, ENT_QUOTES, 'UTF-8');
        }
        if (
            $district_raw !== ''
            && mb_strtolower($district_raw, 'UTF-8') !== mb_strtolower($city_raw, 'UTF-8')
        ) {
            $loc_parts[] = htmlspecialchars($district_raw, ENT_QUOTES, 'UTF-8');
        }
        $display_loc = $loc_parts !== []
            ? implode(' • ', $loc_parts)
            : htmlspecialchars($location_raw, ENT_QUOTES, 'UTF-8');
        $sleep_cap     = (int)($property['sleep_capacity'] ?? 0);
        $price_std     = (float) ($property['price'] ?? 0);
        $price         = lh_format_money($price_std, 0);
        $property_type = htmlspecialchars($property['property_type'] ?? '');

        ob_start(); ?>
<article class="group/card bg-white rounded-2xl border border-black/[0.08] shadow-lg shadow-black/[0.04] hover:shadow-2xl hover:shadow-black/[0.12] hover:-translate-y-1 overflow-hidden transition-all duration-300 flex flex-col">

  <div
    class="lh-property-card-media relative aspect-[4/3] overflow-hidden rounded-t-2xl bg-surface-2 group block"
    <?php if ($slide_urls_json !== '' && count($slide_urls) >= 2): ?>
      data-lh-slide-urls="<?= $slide_urls_json ?>"
    <?php endif; ?>
  >
    <img
      src="<?= $img_src ?>"
      alt="<?= $title ?>"
      class="lh-property-card-slide-img absolute inset-0 z-0 h-full w-full object-cover object-center transition-transform duration-700 group-hover:scale-105"
      loading="lazy"
      decoding="async"
    >
    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-[3] h-24 bg-gradient-to-t from-ink/25 to-transparent"></div>
    <a
      href="<?= $url ?>"
      class="absolute inset-0 z-[8] rounded-t-2xl outline-none ring-0"
      aria-label="<?= $title ?> — deschide detaliile"
    ></a>
  </div>

  <div class="p-5 flex flex-col flex-1">

    <div class="flex items-center justify-between gap-2 mb-2">
      <?php if ($property_type): ?>
        <span class="text-[10px] font-bold uppercase tracking-widest text-ink/80 bg-brand-100 px-2.5 py-1 rounded-full border border-black/[0.06]">
          <?= $property_type ?>
        </span>
      <?php endif; ?>

      <?php if ($display_loc): ?>
        <span class="text-xs text-blue-grey flex items-center gap-1 ml-auto min-w-0">
          <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          <span class="truncate"><?= $display_loc ?></span>
        </span>
      <?php endif; ?>
    </div>

    <h3 class="font-semibold text-ink text-lg tracking-tight leading-snug mb-3">
      <a href="<?= $url ?>" class="hover:text-cta transition-colors">
        <?= $title ?>
      </a>
    </h3>

    <div class="flex items-center gap-3 text-xs text-blue-grey mb-4">
      <?php if ($sleep_cap > 0): ?>
        <span class="flex items-center gap-1">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          <?= $sleep_cap ?> <?= $sleep_cap === 1 ? 'persoană' : 'persoane' ?>
        </span>
      <?php endif; ?>

      <?php if (!empty($property['rooms'])): ?>
        <span class="flex items-center gap-1">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          <?= (int)$property['rooms'] ?> camere
        </span>
      <?php endif; ?>
    </div>

    <div class="mt-auto flex items-center justify-between gap-3">
      <div>
        <span class="text-xs font-semibold text-blue-grey">De la </span>
        <span class="text-xl font-bold text-ink tracking-tight"><?= htmlspecialchars($price, ENT_QUOTES, 'UTF-8') ?></span>
      </div>

      <a
        href="<?= $url ?>"
        class="inline-flex items-center gap-1.5 bg-cta hover:brightness-110 text-white text-xs font-semibold px-4 py-2.5 rounded-2xl transition-all shadow-md shadow-black/10"
      >
        Vezi proprietatea
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </a>
    </div>

  </div>
</article>
<?php
        return ob_get_clean();
    }
}

if (isset($property) && is_array($property)) {
    echo render_property_card(
        $property,
        $_GET['check_in'] ?? '',
        $_GET['check_out'] ?? '',
        $_GET['guests'] ?? ''
    );
}
