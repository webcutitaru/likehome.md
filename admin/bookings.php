<?php

declare(strict_types=1);

include('../config.php');
require_once __DIR__ . '/../includes/checkin_reminder_send.php';
require_once __DIR__ . '/../includes/booking_payment.php';
require_once __DIR__ . '/../includes/booking_admin.php';
include('includes/header.php');

$pdo = getPDO();

$message = '';
$message_type = 'success';

$search_q_raw = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$search_q = $search_q_raw;
if (function_exists('mb_strlen') && function_exists('mb_substr')) {
    if (mb_strlen($search_q, 'UTF-8') > 120) {
        $search_q = mb_substr($search_q, 0, 120, 'UTF-8');
    }
} elseif (strlen($search_q) > 120) {
    $search_q = substr($search_q, 0, 120);
}

$status_raw = isset($_GET['status']) ? (string) $_GET['status'] : 'all';
if ($status_raw === 'confirmed') {
    $status_filter = 'active';
} else {
    $status_filter = $status_raw;
}
$allowed_status_filters = ['all', 'active', 'finished', 'cancelled'];
if (!in_array($status_filter, $allowed_status_filters, true)) {
    $status_filter = 'all';
}

$focus_booking_id = isset($_GET['booking_id']) ? (int) $_GET['booking_id'] : 0;
if ($focus_booking_id > 0) {
    $status_filter = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!lh_csrf_verify_post()) {
        $message = 'Sesiune invalidă. Reîncarcă pagina și încearcă din nou.';
        $message_type = 'warning';
    } else {
        $booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
        $action = $_POST['action'] ?? '';

        if ($booking_id > 0 && $action === 'send_checkin_reminder') {
            $sqlRem = <<<'SQL'
SELECT b.id AS booking_id, b.guest_name, b.guest_email, b.guest_phone, b.check_in, b.check_out, b.guests, b.total_price,
       p.id AS property_id, p.title AS property_title, p.check_in_start, p.check_in_end, p.check_out_end,
       p.address, p.city, p.district, p.floor,
       p.pre_checkin_email_message
FROM bookings b
INNER JOIN properties p ON p.id = b.property_id
WHERE b.id = ? AND b.status = 'confirmed' AND b.check_out >= CURDATE()
LIMIT 1
SQL;
            $stmtRem = $pdo->prepare($sqlRem);
            $stmtRem->execute([$booking_id]);
            $remRow = $stmtRem->fetch(PDO::FETCH_ASSOC);
            if (!$remRow) {
                $message = 'Rezervarea nu există, nu este confirmată sau sejurul este deja încheiat.';
                $message_type = 'warning';
            } else {
                $now = new DateTimeImmutable('now');
                $adminEmailRem = lh_booking_resolve_admin_notification_email();
                $tgToken = defined('TELEGRAM_BOT_TOKEN') ? trim((string) TELEGRAM_BOT_TOKEN) : '';
                $tgChat = defined('TELEGRAM_CHAT_ID') ? trim((string) TELEGRAM_CHAT_ID) : '';
                $out = lh_checkin_reminder_send_for_booking_row($pdo, $remRow, $now, [
                    'enforce_24h_window' => false,
                    'admin_email' => $adminEmailRem,
                    'telegram_bot_token' => $tgToken,
                    'telegram_chat_id' => $tgChat,
                    'sent_at_update_mode' => 'always',
                    'log_context' => 'checkin_reminder_admin',
                ]);
                if ($out['result'] === 'sent') {
                    lh_admin_log_activity($conn, 'booking_checkin_reminder_manual', 'booking', $booking_id, [
                        'property_id' => (int) ($remRow['property_id'] ?? 0),
                    ]);
                    $message = 'Reminder-ul check-in a fost trimis la oaspete.';
                    $message_type = 'success';
                } elseif ($out['result'] === 'skipped') {
                    $message = 'Reminder-ul nu a putut fi trimis (date check-in invalide).';
                    $message_type = 'warning';
                } else {
                    $reason = (string) ($out['reason'] ?? '');
                    if ($reason === 'invalid_guest_email') {
                        $message = 'Emailul oaspetelui nu este valid.';
                    } elseif ($reason === 'client_mail_failed') {
                        $message = 'Trimiterea emailului a eșuat. Verifică configurația de mail a serverului.';
                    } else {
                        $message = 'Nu s-a putut trimite reminder-ul.';
                    }
                    $message_type = 'warning';
                }
            }
        } elseif ($booking_id > 0 && $action === 'refund') {
            require_once __DIR__ . '/../includes/booking_refund.php';
            $refundAmountRaw = trim((string) ($_POST['refund_amount'] ?? ''));
            $refundAmount = $refundAmountRaw === '' ? null : (float) str_replace(',', '.', $refundAmountRaw);
            $refundReason = trim((string) ($_POST['refund_reason'] ?? ''));
            $refundOut = lh_booking_process_maib_refund(
                $pdo,
                $booking_id,
                $refundAmount,
                $refundReason !== '' ? $refundReason : null
            );
            if (!empty($refundOut['ok'])) {
                lh_admin_log_activity($conn, 'booking_refund', 'booking', $booking_id, [
                    'refund_id' => (string) ($refundOut['refund_id'] ?? ''),
                    'refunded_amount' => (float) ($refundOut['refunded_amount'] ?? 0),
                    'remaining' => (float) ($refundOut['remaining'] ?? 0),
                ]);
                $message = (string) ($refundOut['message'] ?? 'Rambursarea a fost inițiată.');
                $message_type = 'success';
            } else {
                $message = (string) ($refundOut['message'] ?? 'Rambursarea a eșuat.');
                $message_type = 'warning';
            }
        } elseif ($booking_id > 0 && $action === 'update') {
            $updateOut = lh_admin_process_booking_update($pdo, $_POST);
            if (!empty($updateOut['ok'])) {
                lh_admin_log_activity($conn, 'booking_update', 'booking', $booking_id, [
                    'property_id' => (int) ($updateOut['property_id'] ?? 0),
                    'check_in' => (string) ($updateOut['check_in'] ?? ''),
                    'check_out' => (string) ($updateOut['check_out'] ?? ''),
                    'source' => 'bookings',
                ]);
                $message = (string) ($updateOut['message'] ?? 'Rezervarea a fost actualizată.');
                $message_type = 'success';
            } else {
                $message = (string) ($updateOut['message'] ?? 'Salvarea a eșuat.');
                $message_type = 'warning';
            }
        } elseif ($booking_id > 0 && in_array($action, ['confirm', 'cancel'], true)) {
            $stmtBook = $pdo->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
            $stmtBook->execute([$booking_id]);
            $booking = $stmtBook->fetch();

            if ($booking) {
                if ($action === 'confirm') {
                    $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$booking_id]);

                    $property_id = (int) $booking['property_id'];
                    $check_in = (string) $booking['check_in'];
                    $check_out = (string) $booking['check_out'];
                    $external_event_id = 'booking-' . $booking_id;
                    $notes = 'Booking #' . $booking_id;

                    $exists = $pdo->prepare(
                        'SELECT id FROM blocked_dates WHERE property_id = ? AND source = ? AND external_event_id = ? LIMIT 1'
                    );
                    $exists->execute([$property_id, 'direct_booking', $external_event_id]);

                    if (!$exists->fetch()) {
                        $ins = $pdo->prepare(
                            'INSERT INTO blocked_dates (property_id, start_date, end_date, source, external_event_id, notes)
                             VALUES (?, ?, ?, ?, ?, ?)'
                        );
                        $ins->execute([
                            $property_id,
                            $check_in,
                            $check_out,
                            'direct_booking',
                            $external_event_id,
                            $notes,
                        ]);
                    }

                    lh_admin_log_activity($conn, 'booking_confirm', 'booking', $booking_id, [
                        'property_id' => $property_id,
                        'check_in' => $check_in,
                        'check_out' => $check_out,
                    ]);
                    $message = 'Rezervarea a fost confirmată.';
                    $message_type = 'success';
                }

                if ($action === 'cancel') {
                    require_once __DIR__ . '/../includes/booking_confirm.php';
                    $cancelOut = lh_booking_cancel_booking($pdo, $booking_id);
                    lh_admin_log_activity($conn, 'booking_cancel', 'booking', $booking_id, [
                        'property_id' => (int) ($booking['property_id'] ?? 0),
                    ]);
                    $message = (string) ($cancelOut['message'] ?? 'Rezervarea a fost anulată.');
                    $message_type = !empty($cancelOut['ok']) ? 'warning' : 'warning';
                }
            }
        }
    }
}

