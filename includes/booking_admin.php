<?php

declare(strict_types=1);

require_once __DIR__ . '/booking_payment.php';

if (!function_exists('lh_admin_booking_payment_method_label')) {
    function lh_admin_booking_payment_method_label(string $method): string
    {
        return match ($method) {
            'online' => 'Plată online (maib)',
            'on_site' => 'Plată la check-in',
            default => $method !== '' ? $method : '—',
        };
    }
}

if (!function_exists('lh_admin_booking_status_label')) {
    function lh_admin_booking_status_label(array $booking): string
    {
        $status = (string) ($booking['status'] ?? '');
        if ($status === 'confirmed') {
            $co = (string) ($booking['check_out'] ?? '');
            $today = (new DateTimeImmutable('today'))->format('Y-m-d');
            if ($co !== '' && $co < $today) {
                return 'Finalizată';
            }

            return 'Activă';
        }
        if ($status === 'pending') {
            return 'În așteptare';
        }
        if ($status === 'cancelled') {
            return 'Anulată';
        }

        return $status !== '' ? $status : '—';
    }
}

if (!function_exists('lh_admin_booking_modal_payload')) {
    /**
     * @param array<string, mixed> $booking
     * @return array<string, mixed>
     */
    function lh_admin_booking_modal_payload(array $booking, string $context = 'bookings'): array
    {
        $paid = lh_booking_paid_amount($booking);
        $refunded = lh_booking_refunded_amount($booking);
        $refundable = lh_booking_refundable_amount($booking);
        $paymentMethod = (string) ($booking['payment_method'] ?? '');
        $paymentStatus = (string) ($booking['payment_status'] ?? '');

        return [
            'id' => (int) ($booking['id'] ?? 0),
            'guest_name' => (string) ($booking['guest_name'] ?? ''),
            'guest_email' => (string) ($booking['guest_email'] ?? ''),
            'guest_phone' => (string) ($booking['guest_phone'] ?? ''),
            'check_in' => (string) ($booking['check_in'] ?? ''),
            'check_out' => (string) ($booking['check_out'] ?? ''),
            'status' => (string) ($booking['status'] ?? ''),
            'status_label' => lh_admin_booking_status_label($booking),
            'total_price' => (float) ($booking['total_price'] ?? 0),
            'guests' => (int) ($booking['guests'] ?? 0),
            'coupon_code' => (string) ($booking['coupon_code'] ?? ''),
            'coupon_discount_amount' => (float) ($booking['coupon_discount_amount'] ?? 0),
            'property_title' => (string) ($booking['property_title'] ?? ''),
            'property_lot_id' => (string) ($booking['property_lot_id'] ?? ''),
            'property_city' => (string) ($booking['property_city'] ?? ''),
            'created_at' => (string) ($booking['created_at'] ?? ''),
            'payment_method' => $paymentMethod,
            'payment_method_label' => lh_admin_booking_payment_method_label($paymentMethod),
            'payment_status' => $paymentStatus,
            'payment_status_label' => $paymentStatus !== '' ? lh_booking_payment_status_label($paymentStatus) : '—',
            'payment_amount' => $paid,
            'refunded_amount' => $refunded,
            'refundable_amount' => $refundable,
            'paid_at' => (string) ($booking['paid_at'] ?? ''),
            'maib_checkout_id' => (string) ($booking['maib_checkout_id'] ?? ''),
            'maib_payment_id' => (string) ($booking['maib_payment_id'] ?? ''),
            'maib_refund_id' => (string) ($booking['maib_refund_id'] ?? ''),
            'can_refund' => lh_booking_can_refund($booking),
            'refund_warning_24h' => lh_booking_below_standard_refund_window($booking),
            'can_cancel' => ($booking['status'] ?? '') !== 'cancelled',
            'context' => $context,
        ];
    }
}

