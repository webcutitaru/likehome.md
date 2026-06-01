<?php

declare(strict_types=1);

if (!function_exists('lh_coupon_normalize_code')) {
    function lh_coupon_normalize_code(string $raw): string
    {
        return strtoupper(trim($raw));
    }
}

if (!function_exists('lh_coupon_amount_from_base')) {
    /**
     * Discount off accommodation nights total only (not extra guest charges).
     */
    function lh_coupon_amount_from_base(array $couponRow, float $baseNightsTotal): float
    {
        if ($baseNightsTotal <= 0.0) {
            return 0.0;
        }
        $type = (string) ($couponRow['discount_type'] ?? '');
        $val = (float) ($couponRow['discount_value'] ?? 0);
        if ($val <= 0.0) {
            return 0.0;
        }
        if ($type === 'percent') {
            $p = min(100.0, $val);

            return min($baseNightsTotal, $baseNightsTotal * ($p / 100.0));
        }
        if ($type === 'fixed') {

            return min($baseNightsTotal, $val);
        }

        return 0.0;
    }
}

if (!function_exists('lh_coupon_is_valid_for_checkin_date')) {
    function lh_coupon_is_valid_for_checkin_date(array $couponRow, string $checkInYmd): bool
    {
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $checkInYmd);
        if (!$d || $d->format('Y-m-d') !== $checkInYmd) {
            return false;
        }
        $from = $couponRow['valid_from'] ?? null;
        $to = $couponRow['valid_to'] ?? null;
        if ($from !== null && $from !== '' && (string) $from > $checkInYmd) {
            return false;
        }
        if ($to !== null && $to !== '' && (string) $to < $checkInYmd) {
            return false;
        }

        return true;
    }
}

if (!function_exists('lh_coupon_count_confirmed_redemptions')) {
    function lh_coupon_count_confirmed_redemptions(PDO $pdo, int $couponId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM bookings WHERE coupon_id = ? AND status = \'confirmed\''
        );
        $stmt->execute([$couponId]);

        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('lh_coupon_load_locked_by_code')) {
    /**
     * Load active coupon matching code within transaction; locks row FOR UPDATE when $forUpdate true.
     *
     * @return array<string,mixed>|null normalized coupon row including id
     */
    function lh_coupon_load_locked_by_code(PDO $pdo, string $normalizedCode, bool $forUpdate = false): ?array
    {
        if ($normalizedCode === '') {
            return null;
        }
        $sql = 'SELECT * FROM discount_coupons WHERE code = ? AND is_active = 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$normalizedCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}

if (!function_exists('lh_coupon_applies_to_property')) {
    function lh_coupon_applies_to_property(PDO $pdo, int $couponId, int $propertyId, array $couponRow): bool
    {
        if (!empty((int) ($couponRow['applies_all_properties'] ?? 0))) {
            return true;
        }
        $stmt = $pdo->prepare(
            'SELECT 1 FROM discount_coupon_properties WHERE coupon_id = ? AND property_id = ? LIMIT 1'
        );
        $stmt->execute([$couponId, $propertyId]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('lh_coupon_translate_error')) {
    function lh_coupon_translate_error(string $error, ?string $locale = null): string
    {
        $map = [
            'Codul promoțional nu este valid sau a expirat.' => 'coupon.invalid_expired',
            'Codul promoțional nu este valabil pentru această perioadă.' => 'coupon.invalid_period',
            'Codul promoțional nu este valabil pentru această proprietate.' => 'coupon.invalid_property',
            'Codul promoțional a fost deja folosit de numărul maxim de ori.' => 'coupon.max_redemptions',
            'Codul promoțional nu poate fi aplicat pentru această rezervare.' => 'coupon.cannot_apply',
        ];
        $key = $map[$error] ?? null;

        return $key !== null ? lh_translate($key, [], $locale) : $error;
    }
}

if (!function_exists('lh_coupon_resolve_for_booking')) {
    /**
     * Validate coupon inside an open DB transaction when $forUpdate is true (max redemptions + row lock).
     *
     * @return array{coupon:null|array<string,mixed>,discount:float,error:null|string}
     */
    function lh_coupon_resolve_for_booking(
        PDO $pdo,
        string $rawCouponCode,
        int $propertyId,
        string $checkInYmd,
        float $baseNightsTotal,
        bool $forUpdate
    ): array {
        $out = ['coupon' => null, 'discount' => 0.0, 'error' => null];
        $code = lh_coupon_normalize_code($rawCouponCode);
        if ($code === '') {
            return $out;
        }
        $coupon = lh_coupon_load_locked_by_code($pdo, $code, $forUpdate);
        if ($coupon === null) {
            $out['error'] = 'Codul promoțional nu este valid sau a expirat.';

            return $out;
        }
        $cid = (int) ($coupon['id'] ?? 0);
        if ($cid < 1) {
            $out['error'] = 'Codul promoțional nu este valid sau a expirat.';

            return $out;
        }
        if (!lh_coupon_is_valid_for_checkin_date($coupon, $checkInYmd)) {
            $out['error'] = 'Codul promoțional nu este valabil pentru această perioadă.';

            return $out;
        }
        if (!lh_coupon_applies_to_property($pdo, $cid, $propertyId, $coupon)) {
            $out['error'] = 'Codul promoțional nu este valabil pentru această proprietate.';

            return $out;
        }
        $max = $coupon['max_redemptions'];
        if ($max !== null && $max !== '') {
            $mx = (int) $max;
            if ($mx > 0) {
                $used = lh_coupon_count_confirmed_redemptions($pdo, $cid);
                if ($used >= $mx) {
                    $out['error'] = 'Codul promoțional a fost deja folosit de numărul maxim de ori.';

                    return $out;
                }
            }
        }
        $discount = lh_coupon_amount_from_base($coupon, $baseNightsTotal);
        if ($discount <= 0.0) {
            $out['error'] = 'Codul promoțional nu poate fi aplicat pentru această rezervare.';

            return $out;
        }
        $out['coupon'] = $coupon;
        $out['discount'] = $discount;

        return $out;
    }
}
