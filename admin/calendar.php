<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auth.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if (isset($conn) && $conn instanceof mysqli) {
    dashboard_enforce_active_user($conn);
}

require_once __DIR__ . '/../includes/booking_pricing.php';
require_once __DIR__ . '/../includes/booking_admin.php';

$pdo = getPDO();

$flashOk = '';
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['calendar_action'] ?? '') === 'special_price') {
    if (!lh_csrf_verify_post()) {
        $flashErr = 'Sesiune invalidă. Reîncarcă pagina și încearcă din nou.';
    } else {
        $propertyId = (int) ($_POST['property_id'] ?? 0);
        $rangeStart = trim((string) ($_POST['range_start'] ?? ''));
        $rangeEndEx = trim((string) ($_POST['range_end_exclusive'] ?? ''));
        $priceRaw = trim((string) ($_POST['price'] ?? ''));
        $pwRaw = trim((string) ($_POST['price_weekend'] ?? ''));
        $msRaw = trim((string) ($_POST['min_stay'] ?? ''));
        $price = (float) str_replace(',', '.', $priceRaw);
        $pw = $pwRaw === '' ? null : (float) str_replace(',', '.', $pwRaw);
        $newMinStay = null;
        if ($msRaw !== '') {
            $msi = (int) $msRaw;
            if ($msi >= 1) {
                $newMinStay = $msi;
            }
        }

        $fromRedirect = trim((string) ($_POST['redirect_from'] ?? ''));
        $daysRedirect = (int) ($_POST['redirect_days'] ?? 60);
        $qs = 'from=' . rawurlencode($fromRedirect !== '' ? $fromRedirect : date('Y-m-d')) . '&days=' . max(1, min(120, $daysRedirect));

        if ($propertyId < 1) {
            header('Location: calendar.php?' . $qs . '&flash_err=' . rawurlencode('Proprietate invalidă.'));
            exit;
        }

        $d0 = DateTimeImmutable::createFromFormat('Y-m-d', $rangeStart);
        $d1 = DateTimeImmutable::createFromFormat('Y-m-d', $rangeEndEx);
        if (!$d0 || $d0->format('Y-m-d') !== $rangeStart || !$d1 || $d1->format('Y-m-d') !== $rangeEndEx || $d1 <= $d0) {
            header('Location: calendar.php?' . $qs . '&flash_err=' . rawurlencode('Intervalul selectat este invalid.'));
            exit;
        }

        $stmtP = $pdo->prepare('SELECT id FROM properties WHERE id = ? LIMIT 1');
        $stmtP->execute([$propertyId]);
        if (!$stmtP->fetch()) {
            header('Location: calendar.php?' . $qs . '&flash_err=' . rawurlencode('Proprietatea nu există.'));
            exit;
        }

        $cur = $d0;
        while ($cur < $d1) {
            $ymd = $cur->format('Y-m-d');
            $blk = $pdo->prepare(
                'SELECT COUNT(*) FROM blocked_dates
                 WHERE property_id = ? AND start_date <= ? AND end_date > ?'
            );
            $blk->execute([$propertyId, $ymd, $ymd]);
            if ((int) $blk->fetchColumn() > 0) {
                header('Location: calendar.php?' . $qs . '&flash_err=' . rawurlencode('Intervalul include date blocate (rezervare sau calendar extern). Alege doar zile libere.'));
                exit;
            }
            $cur = $cur->modify('+1 day');
        }

        $existing = lh_property_pricing_periods_load($propertyId);
        $merged = lh_property_pricing_periods_merge_apply_range(
            $existing,
            $rangeStart,
            $rangeEndEx,
            $price,
            $pw,
            LH_PRICING_PERIOD_LABEL_CALENDAR_SPECIAL,
            $newMinStay
        );

        if ($merged['error'] !== null) {
            header('Location: calendar.php?' . $qs . '&flash_err=' . rawurlencode($merged['error']));
            exit;
        }

        try {
            $pdo->beginTransaction();
            $keepGlobalDiscounts = lh_property_stay_discounts_load_by_property($propertyId)['global'];
            lh_property_pricing_periods_save($pdo, $propertyId, $merged['periods'], $keepGlobalDiscounts);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('calendar special_price: ' . $e->getMessage());
            header('Location: calendar.php?' . $qs . '&flash_err=' . rawurlencode('Salvarea a eșuat. Încearcă din nou.'));
            exit;
        }

        lh_admin_log_activity($conn, 'calendar_pricing_special', 'property', $propertyId, [
            'range_start' => $rangeStart,
            'range_end_exclusive' => $rangeEndEx,
            'price' => $price,
            'price_weekend' => $pw,
            'min_stay' => $newMinStay,
        ]);

        header('Location: calendar.php?' . $qs . '&flash_ok=1');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['calendar_action'] ?? '') === 'booking_cancel') {
    $fromRedirect = trim((string) ($_POST['redirect_from'] ?? ''));
    $daysRedirect = (int) ($_POST['redirect_days'] ?? 60);
    $qs = 'from=' . rawurlencode($fromRedirect !== '' ? $fromRedirect : date('Y-m-d')) . '&days=' . max(1, min(120, $daysRedirect));
    $locBase = 'calendar.php?' . $qs;

    if (!lh_csrf_verify_post()) {
        header('Location: ' . $locBase . '&flash_err=' . rawurlencode('Sesiune invalidă. Reîncarcă pagina și încearcă din nou.'));
        exit;
    }

    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    if ($bookingId < 1) {
        header('Location: ' . $locBase . '&flash_err=' . rawurlencode('Rezervare invalidă.'));
        exit;
    }

    $booking = null;
    try {
        $pdo->beginTransaction();
        $stmtB = $pdo->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
        $stmtB->execute([$bookingId]);
        $booking = $stmtB->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            $pdo->rollBack();
            header('Location: ' . $locBase . '&flash_err=' . rawurlencode('Rezervarea nu a fost găsită.'));
            exit;
        }
        if (($booking['status'] ?? '') === 'cancelled') {
            $pdo->rollBack();
            header('Location: ' . $locBase . '&flash_err=' . rawurlencode('Rezervarea este deja anulată.'));
            exit;
        }
        $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
        $del = $pdo->prepare(
            'DELETE FROM blocked_dates WHERE property_id = ? AND source = ? AND external_event_id = ?'
        );
        $del->execute([(int) $booking['property_id'], 'direct_booking', 'booking-' . $bookingId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('calendar booking_cancel: ' . $e->getMessage());
        header('Location: ' . $locBase . '&flash_err=' . rawurlencode('Anularea a eșuat. Încearcă din nou.'));
        exit;
    }

    lh_admin_log_activity($conn, 'booking_cancel', 'booking', $bookingId, [
        'property_id' => (int) ($booking['property_id'] ?? 0),
        'source' => 'calendar',
    ]);
    header('Location: ' . $locBase . '&flash_ok=booking_cancelled');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['calendar_action'] ?? '') === 'booking_update') {
    $fromRedirect = trim((string) ($_POST['redirect_from'] ?? ''));
    $daysRedirect = (int) ($_POST['redirect_days'] ?? 60);
    $qs = 'from=' . rawurlencode($fromRedirect !== '' ? $fromRedirect : date('Y-m-d')) . '&days=' . max(1, min(120, $daysRedirect));
    $locBase = 'calendar.php?' . $qs;

    $failRedirect = static function (string $msg) use ($locBase): void {
        header('Location: ' . $locBase . '&flash_err=' . rawurlencode($msg));
        exit;
    };

    if (!lh_csrf_verify_post()) {
        $failRedirect('Sesiune invalidă. Reîncarcă pagina și încearcă din nou.');
    }

    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $updateOut = lh_admin_process_booking_update($pdo, $_POST);
    if (empty($updateOut['ok'])) {
        $failRedirect((string) ($updateOut['message'] ?? 'Salvarea a eșuat.'));
    }

    lh_admin_log_activity($conn, 'booking_update', 'booking', $bookingId, [
        'property_id' => (int) ($updateOut['property_id'] ?? 0),
        'check_in' => (string) ($updateOut['check_in'] ?? ''),
        'check_out' => (string) ($updateOut['check_out'] ?? ''),
        'source' => 'calendar',
    ]);
    header('Location: ' . $locBase . '&flash_ok=booking_updated');
    exit;
}

