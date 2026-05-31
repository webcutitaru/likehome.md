<?php

declare(strict_types=1);

include('../config.php');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/ical_importer.php';
require_once __DIR__ . '/../includes/booking_pricing.php';
require_once __DIR__ . '/../includes/lh_edit_property_save_core.php';
require_once __DIR__ . '/../includes/lh_add_property_core.php';

$lhCurrencyCode = lh_currency_code();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
if (isset($conn) && $conn instanceof mysqli) {
    dashboard_enforce_active_user($conn);
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$form_save_error = '';

/**
 * All POST actions that call header() must run before includes/header.php (HTML output).
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id > 0) {
    $pdoEarly = getPDO();

    if (lh_post_exceeds_post_max_size()) {
        header('Location: edit-property.php?id=' . $id . '&post_too_large=1');
        exit;
    }

    if (!lh_csrf_verify_post()) {
        header('Location: edit-property.php?id=' . $id . '&csrf_err=1');
        exit;
    }

    if (isset($_POST['manual_block_add'])) {
        $sd = trim((string) ($_POST['manual_block_start'] ?? ''));
        $ed = trim((string) ($_POST['manual_block_end'] ?? ''));
        $note = trim((string) ($_POST['manual_block_note'] ?? ''));
        $d1 = DateTime::createFromFormat('Y-m-d', $sd);
        $d2 = DateTime::createFromFormat('Y-m-d', $ed);
        if (
            !$d1 || $d1->format('Y-m-d') !== $sd
            || !$d2 || $d2->format('Y-m-d') !== $ed
            || $ed <= $sd
        ) {
            header('Location: edit-property.php?id=' . $id . '&mb_err=1');
            exit;
        }
        $eid = 'manual-' . bin2hex(random_bytes(8));
        $insMb = $pdoEarly->prepare(
            'INSERT INTO blocked_dates (property_id, start_date, end_date, source, external_event_id, notes) VALUES (?, ?, ?, \'manual_block\', ?, ?)'
        );
        $insMb->execute([$id, $sd, $ed, $eid, $note !== '' ? $note : null]);
        lh_admin_log_activity($conn, 'manual_block_add', 'property', $id, [
            'start' => $sd,
            'end' => $ed,
            'note' => $note !== '' ? $note : null,
        ]);
        header('Location: edit-property.php?id=' . $id . '&mb_added=1');
        exit;
    }

    if (isset($_POST['manual_block_delete_id'])) {
        $bid = (int) ($_POST['manual_block_delete_id'] ?? 0);
        if ($bid > 0) {
            $delMb = $pdoEarly->prepare("DELETE FROM blocked_dates WHERE id = ? AND property_id = ? AND source = 'manual_block'");
            $delMb->execute([$bid, $id]);
            lh_admin_log_activity($conn, 'manual_block_delete', 'property', $id, ['blocked_date_id' => $bid]);
        }
        header('Location: edit-property.php?id=' . $id . '&mb_deleted=1');
        exit;
    }

    if (!isset($_POST['save_property'])) {
        header('Location: edit-property.php?id=' . $id);
        exit;
    }

    $parsedPeriodsPost = lh_pricing_periods_from_post($_POST);
    $parsedGlobalSd = lh_stay_discount_global_rules_from_post($_POST);
    if ($parsedPeriodsPost['error'] !== null) {
        $form_save_error = $parsedPeriodsPost['error'];
    } elseif ($parsedGlobalSd['error'] !== null) {
        $form_save_error = $parsedGlobalSd['error'];
    } else {
        $saveResult = lh_edit_property_save_from_post($conn, $pdoEarly, $id, $_POST, $_FILES);
        if ($saveResult['ok']) {
            header('Location: dashboard.php?success=updated');
            exit;
        }
        $form_save_error = $saveResult['error'];
    }

}

$res = mysqli_query($conn, 'SELECT * FROM properties WHERE id = ' . (int) $id);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    die('Proprietatea nu există.');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if (empty($data['ical_export_token'])) {
    $generated_export_token = bin2hex(random_bytes(16));
    mysqli_query(
        $conn,
        "UPDATE properties SET ical_export_token='" . mysqli_real_escape_string($conn, $generated_export_token) . "' WHERE id=" . (int) $id
    );
    $data['ical_export_token'] = $generated_export_token;
}

$export_calendar_url = $scheme . '://' . $host . lh_public_url('ical/export.php?token=' . urlencode($data['ical_export_token'] ?? ''));

$saved_amenities = json_decode($data['amenities'] ?? '[]', true);
if (!is_array($saved_amenities)) {
    $saved_amenities = [];
}
$current_images = !empty($data['image_name']) ? explode(',', $data['image_name']) : [];

require_once __DIR__ . '/../includes/property_amenity_catalog.php';
$categories = lh_property_amenity_categories();

$pdoManual = getPDO();
$mbStmt = $pdoManual->prepare("SELECT id, start_date, end_date, notes FROM blocked_dates WHERE property_id = ? AND source = 'manual_block' ORDER BY start_date ASC");
$mbStmt->execute([(int) $id]);
$manual_blocks = $mbStmt->fetchAll();
$manual_blocks_dropdown_open = !empty($_GET['mb_added']) || !empty($_GET['mb_deleted']) || !empty($_GET['mb_err']);

$pricing_periods = lh_property_pricing_periods_load((int) $id);
$stay_discounts_global = lh_property_stay_discounts_load_by_property((int) $id)['global'];

$property_type_raw = trim((string) ($data['property_type'] ?? ''));
$property_type_effective = $property_type_raw !== '' ? $property_type_raw : 'Apartament';
$property_type_presets = ['Apartament', 'Studio', 'Casă'];
$property_type_is_preset = in_array($property_type_effective, $property_type_presets, true);

include 'includes/header.php';
?>

<div class="max-w-6xl mx-auto pb-20">
    <div class="flex items-center justify-between mb-10">
        <div class="flex items-center gap-4">
            <a href="dashboard.php" class="p-3 bg-white rounded-2xl border border-slate-100 text-slate-400 hover:text-slate-900 transition-all shadow-sm">
                <i data-lucide="arrow-left"></i>
            </a>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Editează: <span class="text-cta font-black"><?php echo htmlspecialchars($data['lot_id']); ?></span></h2>
        </div>
    </div>

    <?php if (!empty($_GET['mb_added'])): ?>
        <div class="mb-6 p-5 rounded-2xl border border-black/10 bg-brand-100 text-ink font-bold shadow-sm">Blocarea manuală a fost adăugată. Apare în calendarul exportat și în disponibilitatea site-ului.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['mb_deleted'])): ?>
        <div class="mb-6 p-5 rounded-2xl border border-black/10 bg-slate-100 text-slate-800 font-bold shadow-sm">Blocarea manuală a fost ștearsă.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['mb_err'])): ?>
        <div class="mb-6 p-5 rounded-2xl border border-red-200 bg-red-50 text-red-700 font-bold shadow-sm">Date invalide pentru blocare: folosește format AAAA-LL-ZZ și check-out după check-in.</div>
    <?php endif; ?>
    <?php if (!empty($_GET['post_too_large'])): ?>
        <div class="mb-6 p-5 rounded-2xl border border-red-200 bg-red-50 text-red-700 font-bold shadow-sm">
            Fișierele trimise depășesc limita <code class="font-mono text-sm bg-red-100 px-1 rounded">post_max_size</code> a PHP pe acest server (acum: <?php echo htmlspecialchars(ini_get('post_max_size'), ENT_QUOTES, 'UTF-8'); ?>,
            încărcare max. per fișier: <?php echo htmlspecialchars(ini_get('upload_max_filesize'), ENT_QUOTES, 'UTF-8'); ?>).
            Mărește aceste valori în <code class="font-mono text-sm bg-red-100 px-1 rounded">php.ini</code> sau <code class="font-mono text-sm bg-red-100 px-1 rounded">.user.ini</code> (ex. <code class="font-mono text-sm bg-red-100 px-1 rounded">post_max_size = 64M</code>, <code class="font-mono text-sm bg-red-100 px-1 rounded">upload_max_filesize = 32M</code>),
            apoi reîncearcă cu mai puține poze sau fișiere mai mici.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['csrf_err'])): ?>
        <div class="mb-6 p-5 rounded-2xl border border-red-200 bg-red-50 text-red-700 font-bold shadow-sm">
            Sesiune invalidă. Reîncarcă pagina și încearcă din nou.
            <p class="mt-2 text-sm font-semibold text-red-800/90">Dacă apare doar la salvare cu multe poze sau fișiere foarte mari, pe server limitele PHP (<code class="font-mono text-xs bg-red-100 px-1 rounded">post_max_size</code>, <code class="font-mono text-xs bg-red-100 px-1 rounded">upload_max_filesize</code>) sunt adesea prea mici și cererea este trunchiată înainte de tokenul CSRF.</p>
        </div>
    <?php endif; ?>
    <?php if ($form_save_error !== ''): ?>
        <div class="mb-6 p-5 rounded-2xl border border-red-200 bg-red-50 text-red-700 font-bold shadow-sm">Eroare la salvare: <?php echo htmlspecialchars($form_save_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <details class="group mb-8 bg-white rounded-[2.5rem] border border-slate-100 shadow-sm overflow-hidden" <?php echo $manual_blocks_dropdown_open ? 'open' : ''; ?>>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-8 text-lg font-bold text-slate-800 uppercase tracking-tighter hover:bg-slate-50/80 transition-colors [&::-webkit-details-marker]:hidden">
            <span class="flex items-center gap-2 min-w-0">
                <i data-lucide="calendar-off" class="text-cta shrink-0"></i>
                <span class="truncate">Blocări manuale (întreținere)</span>
                <?php if (!empty($manual_blocks)):
                    $mb_count = count($manual_blocks); ?>
                    <span class="text-xs font-black normal-case tracking-normal px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 shrink-0"><?php echo (int) $mb_count; ?> <?php echo $mb_count === 1 ? 'blocare' : 'blocări'; ?></span>
                <?php endif; ?>
            </span>
            <i data-lucide="chevron-down" class="w-5 h-5 text-slate-400 shrink-0 transition-transform group-open:rotate-180"></i>
        </summary>
        <div class="space-y-6 px-8 pb-8 pt-0 border-t border-slate-100">
            <p class="text-sm text-slate-500 pt-6">Apare în <strong>exportul iCal</strong> și blochează rezervările pe site, fără rezervare în sistem.</p>
            <form method="POST" action="edit-property.php?id=<?php echo (int) $id; ?>" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <?php lh_csrf_field(); ?>
                <input type="hidden" name="manual_block_add" value="1">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">De la</label>
                    <input type="date" name="manual_block_start" required class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 font-medium">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Până la (ziua plecării)</label>
                    <input type="date" name="manual_block_end" required class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 font-medium">
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Notă (opțional)</label>
                    <input type="text" name="manual_block_note" placeholder="ex: Reparații" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 font-medium">
                </div>
                <div class="md:col-span-4">
                    <button type="submit" class="bg-slate-900 text-white px-6 py-3 rounded-2xl font-bold hover:bg-slate-800 transition-all">Adaugă blocare</button>
                </div>
            </form>
            <?php if (!empty($manual_blocks)): ?>
                <div class="border-t border-slate-100 pt-6">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Active</p>
                    <ul class="space-y-2">
                        <?php foreach ($manual_blocks as $mb): ?>
                            <li class="flex flex-wrap items-center justify-between gap-3 p-4 bg-slate-50 rounded-2xl text-sm">
                                <span class="font-bold text-slate-800"><?php echo htmlspecialchars((string) $mb['start_date']); ?> → <?php echo htmlspecialchars((string) $mb['end_date']); ?></span>
                                <?php if (!empty($mb['notes'])): ?>
                                    <span class="text-slate-500"><?php echo htmlspecialchars((string) $mb['notes']); ?></span>
                                <?php endif; ?>
                                <form method="POST" action="edit-property.php?id=<?php echo (int) $id; ?>" class="inline" onsubmit="return confirm('Ștergi această blocare?');">
                                    <?php lh_csrf_field(); ?>
                                    <input type="hidden" name="manual_block_delete_id" value="<?php echo (int) $mb['id']; ?>">
                                    <button type="submit" class="text-xs font-bold uppercase text-red-500 hover:text-red-700 px-3 py-2 rounded-xl border border-red-200">Șterge</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </details>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-8" id="editForm">
        <input type="hidden" name="save_property" value="1">
        <input type="hidden" name="property_id" value="<?php echo (int) $id; ?>">
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
                <input type="file" name="images[]" id="image_input" multiple class="hidden" accept="image/jpeg,image/jpg,image/png,image/webp,.jpg,.jpeg,.png,.webp" onchange="handleNewImages(this)">
            </label>

            <div id="combined_preview" class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <?php foreach ($current_images as $img): if (empty($img)) {
                    continue;
                } ?>
                <div class="lh-gallery-item lh-gallery-existing relative">
                    <button type="button" class="lh-gallery-drag absolute left-2 top-2 z-20 flex h-9 w-9 items-center justify-center rounded-xl bg-slate-900/75 text-white shadow-md backdrop-blur-sm hover:bg-slate-800 cursor-grab active:cursor-grabbing" aria-label="Reordonează" title="Trage pentru a reordona">
                        <i data-lucide="grip-vertical" class="h-4 w-4"></i>
                    </button>
                    <div class="relative group aspect-square cursor-zoom-in rounded-2xl overflow-hidden border border-slate-100 shadow-sm bg-white">
                        <img src="<?php echo htmlspecialchars(lh_property_image_url($id, $img, 'full'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full h-full object-cover" alt="">
                        <input type="hidden" name="existing_images[]" value="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="lh-gallery-remove-existing absolute top-2 right-2 bg-slate-900/80 text-white p-2 rounded-xl hover:bg-red-500 transition-colors backdrop-blur-sm opacity-0 group-hover:opacity-100">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
            <h3 class="text-lg font-bold text-slate-800 border-b pb-4 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="map-pin" class="text-cta"></i> Locație & Identitate
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Titlu Public</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($data['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="ex: Apartament Modern Centru" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30"></div>
                <div><label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">LOT ID</label>
                <input type="text" name="lot_id" required value="<?php echo htmlspecialchars($data['lot_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="ex: REAL-102" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <input type="text" name="city" value="<?php echo htmlspecialchars($data['city'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Oraș" class="p-4 bg-slate-50 border-none rounded-2xl outline-none">
                <input type="text" name="district" value="<?php echo htmlspecialchars($data['district'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Sector" class="p-4 bg-slate-50 border-none rounded-2xl outline-none">
                <input type="text" name="address" value="<?php echo htmlspecialchars($data['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Adresă Exactă" class="p-4 bg-slate-50 border-none rounded-2xl outline-none">
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-6">
            <h3 class="text-lg font-bold text-slate-800 border-b pb-4 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="settings-2" class="text-cta"></i> Detalii Tehnice & Preț
            </h3>
            <?php $egu = (string) ($data['extra_guest_unit'] ?? 'per_guest_per_night'); ?>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Tip</label>
                    <select name="property_type" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none appearance-none">
                        <option value="Apartament" <?php echo $property_type_effective === 'Apartament' ? 'selected' : ''; ?>>Apartament</option>
                        <option value="Studio" <?php echo $property_type_effective === 'Studio' ? 'selected' : ''; ?>>Studio</option>
                        <option value="Casă" <?php echo $property_type_effective === 'Casă' ? 'selected' : ''; ?>>Casă</option>
                        <?php if (!$property_type_is_preset && $property_type_raw !== ''): ?>
                        <option value="<?php echo htmlspecialchars($property_type_effective, ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($property_type_effective, ENT_QUOTES, 'UTF-8'); ?> (existent)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Camere</label>
                    <input type="number" name="rooms" value="<?php echo htmlspecialchars((string) ($data['rooms'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Capacitate</label>
                    <input type="number" name="sleep_capacity" value="<?php echo htmlspecialchars((string) ($data['sleep_capacity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Mp</label>
                    <input type="number" name="area_sqm" value="<?php echo htmlspecialchars((string) ($data['area_sqm'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Etaj</label>
                    <input type="number" name="floor" value="<?php echo htmlspecialchars((string) ($data['floor'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>" placeholder="—" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Preț standard (<?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> / noapte)</label>
                    <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars((string) ($data['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none font-black text-cta text-xl">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Preț weekend (<?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> / noapte)</label>
                    <p class="text-[10px] text-slate-400 mt-1 ml-1 leading-snug">Nopțile care încep sâmbătă sau duminică. Lasă gol = același ca standard.</p>
                    <input type="number" step="0.01" name="price_weekend" value="<?php echo htmlspecialchars(isset($data['price_weekend']) && (float) $data['price_weekend'] > 0 ? (string) $data['price_weekend'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="opțional" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Oaspeți incluși în preț</label>
                    <p class="text-[10px] text-slate-400 mt-1 ml-1 leading-snug">Ex.: 4 — peste 4 se aplică suplimentul.</p>
                    <input type="number" min="1" name="guests_included" value="<?php echo htmlspecialchars(isset($data['guests_included']) && (int) $data['guests_included'] > 0 ? (string) (int) $data['guests_included'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="ex. 4" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Supliment oaspete în plus (<?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?>)</label>
                    <input type="number" step="0.01" name="extra_guest_price" value="<?php echo htmlspecialchars(isset($data['extra_guest_price']) && (float) $data['extra_guest_price'] > 0 ? (string) $data['extra_guest_price'] : '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="opțional" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Mod supliment</label>
                    <select name="extra_guest_unit" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none appearance-none">
                        <option value="per_guest_per_night" <?php echo $egu === 'per_guest_per_night' ? 'selected' : ''; ?>>Per oaspete / noapte</option>
                    </select>
                </div>
            </div>
            <div class="border-t border-slate-100 pt-6 mt-2">
                <button type="button" id="lh-sdg-toggle" class="inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline">
                    <span aria-hidden="true" class="font-black">+</span><span>Reduceri după durata sejurului (global)</span>
                </button>
                <div id="lh-sdg-panel" class="<?php echo !empty($stay_discounts_global) ? '' : 'hidden '; ?>mt-4 space-y-3">
                    <p class="text-[10px] text-slate-400 leading-snug max-w-3xl">Se aplică la rezervările care nu sunt în întregime într-o perioadă cu preț special (sau când nu există reguli pe perioadă). O singură regulă activă: cea cu cel mai mare prag îndeplinit (mai mult de X nopți).</p>
                    <div id="lh-sdg-rows" class="space-y-2">
                        <?php foreach ($stay_discounts_global as $g): ?>
                            <div class="lh-sdg-row flex flex-wrap items-end gap-2 p-3 bg-slate-50/80 rounded-xl border border-slate-100">
                                <span class="text-xs font-medium text-slate-600 pb-3">La peste</span>
                                <input type="number" min="1" name="g_sd_min[]" value="<?php echo (int) ($g['min_nights'] ?? 0); ?>" class="w-20 p-3 bg-white border-none rounded-xl outline-none text-sm font-bold">
                                <span class="text-xs font-medium text-slate-600 pb-3">nopți — reducere</span>
                                <input type="number" step="0.01" name="g_sd_val[]" value="<?php echo htmlspecialchars((string) ($g['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-24 p-3 bg-white border-none rounded-xl outline-none text-sm font-bold">
                                <?php $gu = (string) ($g['unit'] ?? 'percent'); ?>
                                <select name="g_sd_unit[]" class="p-3 bg-white border-none rounded-xl outline-none text-sm font-bold">
                                    <option value="percent" <?php echo $gu === 'percent' ? 'selected' : ''; ?>>%</option>
                                    <option value="fixed_stay" <?php echo $gu === 'fixed_stay' ? 'selected' : ''; ?>><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> tot sejurul</option>
                                </select>
                                <button type="button" class="lh-sdg-remove text-[10px] font-bold text-red-500 uppercase hover:underline pb-3">Șterge</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
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
                    <input type="number" name="min_stay" value="<?php echo htmlspecialchars((string) ($data['min_stay'] ?? 1), ENT_QUOTES, 'UTF-8'); ?>" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Link iCal (Sincronizare)</label>
                    <input type="text" name="ical_import_link" value="<?php echo htmlspecialchars((string) ($data['ical_import_link'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://..." class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none">
                </div>
            </div>
            <div class="border-t border-slate-100 pt-6 mt-2">
                <h4 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-1">Perioade cu preț special</h4>
                <p class="text-[10px] text-slate-400 mb-4 leading-snug max-w-3xl">Tarife care înlocuiesc prețul standard și weekend pentru nopțile din interval. „Până la” este ziua de checkout (exclusă), ca la rezervări. Perioadele nu trebuie să se suprapună. Opțional: „Min. nopți” pe perioadă înlocuiește minimul de bază al proprietății doar pentru sejururi care cad integral în acea perioadă.</p>
                    <div id="lh-pp-rows" class="space-y-4">
                        <?php foreach ($pricing_periods as $pp): ?>
                            <?php
                            $ppSdJson = json_encode($pp['stay_discounts'] ?? [], JSON_UNESCAPED_UNICODE);
                            $ppSdHas = !empty($pp['stay_discounts']);
                            ?>
                            <div class="lh-pp-row-wrap space-y-3 p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                                <input type="hidden" name="pp_stay_discounts_json[]" class="lh-pp-sd-json" value="<?php echo htmlspecialchars($ppSdJson, ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="lh-pp-row grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                    <div class="md:col-span-3">
                                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Denumire (opțional)</label>
                                        <input type="text" name="pp_label[]" value="<?php echo htmlspecialchars((string) ($pp['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none" placeholder="ex. Sezon estival">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">De la</label>
                                        <input type="date" name="pp_date_start[]" value="<?php echo htmlspecialchars((string) ($pp['date_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Până la</label>
                                        <input type="date" name="pp_date_end[]" value="<?php echo htmlspecialchars((string) ($pp['date_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> / noapte</label>
                                        <input type="number" step="0.01" name="pp_price[]" value="<?php echo htmlspecialchars((string) ($pp['price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> weekend</label>
                                        <input type="number" step="0.01" name="pp_price_weekend[]" value="<?php echo htmlspecialchars(isset($pp['price_weekend']) && (float) $pp['price_weekend'] > 0 ? (string) $pp['price_weekend'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none" placeholder="opțional">
                                    </div>
                                    <div class="md:col-span-1 flex justify-end pb-1">
                                        <button type="button" class="lh-pp-remove text-[10px] font-bold text-red-500 uppercase hover:underline">Șterge</button>
                                    </div>
                                </div>
                                <div class="max-w-xs">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Min. nopți (opțional)</label>
                                    <p class="text-[10px] text-slate-400 mt-0.5 leading-snug">Lasă gol = folosește minimul de bază al proprietății.</p>
                                    <input type="number" min="1" name="pp_min_stay[]" value="<?php echo isset($pp['min_stay']) && (int) ($pp['min_stay'] ?? 0) >= 1 ? (int) $pp['min_stay'] : ''; ?>" class="w-full mt-1 p-3 bg-white border-none rounded-xl outline-none" placeholder="ex. 7">
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="lh-pp-sd-toggle inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline"><span aria-hidden="true" class="font-black">+</span><span>Reduceri pentru această perioadă</span></button>
                                </div>
                                <div class="lh-pp-sd-panel space-y-2 border-t border-slate-200/80 pt-3 <?php echo $ppSdHas ? '' : 'hidden'; ?>">
                                    <p class="text-[10px] text-slate-400">Doar dacă tot sejurul e în această perioadă. Altfel se folosesc reducerile globale.</p>
                                    <div class="lh-pp-sd-rows space-y-2">
                                        <?php foreach ($pp['stay_discounts'] ?? [] as $psd): ?>
                                            <div class="lh-pp-sd-mini flex flex-wrap items-end gap-2 p-2 bg-white rounded-xl border border-slate-100">
                                                <span class="text-xs text-slate-600 pb-3">La peste</span>
                                                <input type="number" min="1" class="lh-pp-sd-min w-20 p-2 rounded-lg border-none bg-slate-50 outline-none text-sm font-bold" value="<?php echo (int) ($psd['min_nights'] ?? 0); ?>">
                                                <span class="text-xs text-slate-600 pb-3">nopți — reducere</span>
                                                <input type="number" step="0.01" class="lh-pp-sd-val w-24 p-2 rounded-lg border-none bg-slate-50 outline-none text-sm font-bold" value="<?php echo htmlspecialchars((string) ($psd['value'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php $pu = (string) ($psd['unit'] ?? 'percent'); ?>
                                                <select class="lh-pp-sd-unit p-2 rounded-lg border-none bg-slate-50 outline-none text-sm font-bold">
                                                    <option value="percent" <?php echo $pu === 'percent' ? 'selected' : ''; ?>>%</option>
                                                    <option value="fixed_stay" <?php echo $pu === 'fixed_stay' ? 'selected' : ''; ?>><?php echo htmlspecialchars($lhCurrencyCode, ENT_QUOTES, 'UTF-8'); ?> tot sejurul</option>
                                                </select>
                                                <button type="button" class="lh-pp-sd-remove text-[10px] font-bold text-red-500 uppercase hover:underline pb-3">Șterge</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="lh-pp-sd-add inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline"><span aria-hidden="true" class="font-black">+</span><span>Adaugă regulă</span></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" id="lh-pp-add" class="mt-3 inline-flex items-center gap-1.5 leading-none text-xs font-bold text-cta uppercase tracking-wide hover:underline"><span aria-hidden="true" class="font-black">+</span><span>Adaugă perioadă</span></button>
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
            <div class="border-t border-slate-100 pt-6 mt-2">
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1 block">Link iCal export</label>
                <div class="mt-2 flex flex-col md:flex-row gap-3">
                    <input
                        type="text"
                        id="ical-export-link"
                        value="<?php echo htmlspecialchars($export_calendar_url, ENT_QUOTES, 'UTF-8'); ?>"
                        readonly
                        class="w-full p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30 font-medium text-slate-600"
                    >
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(document.getElementById('ical-export-link').value); this.innerText='Copiat'; setTimeout(() => this.innerText='Copy link', 1500);"
                        class="px-5 py-4 rounded-2xl bg-slate-900 text-white font-semibold whitespace-nowrap hover:bg-slate-800 transition-colors"
                    >Copy link</button>
                </div>
            </div>
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
                            <input type="checkbox" name="amenities[]" value="<?php echo htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8'); ?>"
                                <?php echo (is_array($saved_amenities) && in_array((string) $key, array_map('strval', $saved_amenities), true)) ? 'checked' : ''; ?>
                                class="w-5 h-5 rounded-lg border-none bg-slate-200 checked:bg-cta transition-all outline-none">
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
                            <input type="time" name="check_in_start" value="<?php echo !empty($data['check_in_start']) ? substr($data['check_in_start'], 0, 5) : '14:00'; ?>" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-300 uppercase tracking-widest ml-1">Până la</label>
                            <input type="time" name="check_in_end" value="<?php echo !empty($data['check_in_end']) ? substr($data['check_in_end'], 0, 5) : '21:00'; ?>" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                    </div>
                </div>
                <div class="space-y-4">
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Check-out</label>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] font-black text-slate-300 uppercase tracking-widest ml-1">De la</label>
                            <input type="time" name="check_out_start" value="<?php echo !empty($data['check_out_start']) ? substr($data['check_out_start'], 0, 5) : '08:00'; ?>" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                        <div>
                            <label class="text-[10px] font-black text-slate-300 uppercase tracking-widest ml-1">Până la</label>
                            <input type="time" name="check_out_end" value="<?php echo !empty($data['check_out_end']) ? substr($data['check_out_end'], 0, 5) : '11:00'; ?>" class="w-full mt-2 p-4 bg-slate-50 border-none rounded-2xl outline-none focus:ring-2 focus:ring-cta/30">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
            <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="text-quote" class="text-cta"></i> Descriere Marketing
            </h3>
            <textarea name="description_long" rows="8" placeholder="Scrie o descriere atractivă pentru clienți..." class="w-full p-6 bg-slate-50 border-none rounded-[2rem] focus:ring-2 focus:ring-cta/30 outline-none leading-relaxed text-slate-600 font-medium"><?php echo htmlspecialchars($data['description_long'] ?? ''); ?></textarea>
        </div>

        <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm">
            <h3 class="text-lg font-bold text-slate-800 mb-2 flex items-center gap-2 uppercase tracking-tighter">
                <i data-lucide="mail" class="text-cta"></i> Email reminder înainte de check-in
            </h3>
            <p class="text-xs text-slate-500 font-medium mb-4 leading-relaxed">Acest text este inserat în emailul „Înainte de sosire” (~24h înainte de check-in). Emailul include deja orele de check-in/out, politicile standard și semnătura Like Home. Aici pune tot ce e specific apartamentului: adresă detaliată (scară, etaj dacă nu e în câmpul Etaj, lift, nr. apartament), <strong>SSID și parolă Wi‑Fi</strong>, parcare, cod interfon, contact manager la fața locului. Lasă gol doar dacă vei transmite aceste detalii altfel — oaspeții vor fi îndrumați să te contacteze pentru Wi‑Fi.</p>
            <textarea name="pre_checkin_email_message" rows="8" placeholder="Adresă detaliată, scară, apartament, Wi-Fi (nume rețea + parolă)…" class="w-full p-6 bg-slate-50 border-none rounded-[2rem] focus:ring-2 focus:ring-cta/30 outline-none leading-relaxed text-slate-600 font-medium"><?php echo htmlspecialchars($data['pre_checkin_email_message'] ?? ''); ?></textarea>
        </div>

        <div class="sticky bottom-6 z-20">
            <button type="submit" class="w-full bg-cta text-white py-6 rounded-[2rem] font-black text-xl hover:brightness-110 transition-all shadow-2xl flex items-center justify-center gap-4 group">
                <i data-lucide="check-circle" class="w-6 h-6 group-hover:scale-125 transition-transform"></i> ACTUALIZEAZĂ PROPRIETATEA
            </button>
        </div>
    </form>

    <div id="lh-property-save-overlay" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/55 backdrop-blur-[2px]" aria-hidden="true" role="status" aria-live="polite">
        <div class="bg-white rounded-[2rem] p-10 shadow-2xl max-w-md w-full mx-4 border border-slate-100">
            <p class="text-lg font-black text-slate-900 mb-1" id="lh-prop-save-title">Se salvează modificările…</p>
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

var LH_EDIT_PROPERTY_BATCH = <?php echo (int) LH_ADD_PROPERTY_IMAGE_BATCH_MAX; ?>;
var LH_EDIT_PROPERTY_API = 'edit-property-api.php';

function lhEditPropSetProgress(percent, detailText) {
    var fill = document.getElementById('lh-prop-save-progress-fill');
    var pct = document.getElementById('lh-prop-save-percent');
    var det = document.getElementById('lh-prop-save-detail');
    var p = Math.max(0, Math.min(100, percent));
    if (fill) fill.style.width = p + '%';
    if (pct) pct.textContent = Math.round(p) + '%';
    if (det && detailText) det.textContent = detailText;
}

function lhEditPropShowOverlay(show) {
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
        lhEditPropSetProgress(0, 'Pregătire…');
    } else {
        overlay.classList.add('hidden');
        overlay.setAttribute('aria-hidden', 'true');
    }
}

function lhEditPropAppendBatch(url, csrf, propertyId, batchFiles, onUploadProgress) {
    return new Promise(function (resolve, reject) {
        var fd = new FormData();
        fd.append('lh_action', 'append_images_edit');
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

document.getElementById('editForm')?.addEventListener('submit', function (e) {
    if (!this.checkValidity()) {
        this.reportValidity();
        e.preventDefault();
        return;
    }
    e.preventDefault();
    lhPpSdSyncAllJson();
    var form = this;
    var submitBtn = form.querySelector('button[type="submit"]');
    var errEl = document.getElementById('lh-prop-save-error');

    lhEditPropShowOverlay(true);
    form.setAttribute('aria-busy', 'true');
    if (submitBtn) submitBtn.disabled = true;

    var csrfEl = form.querySelector('input[name="csrf_token"]');
    var csrf = csrfEl ? csrfEl.value : '';
    var files =
        typeof lhGalleryCollectEditNewFiles === 'function' ? lhGalleryCollectEditNewFiles() : [];
    var propertyIdField = form.querySelector('input[name="property_id"]');
    var propertyId = propertyIdField ? parseInt(String(propertyIdField.value), 10) : 0;
    if (!propertyId) {
        lhEditPropShowOverlay(false);
        if (submitBtn) submitBtn.disabled = false;
        alert('ID proprietate lipsă.');
        return;
    }

    var fdSave = new FormData(form);
    if (typeof fdSave.delete === 'function') {
        fdSave.delete('images[]');
    }
    fdSave.append('lh_action', 'save_property');

    lhEditPropSetProgress(2, 'Se salvează datele proprietății…');

    fetch(LH_EDIT_PROPERTY_API, {
        method: 'POST',
        body: fdSave,
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
            var pid = data.property_id || propertyId;
            if (files.length === 0) {
                lhEditPropSetProgress(100, 'Gata.');
                window.location.href = 'dashboard.php?success=updated';
                return null;
            }
            var batchSize = LH_EDIT_PROPERTY_BATCH;
            var batches = [];
            for (var i = 0; i < files.length; i += batchSize) {
                batches.push(files.slice(i, i + batchSize));
            }
            var totalBatches = batches.length;
            var baseAfterSave = 8;
            var uploadSpan = 92;
            var batchIndex = 0;

            function runNext() {
                if (batchIndex >= totalBatches) {
                    lhEditPropSetProgress(100, 'Toate imaginile au fost încărcate.');
                    window.location.href = 'dashboard.php?success=updated';
                    return;
                }
                var batch = batches[batchIndex];
                var humanStart = batchIndex * batchSize + 1;
                var humanEnd = Math.min((batchIndex + 1) * batchSize, files.length);
                lhEditPropSetProgress(
                    baseAfterSave + (batchIndex / totalBatches) * uploadSpan,
                    'Încărcare imagini ' + humanStart + '–' + humanEnd + ' din ' + files.length + '…'
                );
                lhEditPropAppendBatch(
                    LH_EDIT_PROPERTY_API,
                    csrf,
                    pid,
                    batch,
                    function (loaded, total) {
                        if (total <= 0) return;
                        var inBatch = loaded / total;
                        var overall =
                            baseAfterSave + ((batchIndex + inBatch) / totalBatches) * uploadSpan;
                        lhEditPropSetProgress(
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
                                ' Poți reîncărca pagina și salva din nou, sau edita proprietatea #' +
                                pid +
                                ' pentru a adăuga poze.';
                            errEl.classList.remove('hidden');
                        }
                        lhEditPropSetProgress(0, 'Oprit la eroare.');
                        form.removeAttribute('aria-busy');
                        if (submitBtn) submitBtn.disabled = false;
                    });
            }
            runNext();
        })
        .catch(function (err) {
            if (errEl) {
                errEl.textContent = err && err.message ? err.message : 'Eroare la salvare.';
                errEl.classList.remove('hidden');
            }
            lhEditPropSetProgress(0, 'Eroare.');
            form.removeAttribute('aria-busy');
            if (submitBtn) submitBtn.disabled = false;
        });
});

document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
});
</script>

<?php include('includes/footer.php'); ?>