<?php

declare(strict_types=1);

include '../config.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/includes/header.php';

$pdo = getPDO();

$message = '';
$message_type = 'success';

if (isset($_GET['saved'])) {
    $message = 'Cuponul a fost salvat.';
    $message_type = 'success';
}

$edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$usages_coupon_id = isset($_GET['usages']) ? (int) $_GET['usages'] : 0;

$edit_row = null;
$edit_property_ids = [];

if ($edit_id > 0) {
    $st = $pdo->prepare('SELECT * FROM discount_coupons WHERE id = ? LIMIT 1');
    $st->execute([$edit_id]);
    $edit_row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$edit_row) {
        $edit_id = 0;
        $edit_row = null;
    } else {
        $pj = $pdo->prepare('SELECT property_id FROM discount_coupon_properties WHERE coupon_id = ? ORDER BY property_id');
        $pj->execute([$edit_id]);
        $edit_property_ids = array_map('intval', array_column($pj->fetchAll(PDO::FETCH_ASSOC), 'property_id'));
    }
}

$props_stmt = $pdo->query('SELECT id, title, lot_id FROM properties ORDER BY title ASC');
$all_properties = $props_stmt === false ? [] : $props_stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lh_csrf_verify_post()) {
        $message = 'Sesiune invalidă. Reîncarcă pagina.';
        $message_type = 'warning';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'toggle_active') {
            $tid = (int) ($_POST['id'] ?? 0);
            if ($tid > 0) {
                $cur = $pdo->prepare('SELECT is_active FROM discount_coupons WHERE id = ? LIMIT 1');
                $cur->execute([$tid]);
                $v = (int) ($cur->fetchColumn() ?: 0);
                $pdo->prepare('UPDATE discount_coupons SET is_active = ? WHERE id = ?')->execute([$v === 1 ? 0 : 1, $tid]);
                header('Location: coupons.php');
                exit;
            }
        }

        if ($action === 'save') {
        $sid = (int) ($_POST['id'] ?? 0);
        $code = lh_coupon_normalize_code(trim((string) ($_POST['code'] ?? '')));
        $dtype = (string) ($_POST['discount_type'] ?? '');
        if (!in_array($dtype, ['percent', 'fixed'], true)) {
            $dtype = 'percent';
        }
        $dval = (float) str_replace(',', '.', (string) ($_POST['discount_value'] ?? '0'));
        $valid_from_in = trim((string) ($_POST['valid_from'] ?? ''));
        $valid_to_in = trim((string) ($_POST['valid_to'] ?? ''));
        $max_red_raw = trim((string) ($_POST['max_redemptions'] ?? ''));
        $applies_all = !empty($_POST['applies_all_properties']);
        $prop_ids_raw = isset($_POST['property_ids']) && is_array($_POST['property_ids']) ? $_POST['property_ids'] : [];
        $property_ids_sel = [];
        foreach ($prop_ids_raw as $pid) {
            $ipi = filter_var($pid, FILTER_VALIDATE_INT);
            if ($ipi) {
                $property_ids_sel[] = $ipi;
            }
        }

        if (strlen($code) < 2) {
            $message = 'Codul cuponului trebuie să aibă cel puțin 2 caractere.';
            $message_type = 'warning';
        } elseif (!$applies_all && $property_ids_sel === []) {
            $message = 'Selectează cel puțin o proprietate sau bifează „Toate proprietățile”.';
            $message_type = 'warning';
        } elseif ($dtype === 'percent' && ($dval <= 0 || $dval > 100)) {
            $message = 'Procentul trebuie să fie între 1 și 100.';
            $message_type = 'warning';
        } elseif ($dtype === 'fixed' && $dval <= 0) {
            $message = 'Suma fixă trebuie să fie mai mare decât 0.';
            $message_type = 'warning';
        } else {
            $vf = null;
            $vt = null;
            if ($valid_from_in !== '') {
                $df = DateTimeImmutable::createFromFormat('Y-m-d', $valid_from_in);
                if ($df && $df->format('Y-m-d') === $valid_from_in) {
                    $vf = $valid_from_in;
                } else {
                    $message = 'Data „Valid de la” nu este validă.';
                    $message_type = 'warning';
                }
            }
            if ($message === '' && $valid_to_in !== '') {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d', $valid_to_in);
                if ($dt && $dt->format('Y-m-d') === $valid_to_in) {
                    $vt = $valid_to_in;
                } else {
                    $message = 'Data „Valid până la” nu este validă.';
                    $message_type = 'warning';
                }
            }
            if ($message === '' && $vf !== null && $vt !== null && $vt < $vf) {
                $message = '„Valid până la” nu poate fi înaintea „Valid de la”.';
                $message_type = 'warning';
            }

            if ($message === '') {
                $max_red = $max_red_raw === '' ? null : filter_var($max_red_raw, FILTER_VALIDATE_INT);
                if ($max_red !== false && $max_red !== null && $max_red < 1) {
                    $max_red = null;
                }

                try {
                    $pdo->beginTransaction();

                    $dupCh = $pdo->prepare('SELECT id FROM discount_coupons WHERE code = ? AND id <> ? LIMIT 1');
                    $dupCh->execute([$code, max(0, $sid)]);
                    if ($dupCh->fetch()) {
                        $pdo->rollBack();
                        $message = 'Există deja un cupon cu acest cod.';
                        $message_type = 'warning';
                    } elseif ($sid > 0) {
                        $pdo->prepare(
                            'UPDATE discount_coupons SET code = ?, discount_type = ?, discount_value = ?, is_active = ?, valid_from = ?, valid_to = ?, max_redemptions = ?, applies_all_properties = ? WHERE id = ?'
                        )->execute([
                            $code,
                            $dtype,
                            $dval,
                            !empty($_POST['is_active']) ? 1 : 0,
                            $vf,
                            $vt,
                            $max_red,
                            $applies_all ? 1 : 0,
                            $sid,
                        ]);
                        $pdo->prepare('DELETE FROM discount_coupon_properties WHERE coupon_id = ?')->execute([$sid]);
                        if (!$applies_all) {
                            $insP = $pdo->prepare(
                                'INSERT INTO discount_coupon_properties (coupon_id, property_id) VALUES (?, ?)'
                            );
                            foreach ($property_ids_sel as $pida) {
                                $insP->execute([$sid, $pida]);
                            }
                        }
                        $pdo->commit();
                        header('Location: coupons.php?saved=1');
                        exit;
                    } else {
                        $pdo->prepare(
                            'INSERT INTO discount_coupons (code, discount_type, discount_value, is_active, valid_from, valid_to, max_redemptions, applies_all_properties)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                        )->execute([
                            $code,
                            $dtype,
                            $dval,
                            !empty($_POST['is_active']) ? 1 : 0,
                            $vf,
                            $vt,
                            $max_red,
                            $applies_all ? 1 : 0,
                        ]);
                        $newId = (int) $pdo->lastInsertId();
                        if (!$applies_all) {
                            $insP = $pdo->prepare(
                                'INSERT INTO discount_coupon_properties (coupon_id, property_id) VALUES (?, ?)'
                            );
                            foreach ($property_ids_sel as $pida) {
                                $insP->execute([$newId, $pida]);
                            }
                        }
                        $pdo->commit();
                        header('Location: coupons.php?saved=1');
                        exit;
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('coupons save: ' . $e->getMessage());
                    $message = 'Nu s-a putut salva cuponul (cod duplicat sau eroare DB).';
                    $message_type = 'warning';
                }
            }
        }
        if ($message !== '') {
            $edit_row = [
                'id' => $sid,
                'code' => $code,
                'discount_type' => $dtype,
                'discount_value' => $dval,
                'is_active' => !empty($_POST['is_active']),
                'valid_from' => $valid_from_in,
                'valid_to' => $valid_to_in,
                'max_redemptions' => $max_red_raw,
                'applies_all_properties' => $applies_all ? 1 : 0,
            ];
            $edit_property_ids = $property_ids_sel;
            $edit_id = $sid;
        }
        }
    }
}