$fromGet = trim((string) ($_GET['from'] ?? ''));
$days = (int) ($_GET['days'] ?? 60);
if ($days < 30) {
    $days = 30;
}
if ($days > 120) {
    $days = 120;
}

$fromDt = DateTimeImmutable::createFromFormat('Y-m-d', $fromGet);
if (!$fromDt || $fromDt->format('Y-m-d') !== $fromGet) {
    $fromDt = new DateTimeImmutable('today');
}
$fromYmd = $fromDt->format('Y-m-d');

$rangeEndEx = $fromDt->modify('+' . $days . ' days')->format('Y-m-d');

$dates = [];
$d = $fromDt;
$endExclusive = DateTimeImmutable::createFromFormat('Y-m-d', $rangeEndEx);
if (!$endExclusive) {
    $endExclusive = $fromDt->modify('+60 days');
}
while ($d < $endExclusive) {
    $dates[] = $d->format('Y-m-d');
    $d = $d->modify('+1 day');
}
$dayCount = count($dates);
if ($dayCount === 0) {
    $dates[] = $fromYmd;
    $dayCount = 1;
}

$prevFrom = $fromDt->modify('-' . $dayCount . ' days')->format('Y-m-d');
$nextFrom = $fromDt->modify('+' . $dayCount . ' days')->format('Y-m-d');
$todayYmd = (new DateTimeImmutable('today'))->format('Y-m-d');

$flashOkGet = (string) ($_GET['flash_ok'] ?? '');
$flashErrGet = (string) ($_GET['flash_err'] ?? '');
if ($flashOkGet === '1') {
    $flashOk = 'Prețul special a fost salvat.';
} elseif ($flashOkGet === 'booking_cancelled') {
    $flashOk = 'Rezervarea a fost anulată.';
} elseif ($flashOkGet === 'booking_updated') {
    $flashOk = 'Rezervarea a fost actualizată.';
}
if ($flashErrGet !== '') {
    $flashErr = $flashErrGet;
}
if ($flashErr !== '') {
    $flashOk = '';
}

