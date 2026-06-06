<?php

declare(strict_types=1);

require_once __DIR__ . '/booking_notifications.php';
require_once __DIR__ . '/booking_guest_email_bodies.php';
require_once __DIR__ . '/booking_payment.php';
require_once __DIR__ . '/company_legal.php';
require_once __DIR__ . '/seo.php';

if (!function_exists('lh_booking_release_blocked_dates')) {
    function lh_booking_release_blocked_dates(PDO $pdo, int $propertyId, int $bookingId, ?string $source = null): void
    {
        $external = 'booking-' . $bookingId;
        if ($source !== null) {
            $stmt = $pdo->prepare(
                'DELETE FROM blocked_dates WHERE property_id = ? AND source = ? AND external_event_id = ?'
            );
            $stmt->execute([$propertyId, $source, $external]);

            return;
        }

        $stmt = $pdo->prepare(
            'DELETE FROM blocked_dates WHERE property_id = ? AND external_event_id = ?'
        );
        $stmt->execute([$propertyId, $external]);
    }
}

if (!function_exists('lh_booking_ensure_direct_block')) {
    function lh_booking_ensure_direct_block(PDO $pdo, int $propertyId, string $checkIn, string $checkOut, int $bookingId): void
    {
        $external = 'booking-' . $bookingId;
        $exists = $pdo->prepare(
            'SELECT id FROM blocked_dates WHERE property_id = ? AND source = ? AND external_event_id = ? LIMIT 1'
        );
        $exists->execute([$propertyId, 'direct_booking', $external]);
        if ($exists->fetch()) {
            return;
        }

        lh_booking_release_blocked_dates($pdo, $propertyId, $bookingId, 'pending_payment');

        $ins = $pdo->prepare(
            'INSERT INTO blocked_dates (property_id, start_date, end_date, source, external_event_id, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $propertyId,
            $checkIn,
            $checkOut,
            'direct_booking',
            $external,
            'Booking #' . $bookingId,
        ]);
    }
}

if (!function_exists('lh_booking_send_confirmation_notifications')) {
    function lh_booking_send_confirmation_notifications(
        PDO $pdo,
        array $booking,
        array $property,
        string $bookingLocale,
        bool $paidOnline = false
    ): void {
        $booking_id = (int) ($booking['id'] ?? 0);
        $guest_name = (string) ($booking['guest_name'] ?? '');
        $guest_phone = (string) ($booking['guest_phone'] ?? '');
        $guest_email = (string) ($booking['guest_email'] ?? '');
        $check_in = (string) ($booking['check_in'] ?? '');
        $check_out = (string) ($booking['check_out'] ?? '');
        $guests = (int) ($booking['guests'] ?? 0);
        $total_price = (float) ($booking['total_price'] ?? 0);
        $coupon_code_ins = $booking['coupon_code'] ?? null;
        $coupon_discount_ins = (float) ($booking['coupon_discount_amount'] ?? 0);

        $property_title = $property['title'] ?? ('Property #' . (int) ($booking['property_id'] ?? 0));
        if (function_exists('lh_property_apply_locale')) {
            $property = lh_property_apply_locale($property, $pdo, $bookingLocale);
            $property_title = $property['title'] ?? $property_title;
        }

        $paymentMethod = (string) ($booking['payment_method'] ?? 'on_site');
        $paymentLine = $paymentMethod === 'online'
            ? ('Plătit online: ' . lh_format_money((float) ($booking['payment_amount'] ?? $booking['payment_due_amount'] ?? 0), 2))
            : ('Plată la check-in: ' . lh_format_money($total_price, 2));

        $admin_notification_email = lh_booking_resolve_admin_notification_email();
        $telegram_bot_token = defined('TELEGRAM_BOT_TOKEN') ? trim((string) TELEGRAM_BOT_TOKEN) : '';
        $telegram_chat_id = defined('TELEGRAM_CHAT_ID') ? trim((string) TELEGRAM_CHAT_ID) : '';

        $admin_subject = 'Rezervare nouă #' . $booking_id . ' - ' . $property_title;
        $admin_message = "Ai primit o rezervare nouă pe site.\n\n"
            . "Booking ID: #" . $booking_id . "\n"
            . "Proprietate: " . $property_title . "\n"
            . "Nume client: " . $guest_name . "\n"
            . "Telefon: " . $guest_phone . "\n"
            . "Email: " . $guest_email . "\n"
            . "Check-in: " . $check_in . "\n"
            . "Check-out: " . $check_out . "\n"
            . "Oaspeți: " . $guests . "\n"
            . ($coupon_discount_ins > 0.004 && $coupon_code_ins !== null && trim((string) $coupon_code_ins) !== ''
                ? ('Reducere cupon «' . trim((string) $coupon_code_ins) . '»: ' . lh_format_money($coupon_discount_ins, 2) . "\n")
                : '')
            . $paymentLine . "\n"
            . "Status: confirmată\n";

        if (!empty($admin_notification_email)) {
            send_booking_notification($admin_notification_email, $admin_subject, $admin_message, $guest_email);
        }

        $telegram_message = "🔔 Rezervare nouă\n\n"
            . "Booking ID: #" . $booking_id . "\n"
            . "Proprietate: " . $property_title . "\n"
            . "Nume: " . $guest_name . "\n"
            . "Telefon: " . $guest_phone . "\n"
            . "Email: " . $guest_email . "\n"
            . "Check-in: " . $check_in . "\n"
            . "Check-out: " . $check_out . "\n"
            . "Oaspeți: " . $guests . "\n"
            . $paymentLine . "\n"
            . "Status: confirmată";

        if (!empty($telegram_bot_token) && !empty($telegram_chat_id)) {
            send_telegram_notification($telegram_bot_token, $telegram_chat_id, $telegram_message);
        }

        $client_subject = lh_translate('email.confirm_subject', [], $bookingLocale);
        $guestBodyCtx = [
            'guest_name' => $guest_name,
            'property_title' => $property_title,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'guests' => $guests,
            'total_price' => $total_price,
            'booking_id' => $booking_id,
            'locale' => $bookingLocale,
            'payment_method' => $paymentMethod,
            'payment_amount' => (float) ($booking['payment_amount'] ?? $booking['payment_due_amount'] ?? $total_price),
            'paid_online' => $paidOnline,
            'paid_at' => (string) ($booking['paid_at'] ?? ''),
            'currency' => lh_company_currency(),
        ];
        if ($coupon_discount_ins > 0.004 && $coupon_code_ins !== null && trim((string) $coupon_code_ins) !== '') {
            $guestBodyCtx['coupon_code'] = (string) $coupon_code_ins;
            $guestBodyCtx['coupon_discount_amount'] = $coupon_discount_ins;
        }
        if ($paymentMethod === 'online' && (float) ($booking['online_discount_amount'] ?? 0) > 0.004) {
            $guestBodyCtx['online_discount_amount'] = (float) $booking['online_discount_amount'];
        }

        $client_message = lh_build_guest_booking_confirmation_body($guestBodyCtx);
        send_booking_notification($guest_email, $client_subject, $client_message, $admin_notification_email);
    }
}