$list_stmt = $pdo->query(
    'SELECT c.*,
       (SELECT COUNT(*) FROM bookings b WHERE b.coupon_id = c.id AND b.status = \'confirmed\') AS use_confirmed_count
    FROM discount_coupons c ORDER BY c.id DESC'
);
$coupon_list = $list_stmt === false ? [] : $list_stmt->fetchAll(PDO::FETCH_ASSOC);

$coupon_props_map = [];
if ($coupon_list !== []) {
    $ids = array_map(static fn ($r) => (int) $r['id'], $coupon_list);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $mapSt = $pdo->prepare(
        'SELECT coupon_id, property_id FROM discount_coupon_properties WHERE coupon_id IN (' . $in . ') ORDER BY coupon_id, property_id'
    );
    $mapSt->execute($ids);
    while ($rw = $mapSt->fetch(PDO::FETCH_ASSOC)) {
        $cid = (int) $rw['coupon_id'];
        if (!isset($coupon_props_map[$cid])) {
            $coupon_props_map[$cid] = [];
        }
        $coupon_props_map[$cid][] = (int) $rw['property_id'];
    }
}

$usages_rows = [];
if ($usages_coupon_id > 0) {
    $uSt = $pdo->prepare(
        'SELECT b.id, b.guest_name, b.guest_email, b.check_in, b.check_out, b.created_at,
                b.total_price, b.coupon_code, b.coupon_discount_amount, b.status,
                p.title AS property_title, p.id AS pid
         FROM bookings b
         LEFT JOIN properties p ON p.id = b.property_id
         WHERE b.coupon_id = ?
         ORDER BY b.id DESC LIMIT 100'
    );
    $uSt->execute([$usages_coupon_id]);
    $usages_rows = $uSt->fetchAll(PDO::FETCH_ASSOC);
}

$fv = static function (string $k, $default = '') use ($edit_row): string {
    if (!$edit_row) {
        return htmlspecialchars((string) $default, ENT_QUOTES, 'UTF-8');
    }
    $v = $edit_row[$k] ?? $default;

    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$dval_display = $edit_row ? (string) ($edit_row['discount_value'] ?? '10') : '10';

$coupon_form_details_open =
    $edit_id > 0
    || ($message !== '' && $message_type !== 'success')
    || (isset($_GET['new']) && $edit_id === 0)
    || ($edit_id === 0 && $coupon_list === []);

?>
<div class="max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start gap-4 mb-10">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Reduceri · Cupoane</h2>
            <p class="text-slate-500">Coduri promoționale (procent sau sumă fixă din suma nopților de cazare).</p>
        </div>
        <a href="dashboard.php" class="bg-cta text-white px-6 py-3 rounded-2xl font-bold inline-flex items-center gap-2 hover:brightness-110 shadow-lg shadow-black/10 shrink-0">
            <i data-lucide="layout-grid" class="w-5 h-5"></i> Dashboard
        </a>
    </div>

    <?php if ($message !== ''): ?>
        <div class="mb-8 p-5 rounded-2xl border border-black/10 <?php echo $message_type === 'success' ? 'bg-brand-100 text-ink' : 'bg-slate-100 text-slate-800'; ?> font-bold shadow-sm">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($usages_coupon_id > 0): ?>
        <?php
        $uc_label = 'Cupon #' . $usages_coupon_id;
        foreach ($coupon_list as $cl) {
            if ((int) ($cl['id'] ?? 0) === $usages_coupon_id) {
                $uc_label = (string) ($cl['code'] ?? $uc_label);
                break;
            }
        }
        ?>
        <div class="mb-10">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <h3 class="text-xl font-black text-slate-900">Utilizări · <?php echo htmlspecialchars(strtoupper($uc_label)); ?></h3>
                <a href="coupons.php" class="text-sm font-bold text-cta hover:underline">Înapoi la listă</a>
            </div>
            <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-widest">
                            <th class="px-6 py-4">Rezervare</th>
                            <th class="px-6 py-4">Client</th>
                            <th class="px-6 py-4">Proprietate</th>
                            <th class="px-6 py-4">Perioadă</th>
                            <th class="px-6 py-4 text-center">Reducere</th>
                            <th class="px-6 py-4 text-center">Total</th>
                            <th class="px-6 py-4 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($usages_rows as $ur): ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4 font-black text-cta">#<?php echo (int) $ur['id']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-800"><?php echo htmlspecialchars((string) ($ur['guest_name'] ?? '')); ?></div>
                                    <div class="text-xs text-slate-400 break-all"><?php echo htmlspecialchars((string) ($ur['guest_email'] ?? '')); ?></div>
                                </td>
                                <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars((string) ($ur['property_title'] ?? '')); ?></td>
                                <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars((string) ($ur['check_in'] ?? '')); ?> → <?php echo htmlspecialchars((string) ($ur['check_out'] ?? '')); ?></td>
                                <td class="px-6 py-4 text-center font-bold text-emerald-800"><?php echo htmlspecialchars(lh_format_money((float) ($ur['coupon_discount_amount'] ?? 0), 0)); ?></td>
                                <td class="px-6 py-4 text-center font-black"><?php echo htmlspecialchars(lh_format_money((float) ($ur['total_price'] ?? 0), 0)); ?></td>
                                <td class="px-6 py-4 text-center"><span class="text-xs font-bold uppercase"><?php echo htmlspecialchars((string) ($ur['status'] ?? '')); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($usages_rows === []): ?>
                            <tr><td colspan="7" class="px-6 py-12 text-center text-slate-400 font-bold">Încă nu există rezervări cu acest cupon.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($usages_coupon_id <= 0): ?>
    <div class="space-y-8 mb-14">
        <details class="lh-coupon-form-details bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden" <?php echo $coupon_form_details_open ? 'open' : ''; ?>>
            <summary class="cursor-pointer list-none px-6 sm:px-8 py-5 flex items-center justify-between gap-4 font-black text-slate-900 hover:bg-slate-50/90 transition-colors [&::-webkit-details-marker]:hidden">
                <span class="text-base sm:text-lg"><?php echo $edit_id > 0 ? ('Editează cupon #' . (int) $edit_id) : 'Cupon nou'; ?></span>
                <i data-lucide="chevron-down" class="coupon-details-chevron w-5 h-5 text-slate-400 shrink-0 transition-transform duration-200" aria-hidden="true"></i>
            </summary>
            <div class="px-6 sm:px-8 pb-8 pt-2 border-t border-slate-100">
            <form method="post" action="coupons.php" class="space-y-5 max-w-3xl">
                <?php lh_csrf_field(); ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" value="<?php echo (int) $edit_id; ?>">

                <div>
                    <label class="block text-xs uppercase font-bold text-slate-400 mb-2">Cod (unic)</label>
                    <input type="text" name="code" required value="<?php echo $fv('code', ''); ?>" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-bold uppercase outline-none focus:ring-2 focus:ring-cta/25 focus:border-cta">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs uppercase font-bold text-slate-400 mb-2">Tip</label>
                        <select name="discount_type" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-cta/25 focus:border-cta">
                            <option value="percent" <?php echo (!$edit_row || (($edit_row['discount_type'] ?? '') === 'percent')) ? 'selected' : ''; ?>>Procent</option>
                            <option value="fixed" <?php echo ($edit_row && (($edit_row['discount_type'] ?? '') === 'fixed')) ? 'selected' : ''; ?>>Sumă fixă</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-slate-400 mb-2">Valoare</label>
                        <input type="number" step="0.01" min="0.01" name="discount_value" required value="<?php echo htmlspecialchars($dval_display ?: '10', ENT_QUOTES, 'UTF-8'); ?>" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-black text-cta outline-none focus:ring-2 focus:ring-cta/25 focus:border-cta">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs uppercase font-bold text-slate-400 mb-2">Valid de la</label>
                        <input type="date" name="valid_from" value="<?php echo $fv('valid_from', ''); ?>" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-cta/25 focus:border-cta">
                    </div>
                    <div>
                        <label class="block text-xs uppercase font-bold text-slate-400 mb-2">Valid până la</label>
                        <input type="date" name="valid_to" value="<?php echo $fv('valid_to', ''); ?>" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-cta/25 focus:border-cta">
                    </div>
                    <p class="sm:col-span-2 text-xs text-slate-500 leading-relaxed mt-0 -mt-1">
                        Lasă gol pentru fără limită la această margine. Ambele goale înseamnă valabilitate <strong class="text-slate-700">nelimitată</strong> pentru check-in-uri.
                    </p>
                </div>

                <div>
                    <label class="block text-xs uppercase font-bold text-slate-400 mb-2">Max. utilizări (gol = nelimitat)</label>
                    <input type="number" min="1" step="1" name="max_redemptions" value="<?php echo $fv('max_redemptions', ''); ?>" placeholder="ex. 50" class="w-full border border-slate-200 rounded-xl px-4 py-3 font-bold outline-none focus:ring-2 focus:ring-cta/25 focus:border-cta">
                </div>

                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" name="applies_all_properties" value="1" class="rounded border-slate-300 w-5 h-5 accent-cta"
                           <?php echo (!$edit_row || !empty($edit_row['applies_all_properties'])) ? 'checked' : ''; ?>>
                    <span class="font-bold text-slate-800">Toate proprietățile</span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 w-5 h-5 accent-cta"
                           <?php echo (!$edit_row || !empty($edit_row['is_active'])) ? 'checked' : ''; ?>>
                    <span class="font-bold text-slate-800">Activ</span>
                </label>

                <fieldset class="border border-slate-100 rounded-2xl p-4 space-y-2 max-h-56 overflow-y-auto">
                    <legend class="text-xs uppercase font-bold text-slate-400 px-1">Proprietăți (dacă nu e bifat „Toate”)</legend>
                    <?php foreach ($all_properties as $prop): ?>
                        <?php
                        $pid = (int) ($prop['id'] ?? 0);
                        $chk = in_array($pid, $edit_property_ids, true);
                        ?>
                        <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 rounded-lg px-2 py-1 cursor-pointer">
                            <input type="checkbox" name="property_ids[]" value="<?php echo $pid; ?>" class="accent-cta rounded border-slate-300" <?php echo $chk ? 'checked' : ''; ?>>
                            <span class="min-w-0"><?php echo htmlspecialchars((string) ($prop['title'] ?? '') . ' · LOT ' . (string) ($prop['lot_id'] ?? '')); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" class="bg-cta text-white px-6 py-3 rounded-xl font-black hover:brightness-110">Salvează</button>
                    <?php if ($edit_id > 0): ?>
                        <a href="coupons.php" class="inline-flex items-center px-6 py-3 rounded-xl font-bold border border-slate-200 text-slate-600 hover:bg-slate-50">Renunță</a>
                    <?php endif; ?>
                </div>
            </form>
            </div>
        </details>

        <section class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="px-8 py-6 border-b border-slate-50 flex flex-wrap items-center justify-between gap-4">
                <h3 class="text-lg font-black text-slate-900">Cupoane existente</h3>
                <a href="coupons.php?new=1" class="text-sm font-black uppercase text-cta hover:underline shrink-0">+ Adaugă cupon</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 text-[10px] uppercase font-bold tracking-widest">
                            <th class="px-6 py-4">Cod</th>
                            <th class="px-6 py-4">Reduceri</th>
                            <th class="px-6 py-4 whitespace-nowrap">Valabilitate</th>
                            <th class="px-6 py-4 text-center">Utilizări</th>
                            <th class="px-6 py-4">Proprietăți</th>
                            <th class="px-6 py-4 text-center">Status</th>
                            <th class="px-6 py-4 text-right">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php
                        $couponActionClass =
                            'inline-flex items-center justify-center min-h-[2rem] px-2 py-1 text-xs font-black uppercase rounded-lg whitespace-nowrap';
                        ?>
                        <?php foreach ($coupon_list as $cr): ?>
                            <?php
                            $cid = (int) ($cr['id'] ?? 0);
                            $dt = (string) ($cr['discount_type'] ?? '');
                            $dv = (float) ($cr['discount_value'] ?? 0);
                            $discLabel = $dt === 'percent' ? ($dv . '%') : lh_format_money($dv, 0);
                            $vfRow = $cr['valid_from'] ?? null;
                            $vtRow = $cr['valid_to'] ?? null;
                            $vfSet = $vfRow !== null && $vfRow !== '';
                            $vtSet = $vtRow !== null && $vtRow !== '';
                            if (!$vfSet && !$vtSet) {
                                $validLabel = 'Nelimitată';
                            } elseif ($vfSet && $vtSet) {
                                $validLabel = (string) $vfRow . ' → ' . (string) $vtRow;
                            } elseif ($vfSet) {
                                $validLabel = 'De la ' . (string) $vfRow;
                            } else {
                                $validLabel = 'Până la ' . (string) $vtRow;
                            }
                            ?>
                            <tr class="hover:bg-slate-50 align-middle">
                                <td class="px-6 py-4 font-black text-cta uppercase"><?php echo htmlspecialchars((string) ($cr['code'] ?? '')); ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800"><?php echo htmlspecialchars($discLabel); ?></td>
                                <td class="px-6 py-4 text-xs font-semibold text-slate-600 whitespace-nowrap"><?php echo htmlspecialchars($validLabel); ?></td>
                                <td class="px-6 py-4 text-center font-black"><?php echo (int) ($cr['use_confirmed_count'] ?? 0); ?></td>
                                <td class="px-6 py-4 text-slate-600 text-xs font-medium max-w-xs">
                                    <?php if (!empty($cr['applies_all_properties'])): ?>
                                        <span class="text-cta font-bold uppercase">Toate</span>
                                    <?php else:
                                        $pids_list = $coupon_props_map[$cid] ?? [];
                                        if ($pids_list === []) {
                                            echo '<span class="text-amber-700 font-bold">—</span>';
                                        } else {
                                            $names = [];
                                            foreach ($all_properties as $ap) {
                                                if (in_array((int) $ap['id'], $pids_list, true)) {
                                                    $names[] = (string) ($ap['lot_id'] ?? '');
                                                }
                                            }

                                            echo htmlspecialchars(implode(', ', $names !== [] ? $names : array_map('strval', $pids_list)));
                                        }
                                    endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if (!empty($cr['is_active'])): ?>
                                        <span class="text-xs font-black uppercase text-emerald-700">Activ</span>
                                    <?php else: ?>
                                        <span class="text-xs font-black uppercase text-slate-400">Inactiv</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="inline-flex items-center justify-end gap-2 flex-wrap">
                                        <a href="coupons.php?edit=<?php echo $cid; ?>" class="<?php echo $couponActionClass; ?> text-cta hover:bg-brand-50">Edit</a>
                                        <a href="coupons.php?usages=<?php echo $cid; ?>" class="<?php echo $couponActionClass; ?> text-slate-600 hover:bg-slate-50">Detalii</a>
                                        <form method="post" class="inline-flex items-center shrink-0 m-0">
                                            <?php lh_csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="id" value="<?php echo $cid; ?>">
                                            <button type="submit" class="<?php echo $couponActionClass; ?> text-slate-500 hover:text-cta hover:bg-slate-50 border-0 bg-transparent cursor-pointer font-sans"><?php echo !empty($cr['is_active']) ? 'Dezactivează' : 'Activează'; ?></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($coupon_list === []): ?>
                            <tr><td colspan="7" class="px-6 py-16 text-center text-slate-400 font-bold">Încă nu ai cupoane.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof lucide !== 'undefined') lucide.createIcons();
    var det = document.querySelector('.lh-coupon-form-details');
    var chev = det && det.querySelector('.coupon-details-chevron');
    if (det && chev) {
        function lhSyncCouponChevron() {
            chev.style.transform = det.open ? 'rotate(180deg)' : '';
        }
        lhSyncCouponChevron();
        det.addEventListener('toggle', lhSyncCouponChevron);
    }
});
</script>
<?php include 'includes/footer.php'; ?>
