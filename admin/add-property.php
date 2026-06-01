<?php
include('../config.php');
require_once('../includes/ical_importer.php');
require_once __DIR__ . '/../includes/booking_pricing.php';
require_once __DIR__ . '/../includes/lh_add_property_core.php';

$lhPropertyTranslations = [
    'en' => ['title' => '', 'slug' => '', 'description_long' => ''],
    'ru' => ['title' => '', 'slug' => '', 'description_long' => ''],
];

include('includes/header.php');

$lhCurrencyCode = lh_currency_code();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (lh_post_exceeds_post_max_size()) {
        die("<div class='p-10 bg-red-600 text-white rounded-xl font-bold'>Cererea este prea mare pentru server (post_max_size). Reduce numărul sau mărimea pozelor, sau folosește încărcarea automată din browser (reîncarcă pagina).</div>");
    }
    if (!lh_csrf_verify_post()) {
        die("<div class='p-10 bg-red-600 text-white rounded-xl font-bold'>Sesiune invalidă. Reîncarcă pagina și încearcă din nou.</div>");
    }

    $created = lh_add_property_create_from_post($conn, $_POST);
    if (!$created['ok']) {
        die("<div class='p-10 bg-red-600 text-white rounded-xl font-bold'>" . htmlspecialchars($created['error'], ENT_QUOTES, 'UTF-8') . "</div>");
    }

    $new_property_id = (int) $created['property_id'];

    $uploaded_images = [];
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp) {
            $file = [
                'name' => $_FILES['images']['name'][$key] ?? '',
                'type' => $_FILES['images']['type'][$key] ?? '',
                'tmp_name' => $tmp,
                'error' => (int) ($_FILES['images']['error'][$key] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($_FILES['images']['size'][$key] ?? 0),
            ];
            $stored = lh_store_property_image($file, $new_property_id);
            if ($stored !== null) {
                $uploaded_images[] = $stored;
            }
        }
    }
    if ($uploaded_images !== []) {
        $image_string = mysqli_real_escape_string($conn, implode(',', $uploaded_images));
        mysqli_query($conn, "UPDATE properties SET image_name='$image_string' WHERE id=" . $new_property_id);
    }

    echo "<script>window.location.href='dashboard.php?success=added';</script>";
    exit();
}

require_once __DIR__ . '/../includes/property_amenity_catalog.php';
$categories = lh_property_amenity_categories();
?>