if (!function_exists('lh_booking_confirm_after_online_payment')) {
    function lh_booking_confirm_after_online_payment(
        PDO $pdo,
        int $bookingId,
        string $checkoutId,
        ?string $paymentId,
        float $paymentAmount
    ): bool {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT b.*, p.* FROM bookings b INNER JOIN properties p ON p.id = b.property_id WHERE b.id = ? FOR UPDATE');
            $stmt->execute([$bookingId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $pdo->rollBack();

                return false;
            }

            if (($row['status'] ?? '') === 'confirmed' && ($row['payment_status'] ?? '') === 'paid') {
                $pdo->commit();

                return true;
            }

            if (($row['payment_method'] ?? '') !== 'online') {
                $pdo->rollBack();

                return false;
            }

            $propertyId = (int) $row['property_id'];
            $checkIn = (string) $row['check_in'];
            $checkOut = (string) $row['check_out'];
            $locale = (string) ($row['locale'] ?? lh_default_locale());

            $upd = $pdo->prepare(
                "UPDATE bookings SET
                    status = 'confirmed',
                    payment_status = 'paid',
                    payment_amount = :payment_amount,
                    maib_checkout_id = COALESCE(maib_checkout_id, :checkout_id),
                    maib_payment_id = COALESCE(NULLIF(maib_payment_id, ''), :payment_id),
                    paid_at = COALESCE(paid_at, NOW()),
                    payment_expires_at = NULL
                 WHERE id = :id"
            );
            $upd->execute([
                ':payment_amount' => round($paymentAmount, 2),
                ':checkout_id' => $checkoutId,
                ':payment_id' => $paymentId ?? '',
                ':id' => $bookingId,
            ]);

            lh_booking_ensure_direct_block($pdo, $propertyId, $checkIn, $checkOut, $bookingId);

            $pdo->commit();

            $stmtFresh = $pdo->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
            $stmtFresh->execute([$bookingId]);
            $booking = $stmtFresh->fetch(PDO::FETCH_ASSOC) ?: $row;

            $propStmt = $pdo->prepare('SELECT * FROM properties WHERE id = ? LIMIT 1');
            $propStmt->execute([$propertyId]);
            $property = $propStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            lh_booking_send_confirmation_notifications($pdo, $booking, $property, $locale, true);

            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('lh_booking_confirm_after_online_payment: ' . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('lh_booking_fail_online_payment')) {
    function lh_booking_fail_online_payment(PDO $pdo, int $bookingId): void
    {
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking || ($booking['payment_method'] ?? '') !== 'online') {
            return;
        }
        if (($booking['status'] ?? '') === 'confirmed') {
            return;
        }

        $pdo->prepare(
            "UPDATE bookings SET status = 'cancelled', payment_status = 'failed', payment_expires_at = NULL, cancelled_at = NOW() WHERE id = ?"
        )->execute([$bookingId]);

        lh_booking_release_blocked_dates($pdo, (int) $booking['property_id'], $bookingId);
    }
}

if (!function_exists('lh_booking_cancel_booking')) {
    function lh_booking_cancel_booking(PDO $pdo, int $bookingId): array
    {
        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            return ['ok' => false, 'message' => 'Rezervarea nu există.'];
        }

        if (($booking['status'] ?? '') === 'cancelled') {
            return ['ok' => true, 'message' => 'Rezervarea este deja anulată.'];
        }

        $pdo->prepare(
            "UPDATE bookings SET status = 'cancelled', cancelled_at = COALESCE(cancelled_at, NOW()) WHERE id = ?"
        )->execute([$bookingId]);

        lh_booking_release_blocked_dates($pdo, (int) $booking['property_id'], $bookingId, 'direct_booking');
        lh_booking_release_blocked_dates($pdo, (int) $booking['property_id'], $bookingId, 'pending_payment');

        return ['ok' => true, 'message' => 'Rezervarea a fost anulată. Rambursarea nu se face automat — folosește „Rambursare maib” dacă e cazul.'];
    }
}

/** @deprecated Use lh_booking_cancel_booking() — cancel fără refund automat. */
if (!function_exists('lh_booking_cancel_with_optional_refund')) {
    function lh_booking_cancel_with_optional_refund(PDO $pdo, int $bookingId): array
    {
        return lh_booking_cancel_booking($pdo, $bookingId);
    }
}