$data_total = $pdo->query('SELECT COUNT(*) AS total FROM bookings')->fetch() ?: ['total' => 0];
$data_active = $pdo->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'confirmed' AND check_out >= CURDATE()")->fetch() ?: ['total' => 0];
$data_finished = $pdo->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'confirmed' AND check_out < CURDATE()")->fetch() ?: ['total' => 0];
$data_cancelled = $pdo->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'cancelled'")->fetch() ?: ['total' => 0];

$sql = '
    SELECT b.*, p.title AS property_title, p.city AS property_city, p.lot_id AS property_lot_id
    FROM bookings b
    LEFT JOIN properties p ON p.id = b.property_id
';
$params = [];
$wheres = [];
switch ($status_filter) {
    case 'active':
        $wheres[] = "b.status = 'confirmed' AND b.check_out >= CURDATE()";
        break;
    case 'finished':
        $wheres[] = "b.status = 'confirmed' AND b.check_out < CURDATE()";
        break;
    case 'cancelled':
        $wheres[] = "b.status = 'cancelled'";
        break;
    case 'all':
    default:
        break;
}
if ($search_q !== '') {
    $like = lh_bookings_like_pattern($search_q);
    $wheres[] = '(b.guest_name LIKE ? OR b.guest_email LIKE ? OR b.guest_phone LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($wheres !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $wheres);
}
$sql .= ' ORDER BY b.id DESC';