$stmtProps = $pdo->query(
    'SELECT id, title, lot_id, slug, price, price_weekend, address, city, district, is_active
     FROM properties
     ORDER BY title ASC, id ASC'
);
$properties = $stmtProps->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmtBook = $pdo->prepare(
    "SELECT id, property_id, guest_name, guest_email, guest_phone, check_in, check_out, status, total_price, guests,
            coupon_code, coupon_discount_amount,
            payment_method, payment_status, payment_amount, refunded_amount, paid_at,
            maib_checkout_id, maib_payment_id, maib_refund_id
     FROM bookings
     WHERE status IN ('pending', 'confirmed')
       AND check_out > ?
       AND check_in < ?"
);
$stmtBook->execute([$fromYmd, $rangeEndEx]);
$bookingRows = $stmtBook->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmtBlock = $pdo->prepare(
    'SELECT property_id, start_date, end_date, source, notes
     FROM blocked_dates
     WHERE end_date > ?
       AND start_date < ?'
);
$stmtBlock->execute([$fromYmd, $rangeEndEx]);
$blockedRows = $stmtBlock->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmtPer = $pdo->prepare(
    'SELECT property_id, date_start, date_end, price, price_weekend, label
     FROM property_pricing_periods
     WHERE date_end > ?
       AND date_start < ?'
);
$stmtPer->execute([$fromYmd, $rangeEndEx]);
$periodRows = $stmtPer->fetchAll(PDO::FETCH_ASSOC) ?: [];

$bookingsByProperty = [];
foreach ($bookingRows as $br) {
    $pid = (int) $br['property_id'];
    $bookingsByProperty[$pid][] = $br;
}

$blocksByProperty = [];
foreach ($blockedRows as $br) {
    $pid = (int) $br['property_id'];
    $blocksByProperty[$pid][] = $br;
}

$periodsByProperty = [];
foreach ($periodRows as $pr) {
    $pid = (int) $pr['property_id'];
    $periodsByProperty[$pid][] = $pr;
}

/**
 * @param list<array{start_date:string,end_date:string}> $blocks
 */