<div class="max-w-6xl mx-auto pb-20">
    <div class="flex items-center gap-4 mb-10">
        <a href="dashboard.php" class="p-3 bg-white rounded-2xl border border-slate-100 text-slate-400 hover:text-slate-900 transition-all shadow-sm">
            <i data-lucide="arrow-left"></i>
        </a>
        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight text-slate-900">Adaugă Locuință Nouă</h2>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-8" id="addPropertyForm">
        <?php lh_csrf_field(); ?>
        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
            <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2 border-b pb-4 uppercase tracking-tighter">
                <i data-lucide="image-plus" class="text-cta"></i> Galerie Imagini
            </h3>
            
            <label for="image_input" class="w-full h-44 border-2 border-dashed border-slate-200 rounded-[2rem] flex flex-col items-center justify-center cursor-pointer hover:bg-brand-50 hover:border-cta/25 transition-all group mb-8">
                <div class="bg-brand-100 p-4 rounded-full group-hover:scale-110 transition-transform mb-3">
                    <i data-lucide="upload-cloud" class="w-8 h-8 text-cta"></i>
                </div>
                <span class="text-base font-bold text-slate-600 group-hover:text-ink">Alege fotografiile</span>
                <span class="text-xs text-slate-400 mt-1 uppercase tracking-widest italic">Puteți selecta mai multe fișiere deodată</span>
                <input type="file" name="images[]" id="image_input" multiple class="hidden" accept="image/jpeg,image/jpg,image/png,image/webp,.jpg,.jpeg,.png,.webp">
            </label>

            <div id="image_preview_container" class="grid grid-cols-2 md:grid-cols-6 gap-4">
                </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
            <h3 class="text-lg font-bold text-slate-800 border-b pb-4 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="map-pin" class="text-cta"></i> Locație & Identitate
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Titlu Public</label>
                <input type="text" name="title" required placeholder="ex: Apartament Modern Centru" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30"></div>
                <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">LOT ID</label>
                <input type="text" name="lot_id" required placeholder="ex: REAL-102" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <input type="text" name="city" placeholder="Oraș" class="p-4 bg-slate-50 border-none rounded-2xl outline-none">
                <input type="text" name="district" placeholder="Sector" class="p-4 bg-slate-50 border-none rounded-2xl outline-none">
                <input type="text" name="address" placeholder="Adresă Exactă" class="p-4 bg-slate-50 border-none rounded-2xl outline-none">
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
            <h3 class="text-lg font-bold text-slate-800 border-b pb-4 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="settings-2" class="text-cta"></i> Detalii Tehnice & Preț
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Tip</label>
                    <select name="property_type" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none appearance-none">
                        <option value="Apartament">Apartament</option>
                        <option value="Studio">Studio</option>
                        <option value="Casă">Casă</option>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Camere</label>
                    <input type="number" name="rooms" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Capacitate</label>
                    <input type="number" name="sleep_capacity" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Mp</label>
                    <input type="number" name="area_sqm" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Etaj</label>
                    <input type="number" name="floor" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Preț standard (<?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> / noapte)</label>
                    <input type="number" step="0.01" name="price" required class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none font-black text-cta text-xl">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Preț weekend (<?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> / noapte)</label>
                    <p class="text-[10px] text-slate-400 mt-1 ml-1 leading-snug">Nopțile care încep sâmbătă sau duminică. Lasă gol = același ca standard.</p>
                    <input type="number" step="0.01" name="price_weekend" placeholder="opțional" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Oaspeți incluși în preț</label>
                    <p class="text-[10px] text-slate-400 mt-1 ml-1 leading-snug">Ex.: 4 — peste 4 se aplică suplimentul.</p>
                    <input type="number" min="1" name="guests_included" placeholder="ex. 4" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Supliment oaspete în plus (<?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?>)</label>
                    <input type="number" step="0.01" name="extra_guest_price" placeholder="opțional" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Mod supliment</label>
                    <select name="extra_guest_unit" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none appearance-none">
                        <option value="per_guest_per_night">Per oaspete / noapte</option>
                    </select>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-6 mt-2">
                <button type="button" id="lh-sdg-toggle" class="inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline">
                    <span aria-hidden="true" class="font-black">+</span><span>Reduceri după durata sejurului (global)</span>
                </button>
                <div id="lh-sdg-panel" class="hidden mt-4 space-y-3">
                    <p class="text-[10px] text-slate-400 leading-snug max-w-3xl">Se aplică la rezervările care nu sunt în întregime într-o perioadă cu preț special (sau când nu există reguli pe perioadă). O singură regulă activă: cea cu cel mai mare prag îndeplinit (mai mult de X nopți).</p>
                    <div id="lh-sdg-rows" class="space-y-2"></div>
                    <button type="button" id="lh-sdg-add" class="inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline"><span aria-hidden="true" class="font-black">+</span><span>Adaugă regulă</span></button>
                </div>
            </div>
            <template id="lh-sdg-row-tpl">
                <div class="lh-sdg-row flex flex-wrap items-end gap-2 p-3 bg-slate-50/80 rounded-xl border border-slate-100">
                    <span class="text-xs font-medium text-slate-600 pb-3">La peste</span>
                    <input type="number" min="1" name="g_sd_min[]" class="w-20 p-3 bg-white border-none rounded-xl outline-none text-sm font-bold" placeholder="5">
                    <span class="text-xs font-medium text-slate-600 pb-3">nopți — reducere</span>
                    <input type="number" step="0.01" name="g_sd_val[]" class="w-24 p-3 bg-white border-none rounded-xl outline-none text-sm font-bold" placeholder="10">
                    <select name="g_sd_unit[]" class="p-3 bg-white border-none rounded-xl outline-none text-sm font-bold">
                        <option value="percent">%</option>
                        <option value="fixed_stay"><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> tot sejurul</option>
                    </select>
                    <button type="button" class="lh-sdg-remove text-[10px] font-bold text-red-500 uppercase hover:underline pb-3">Șterge</button>
                </div>
            </template>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Min. Nopți</label>
                    <input type="number" name="min_stay" value="1" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Link iCal (Sincronizare)</label>
                    <input type="text" name="ical_import_link" placeholder="https://..." class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
            </div>
            <div class="border-t border-slate-100 pt-6 mt-2">
                <h4 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Perioade cu preț special</h4>
                <p class="text-[10px] text-slate-400 mb-4 leading-snug max-w-3xl">Tarife care înlocuiesc prețul standard și weekend pentru nopțile din interval. „Până la” este ziua de checkout (exclusă), ca la rezervări. Perioadele nu trebuie să se suprapună. Opțional: „Min. nopți” pe perioadă înlocuiește minimul de bază al proprietății doar pentru sejururi care cad integral în acea perioadă.</p>
                <div id="lh-pp-rows" class="space-y-4"></div>
                <button type="button" id="lh-pp-add" class="mt-3 inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline"><span aria-hidden="true" class="font-black">+</span><span>Adaugă perioadă</span></button>
            </div>
            <template id="lh-pp-row-tpl">
                <div class="lh-pp-row-wrap space-y-3 p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                    <input type="hidden" name="pp_stay_discounts_json[]" class="lh-pp-sd-json" value="[]">
                    <div class="lh-pp-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Denumire (opțional)</label>
                            <input type="text" name="pp_label[]" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none" placeholder="ex. Sezon estival">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">De la</label>
                            <input type="date" name="pp_date_start[]" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Până la</label>
                            <input type="date" name="pp_date_end[]" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> / noapte</label>
                            <input type="number" step="0.01" name="pp_price[]" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none" placeholder="0">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> weekend</label>
                            <input type="number" step="0.01" name="pp_price_weekend[]" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none" placeholder="opțional">
                        </div>
                        <div class="md:col-span-1 flex justify-end pb-1">
                            <button type="button" class="lh-pp-remove text-[10px] font-bold text-red-500 uppercase hover:underline">Șterge</button>
                        </div>
                    </div>
                    <div class="max-w-xs">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Min. nopți (opțional)</label>
                        <p class="text-[10px] text-slate-400 mt-0.5 leading-snug">Lasă gol = folosește minimul de bază al proprietății.</p>
                        <input type="number" min="1" name="pp_min_stay[]" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none" placeholder="ex. 7">
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="lh-pp-sd-toggle inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline"><span aria-hidden="true" class="font-black">+</span><span>Reduceri pentru această perioadă</span></button>
                    </div>
                    <div class="lh-pp-sd-panel hidden space-y-2 border-t border-slate-200/80 pt-3">
                        <p class="text-[10px] text-slate-400">Doar dacă tot sejurul e în această perioadă. Altfel se folosesc reducerile globale.</p>
                        <div class="lh-pp-sd-rows space-y-2"></div>
                        <button type="button" class="lh-pp-sd-add inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline"><span aria-hidden="true" class="font-black">+</span><span>Adaugă regulă</span></button>
                    </div>
                </div>
            </template>
            <template id="lh-pp-sd-row-tpl">
                <div class="lh-pp-sd-mini flex flex-wrap items-end gap-2 p-2 bg-white rounded-xl border border-slate-100">
                    <span class="text-xs text-slate-600 pb-3">La peste</span>
                    <input type="number" min="1" class="lh-pp-sd-min w-20 p-2 rounded-lg border-none bg-slate-50 outline-none text-sm font-bold" placeholder="5">
                    <span class="text-xs text-slate-600 pb-3">nopți — reducere</span>
                    <input type="number" step="0.01" class="lh-pp-sd-val w-24 p-2 rounded-lg border-none bg-slate-50 outline-none text-sm font-bold" placeholder="10">
                    <select class="lh-pp-sd-unit p-2 rounded-lg border-none bg-slate-50 outline-none text-sm font-bold">
                        <option value="percent">%</option>
                        <option value="fixed_stay"><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> tot sejurul</option>
                    </select>
                    <button type="button" class="lh-pp-sd-remove text-[10px] font-bold text-red-500 uppercase hover:underline pb-3">Șterge</button>
                </div>
            </template>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
           <h3 class="text-lg font-bold text-slate-800 border-b pb-4 mb-8 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="layout-list" class="text-cta"></i> Facilități & Dotări
            </h3>
            <div class="space-y-12">
                <?php foreach ($categories as $catName => $items): ?>
                <div>
                    <?php if (trim((string) $catName) !== ''): ?>
                    <h4 class="text-[10px] font-black uppercase text-slate-400 tracking-[0.2em] mb-5 flex items-center gap-2 italic">
                        <span class="w-2 h-2 bg-cta rounded-full"></span> <?php echo htmlspecialchars((string) $catName, ENT_QUOTES, 'UTF-8'); ?>
                    </h4>
                    <?php endif; ?>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 md:gap-4">
                        <?php foreach ($items as $key => $info): ?>
                        <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-2xl cursor-pointer hover:bg-white hover:shadow-md transition-all group border-2 border-transparent has-[:checked]:border-cta/40 has-[:checked]:bg-white">
                            <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>" class="w-5 h-5 rounded-lg border-none bg-slate-200 checked:bg-cta transition-all outline-none">
                            <div class="flex items-center gap-2">
                                <i data-lucide="<?php echo htmlspecialchars($info[1], ENT_QUOTES, 'UTF-8'); ?>" class="w-4 h-4 text-slate-400 group-hover:text-cta transition-colors"></i>
                                <span class="text-sm font-bold text-slate-500 group-hover:text-slate-900"><?php echo htmlspecialchars($info[0], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
            <h3 class="text-lg font-bold text-slate-800 border-b pb-4 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="clock-3" class="text-cta"></i> Check-in & Check-out
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Check-in</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-300 uppercase tracking-widest ml-1">De la</label>
                            <input type="time" name="check_in_start" value="14:00" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-300 uppercase tracking-widest ml-1">Până la</label>
                            <input type="time" name="check_in_end" value="21:00" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Check-out</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-300 uppercase tracking-widest ml-1">De la</label>
                            <input type="time" name="check_out_start" value="08:00" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-300 uppercase tracking-widest ml-1">Până la</label>
                            <input type="time" name="check_out_end" value="11:00" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="text-quote" class="text-cta"></i> Descriere Marketing
            </h3>
            <textarea name="description_long" rows="8" placeholder="Scrie o descriere atractivă pentru clienți..." class="w-full p-6 bg-slate-50 border-none rounded-[2rem] focus:ring-2 focus:ring-cta/30 outline-none leading-relaxed text-slate-600 font-medium"></textarea>
        </div>

        <?php require __DIR__ . '/includes/property_translation_fields.php'; ?>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
            <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="mail" class="text-cta"></i> Email reminder înainte de check-in
            </h3>
            <p class="text-xs text-slate-500 font-medium mb-4 leading-relaxed">Acest text este inserat în emailul „Înainte de sosire” (~24h înainte de check-in). Emailul include deja orele de check-in/out, politicile standard și semnătura Like Home. Aici pune tot ce e specific apartamentului: adresă detaliată (scară, etaj dacă nu e în câmpul Etaj, lift, nr. apartament), <strong>SSID și parolă Wi‑Fi</strong>, parcare, cod interfon, contact manager la fața locului. Lasă gol doar dacă vei transmite aceste detalii altfel — oaspeții vor fi îndrumați să te contacteze pentru Wi‑Fi.</p>
            <textarea name="pre_checkin_email_message" rows="8" placeholder="Adresă detaliată, scară, apartament, Wi-Fi (nume rețea + parolă)…" class="w-full p-6 bg-slate-50 border-none rounded-[2rem] focus:ring-2 focus:ring-cta/30 outline-none leading-relaxed text-slate-600 font-medium"></textarea>
        </div>

        <div class="sticky bottom-6 z-10">
            <button type="submit" class="w-full bg-cta text-white py-6 rounded-[2rem] font-black text-xl hover:brightness-110 transition-all shadow-2xl flex items-center justify-center gap-4 group">
                <i data-lucide="rocket" class="w-6 h-6 group-hover:scale-125 transition-transform"></i> LANSEAZĂ PROPRIETATEA
            </button>
        </div>
    </form>

    <div id="lh-property-save-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/55 backdrop-blur-[2px]" aria-hidden="true" role="status" aria-live="polite">
        <div class="bg-white rounded-[2rem] p-10 shadow-2xl max-w-md w-full mx-4 border border-slate-100">
            <p class="text-lg font-black text-slate-900 mb-1" id="lh-prop-save-title">Se publică proprietatea…</p>
            <p class="text-xs text-slate-500 font-medium mb-2" id="lh-prop-save-detail">Pregătire…</p>
            <p class="text-xs font-bold text-cta mb-4" id="lh-prop-save-percent" aria-hidden="true">0%</p>
            <div class="relative h-3 rounded-full bg-slate-100 overflow-hidden">
                <div id="lh-prop-save-progress-fill" class="absolute left-0 top-0 bottom-0 rounded-full bg-cta transition-[width] duration-150 ease-out" style="width:0%"></div>
            </div>
            <p class="text-xs text-red-600 font-semibold mt-4 hidden" id="lh-prop-save-error"></p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
window.LH_GALLERY_PREPARE_UPLOAD = <?php echo json_encode([
    'maxWidth' => LH_PROPERTY_IMAGE_MAX_WIDTH,
    'webpQuality01' => round(LH_PROPERTY_WEBP_QUALITY / 100, 3),
]); ?>;
</script>
<script src="../assets/js/admin-property-gallery-dnd.js"></script>
<script>
var LH_ADD_PROPERTY_BATCH = <?php echo (int) LH_ADD_PROPERTY_IMAGE_BATCH_MAX; ?>;
var LH_ADD_PROPERTY_API = 'add-property-api.php';

function lhAddPropertySetProgress(percent, detailText) {
    var fill = document.getElementById('lh-prop-save-progress-fill');
    var pct = document.getElementById('lh-prop-save-percent');
    var det = document.getElementById('lh-prop-save-detail');
    var p = Math.max(0, Math.min(100, percent));
    if (fill) fill.style.width = p + '%';
    if (pct) pct.textContent = Math.round(p) + '%';
    if (det && detailText) det.textContent = detailText;
}

function lhAddPropertyShowOverlay(show) {
    var overlay = document.getElementById('lh-property-save-overlay');
    var err = document.getElementById('lh-prop-save-error');
    if (!overlay) return;
    if (show) {
        overlay.classList.remove('hidden');
        overlay.setAttribute('aria-hidden', 'false');
        if (err) {
            err.classList.add('hidden');
            err.textContent = '';
        }
        lhAddPropertySetProgress(0, 'Pregătire…');
    } else {
        overlay.classList.add('hidden');
        overlay.setAttribute('aria-hidden', 'true');
    }
}

function lhAddPropertyAppendBatch(url, csrf, propertyId, batchFiles, onUploadProgress) {
    return new Promise(function (resolve, reject) {
        var fd = new FormData();
        fd.append('lh_action', 'append_images');
        fd.append('csrf_token', csrf);
        fd.append('property_id', String(propertyId));
        batchFiles.forEach(function (f) {
            fd.append('images[]', f, f.name);
        });
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.withCredentials = true;
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable && typeof onUploadProgress === 'function') {
                onUploadProgress(e.loaded, e.total);
            }
        };
        xhr.onload = function () {
            var raw = xhr.responseText || '';
            var data;
            try {
                data = JSON.parse(raw);
            } catch (ex) {
                reject(new Error('Răspuns invalid de la server.'));
                return;
            }
            if (xhr.status >= 200 && xhr.status < 300 && data.ok) {
                resolve(data);
                return;
            }
            reject(new Error(data.error || ('Eroare HTTP ' + xhr.status)));
        };
        xhr.onerror = function () {
            reject(new Error('Eroare de rețea.'));
        };
        xhr.send(fd);
    });
}