$stmtList = $pdo->prepare($sql);
$stmtList->execute($params);
$booking_rows = $stmtList->fetchAll();

function lh_bookings_like_pattern(string $term): string
{
    $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

    return '%' . $term . '%';
}

function lh_bookings_query_url(array $overrides): string
{
    $base = ['status' => 'all', 'q' => '', 'booking_id' => ''];
    $merged = array_merge($base, $overrides);
    $parts = [];
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null || $v === 0 || $v === '0') {
            continue;
        }
        $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }

    return $parts === [] ? 'bookings.php' : ('bookings.php?' . implode('&', $parts));
}

function lh_booking_stay_finished_by_row(array $row): bool
{
    if (($row['status'] ?? '') !== 'confirmed') {
        return false;
    }
    $co = (string) ($row['check_out'] ?? '');
    if ($co === '') {
        return false;
    }
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');

    return $co < $today;
}

function booking_status_badge(string $status): string
{
    switch ($status) {
        case 'confirmed':
            return 'bg-brand-100 text-ink border border-black/8';
        case 'cancelled':
            return 'bg-slate-100 text-slate-600 border border-slate-200';
        default:
            return 'bg-slate-50 text-slate-700 border border-slate-200';
    }
}

function booking_row_status_badge_class(array $row): string
{
    if (lh_booking_stay_finished_by_row($row)) {
        return 'bg-slate-100 text-slate-700 border border-slate-200';
    }

    return booking_status_badge((string) ($row['status'] ?? ''));
}

