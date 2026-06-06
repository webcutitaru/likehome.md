<?php

declare(strict_types=1);

require_once __DIR__ . '/booking_payment.php';
require_once __DIR__ . '/maib_client.php';

if (!function_exists('lh_booking_process_maib_refund')) {
    /**
     * @return array{
     *   ok: bool,
     *   message: string,
     *   refund_id?: string,
     *   refunded_amount?: float,
     *   remaining?: float,
     *   payment_status?: string
     * }
     */
    function lh_booking_process_maib_refund(
        PDO $pdo,
        int $bookingId,
        ?float $amount = null,
        ?string $reason = null
    ): array {
        if (!lh_maib_configured()) {
            return ['ok' => false, 'message' => 'Gateway-ul maib nu este configurat.'];
        }

        if (!lh_bookings_has_refunded_amount_column($pdo)) {
            return ['ok' => false, 'message' => 'Migrarea refunded_amount lipsește. Rulează migrations/004_booking_refunds.sql.'];
        }

        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = ? LIMIT 1');
        $stmt->execute([$bookingId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$booking) {
            return ['ok' => false, 'message' => 'Rezervarea nu există.'];
        }

        if (!lh_booking_can_refund($booking)) {
            return ['ok' => false, 'message' => 'Această rezervare nu poate fi rambursată prin maib.'];
        }

        $remaining = lh_booking_refundable_amount($booking);
        if ($remaining <= 0.004) {
            return ['ok' => false, 'message' => 'Nu mai există sumă de rambursat.'];
        }

        if ($amount === null || $amount <= 0) {
            $amount = $remaining;
        } else {
            $amount = round($amount, 2);
        }

        if ($amount > $remaining + 0.004) {
            return [
                'ok' => false,
                'message' => 'Suma depășește restul rambursabil (' . lh_format_money($remaining, 2) . ').',
            ];
        }

        if ($amount <= 0.004) {
            return ['ok' => false, 'message' => 'Suma de rambursare este invalidă.'];
        }

        $payId = trim((string) ($booking['maib_payment_id'] ?? ''));
        $reasonText = trim((string) ($reason ?? ''));
        if ($reasonText === '') {
            $reasonText = 'Rambursare admin rezervare #' . $bookingId;
        }

        try {
            $result = lh_maib_refund_payment($payId, $amount, $reasonText);
        } catch (Throwable $e) {
            error_log('lh_booking_process_maib_refund #' . $bookingId . ': ' . $e->getMessage());

            return ['ok' => false, 'message' => 'Rambursarea maib a eșuat: ' . $e->getMessage()];
        }

        $refundId = is_object($result) ? trim((string) ($result->refundId ?? '')) : '';
        $newRefunded = round(lh_booking_refunded_amount($booking) + $amount, 2);
        $newRemaining = round(max(0.0, lh_booking_paid_amount($booking) - $newRefunded), 2);
        $newStatus = $newRemaining <= 0.004 ? 'refunded' : 'partial_refund';

        $pdo->prepare(
            'UPDATE bookings SET
                refunded_amount = :refunded_amount,
                payment_status = :payment_status,
                maib_refund_id = :refund_id
             WHERE id = :id'
        )->execute([
            ':refunded_amount' => $newRefunded,
            ':payment_status' => $newStatus,
            ':refund_id' => $refundId !== '' ? $refundId : null,
            ':id' => $bookingId,
        ]);

        return [
            'ok' => true,
            'message' => $newStatus === 'refunded'
                ? 'Rambursare integrală inițiată (' . lh_format_money($amount, 2) . ').'
                : 'Rambursare parțială inițiată (' . lh_format_money($amount, 2) . '). Rest: ' . lh_format_money($newRemaining, 2) . '.',
            'refund_id' => $refundId,
            'refunded_amount' => $newRefunded,
            'remaining' => $newRemaining,
            'payment_status' => $newStatus,
        ];
    }
}