document.getElementById('addPropertyForm')?.addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        this.reportValidity();
        e.preventDefault();
        return;
    }
    e.preventDefault();
    lhPpSdSyncAllJson();
    var form = this;
    var overlay = document.getElementById('lh-property-save-overlay');
    var submitBtn = form.querySelector('button[type="submit"]');
    var errEl = document.getElementById('lh-prop-save-error');

    lhAddPropertyShowOverlay(true);
    form.setAttribute('aria-busy', 'true');
    if (submitBtn) submitBtn.disabled = true;

    var csrfEl = form.querySelector('input[name="csrf_token"]');
    var csrf = csrfEl ? csrfEl.value : '';
    var files =
        typeof lhGalleryCollectAddOrderedFiles === 'function' ? lhGalleryCollectAddOrderedFiles() : [];

    var fdCreate = new FormData(form);
    if (typeof fdCreate.delete === 'function') {
        fdCreate.delete('images[]');
    }
    fdCreate.append('lh_action', 'create');

    lhAddPropertySetProgress(2, 'Se salvează datele proprietății…');

    fetch(LH_ADD_PROPERTY_API, {
        method: 'POST',
        body: fdCreate,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    })
        .then(function (res) {
            return res.text().then(function (text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (ex) {
                    throw new Error('Răspuns invalid de la server.');
                }
                if (!res.ok || !data.ok) {
                    throw new Error(data.error || ('Eroare HTTP ' + res.status));
                }
                return data;
            });
        })
        .then(function (data) {
            var propertyId = data.property_id;
            if (!propertyId) {
                throw new Error('Lipsește ID proprietate.');
            }
            if (files.length === 0) {
                lhAddPropertySetProgress(100, 'Gata.');
                window.location.href = 'dashboard.php?success=added';
                return null;
            }
            var batchSize = LH_ADD_PROPERTY_BATCH;
            var batches = [];
            for (var i = 0; i < files.length; i += batchSize) {
                batches.push(files.slice(i, i + batchSize));
            }
            var totalBatches = batches.length;
            var baseAfterCreate = 8;
            var uploadSpan = 92;
            var batchIndex = 0;

            function runNext() {
                if (batchIndex >= totalBatches) {
                    lhAddPropertySetProgress(100, 'Toate imaginile au fost încărcate.');
                    window.location.href = 'dashboard.php?success=added';
                    return;
                }
                var batch = batches[batchIndex];
                var humanStart = batchIndex * batchSize + 1;
                var humanEnd = Math.min((batchIndex + 1) * batchSize, files.length);
                lhAddPropertySetProgress(
                    baseAfterCreate + (batchIndex / totalBatches) * uploadSpan,
                    'Încărcare imagini ' + humanStart + '–' + humanEnd + ' din ' + files.length + '…'
                );
                lhAddPropertyAppendBatch(
                    LH_ADD_PROPERTY_API,
                    csrf,
                    propertyId,
                    batch,
                    function (loaded, total) {
                        if (total <= 0) return;
                        var inBatch = loaded / total;
                        var overall =
                            baseAfterCreate +
                            ((batchIndex + inBatch) / totalBatches) * uploadSpan;
                        lhAddPropertySetProgress(
                            overall,
                            'Încărcare imagini ' + humanStart + '–' + humanEnd + ' din ' + files.length + '…'
                        );
                    }
                )
                    .then(function () {
                        batchIndex += 1;
                        runNext();
                    })
                    .catch(function (err) {
                        if (errEl) {
                            errEl.textContent =
                                (err && err.message ? err.message : 'Eroare') +
                                ' Poți continua în „Editează proprietatea” (ID ' +
                                propertyId +
                                ') pentru a reîncerca pozele.';
                            errEl.classList.remove('hidden');
                        }
                        lhAddPropertySetProgress(0, 'Oprit la eroare.');
                        form.removeAttribute('aria-busy');
                        if (submitBtn) submitBtn.disabled = false;
                    });
            }
            runNext();
        })
        .catch(function (err) {
            if (errEl) {
                errEl.textContent = err && err.message ? err.message : 'Eroare la publicare.';
                errEl.classList.remove('hidden');
            }
            lhAddPropertySetProgress(0, 'Eroare.');
            form.removeAttribute('aria-busy');
            if (submitBtn) submitBtn.disabled = false;
        });
});