function lh_calendar_night_blocked(string $ymd, array $blocks): bool
{
    foreach ($blocks as $b) {
        $s = (string) ($b['start_date'] ?? '');
        $e = (string) ($b['end_date'] ?? '');
        if (strlen($s) === 10 && strlen($e) === 10 && $s <= $ymd && $ymd < $e) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array<string,mixed>> $bookings
 *
 * @return array<string,mixed>|null
 */
function lh_calendar_booking_for_night(string $ymd, array $bookings): ?array
{
    foreach ($bookings as $b) {
        $ci = (string) ($b['check_in'] ?? '');
        $co = (string) ($b['check_out'] ?? '');
        if (strlen($ci) === 10 && strlen($co) === 10 && $ci <= $ymd && $ymd < $co) {
            return $b;
        }
    }

    return null;
}

/**
 * Short label for a blocked night (no active booking on that night).
 *
 * @param list<array<string,mixed>> $blocks
 */
function lh_calendar_blocked_cell_label(string $ymd, array $blocks): ?string
{
    $hasIcal = false;
    $hasAny = false;
    foreach ($blocks as $b) {
        $s = (string) ($b['start_date'] ?? '');
        $e = (string) ($b['end_date'] ?? '');
        if (strlen($s) !== 10 || strlen($e) !== 10 || !($s <= $ymd && $ymd < $e)) {
            continue;
        }
        $hasAny = true;
        if (($b['source'] ?? '') === 'ical_import') {
            $hasIcal = true;
        }
    }
    if (!$hasAny) {
        return null;
    }

    return $hasIcal ? 'REALTY' : 'Blocat';
}

$roMonths = [
    1 => 'Ian', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mai', 6 => 'Iun',
    7 => 'Iul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Noi', 12 => 'Dec',
];
$roDow = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];

$monthSpans = [];
if ($dayCount > 0) {
    $i = 0;
    while ($i < $dayCount) {
        $d0 = new DateTimeImmutable($dates[$i] . ' 12:00:00');
        $m = (int) $d0->format('n');
        $span = 1;
        $j = $i + 1;
        while ($j < $dayCount) {
            $dj = new DateTimeImmutable($dates[$j] . ' 12:00:00');
            if ((int) $dj->format('n') !== $m) {
                break;
            }
            $span++;
            $j++;
        }
        $monthSpans[] = ['label' => $roMonths[$m] ?? $d0->format('M'), 'span' => $span];
        $i = $j;
    }
}
$todayIdx = array_search($todayYmd, $dates, true);
$calScrollTodayIdx = $todayIdx === false ? -1 : (int) $todayIdx;

include __DIR__ . '/includes/header.php';
?>

<div class="max-w-full flex min-h-0 flex-col h-[calc(100dvh-2.5rem*2)] max-h-[calc(100dvh-2.5rem*2)]">
    <div class="mb-6 flex shrink-0 flex-col gap-4 sm:gap-4 lg:flex-row lg:items-stretch lg:justify-between">
        <div class="min-w-0 max-w-2xl">
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Calendar rezervări și prețuri</h2>
            <p class="text-slate-500 mt-1 max-w-2xl">Vizualizare pe proprietăți: preț pe noapte, rezervări și blocări. Selectează zile libere consecutive pentru un preț special (se salvează în perioadele de preț ale proprietății).</p>
        </div>
        <div class="flex w-full min-w-0 flex-col gap-0 sm:w-auto lg:shrink-0 lg:pl-2 lg:max-w-md">
            <form method="get" class="flex flex-wrap items-center gap-2 bg-white px-3 pt-3 pb-2 rounded-t-2xl border border-slate-100 shadow-sm border-b-0">
                <div class="flex flex-col">
                    <label class="block text-[9px] uppercase font-bold text-slate-400 tracking-widest mb-0.5">De la</label>
                    <input type="date" name="from" value="<?php echo htmlspecialchars($fromYmd, ENT_QUOTES, 'UTF-8'); ?>" class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold">
                </div>
                <div class="flex flex-col">
                    <label class="block text-[9px] uppercase font-bold text-slate-400 tracking-widest mb-0.5">Zile</label>
                    <select name="days" class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs font-bold">
                        <?php foreach ([30, 45, 60, 90, 120] as $opt): ?>
                            <option value="<?php echo $opt; ?>"<?php echo $dayCount === $opt ? ' selected' : ''; ?>><?php echo $opt; ?> zile</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="bg-cta text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:brightness-110 self-end">Afișează</button>
            </form>
            <div class="flex items-center justify-between gap-1 bg-white px-3 py-2 rounded-b-2xl border border-slate-100 shadow-sm border-t border-slate-100">
                <a href="calendar.php?from=<?php echo htmlspecialchars($prevFrom, ENT_QUOTES, 'UTF-8'); ?>&days=<?php echo (int) $dayCount; ?>" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors">← Înapoi</a>
                <a href="calendar.php?from=<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>&days=<?php echo (int) $dayCount; ?>" class="px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors">Azi</a>
                <a href="calendar.php?from=<?php echo htmlspecialchars($nextFrom, ENT_QUOTES, 'UTF-8'); ?>&days=<?php echo (int) $dayCount; ?>" class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-500 hover:bg-slate-100 hover:text-slate-800 transition-colors">Înainte →</a>
            </div>
        </div>
    </div>

    <?php if ($flashOk !== ''): ?>
        <div class="mb-4 shrink-0 p-4 rounded-2xl border border-black/10 bg-brand-100 text-ink font-bold"><?php echo htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($flashErr !== ''): ?>
        <div class="mb-4 shrink-0 p-4 rounded-2xl border border-red-200 bg-red-50 text-red-800 font-bold"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div id="calCalendarRoot" class="flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" style="--cal-cell: 44px;">
        <div id="calVertScroll" class="min-h-0 w-full min-w-0 flex-1 overflow-y-auto overflow-x-hidden [scrollbar-gutter:stable]">
            <div class="cal-sticky-header flex w-full min-w-0 items-stretch sticky top-0 z-50 border-b border-slate-200 shadow-sm [isolation:isolate]">
                <div class="w-64 shrink-0 border-r border-slate-200 flex flex-col justify-end p-2 h-[76px] min-h-[76px] bg-slate-50/95">
                    <input type="search" id="calPropFilter" placeholder="Caută ID, nume, adresă…" class="w-full text-xs font-bold border border-slate-200 rounded-xl px-3 py-2 bg-white" autocomplete="off">
                </div>
                <div class="min-w-0 flex-1 min-h-0 flex flex-col bg-white/95">
                    <div id="calDateHeaderHScroll" class="w-full min-w-0 overflow-x-auto [scrollbar-gutter:stable]">
                    <div class="inline-block align-top" style="min-width: calc(var(--cal-cell) * <?php echo (int) $dayCount; ?>);">
                <div class="h-[76px] min-h-[76px] shrink-0 flex flex-col border-b border-slate-200 bg-white">
                    <div class="flex h-[38px] shrink-0 min-h-0 border-b border-slate-200 bg-slate-50 text-[10px] font-extrabold uppercase tracking-widest text-slate-500">
                        <?php foreach ($monthSpans as $ms): ?>
                            <div class="flex h-full items-center justify-center border-r border-slate-200 text-center" style="width: calc(var(--cal-cell) * <?php echo (int) $ms['span']; ?>); min-width: calc(var(--cal-cell) * <?php echo (int) $ms['span']; ?>);">
                                <?php echo htmlspecialchars($ms['label'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex flex-1 min-h-0 bg-white text-[10px] font-bold h-[38px] min-h-0">
                        <?php foreach ($dates as $ymd): ?>
                            <?php
                            $dt = new DateTimeImmutable($ymd . ' 12:00:00');
                            $w = (int) $dt->format('w');
                            $isWeekend = $w === 0 || $w === 6;
                            $isToday = $ymd === $todayYmd;
                            $dow = $roDow[$w] ?? $dt->format('D');
                            ?>
                            <div class="shrink-0 flex h-full min-h-0 flex-col items-center justify-center border-r border-slate-100 relative <?php echo $isWeekend ? 'text-red-500' : 'text-slate-600'; ?>"
                                style="width: var(--cal-cell); min-width: var(--cal-cell);">
                                <?php if ($isToday): ?>
                                    <span class="absolute left-1/2 top-0 bottom-0 w-0.5 bg-red-500 z-20 -translate-x-1/2 pointer-events-none" title="Azi"></span>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($dow, ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="text-slate-900"><?php echo htmlspecialchars($dt->format('j'), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                    </div>
                    </div>
                </div>
            </div>
            <div class="flex w-full min-w-0 items-stretch">
                <div class="w-64 shrink-0 border-r border-slate-200 flex flex-col bg-slate-50/80 [isolation:isolate]">
                    <div>
                <?php foreach ($properties as $p): ?>
                    <?php
                    $pid = (int) $p['id'];
                    $propJson = htmlspecialchars(
                        json_encode([
                            'id' => $pid,
                            'title' => (string) ($p['title'] ?? ''),
                            'lot_id' => (string) ($p['lot_id'] ?? ''),
                            'address' => (string) ($p['address'] ?? ''),
                            'city' => (string) ($p['city'] ?? ''),
                            'district' => (string) ($p['district'] ?? ''),
                            'price' => (float) ($p['price'] ?? 0),
                            'price_weekend' => isset($p['price_weekend']) ? (float) $p['price_weekend'] : 0.0,
                            'is_active' => (int) ($p['is_active'] ?? 0),
                        ], JSON_UNESCAPED_UNICODE),
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    $searchBlob = strtolower(
                        (string) $p['id'] . ' ' . ($p['title'] ?? '') . ' ' . ($p['lot_id'] ?? '') . ' ' . ($p['address'] ?? '') . ' ' . ($p['city'] ?? '') . ' ' . ($p['district'] ?? '')
                    );
                    $propTitlePlain = (string) ($p['title'] ?? '');
                    $isInactiveProp = (int) ($p['is_active'] ?? 0) !== 1;
                    $calPropRowTitle = $propTitlePlain;
                    if ($isInactiveProp) {
                        $calPropRowTitle = $calPropRowTitle !== '' ? $calPropRowTitle . ' — Inactivă' : 'Inactivă';
                    }
                    ?>
                    <button type="button"
                        class="cal-prop-row w-full text-left h-[60px] min-h-[60px] shrink-0 flex flex-col justify-center px-3 py-0 border-b border-slate-100 hover:bg-brand-100/60 transition-colors<?php echo $isInactiveProp ? ' border-l-4 border-amber-400' : ''; ?>"
                        data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>"
                        data-property="<?php echo $propJson; ?>"
                        title="<?php echo htmlspecialchars($calPropRowTitle, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="min-w-0 w-full font-bold text-slate-900 text-xs truncate"><?php echo htmlspecialchars($propTitlePlain, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="min-w-0 w-full text-[10px] text-cta font-extrabold uppercase mt-0.5 truncate">LOT ID: <?php echo htmlspecialchars((string) ($p['lot_id'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
                    </button>
                <?php endforeach; ?>
                    </div>
                </div>
                <div class="flex min-w-0 min-h-0 flex-1 self-stretch flex flex-col min-h-0">
                <div id="calGridHScroll" class="relative z-0 w-full min-w-0 min-h-0 shrink-0 self-stretch overflow-x-auto [scrollbar-gutter:stable]">
                    <div class="inline-block align-top" style="min-width: calc(var(--cal-cell) * <?php echo (int) $dayCount; ?>);">
                <?php foreach ($properties as $p): ?>
                    <?php
                    $pid = (int) $p['id'];
                    $pBook = $bookingsByProperty[$pid] ?? [];
                    $pBlock = $blocksByProperty[$pid] ?? [];
                    $pPeriods = $periodsByProperty[$pid] ?? [];
                    $searchBlob = strtolower(
                        (string) $p['id'] . ' ' . ($p['title'] ?? '') . ' ' . ($p['lot_id'] ?? '') . ' ' . ($p['address'] ?? '') . ' ' . ($p['city'] ?? '') . ' ' . ($p['district'] ?? '')
                    );
                    ?>
                    <div class="cal-grid-row relative flex h-[60px] min-h-[60px] max-h-[60px] shrink-0 overflow-hidden border-b border-slate-100 select-none"
                        data-property-id="<?php echo $pid; ?>"
                        data-row-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>"
                        style="width: calc(var(--cal-cell) * <?php echo (int) $dayCount; ?>);">
                        <?php if ($todayIdx !== false): ?>
                            <span class="absolute top-0 bottom-0 w-0.5 bg-red-500 z-[25] pointer-events-none" style="left: calc(var(--cal-cell) * <?php echo (int) $todayIdx; ?> + var(--cal-cell) * 0.5 - 1px);" title="Azi"></span>
                        <?php endif; ?>
                        <?php foreach ($dates as $ymd): ?>
                            <?php
                            $bookingNight = lh_calendar_booking_for_night($ymd, $pBook);
                            $blocked = lh_calendar_night_blocked($ymd, $pBlock);
                            $selectable = ($bookingNight === null && !$blocked);
                            $rate = lh_booking_night_rate_for_date($ymd, $p, $pPeriods);
                            $rateDisp = $rate > 0 ? (string) (int) round($rate) : '—';
                            ?>
                            <?php if ($bookingNight !== null): ?>
                                <?php
                                $guestName = (string) ($bookingNight['guest_name'] ?? '');
                                $guestDisp = $guestName !== '' ? $guestName : 'Rezervare';
                                if (mb_strlen($guestDisp) > 40) {
                                    $guestDisp = mb_substr($guestDisp, 0, 38) . '…';
                                }
                                $bookPayload = lh_admin_booking_modal_payload(array_merge($bookingNight, [
                                    'property_title' => (string) ($p['title'] ?? ''),
                                    'property_lot_id' => (string) ($p['lot_id'] ?? ''),
                                ]), 'calendar');
                                $bookJson = htmlspecialchars(json_encode($bookPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                $hasCouponCal =
                                    (float) ($bookingNight['coupon_discount_amount'] ?? 0) > 0.004
                                    && trim((string) ($bookingNight['coupon_code'] ?? '')) !== '';
                                ?>
                                <div class="cal-cell cal-cell-booking shrink-0 flex flex-col items-center justify-center border-r border-rose-300/90 text-[10px] font-bold text-rose-950 leading-tight text-center px-0.5 relative z-[3] bg-rose-200 hover:bg-rose-300/95 cursor-pointer select-none"
                                    style="width: var(--cal-cell); min-width: var(--cal-cell);"
                                    role="button"
                                    tabindex="0"
                                    data-ymd="<?php echo htmlspecialchars($ymd, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-selectable="0"
                                    data-booking="<?php echo $bookJson; ?>"
                                    title="Click pentru detalii rezervare">
                                    <span class="flex flex-col items-center justify-center gap-0 leading-tight w-full min-w-0">
                                        <span class="line-clamp-3 break-words"><?php echo htmlspecialchars($guestDisp, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($hasCouponCal): ?>
                                            <span class="text-[11px] font-black leading-none text-rose-900/95 shrink-0 select-none mt-px" aria-hidden="true">%</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php elseif ($blocked): ?>
                                <?php
                                $blLabel = lh_calendar_blocked_cell_label($ymd, $pBlock) ?? 'Blocat';
                                $notesHint = '';
                                foreach ($pBlock as $blk) {
                                    $s = (string) ($blk['start_date'] ?? '');
                                    $e = (string) ($blk['end_date'] ?? '');
                                    if (strlen($s) === 10 && strlen($e) === 10 && $s <= $ymd && $ymd < $e && !empty($blk['notes'])) {
                                        $notesHint = (string) $blk['notes'];
                                        break;
                                    }
                                }
                                $titleBl = $blLabel . ($notesHint !== '' ? ': ' . $notesHint : '');
                                ?>
                                <div class="cal-cell cal-cell-blocked shrink-0 flex flex-col items-center justify-center border-r border-slate-400/70 text-[9px] font-extrabold uppercase tracking-tight text-slate-800 relative z-0 bg-slate-300/90 select-none"
                                    style="width: var(--cal-cell); min-width: var(--cal-cell);"
                                    data-ymd="<?php echo htmlspecialchars($ymd, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-selectable="0"
                                    title="<?php echo htmlspecialchars($titleBl, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($blLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            <?php else: ?>
                                <?php
                                $winPeriod = lh_pricing_first_period_for_night_rate($ymd, $pPeriods);
                                $isCalSpecial = lh_pricing_period_is_calendar_special_row($winPeriod);
                                $calSpecMin = lh_pricing_period_calendar_special_min_stay($winPeriod);
                                $displayMinNights = null;
                                if ($isCalSpecial) {
                                    $displayMinNights = $calSpecMin;
                                    if ($displayMinNights === null) {
                                        $displayMinNights = max(1, (int) ($p['min_stay'] ?? 1));
                                    }
                                }
                                $titleCal = '';
                                if ($isCalSpecial) {
                                    $titleCal = 'Preț special (calendar) · sejur min. ' . (int) $displayMinNights . ' nopți';
                                }
                                $calFreeLayout = $isCalSpecial
                                    ? 'flex flex-col items-center justify-center text-center leading-tight py-0.5'
                                    : 'items-center justify-center text-[11px] font-medium text-slate-600';
                                ?>
                                <div class="cal-cell cal-cell-free shrink-0 flex border-r border-slate-100 relative z-0 <?php echo $selectable ? 'cal-cell-selectable cursor-crosshair bg-white hover:bg-brand-50' : ''; ?> <?php echo $calFreeLayout; ?>"
                                    style="width: var(--cal-cell); min-width: var(--cal-cell);"
                                    data-ymd="<?php echo htmlspecialchars($ymd, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-selectable="<?php echo $selectable ? '1' : '0'; ?>"
                                    title="<?php echo $titleCal !== '' ? htmlspecialchars($titleCal, ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    <?php if (!$isCalSpecial): ?>
                                        <?php echo htmlspecialchars($rateDisp, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php else: ?>
                                        <span class="text-[11px] font-extrabold text-slate-900 leading-tight"><?php echo htmlspecialchars($rateDisp, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <span class="text-[10px] font-bold text-slate-700 leading-tight whitespace-nowrap">*<?php echo (int) $displayMinNights; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal proprietate -->
<div id="modalProperty" class="fixed inset-0 z-[8000] hidden items-center justify-center bg-slate-900/70 p-4">
    <div class="bg-white max-w-md w-full rounded-2xl shadow-2xl p-8 relative">
        <button type="button" class="cal-modal-close absolute top-4 right-4 text-slate-400 hover:text-slate-900 font-bold text-sm">✕</button>
        <h3 id="mpTitle" class="text-xl font-black text-slate-900 pr-8"></h3>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0">LOT</dt><dd id="mpLot" class="text-slate-800"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0">Adresă</dt><dd id="mpAddr" class="text-slate-800"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0">Preț/noapte</dt><dd id="mpPrice" class="text-slate-800"></dd></div>
            <div class="flex gap-2"><dt class="text-slate-400 font-bold shrink-0">Status</dt><dd id="mpAct" class="text-slate-800"></dd></div>
        </dl>
        <a id="mpEdit" href="#" class="mt-6 inline-flex items-center justify-center w-full py-3 rounded-xl bg-cta text-white font-bold hover:brightness-110">Editează proprietatea</a>
    </div>
</div>

<!-- Modal preț special -->
<div id="modalSpecial" class="fixed inset-0 z-[8000] hidden items-center justify-center bg-slate-900/70 p-4">
    <form method="post" class="bg-white max-w-md w-full rounded-2xl shadow-2xl p-8 relative">
        <?php lh_csrf_field(); ?>
        <input type="hidden" name="calendar_action" value="special_price">
        <input type="hidden" name="property_id" id="spPropId" value="">
        <input type="hidden" name="range_start" id="spRangeStart" value="">
        <input type="hidden" name="range_end_exclusive" id="spRangeEndEx" value="">
        <input type="hidden" name="redirect_from" value="<?php echo htmlspecialchars($fromYmd, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="redirect_days" value="<?php echo (int) $dayCount; ?>">
        <button type="button" class="cal-modal-close absolute top-4 right-4 text-slate-400 hover:text-slate-900 font-bold text-sm">✕</button>
        <h3 class="text-xl font-black text-slate-900">Preț special</h3>
        <p id="spRangeLabel" class="text-sm text-slate-500 mt-2"></p>
        <div class="mt-4 space-y-3">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Preț / noapte (<?php echo htmlspecialchars(lh_currency_code(), ENT_QUOTES, 'UTF-8'); ?>)</label>
                <input type="text" name="price" required class="w-full border border-slate-200 rounded-xl px-3 py-2 font-bold" placeholder="ex. 1200">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Preț weekend (opțional, <?php echo htmlspecialchars(lh_currency_code(), ENT_QUOTES, 'UTF-8'); ?>)</label>
                <input type="text" name="price_weekend" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-bold" placeholder="lăsat gol = același ca weekday">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Min. nopți (opțional)</label>
                <input type="number" min="1" name="min_stay" class="w-full border border-slate-200 rounded-xl px-3 py-2 font-bold" placeholder="lăsat gol = minim proprietate">
                <p class="text-[10px] text-slate-400 mt-1 leading-snug">Se aplică doar la rezervări cu tot sejurul în acest interval; înlocuiește minimul de bază.</p>
            </div>
        </div>
        <button type="submit" class="mt-6 w-full py-3 rounded-xl bg-cta text-white font-bold hover:brightness-110">Salvează</button>
    </form>
</div>

<script>
(function () {
    const LH_CUR = <?php echo json_encode(lh_currency_client_config(), JSON_UNESCAPED_UNICODE); ?>;
    function lhCalCurrencySuffix() {
        if (LH_CUR && LH_CUR.suffix != null && String(LH_CUR.suffix) !== '') {
            return String(LH_CUR.suffix);
        }
        return ' MDL';
    }
    const dates = <?php echo json_encode($dates, JSON_UNESCAPED_UNICODE); ?>;
    const calScrollTodayIdx = <?php echo (int) $calScrollTodayIdx; ?>;

    (function calScrollToTodayH() {
        function run() {
            const hScroll = document.getElementById('calDateHeaderHScroll');
            const sc = document.getElementById('calGridHScroll');
            const root = document.getElementById('calCalendarRoot');
            if (!root || calScrollTodayIdx < 0) {
                return;
            }
            if (!sc && !hScroll) {
                return;
            }
            const w = parseFloat(String(getComputedStyle(root).getPropertyValue('--cal-cell') || '44').trim(), 10);
            const cellW = Number.isFinite(w) && w > 0 ? w : 44;
            const pos = calScrollTodayIdx * cellW;
            const ref = sc || hScroll;
            const maxL = Math.max(0, ref.scrollWidth - ref.clientWidth);
            const v = Math.min(pos, maxL);
            if (hScroll) {
                hScroll.scrollLeft = v;
            }
            if (sc) {
                sc.scrollLeft = v;
            }
        }
        if (typeof requestAnimationFrame === 'function') {
            requestAnimationFrame(run);
        } else {
            setTimeout(run, 0);
        }
    }());

    (function calBindHScrollSync() {
        const a = document.getElementById('calDateHeaderHScroll');
        const b = document.getElementById('calGridHScroll');
        if (!a || !b) {
            return;
        }
        let syncing = false;
        a.addEventListener('scroll', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            b.scrollLeft = a.scrollLeft;
            syncing = false;
        }, { passive: true });
        b.addEventListener('scroll', function () {
            if (syncing) {
                return;
            }
            syncing = true;
            a.scrollLeft = b.scrollLeft;
            syncing = false;
        }, { passive: true });
    }());

    document.getElementById('calPropFilter')?.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        document.querySelectorAll('.cal-prop-row').forEach(function (btn) {
            const hay = (btn.getAttribute('data-search') || '');
            btn.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
        });
        document.querySelectorAll('.cal-grid-row').forEach(function (row) {
            const hay = (row.getAttribute('data-row-search') || '');
            row.style.display = !q || hay.indexOf(q) !== -1 ? '' : 'none';
        });
    });

    function openModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.remove('hidden');
        el.classList.add('flex');
        try {
            if (typeof lucide !== 'undefined' && lucide.createIcons) {
                lucide.createIcons();
            }
        } catch (e2) {
            /* ignore icon errors */
        }
    }

    const CAL_REDIRECT_FROM = <?php echo json_encode($fromYmd, JSON_UNESCAPED_UNICODE); ?>;
    const CAL_REDIRECT_DAYS = <?php echo (int) $dayCount; ?>;

    function fillAndOpenBookingModal(rawAttr) {
        let b;
        try {
            b = JSON.parse(rawAttr || '{}');
        } catch (err) {
            return;
        }
        if (window.LhBookingModal) {
            window.LhBookingModal.setCalendarContext(CAL_REDIRECT_FROM, CAL_REDIRECT_DAYS);
            window.LhBookingModal.open(b);
        }
    }

    function closeModalEl(el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
    }
    document.querySelectorAll('.cal-modal-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modal = btn.closest('[id^="modal"]');
            if (modal) closeModalEl(modal);
        });
    });
    document.querySelectorAll('[id^="modal"]').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModalEl(modal);
        });
    });

    document.querySelectorAll('.cal-prop-row').forEach(function (btn) {
        btn.addEventListener('click', function () {
            let p;
            try { p = JSON.parse(btn.getAttribute('data-property') || '{}'); } catch (e) { return; }
            document.getElementById('mpTitle').textContent = p.title || '';
            document.getElementById('mpLot').textContent = p.lot_id ? ('#' + p.lot_id) : '—';
            const addr = [p.address, p.district, p.city].filter(Boolean).join(', ');
            document.getElementById('mpAddr').textContent = addr || '—';
            let pr = Math.round(p.price || 0).toLocaleString('ro-RO') + lhCalCurrencySuffix();
            if (p.price_weekend > 0) pr += ' · weekend ' + Math.round(p.price_weekend).toLocaleString('ro-RO') + lhCalCurrencySuffix();
            document.getElementById('mpPrice').textContent = pr;
            document.getElementById('mpAct').textContent = p.is_active ? 'Activă' : 'Inactivă';
            document.getElementById('mpEdit').href = 'edit-property.php?id=' + encodeURIComponent(p.id);
            openModal('modalProperty');
        });
    });

    const calScroll = document.getElementById('calGridHScroll');
    calScroll?.addEventListener('click', function (e) {
        const cell = e.target.closest('.cal-cell-booking');
        if (!cell) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        fillAndOpenBookingModal(cell.getAttribute('data-booking'));
    });
    calScroll?.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        const cell = e.target.closest('.cal-cell-booking');
        if (!cell || e.target !== cell) {
            return;
        }
        e.preventDefault();
        fillAndOpenBookingModal(cell.getAttribute('data-booking'));
    });

    let dragProp = null;
    let dragStartIdx = null;
    let dragCurIdx = null;
    let dragging = false;

    function clearHighlight(row) {
        if (!row) return;
        row.querySelectorAll('.cal-cell').forEach(function (c) {
            c.classList.remove('ring-2', 'ring-cta', 'z-[5]');
        });
    }
    function highlightRange(row, i0, i1) {
        clearHighlight(row);
        const a = Math.min(i0, i1);
        const b = Math.max(i0, i1);
        const cells = row.querySelectorAll('.cal-cell');
        for (let k = a; k <= b; k++) {
            const c = cells[k];
            if (!c || c.getAttribute('data-selectable') !== '1') return false;
            c.classList.add('ring-2', 'ring-cta', 'z-[5]');
        }
        return true;
    }

    document.querySelectorAll('.cal-grid-row').forEach(function (row) {
        row.addEventListener('mousedown', function (e) {
            const cell = e.target.closest('.cal-cell-selectable');
            if (!cell || cell.getAttribute('data-selectable') !== '1') return;
            dragging = true;
            dragProp = row.getAttribute('data-property-id');
            const cells = Array.prototype.slice.call(row.querySelectorAll('.cal-cell'));
            dragStartIdx = cells.indexOf(cell);
            dragCurIdx = dragStartIdx;
            if (dragStartIdx < 0) return;
            highlightRange(row, dragStartIdx, dragStartIdx);
            e.preventDefault();
        });
        row.addEventListener('mouseover', function (e) {
            if (!dragging || dragProp !== row.getAttribute('data-property-id')) return;
            const cell = e.target.closest('.cal-cell');
            if (!cell || cell.getAttribute('data-selectable') !== '1') return;
            const cells = Array.prototype.slice.call(row.querySelectorAll('.cal-cell'));
            const idx = cells.indexOf(cell);
            if (idx < 0) return;
            dragCurIdx = idx;
            if (!highlightRange(row, dragStartIdx, dragCurIdx)) {
                clearHighlight(row);
            }
        });
    });

    document.addEventListener('mouseup', function () {
        if (!dragging) return;
        dragging = false;
        const row = document.querySelector('.cal-grid-row[data-property-id="' + dragProp + '"]');
        dragProp = null;
        if (!row || dragStartIdx === null || dragCurIdx === null) return;
        const a = Math.min(dragStartIdx, dragCurIdx);
        const b = Math.max(dragStartIdx, dragCurIdx);
        dragStartIdx = null;
        dragCurIdx = null;
        const cells = row.querySelectorAll('.cal-cell');
        for (let k = a; k <= b; k++) {
            if (!cells[k] || cells[k].getAttribute('data-selectable') !== '1') {
                clearHighlight(row);
                return;
            }
        }
        const y0 = cells[a].getAttribute('data-ymd');
        const y1 = cells[b].getAttribute('data-ymd');
        if (!y0 || !y1) { clearHighlight(row); return; }
        const i0 = dates.indexOf(y0);
        const i1 = dates.indexOf(y1);
        if (i0 < 0 || i1 < 0) { clearHighlight(row); return; }
        const endEx = dates[i1 + 1] || null;
        if (!endEx) {
            alert('Selectarea trebuie să se încheie înainte de ultima zi vizibilă a ferestrei (sau extinde intervalul „Zile”).');
            clearHighlight(row);
            return;
        }
        document.getElementById('spPropId').value = row.getAttribute('data-property-id') || '';
        document.getElementById('spRangeStart').value = y0;
        document.getElementById('spRangeEndEx').value = endEx;
        document.getElementById('spRangeLabel').textContent = y0 + ' → ' + y1 + ' (inclusiv; checkout exclus)';
        clearHighlight(row);
        openModal('modalSpecial');
    });
})();
document.addEventListener('DOMContentLoaded', function () { lucide.createIcons(); });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