function booking_status_label(string $status): string
{
    switch ($status) {
        case 'pending':
            return 'În așteptare';
        case 'confirmed':
            return 'Confirmată';
        case 'cancelled':
            return 'Anulată';
        default:
            return $status;
    }
}

function booking_row_status_label(array $row): string
{
    if (($row['status'] ?? '') === 'confirmed') {
        return lh_booking_stay_finished_by_row($row) ? 'Finalizată' : 'Activă';
    }

    return booking_status_label((string) ($row['status'] ?? ''));
}

function booking_filter_page_title(string $status_filter): string
{
    switch ($status_filter) {
        case 'all':
            return 'Toate rezervările';
        case 'active':
            return 'Rezervări active';
        case 'finished':
            return 'Rezervări finalizate';
        case 'cancelled':
            return 'Rezervări anulate';
        default:
            return 'Rezervările';
    }
}
?>

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-10">
        <div>
            <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Rezervări</h2>
            <p class="text-slate-500">Gestionează toate cererile și rezervările primite din site.</p>
        </div>
        <a href="dashboard.php" class="bg-cta text-white px-6 py-3 rounded-2xl font-bold flex items-center gap-2 hover:brightness-110 transition-all shadow-lg shadow-black/10">
            <i data-lucide="arrow-left" class="w-5 h-5"></i> Înapoi la Dashboard
        </a>
    </div>

    <?php if (!empty($message)): ?>
        <div class="mb-8 p-5 rounded-2xl border border-black/10 <?php echo $message_type === 'success' ? 'bg-brand-100 text-ink' : 'bg-slate-100 text-slate-800'; ?> font-bold shadow-sm">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <a href="<?php echo htmlspecialchars(lh_bookings_query_url(['status' => 'all', 'q' => $search_q]), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'all' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-slate-100 w-12 h-12 rounded-2xl flex items-center justify-center text-slate-700 mb-4">
                <i data-lucide="receipt-text"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Total Rezervări</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int)($data_total['total'] ?? 0); ?></h4>
        </a>
        <a href="<?php echo htmlspecialchars(lh_bookings_query_url(['status' => 'active', 'q' => $search_q]), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'active' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-brand-100 w-12 h-12 rounded-2xl flex items-center justify-center text-cta mb-4">
                <i data-lucide="badge-check"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Active</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int)($data_active['total'] ?? 0); ?></h4>
        </a>
        <a href="<?php echo htmlspecialchars(lh_bookings_query_url(['status' => 'finished', 'q' => $search_q]), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'finished' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-slate-100 w-12 h-12 rounded-2xl flex items-center justify-center text-slate-600 mb-4">
                <i data-lucide="calendar-check"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Finalizate</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int)($data_finished['total'] ?? 0); ?></h4>
        </a>
        <a href="<?php echo htmlspecialchars(lh_bookings_query_url(['status' => 'cancelled', 'q' => $search_q]), ENT_QUOTES, 'UTF-8'); ?>" class="bg-white p-6 rounded-2xl border <?php echo $status_filter === 'cancelled' ? 'border-slate-300 ring-2 ring-slate-200' : 'border-slate-100'; ?> shadow-sm hover:shadow-md transition-all block">
            <div class="bg-slate-100 w-12 h-12 rounded-2xl flex items-center justify-center text-slate-600 mb-4">
                <i data-lucide="x-circle"></i>
            </div>
            <p class="text-slate-400 text-sm font-bold uppercase tracking-widest">Anulate</p>
            <h4 class="text-3xl font-black text-slate-900 mt-1"><?php echo (int)($data_cancelled['total'] ?? 0); ?></h4>
        </a>
    </div>

    <form method="get" action="bookings.php" class="mb-8 flex flex-col sm:flex-row gap-3 sm:items-end sm:justify-between">
        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8'); ?>">
        <div class="flex-1 max-w-xl">
            <label for="bookings-search-q" class="block text-xs uppercase tracking-widest text-slate-400 font-bold mb-2">Caută rezervare</label>
            <input type="search" id="bookings-search-q" name="q" value="<?php echo htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Nume, email sau telefon" class="w-full border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-800 outline-none focus:ring-2 focus:ring-cta/25 focus:border-cta">
        </div>
        <div class="flex gap-2 shrink-0">
            <button type="submit" class="bg-cta text-white px-6 py-3 rounded-xl font-bold hover:brightness-110 transition-all">Caută</button>
            <?php if ($search_q !== ''): ?>
                <a href="<?php echo htmlspecialchars(lh_bookings_query_url(['status' => $status_filter]), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center px-5 py-3 rounded-xl font-bold border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all">Șterge căutarea</a>
            <?php endif; ?>
        </div>
    </form>

    <div class="flex items-center justify-between mb-6 px-1">
        <div>
            <p class="text-xs uppercase tracking-widest text-slate-400 font-bold">Filtru activ</p>
            <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">
                <?php echo htmlspecialchars(booking_filter_page_title($status_filter), ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($search_q !== ''): ?>
                    <span class="text-slate-400 font-bold text-base"> · căutare: „<?php echo htmlspecialchars($search_q, ENT_QUOTES, 'UTF-8'); ?>”</span>
                <?php endif; ?>
            </h3>
        </div>
        <?php if ($status_filter !== 'all' || $search_q !== ''): ?>
            <a href="<?php echo htmlspecialchars(lh_bookings_query_url(['status' => 'all']), ENT_QUOTES, 'UTF-8'); ?>" class="text-sm font-bold text-slate-500 hover:text-slate-900 transition-colors">Resetează filtrul</a>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-slate-400 text-[10px] uppercase font-bold tracking-widest">
                    <th class="px-8 py-5">Proprietate</th>
                    <th class="px-8 py-5">Client</th>
                    <th class="px-8 py-5">Perioadă</th>
                    <th class="px-8 py-5 text-center">Total</th>
                    <th class="px-8 py-5 text-center">Cupon</th>
                    <th class="px-8 py-5 text-center">Status</th>
                    <th class="px-8 py-5 text-right">Acțiuni</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50 text-sm">
                <?php if (!empty($booking_rows)): ?>
                    <?php foreach ($booking_rows as $row): ?>
                        <tr id="booking-<?php echo (int) $row['id']; ?>" class="hover:bg-slate-50 transition-colors align-top<?php echo ($focus_booking_id > 0 && (int) $row['id'] === $focus_booking_id) ? ' bg-brand-100 ring-2 ring-cta/40' : ''; ?>">
                            <td class="px-8 py-6">
                                <div class="font-bold text-slate-900"><?php echo htmlspecialchars($row['property_title'] ?? 'Proprietate necunoscută'); ?></div>
                                <div class="text-[10px] text-cta font-extrabold uppercase mt-0.5 tracking-tighter">
                                    LOT: #<?php echo htmlspecialchars($row['property_lot_id'] ?? '—'); ?>
                                </div>
                                <div class="text-xs text-slate-400 mt-1"><?php echo htmlspecialchars($row['property_city'] ?? ''); ?></div>
                            </td>
                            <td class="px-8 py-6">
                                <?php
                                $modalPayload = lh_admin_booking_modal_payload($row, 'bookings');
                                $modalJson = htmlspecialchars(json_encode($modalPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');
                                ?>
                                <button type="button" class="text-left group" data-booking="<?php echo $modalJson; ?>" data-lh-booking-open="1" title="Detalii rezervare">
                                    <div class="font-bold text-slate-800 group-hover:text-cta group-hover:underline underline-offset-2 transition-colors"><?php echo htmlspecialchars((string) $row['guest_name']); ?></div>
                                </button>
                                <div class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars((string) $row['guest_phone']); ?></div>
                                <div class="text-xs text-slate-400 mt-1 break-all"><?php echo htmlspecialchars((string) $row['guest_email']); ?></div>
                            </td>
                            <td class="px-8 py-6 text-slate-600">
                                <div class="font-bold text-slate-800"><?php echo htmlspecialchars((string) $row['check_in']); ?> → <?php echo htmlspecialchars((string) $row['check_out']); ?></div>
                                <div class="text-xs text-slate-400 mt-1">
                                    <?php echo (int) $row['guests']; ?> oaspeți · creată la <?php echo htmlspecialchars((string) $row['created_at']); ?>
                                </div>
                            </td>
                            <td class="px-8 py-6 text-center font-black text-slate-900">
                                <?php echo htmlspecialchars(lh_format_money((float) $row['total_price'], 0), ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td class="px-8 py-6 text-center text-xs font-bold text-slate-600">
                                <?php
                                $cAmt = isset($row['coupon_discount_amount']) ? (float) $row['coupon_discount_amount'] : 0;
                                $cCode = trim((string) ($row['coupon_code'] ?? ''));
                                ?>
                                <?php if ($cCode !== '' && $cAmt > 0): ?>
                                    <span class="block uppercase text-cta"><?php echo htmlspecialchars($cCode, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="text-emerald-800"><?php echo htmlspecialchars(lh_format_money($cAmt, 0), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-6 text-center">
                                <span class="inline-flex px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest <?php echo booking_row_status_badge_class($row); ?>">
                                    <?php echo htmlspecialchars(booking_row_status_label($row)); ?>
                                </span>
                                <?php if (!empty($row['payment_status'])): ?>
                                <div class="text-[10px] text-slate-500 font-semibold mt-1.5"><?php echo htmlspecialchars(lh_booking_payment_status_label((string) $row['payment_status']), ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-8 py-6">
                                <div class="flex flex-col gap-2 w-[180px] ml-auto">
                                    <?php if ($row['status'] === 'confirmed' && !lh_booking_stay_finished_by_row($row)): ?>
                                        <form method="POST" class="w-full">
                                            <?php lh_csrf_field(); ?>
                                            <input type="hidden" name="booking_id" value="<?php echo (int) $row['id']; ?>">
                                            <input type="hidden" name="action" value="send_checkin_reminder">
                                            <button type="submit" class="w-full bg-slate-50 text-slate-700 px-4 py-2 rounded-xl border border-slate-200 hover:bg-slate-100 transition-all text-xs font-bold" title="Trimite acum același email de reminder check-in ca la cron (fără așteptarea ferestrei de 24h).">
                                                Trimite email reminder
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($row['status'] !== 'confirmed'): ?>
                                        <form method="POST" class="w-full">
                                            <?php lh_csrf_field(); ?>
                                            <input type="hidden" name="booking_id" value="<?php echo (int) $row['id']; ?>">
                                            <input type="hidden" name="action" value="confirm">
                                            <button type="submit" class="w-full bg-brand-100 text-cta px-4 py-2 rounded-xl border border-black/8 hover:bg-cta hover:text-white transition-all text-xs font-bold">
                                                Confirmă
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($row['status'] !== 'cancelled'): ?>
                                        <form method="POST" class="w-full">
                                            <?php lh_csrf_field(); ?>
                                            <input type="hidden" name="booking_id" value="<?php echo (int) $row['id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="w-full bg-red-50 text-red-500 px-4 py-2 rounded-xl border border-red-100 hover:bg-red-500 hover:text-white transition-all text-xs font-bold">
                                                Anulează
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="px-8 py-16 text-center text-slate-400 font-bold">
                            Nu există rezervări care să corespundă criteriilor.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        <?php if ($focus_booking_id > 0): ?>
        const row = document.getElementById('booking-<?php echo (int) $focus_booking_id; ?>');
        if (row) {
            row.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
        <?php endif; ?>
    });
</script>

<?php include 'includes/footer.php'; ?>