function lhPpAddRow() {
    var tpl = document.getElementById('lh-pp-row-tpl');
    var host = document.getElementById('lh-pp-rows');
    if (!tpl || !host) return;
    host.appendChild(tpl.content.cloneNode(true));
    lucide.createIcons();
}
function lhPpSdSyncJsonForWrap(wrap) {
    var h = wrap.querySelector('.lh-pp-sd-json');
    var rows = wrap.querySelectorAll('.lh-pp-sd-rows .lh-pp-sd-mini');
    if (!h) return;
    var arr = [];
    rows.forEach(function (r) {
        var mnEl = r.querySelector('.lh-pp-sd-min');
        var valEl = r.querySelector('.lh-pp-sd-val');
        var unitEl = r.querySelector('.lh-pp-sd-unit');
        var mn = parseInt(String(mnEl && mnEl.value), 10);
        var val = parseFloat(String(valEl && valEl.value).replace(',', '.'));
        var unit = unitEl && unitEl.value === 'fixed_stay' ? 'fixed_stay' : 'percent';
        if (!mn || mn < 1 || !val || val <= 0) return;
        arr.push({ min_nights: mn, value: val, unit: unit });
    });
    h.value = JSON.stringify(arr);
}
function lhPpSdSyncAllJson() {
    document.querySelectorAll('.lh-pp-row-wrap').forEach(lhPpSdSyncJsonForWrap);
}
function lhSdgAddRow() {
    var tpl = document.getElementById('lh-sdg-row-tpl');
    var host = document.getElementById('lh-sdg-rows');
    if (!tpl || !host) return;
    host.appendChild(tpl.content.cloneNode(true));
}
function lhPpSdAddMini(wrap) {
    var tpl = document.getElementById('lh-pp-sd-row-tpl');
    var host = wrap.querySelector('.lh-pp-sd-rows');
    if (!tpl || !host) return;
    host.appendChild(tpl.content.cloneNode(true));
}

