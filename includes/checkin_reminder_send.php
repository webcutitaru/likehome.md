<?php

declare(strict_types=1);

/**
 * Shared check-in reminder email (cron ~24h window + optional admin manual send).
 * Safe to remove this file after reverting cron/checkin_reminder.php and admin/bookings.php.
 */

require_once __DIR__ . '/booking_notifications.php';
require_once __DIR__ . '/booking_guest_email_bodies.php';

if (!function_exists('lh_normalize_check_in_start_his')) {
    /**
     * @return string H:i:s for combining with booking check_in date (Y-m-d)
     */
    function lh_normalize_check_in_start_his(string $raw): string
    {
        $t = trim($raw);
        if ($t === '') {
            return '14:00:00';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $s = isset($m[3]) ? (int) $m[3] : 0;
            if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59 && $s >= 0 && $s <= 59) {
                return sprintf('%02d:%02d:%02d', $h, $min, $s);
            }
        }

        return '14:00:00';
    }
}

/**
 * Send one check-in reminder email for a booking row (same shape as cron SELECT).
 *
 * @param array<string,mixed> $row Keys: booking_id, guest_name, guest_email, guest_phone, check_in, check_out,
 *                                property_title, check_in_start, check_in_end, check_out_end, address, city, district,
 *                                floor (optional), pre_checkin_email_message
 * @param array{
 *   enforce_24h_window?: bool,
 *   admin_email?: string,
 *   telegram_bot_token?: string,
 *   telegram_chat_id?: string,
 *   sent_at_update_mode?: 'if_null'|'always',
 *   log_context?: string
 * } $opts sent_at_update_mode: cron uses if_null; admin uses always to record manual resends.
 * @return array{result: 'sent'|'skipped'|'error', reason?: string}
 */
function lh_checkin_reminder_send_for_booking_row(
    PDO $pdo,
    array $row,
    DateTimeImmutable $now,
    array $opts = []
): array {
    $enforce24h = (bool) ($opts['enforce_24h_window'] ?? false);
    $adminEmail = (string) ($opts['admin_email'] ?? '');
    $telegram_bot_token = trim((string) ($opts['telegram_bot_token'] ?? ''));
    $telegram_chat_id = trim((string) ($opts['telegram_chat_id'] ?? ''));
    $sentAtMode = ($opts['sent_at_update_mode'] ?? 'if_null') === 'always' ? 'always' : 'if_null';
    $logCtx = (string) ($opts['log_context'] ?? 'checkin_reminder');

    $bookingId = (int) ($row['booking_id'] ?? 0);
    if ($bookingId < 1) {
        return ['result' => 'error', 'reason' => 'missing_booking_id'];
    }

    $checkInDate = (string) $row['check_in'];
    $checkOutDate = (string) ($row['check_out'] ?? '');
    $timeHis = lh_normalize_check_in_start_his((string) ($row['check_in_start'] ?? ''));

    $checkInAt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $checkInDate . ' ' . $timeHis);
    if ($checkInAt === false) {
        error_log("{$logCtx}: invalid check-in datetime booking #{$bookingId} date={$checkInDate} time={$timeHis}");

        return ['result' => 'skipped', 'reason' => 'invalid_datetime'];
    }

    if ($enforce24h) {
        $dueAt = $checkInAt->modify('-24 hours');
        if ($now < $dueAt) {
            return ['result' => 'skipped', 'reason' => 'before_due_window'];
        }
        if ($now >= $checkInAt) {
            return ['result' => 'skipped', 'reason' => 'past_check_in'];
        }
    }

    $guestName = (string) $row['guest_name'];
    $guestEmail = (string) $row['guest_email'];
    $reminderLocale = (string) ($row['locale'] ?? lh_default_locale());
    $propTitle = (string) ($row['property_title'] ?? lh_translate('card.fallback_title', [], $reminderLocale));

    $clientBody = lh_build_guest_checkin_reminder_body($row);

    $clientSubject = lh_translate('email.reminder.subject', ['property' => $propTitle], $reminderLocale);

    if (!filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("{$logCtx}: invalid guest email booking #{$bookingId}");

        return ['result' => 'error', 'reason' => 'invalid_guest_email'];
    }

    $clientOk = send_booking_notification($guestEmail, $clientSubject, $clientBody, $adminEmail);
    if (!$clientOk) {
        error_log("{$logCtx}: client mail failed booking #{$bookingId}");

        return ['result' => 'error', 'reason' => 'client_mail_failed'];
    }

    $detailBlock = "Reminder check-in (~24h înainte)\n\n"
        . "Booking ID: #{$bookingId}\n"
        . "Proprietate: {$propTitle}\n"
        . "Nume: {$guestName}\n"
        . "Email: {$guestEmail}\n"
        . "Telefon: " . ($row['guest_phone'] ?? '') . "\n"
        . "Check-in: {$checkInDate}\n"
        . "Check-out: {$checkOutDate}\n"
        . 'Trimis la: ' . $now->format('Y-m-d H:i:s') . "\n";

    if ($adminEmail !== '') {
        $adminSubject = "Reminder check-in trimis — booking #{$bookingId}";
        if (!send_booking_notification($adminEmail, $adminSubject, $detailBlock, $guestEmail)) {
            error_log("{$logCtx}: admin mail failed booking #{$bookingId}");
        }
    }

    if ($telegram_bot_token !== '' && $telegram_chat_id !== '') {
        $tgMsg = "⏰ Reminder check-in (~24h)\n\n" . $detailBlock;
        if (!send_telegram_notification($telegram_bot_token, $telegram_chat_id, $tgMsg)) {
            error_log("{$logCtx}: telegram failed booking #{$bookingId}");
        }
    }

    if ($sentAtMode === 'always') {
        $upd = $pdo->prepare('UPDATE bookings SET checkin_reminder_sent_at = NOW() WHERE id = :id');
    } else {
        $upd = $pdo->prepare('UPDATE bookings SET checkin_reminder_sent_at = NOW() WHERE id = :id AND checkin_reminder_sent_at IS NULL');
    }
    $upd->execute([':id' => $bookingId]);
    if ($upd->rowCount() !== 1) {
        error_log("{$logCtx}: unexpected rowCount updating booking #{$bookingId}");
    }

    return ['result' => 'sent'];
}