if (!function_exists('lh_admin_process_booking_update')) {
    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, message: string, booking_id?: int, property_id?: int, check_in?: string, check_out?: string}
     */
    function lh_admin_process_booking_update(PDO $pdo, array $post): array
    {
        $bookingId = (int) ($post['booking_id'] ?? 0);
        $guest_name = trim((string) ($post['guest_name'] ?? ''));
        $guest_email = trim((string) ($post['guest_email'] ?? ''));
        $guest_phone = trim((string) ($post['guest_phone'] ?? ''));
        $check_in = trim((string) ($post['check_in'] ?? ''));
        $check_out = trim((string) ($post['check_out'] ?? ''));
        $guests = filter_var($post['guests'] ?? null, FILTER_VALIDATE_INT);

        if ($bookingId < 1) {
            return ['ok' => false, 'message' => 'Rezervare invalidă.'];
        }
        if ($guest_name === '') {
            return ['ok' => false, 'message' => 'Completează numele.'];
        }
        if (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Email invalid.'];
        }
        if ($guest_phone === '') {
            return ['ok' => false, 'message' => 'Completează numărul de telefon.'];
        }
        if (!$guests || $guests < 1) {
            return ['ok' => false, 'message' => 'Număr invalid de oaspeți.'];
        }

        $checkInDt = DateTimeImmutable::createFromFormat('Y-m-d', $check_in);
        $checkOutDt = DateTimeImmutable::createFromFormat('Y-m-d', $check_out);
        if (!$checkInDt || $checkInDt->format('Y-m-d') !== $check_in) {
            return ['ok' => false, 'message' => 'Data de check-in este invalidă.'];
        }
        if (!$checkOutDt || $checkOutDt->format('Y-m-d') !== $check_out) {
            return ['ok' => false, 'message' => 'Data de check-out este invalidă.'];
        }
        if ($checkOutDt <= $checkInDt) {
            return ['ok' => false, 'message' => 'Check-out trebuie să fie după check-in (minim o noapte).'];
        }

        $extId = 'booking-' . $bookingId;

        try {
            $pdo->beginTransaction();
            $stmtB = $pdo->prepare('SELECT * FROM bookings WHERE id = ? FOR UPDATE');
            $stmtB->execute([$bookingId]);
            $booking = $stmtB->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                $pdo->rollBack();

                return ['ok' => false, 'message' => 'Rezervarea nu a fost găsită.'];
            }
            if (($booking['status'] ?? '') === 'cancelled') {
                $pdo->rollBack();

                return ['ok' => false, 'message' => 'Nu poți edita o rezervare anulată.'];
            }

            $property_id = (int) $booking['property_id'];
            $stmtP = $pdo->prepare('SELECT * FROM properties WHERE id = ? FOR UPDATE');
            $stmtP->execute([$property_id]);
            $property = $stmtP->fetch(PDO::FETCH_ASSOC);
            if (!$property) {
                $pdo->rollBack();

                return ['ok' => false, 'message' => 'Proprietatea nu există.'];
            }

            if (!empty($property['sleep_capacity']) && $guests > (int) $property['sleep_capacity']) {
                $pdo->rollBack();

                return ['ok' => false, 'message' => 'Numărul de oaspeți depășește capacitatea proprietății.'];
            }

            $nightsForMin = (int) $checkOutDt->diff($checkInDt)->days;
            $effMinStay = lh_booking_effective_min_stay($property, $check_in, $check_out);
            if ($nightsForMin < $effMinStay) {
                $pdo->rollBack();

                return ['ok' => false, 'message' => 'Sejurul minim este de ' . $effMinStay . ' nopți.'];
            }

            $overlap = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM blocked_dates
                 WHERE property_id = :property_id
                   AND start_date < :check_out
                   AND end_date > :check_in
                   AND NOT (source = \'direct_booking\' AND external_event_id = :ext_id)'
            );
            $overlap->execute([
                ':property_id' => $property_id,
                ':check_in' => $check_in,
                ':check_out' => $check_out,
                ':ext_id' => $extId,
            ]);
            if ((int) $overlap->fetchColumn() > 0) {
                $pdo->rollBack();

                return ['ok' => false, 'message' => 'Perioada selectată nu este disponibilă (conflict cu alte blocări).'];
            }

            $pricing = lh_booking_stay_total($property, $check_in, $check_out, $guests);
            $total_price = $pricing['total'];

            $upd = $pdo->prepare(
                'UPDATE bookings SET guest_name = ?, guest_phone = ?, guest_email = ?, check_in = ?, check_out = ?, guests = ?, total_price = ? WHERE id = ?'
            );
            $upd->execute([$guest_name, $guest_phone, $guest_email, $check_in, $check_out, $guests, $total_price, $bookingId]);

            $updBlk = $pdo->prepare(
                'UPDATE blocked_dates SET start_date = ?, end_date = ? WHERE property_id = ? AND source = ? AND external_event_id = ?'
            );
            $updBlk->execute([$check_in, $check_out, $property_id, 'direct_booking', $extId]);
            if ($updBlk->rowCount() === 0) {
                $ins = $pdo->prepare(
                    'INSERT INTO blocked_dates (property_id, start_date, end_date, source, external_event_id, notes)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $property_id,
                    $check_in,
                    $check_out,
                    'direct_booking',
                    $extId,
                    'Booking #' . $bookingId,
                ]);
            }

            $pdo->commit();

            return [
                'ok' => true,
                'message' => 'Rezervarea a fost actualizată.',
                'booking_id' => $bookingId,
                'property_id' => $property_id,
                'check_in' => $check_in,
                'check_out' => $check_out,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('lh_admin_process_booking_update: ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Salvarea a eșuat. Încearcă din nou.'];
        }
    }
}