document.getElementById('lh-pp-add')?.addEventListener('click', lhPpAddRow);
document.getElementById('lh-sdg-toggle')?.addEventListener('click', function () {
    var p = document.getElementById('lh-sdg-panel');
    if (p) p.classList.toggle('hidden');
});
document.getElementById('lh-sdg-add')?.addEventListener('click', lhSdgAddRow);
document.getElementById('lh-sdg-rows')?.addEventListener('click', function (e) {
    if (e.target.classList.contains('lh-sdg-remove')) {
        var row = e.target.closest('.lh-sdg-row');
        if (row) row.remove();
    }
});
document.getElementById('lh-pp-rows')?.addEventListener('click', function (e) {
    if (e.target.classList.contains('lh-pp-remove')) {
        var wrap = e.target.closest('.lh-pp-row-wrap');
        if (wrap) wrap.remove();
        return;
    }
    var sdTgl = e.target.closest('.lh-pp-sd-toggle');
    if (sdTgl) {
        var w = sdTgl.closest('.lh-pp-row-wrap');
        var pan = w && w.querySelector('.lh-pp-sd-panel');
        if (pan) pan.classList.toggle('hidden');
        return;
    }
    var sdAdd = e.target.closest('.lh-pp-sd-add');
    if (sdAdd) {
        var wrap = sdAdd.closest('.lh-pp-row-wrap');
        if (wrap) lhPpSdAddMini(wrap);
        return;
    }
    if (e.target.classList.contains('lh-pp-sd-remove')) {
        var mini = e.target.closest('.lh-pp-sd-mini');
        if (mini) mini.remove();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
});
</script>

<?php include('includes/footer.php'); ?>