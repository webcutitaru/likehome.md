<?php

declare(strict_types=1);

/**
 * Stay pricing: standard vs weekend nights + optional extra-guest fee.
 * Weekend = night whose calendar start date is Saturday or Sunday (local Y-m-d).
 *
 * Optional property_pricing_periods: half-open [date_start, date_end) overrides base prices.
 */

/**
 * Label stored in DB for special price ranges created from the admin calendar.
 */
const LH_PRICING_PERIOD_LABEL_CALENDAR_SPECIAL = 'Preț special (calendar)';

/**
 * First pricing period that applies to this night, same order as {@see lh_booking_night_rate_for_date}.
 *
 * @param list<array<string, mixed>>|null $pricingPeriods
 * @return array<string, mixed>|null
 */
function lh_pricing_first_period_for_night_rate(string $ymd, ?array $pricingPeriods): ?array
{
    if ($pricingPeriods === null || $pricingPeriods === []) {
        return null;
    }
    foreach ($pricingPeriods as $row) {
        if (!lh_pricing_period_row_contains_ymd($row, $ymd)) {
            continue;
        }

        return $row;
    }

    return null;
}

/**
 * True if this period row is the admin calendar "special price" (for grid styling only).
 */
function lh_pricing_period_is_calendar_special_row(?array $row): bool
{
    if ($row === null) {
        return false;
    }

    return (string) ($row['label'] ?? '') === LH_PRICING_PERIOD_LABEL_CALENDAR_SPECIAL;
}

/**
 * Min. nights to show in calendar for "special price (calendar)" periods, or null if N/A.
 */
function lh_pricing_period_calendar_special_min_stay(?array $row): ?int
{
    if ($row === null) {
        return null;
    }
    if ((string) ($row['label'] ?? '') !== LH_PRICING_PERIOD_LABEL_CALENDAR_SPECIAL) {
        return null;
    }
    $ms = $row['min_stay'] ?? null;
    if ($ms === null || $ms === '') {
        return null;
    }
    $mi = (int) $ms;
    if ($mi < 1) {
        return null;
    }

    return $mi;
}

function lh_property_stay_discounts_load_by_property(int $propertyId): array
{
    $global = [];
    $byPeriodId = [];
    if ($propertyId < 1 || !function_exists('getPDO')) {
        return ['global' => $global, 'by_period_id' => $byPeriodId];
    }
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, pricing_period_id, min_nights, value, unit
             FROM property_stay_length_discounts
             WHERE property_id = ?
             ORDER BY pricing_period_id IS NULL DESC, min_nights ASC, id ASC'
        );
        $stmt->execute([$propertyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return ['global' => $global, 'by_period_id' => $byPeriodId];
        }
        foreach ($rows as $r) {
            $rule = [
                'min_nights' => (int) ($r['min_nights'] ?? 0),
                'value' => (float) ($r['value'] ?? 0),
                'unit' => (string) ($r['unit'] ?? 'percent'),
            ];
            $ppid = $r['pricing_period_id'] ?? null;
            if ($ppid === null || $ppid === '') {
                $global[] = $rule;
            } else {
                $pid = (int) $ppid;
                if (!isset($byPeriodId[$pid])) {
                    $byPeriodId[$pid] = [];
                }
                $byPeriodId[$pid][] = $rule;
            }
        }
    } catch (Throwable $e) {
        return ['global' => [], 'by_period_id' => []];
    }

    return ['global' => $global, 'by_period_id' => $byPeriodId];
}

function lh_property_pricing_periods_load(int $propertyId): array
{
    if ($propertyId < 1 || !function_exists('getPDO')) {
        return [];
    }
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id, date_start, date_end, price, price_weekend, label, min_stay
             FROM property_pricing_periods
             WHERE property_id = ?
             ORDER BY date_start ASC, id ASC'
        );
        $stmt->execute([$propertyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }
        $disc = lh_property_stay_discounts_load_by_property($propertyId);
        $byPid = $disc['by_period_id'];
        foreach ($rows as &$row) {
            $pid = (int) ($row['id'] ?? 0);
            $row['stay_discounts'] = $byPid[$pid] ?? [];
        }
        unset($row);

        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function lh_property_pricing_money_round(?float $v): ?float
{
    if ($v === null) {
        return null;
    }

    return round($v, 4);
}

/**
 * Stable JSON blob for comparing stored vs incoming pricing (avoids needless DELETE/INSERT).
 *
 * @param list<array<string, mixed>> $normalizedPeriods Rows like {@see lh_property_pricing_period_row_normalize}
 * @param list<array{min_nights:int,value:float,unit:string}> $globalRules
 */
function lh_property_pricing_periods_compare_signature(array $normalizedPeriods, array $globalRules): string
{
    foreach ($normalizedPeriods as &$row) {
        $sd = isset($row['stay_discounts']) && is_array($row['stay_discounts']) ? $row['stay_discounts'] : [];
        usort(
            $sd,
            static function (array $a, array $b): int {
                $c = ((int) ($a['min_nights'] ?? 0)) <=> ((int) ($b['min_nights'] ?? 0));
                if ($c !== 0) {
                    return $c;
                }
                $c = ((float) ($a['value'] ?? 0)) <=> ((float) ($b['value'] ?? 0));

                return $c !== 0 ? $c : strcmp((string) ($a['unit'] ?? ''), (string) ($b['unit'] ?? ''));
            }
        );
        $cleanSd = [];
        foreach ($sd as $r) {
            if (!is_array($r)) {
                continue;
            }
            $cleanSd[] = [
                'min_nights' => (int) ($r['min_nights'] ?? 0),
                'value' => lh_property_pricing_money_round((float) ($r['value'] ?? 0)) ?? 0.0,
                'unit' => (($r['unit'] ?? 'percent') === 'fixed_stay') ? 'fixed_stay' : 'percent',
            ];
        }
        $pw = $row['price_weekend'] ?? null;
        $pw = ($pw !== null && (float) $pw > 0.0) ? lh_property_pricing_money_round((float) $pw) : null;
        $row = [
            'date_start' => (string) ($row['date_start'] ?? ''),
            'date_end' => (string) ($row['date_end'] ?? ''),
            'price' => lh_property_pricing_money_round((float) ($row['price'] ?? 0)) ?? 0.0,
            'price_weekend' => $pw,
            'label' => isset($row['label']) && $row['label'] !== null && (string) $row['label'] !== '' ? (string) $row['label'] : null,
            'min_stay' => isset($row['min_stay']) && $row['min_stay'] !== null && $row['min_stay'] !== ''
                ? (int) $row['min_stay']
                : null,
            'stay_discounts' => $cleanSd,
        ];
    }
    unset($row);

    usort(
        $normalizedPeriods,
        static function (array $a, array $b): int {
            $c = strcmp((string) ($a['date_start'] ?? ''), (string) ($b['date_start'] ?? ''));

            return $c !== 0 ? $c : strcmp((string) ($a['date_end'] ?? ''), (string) ($b['date_end'] ?? ''));
        }
    );

    usort(
        $globalRules,
        static function (array $a, array $b): int {
            $c = ((int) ($a['min_nights'] ?? 0)) <=> ((int) ($b['min_nights'] ?? 0));
            if ($c !== 0) {
                return $c;
            }
            $c = ((float) ($a['value'] ?? 0)) <=> ((float) ($b['value'] ?? 0));

            return $c !== 0 ? $c : strcmp((string) ($a['unit'] ?? ''), (string) ($b['unit'] ?? ''));
        }
    );
    $globOut = [];
    foreach ($globalRules as $r) {
        if (!is_array($r)) {
            continue;
        }
        $globOut[] = [
            'min_nights' => (int) ($r['min_nights'] ?? 0),
            'value' => lh_property_pricing_money_round((float) ($r['value'] ?? 0)) ?? 0.0,
            'unit' => (($r['unit'] ?? 'percent') === 'fixed_stay') ? 'fixed_stay' : 'percent',
        ];
    }

    try {
        return json_encode(['periods' => $normalizedPeriods, 'global' => $globOut], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $e) {
        return '';
    }
}

function lh_property_pricing_periods_unchanged(PDO $pdo, int $propertyId, array $periods, array $globalStayDiscountRules): bool
{
    if ($propertyId < 1) {
        return true;
    }

    $incomingNorm = [];
    foreach ($periods as $p) {
        if (!is_array($p)) {
            continue;
        }
        $incomingNorm[] = lh_property_pricing_period_row_normalize($p);
    }

    $incomingSig = lh_property_pricing_periods_compare_signature($incomingNorm, $globalStayDiscountRules);
    if ($incomingSig === '') {
        return false;
    }

    $loaded = lh_property_pricing_periods_load($propertyId);
    $loadedNorm = [];
    foreach ($loaded as $row) {
        if (!is_array($row)) {
            continue;
        }
        $loadedNorm[] = lh_property_pricing_period_row_normalize($row);
    }
    $pack = lh_property_stay_discounts_load_by_property($propertyId);
    $loadedSig = lh_property_pricing_periods_compare_signature($loadedNorm, $pack['global']);

    return $loadedSig !== '' && hash_equals($loadedSig, $incomingSig);
}

/**
 * Replace all pricing periods and stay-length discounts for a property (caller may wrap in transaction).
 *
 * @param list<array{date_start:string,date_end:string,price:float,price_weekend:?float,label?:?string,min_stay?:?int,stay_discounts?:list<array{min_nights:int,value:float,unit:string}>}> $periods
 * @param list<array{min_nights:int,value:float,unit:string}> $globalStayDiscountRules
 */
function lh_property_pricing_periods_save(PDO $pdo, int $propertyId, array $periods, array $globalStayDiscountRules = []): void
{
    if ($propertyId < 1) {
        return;
    }
    if (lh_property_pricing_periods_unchanged($pdo, $propertyId, $periods, $globalStayDiscountRules)) {
        return;
    }
    $pdo->prepare('DELETE FROM property_stay_length_discounts WHERE property_id = ?')->execute([$propertyId]);
    $pdo->prepare('DELETE FROM property_pricing_periods WHERE property_id = ?')->execute([$propertyId]);
    if ($periods === [] && $globalStayDiscountRules === []) {
        return;
    }
    $ins = $pdo->prepare(
        'INSERT INTO property_pricing_periods (property_id, date_start, date_end, price, price_weekend, label, min_stay)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insDisc = $pdo->prepare(
        'INSERT INTO property_stay_length_discounts (property_id, pricing_period_id, min_nights, value, unit)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($periods as $p) {
        $pw = $p['price_weekend'] ?? null;
        if ($pw !== null && (float) $pw <= 0.0) {
            $pw = null;
        }
        $ms = $p['min_stay'] ?? null;
        $msIns = null;
        if ($ms !== null && $ms !== '' && (int) $ms >= 1) {
            $msIns = (int) $ms;
        }
        $ins->execute([
            $propertyId,
            $p['date_start'],
            $p['date_end'],
            $p['price'],
            $pw,
            isset($p['label']) && $p['label'] !== '' && $p['label'] !== null ? (string) $p['label'] : null,
            $msIns,
        ]);
        $newPid = (int) $pdo->lastInsertId();
        foreach ($p['stay_discounts'] ?? [] as $r) {
            $insDisc->execute([
                $propertyId,
                $newPid,
                (int) ($r['min_nights'] ?? 0),
                (float) ($r['value'] ?? 0),
                ($r['unit'] ?? 'percent') === 'fixed_stay' ? 'fixed_stay' : 'percent',
            ]);
        }
    }
    foreach ($globalStayDiscountRules as $r) {
        $insDisc->execute([
            $propertyId,
            null,
            (int) ($r['min_nights'] ?? 0),
            (float) ($r['value'] ?? 0),
            ($r['unit'] ?? 'percent') === 'fixed_stay' ? 'fixed_stay' : 'percent',
        ]);
    }
}

function lh_pricing_period_row_contains_ymd(array $row, string $ymd): bool
{
    $ds = (string) ($row['date_start'] ?? '');
    $de = (string) ($row['date_end'] ?? '');
    if (strlen($ds) !== 10 || strlen($de) !== 10) {
        return false;
    }

    return $ds <= $ymd && $ymd < $de;
}

/**
 * Half-open overlap: [a0,a1) vs [b0,b1).
 */
function lh_pricing_period_ranges_overlap(string $a0, string $a1, string $b0, string $b1): bool
{
    return $a0 < $b1 && $b0 < $a1;
}

/**
 * @param list<array{date_start:string,date_end:string,price:float,price_weekend:?float,label?:?string,min_stay?:?int}> $periods
 */
function lh_pricing_periods_validate(array $periods): ?string
{
    foreach ($periods as $idx => $p) {
        $ds = $p['date_start'] ?? '';
        $de = $p['date_end'] ?? '';
        $d1 = DateTime::createFromFormat('Y-m-d', $ds);
        $d2 = DateTime::createFromFormat('Y-m-d', $de);
        if (!$d1 || $d1->format('Y-m-d') !== $ds || !$d2 || $d2->format('Y-m-d') !== $de) {
            return 'Perioada #' . ($idx + 1) . ': date invalide (format AAAA-LL-ZZ).';
        }
        if ($de <= $ds) {
            return 'Perioada #' . ($idx + 1) . ': data „până la” trebuie să fie după „de la” (checkout exclus).';
        }
        if ($p['price'] <= 0.0) {
            return 'Perioada #' . ($idx + 1) . ': prețul trebuie să fie mai mare ca 0.';
        }
        $ms = $p['min_stay'] ?? null;
        if ($ms !== null && $ms !== '' && (int) $ms < 1) {
            return 'Perioada #' . ($idx + 1) . ': „min. nopți” trebuie să fie ≥ 1 sau lăsat gol.';
        }
    }
    $n = count($periods);
    for ($i = 0; $i < $n; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            $a = $periods[$i];
            $b = $periods[$j];
            if (lh_pricing_period_ranges_overlap($a['date_start'], $a['date_end'], $b['date_start'], $b['date_end'])) {
                return 'Perioadele nu trebuie să se suprapună (verifică #' . ($i + 1) . ' și #' . ($j + 1) . ').';
            }
        }
    }

    return null;
}

/**
 * @param array{date_start:mixed,date_end:mixed,price:mixed,price_weekend?:mixed,label?:mixed,min_stay?:mixed} $row
 *
 * @return array{date_start:string,date_end:string,price:float,price_weekend:?float,label:?string,min_stay:?int,stay_discounts:list<array{min_nights:int,value:float,unit:string}>}
 */
function lh_property_pricing_period_row_normalize(array $row): array
{
    $pwRaw = $row['price_weekend'] ?? null;
    $pw = null;
    if ($pwRaw !== null && $pwRaw !== '') {
        $pwf = (float) $pwRaw;
        if ($pwf > 0.0) {
            $pw = $pwf;
        }
    }
    $lb = $row['label'] ?? null;

    $msRaw = $row['min_stay'] ?? null;
    $minStay = null;
    if ($msRaw !== null && $msRaw !== '') {
        $msi = (int) $msRaw;
        if ($msi >= 1) {
            $minStay = $msi;
        }
    }

    $sd = $row['stay_discounts'] ?? null;
    $stayDiscounts = [];
    if (is_array($sd)) {
        foreach ($sd as $item) {
            if (!is_array($item)) {
                continue;
            }
            $u = (string) ($item['unit'] ?? 'percent');
            $stayDiscounts[] = [
                'min_nights' => (int) ($item['min_nights'] ?? 0),
                'value' => (float) ($item['value'] ?? 0),
                'unit' => $u === 'fixed_stay' ? 'fixed_stay' : 'percent',
            ];
        }
    }

    return [
        'date_start' => (string) ($row['date_start'] ?? ''),
        'date_end' => (string) ($row['date_end'] ?? ''),
        'price' => (float) ($row['price'] ?? 0),
        'price_weekend' => $pw,
        'label' => ($lb !== null && (string) $lb !== '') ? (string) $lb : null,
        'min_stay' => $minStay,
        'stay_discounts' => $stayDiscounts,
    ];
}

/**
 * Insert/replace one half-open pricing range, splitting any overlapping periods.
 *
 * @param list<array{date_start:mixed,date_end:mixed,price:mixed,price_weekend?:mixed,label?:mixed,min_stay?:mixed}> $existingRows
 *
 * @return array{periods: list<array{date_start:string,date_end:string,price:float,price_weekend:?float,label:?string,min_stay:?int,stay_discounts:list<array{min_nights:int,value:float,unit:string}>}>, error: ?string}
 */
function lh_property_pricing_periods_merge_apply_range(
    array $existingRows,
    string $newStart,
    string $newEnd,
    float $newPrice,
    ?float $newPriceWeekend,
    ?string $newLabel,
    ?int $newMinStay = null
): array {
    $ns = $newStart;
    $ne = $newEnd;
    $dNs = DateTime::createFromFormat('Y-m-d', $ns);
    $dNe = DateTime::createFromFormat('Y-m-d', $ne);
    if (!$dNs || $dNs->format('Y-m-d') !== $ns || !$dNe || $dNe->format('Y-m-d') !== $ne || $ne <= $ns) {
        return ['periods' => [], 'error' => 'Intervalul de date pentru prețul special este invalid.'];
    }
    if ($newPrice <= 0.0) {
        return ['periods' => [], 'error' => 'Prețul trebuie să fie mai mare ca 0.'];
    }

    $out = [];
    foreach ($existingRows as $raw) {
        $row = lh_property_pricing_period_row_normalize($raw);
        $s = $row['date_start'];
        $e = $row['date_end'];
        if (strlen($s) !== 10 || strlen($e) !== 10) {
            continue;
        }
        if (!lh_pricing_period_ranges_overlap($s, $e, $ns, $ne)) {
            $out[] = $row;
            continue;
        }
        $sdCopy = $row['stay_discounts'] ?? [];
        $msCopy = $row['min_stay'] ?? null;
        if ($s < $ns) {
            $leftEnd = $ns < $e ? $ns : $e;
            if ($leftEnd > $s) {
                $out[] = [
                    'date_start' => $s,
                    'date_end' => $leftEnd,
                    'price' => $row['price'],
                    'price_weekend' => $row['price_weekend'],
                    'label' => $row['label'],
                    'min_stay' => $msCopy,
                    'stay_discounts' => $sdCopy,
                ];
            }
        }
        if ($ne < $e && $ne > $s) {
            $out[] = [
                'date_start' => $ne,
                'date_end' => $e,
                'price' => $row['price'],
                'price_weekend' => $row['price_weekend'],
                'label' => $row['label'],
                'min_stay' => $msCopy,
                'stay_discounts' => $sdCopy,
            ];
        }
    }

    $pwNew = $newPriceWeekend;
    if ($pwNew !== null && $pwNew <= 0.0) {
        $pwNew = null;
    }

    $newMs = ($newMinStay !== null && $newMinStay >= 1) ? $newMinStay : null;

    $out[] = [
        'date_start' => $ns,
        'date_end' => $ne,
        'price' => $newPrice,
        'price_weekend' => $pwNew,
        'label' => ($newLabel !== null && $newLabel !== '') ? $newLabel : null,
        'min_stay' => $newMs,
        'stay_discounts' => [],
    ];

    usort(
        $out,
        static function (array $a, array $b): int {
            return strcmp($a['date_start'], $b['date_start']);
        }
    );

    $err = lh_pricing_periods_validate($out);

    return [
        'periods' => $err === null ? $out : [],
        'error' => $err,
    ];
}

/**
 * @return array{rules: list<array{min_nights:int,value:float,unit:string}>, error: ?string}
 */
function lh_stay_discount_rules_from_json_string(string $jsonRaw): array
{
    $jsonRaw = trim($jsonRaw);
    if ($jsonRaw === '') {
        return ['rules' => [], 'error' => null];
    }
    $decoded = json_decode($jsonRaw, true);
    if (!is_array($decoded)) {
        return ['rules' => [], 'error' => 'Reduceri (JSON): format invalid.'];
    }
    $rules = [];
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        $mn = (int) ($item['min_nights'] ?? 0);
        $val = (float) ($item['value'] ?? 0);
        $unit = (string) ($item['unit'] ?? 'percent');
        if ($mn < 1) {
            return ['rules' => [], 'error' => 'Reduceri: pragul „min. nopți” trebuie să fie ≥ 1.'];
        }
        if ($val <= 0) {
            return ['rules' => [], 'error' => 'Reduceri: valoarea trebuie să fie mai mare ca 0.'];
        }
        $u = $unit === 'fixed_stay' ? 'fixed_stay' : 'percent';
        if ($u === 'percent' && $val > 100.0) {
            return ['rules' => [], 'error' => 'Reduceri: procentul nu poate depăși 100.'];
        }
        $rules[] = ['min_nights' => $mn, 'value' => $val, 'unit' => $u];
    }

    return ['rules' => $rules, 'error' => null];
}

/**
 * @return array{rules: list<array{min_nights:int,value:float,unit:string}>, error: ?string}
 */
function lh_stay_discount_global_rules_from_post(array $post): array
{
    $mins = $post['g_sd_min'] ?? [];
    $vals = $post['g_sd_val'] ?? [];
    $units = $post['g_sd_unit'] ?? [];
    if (!is_array($mins)) {
        $mins = [];
    }
    if (!is_array($vals)) {
        $vals = [];
    }
    if (!is_array($units)) {
        $units = [];
    }
    $max = max(count($mins), count($vals), count($units));
    $rules = [];
    for ($i = 0; $i < $max; $i++) {
        $mn = trim((string) ($mins[$i] ?? ''));
        $va = trim((string) ($vals[$i] ?? ''));
        $un = trim((string) ($units[$i] ?? 'percent'));
        if ($mn === '' && $va === '') {
            continue;
        }
        if ($mn === '' || $va === '') {
            return ['rules' => [], 'error' => 'Reduceri globale (rând #' . ($i + 1) . '): completează pragul și valoarea sau golește rândul.'];
        }
        $mnInt = (int) $mn;
        $valF = (float) str_replace(',', '.', $va);
        $u = $un === 'fixed_stay' ? 'fixed_stay' : 'percent';
        if ($mnInt < 1) {
            return ['rules' => [], 'error' => 'Reduceri globale (rând #' . ($i + 1) . '): pragul „min. nopți” trebuie să fie ≥ 1.'];
        }
        if ($valF <= 0) {
            return ['rules' => [], 'error' => 'Reduceri globale (rând #' . ($i + 1) . '): valoarea trebuie să fie mai mare ca 0.'];
        }
        if ($u === 'percent' && $valF > 100.0) {
            return ['rules' => [], 'error' => 'Reduceri globale (rând #' . ($i + 1) . '): procentul nu poate depăși 100.'];
        }
        $rules[] = ['min_nights' => $mnInt, 'value' => $valF, 'unit' => $u];
    }

    return ['rules' => $rules, 'error' => null];
}

/**
 * @return array{periods: list<array{date_start:string,date_end:string,price:float,price_weekend:?float,label:?string,min_stay:?int,stay_discounts?:list<array{min_nights:int,value:float,unit:string}>}>, error: ?string}
 */
function lh_pricing_periods_from_post(array $post): array
{
    $labels = $post['pp_label'] ?? [];
    $starts = $post['pp_date_start'] ?? [];
    $ends = $post['pp_date_end'] ?? [];
    $prices = $post['pp_price'] ?? [];
    $pws = $post['pp_price_weekend'] ?? [];
    $mins = $post['pp_min_stay'] ?? [];
    $ppJson = $post['pp_stay_discounts_json'] ?? [];
    if (!is_array($starts)) {
        $starts = [];
    }
    if (!is_array($ends)) {
        $ends = [];
    }
    if (!is_array($prices)) {
        $prices = [];
    }
    if (!is_array($pws)) {
        $pws = [];
    }
    if (!is_array($labels)) {
        $labels = [];
    }
    if (!is_array($ppJson)) {
        $ppJson = [];
    }
    if (!is_array($mins)) {
        $mins = [];
    }
    $max = max(count($starts), count($ends), count($prices), count($pws), count($labels), count($ppJson), count($mins));
    $periods = [];
    for ($i = 0; $i < $max; $i++) {
        $ds = trim((string) ($starts[$i] ?? ''));
        $de = trim((string) ($ends[$i] ?? ''));
        $pr = trim((string) ($prices[$i] ?? ''));
        $pw = trim((string) ($pws[$i] ?? ''));
        $lb = trim((string) ($labels[$i] ?? ''));
        $mns = trim((string) ($mins[$i] ?? ''));
        $rowEmpty = $ds === '' && $de === '' && $pr === '' && $pw === '' && $lb === '' && $mns === '';
        if ($rowEmpty) {
            continue;
        }
        if ($ds === '' || $de === '' || $pr === '') {
            return ['periods' => [], 'error' => 'Perioada #' . ($i + 1) . ': completează „de la”, „până la” și prețul, sau golește tot rândul.'];
        }
        $price = (float) str_replace(',', '.', $pr);
        $pwf = null;
        if ($pw !== '' && (float) str_replace(',', '.', $pw) > 0) {
            $pwf = (float) str_replace(',', '.', $pw);
        }
        $jraw = trim((string) ($ppJson[$i] ?? '[]'));
        $sdParsed = lh_stay_discount_rules_from_json_string($jraw !== '' ? $jraw : '[]');
        if ($sdParsed['error'] !== null) {
            return ['periods' => [], 'error' => 'Perioada #' . ($i + 1) . ': ' . $sdParsed['error']];
        }
        $minStayPeriod = null;
        if ($mns !== '') {
            $minStayPeriod = (int) $mns;
            if ($minStayPeriod < 1) {
                return ['periods' => [], 'error' => 'Perioada #' . ($i + 1) . ': „min. nopți” trebuie să fie ≥ 1 sau lăsat gol.'];
            }
        }
        $periods[] = [
            'date_start' => $ds,
            'date_end' => $de,
            'price' => $price,
            'price_weekend' => $pwf,
            'label' => $lb !== '' ? $lb : null,
            'min_stay' => $minStayPeriod,
            'stay_discounts' => $sdParsed['rules'],
        ];
    }
    $err = lh_pricing_periods_validate($periods);
    if ($err !== null) {
        return ['periods' => [], 'error' => $err];
    }

    return ['periods' => $periods, 'error' => null];
}

function lh_booking_weekend_night_start_from_ymd(string $ymd): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $ymd);
    if (!$dt || $dt->format('Y-m-d') !== $ymd) {
        return false;
    }
    $w = (int) $dt->format('w');

    return $w === 0 || $w === 6;
}

function lh_booking_night_rate_for_date(string $ymd, array $property, ?array $pricingPeriods = null): float
{
    if ($pricingPeriods !== null) {
        foreach ($pricingPeriods as $row) {
            if (!lh_pricing_period_row_contains_ymd($row, $ymd)) {
                continue;
            }
            $ps = (float) ($row['price'] ?? 0);
            if (lh_booking_weekend_night_start_from_ymd($ymd)) {
                $pw = isset($row['price_weekend']) && $row['price_weekend'] !== null && $row['price_weekend'] !== ''
                    ? (float) $row['price_weekend']
                    : 0.0;

                return $pw > 0 ? $pw : $ps;
            }

            return $ps;
        }
    }

    $standard = (float) ($property['price'] ?? 0);
    if (lh_booking_weekend_night_start_from_ymd($ymd)) {
        $pw = isset($property['price_weekend']) ? (float) $property['price_weekend'] : 0.0;

        return $pw > 0 ? $pw : $standard;
    }

    return $standard;
}

function lh_booking_extra_guest_charge(int $guests, int $nights, array $property): float
{
    if ($nights < 1) {
        return 0.0;
    }
    $included = isset($property['guests_included']) ? (int) $property['guests_included'] : 0;
    if ($included <= 0) {
        return 0.0;
    }
    $ep = isset($property['extra_guest_price']) ? (float) $property['extra_guest_price'] : 0.0;
    if ($ep <= 0.0 || $guests <= $included) {
        return 0.0;
    }
    $unit = (string) ($property['extra_guest_unit'] ?? 'per_guest_per_night');
    $extraPeople = $guests - $included;
    if ($unit === 'per_guest_per_night') {
        return $extraPeople * $ep * $nights;
    }

    return 0.0;
}

function lh_booking_stay_fully_inside_pricing_period(string $checkIn, string $checkOut, array $period): bool
{
    $ds = (string) ($period['date_start'] ?? '');
    $de = (string) ($period['date_end'] ?? '');
    if (strlen($ds) !== 10 || strlen($de) !== 10) {
        return false;
    }

    return $checkIn >= $ds && $checkOut <= $de;
}

/**
 * Minimum nights for a stay: property base, unless the stay lies entirely inside one pricing period
 * that defines its own min_stay (then that value replaces the base for this booking).
 */
function lh_booking_effective_min_stay(array $property, string $checkIn, string $checkOut, ?array $pricingPeriods = null): int
{
    $base = max(1, (int) ($property['min_stay'] ?? 1));
    $d1 = DateTime::createFromFormat('Y-m-d', $checkIn);
    $d2 = DateTime::createFromFormat('Y-m-d', $checkOut);
    if (!$d1 || $d1->format('Y-m-d') !== $checkIn || !$d2 || $d2->format('Y-m-d') !== $checkOut || $d2 <= $d1) {
        return $base;
    }
    $pid = isset($property['id']) ? (int) $property['id'] : 0;
    if ($pricingPeriods === null && $pid > 0) {
        $pricingPeriods = lh_property_pricing_periods_load($pid);
    }
    if ($pricingPeriods === null || $pricingPeriods === []) {
        return $base;
    }
    foreach ($pricingPeriods as $row) {
        if (!lh_booking_stay_fully_inside_pricing_period($checkIn, $checkOut, $row)) {
            continue;
        }
        $ms = $row['min_stay'] ?? null;
        if ($ms !== null && $ms !== '' && (int) $ms >= 1) {
            return (int) $ms;
        }

        return $base;
    }

    return $base;
}

/**
 * @param list<array<string,mixed>> $pricingPeriods
 * @param list<array{min_nights:int,value:float,unit:string}> $globalRules
 *
 * @return list<array{min_nights:int,value:float,unit:string}>
 */
function lh_booking_select_stay_discount_rules(
    string $checkIn,
    string $checkOut,
    array $pricingPeriods,
    array $globalRules
): array {
    foreach ($pricingPeriods as $row) {
        if (lh_booking_stay_fully_inside_pricing_period($checkIn, $checkOut, $row)) {
            $sd = $row['stay_discounts'] ?? [];

            return is_array($sd) ? $sd : [];
        }
    }

    return $globalRules;
}

/**
 * @param list<array{min_nights:int,value:float,unit:string}> $rules
 */
function lh_booking_stay_length_discount_amount(int $nights, float $subtotal, array $rules): float
{
    if ($nights < 1 || $rules === [] || $subtotal <= 0.0) {
        return 0.0;
    }
    $bestMn = null;
    $best = null;
    foreach ($rules as $r) {
        if (!is_array($r)) {
            continue;
        }
        $mn = (int) ($r['min_nights'] ?? 0);
        if ($nights <= $mn) {
            continue;
        }
        if ($bestMn === null || $mn > $bestMn) {
            $bestMn = $mn;
            $best = $r;
        }
    }
    if ($best === null) {
        return 0.0;
    }
    $val = (float) ($best['value'] ?? 0);
    $unit = (string) ($best['unit'] ?? 'percent');
    if ($val <= 0.0) {
        return 0.0;
    }
    if ($unit === 'fixed_stay') {
        return min($subtotal, $val);
    }

    return min($subtotal, $subtotal * ($val / 100.0));
}

/**
 * @return array{nights:int,base_nights_total:float,extra_guest_total:float,length_discount:float,subtotal_before_discount:float,total:float}
 */
function lh_booking_stay_total(array $property, string $check_in, string $check_out, int $guests): array
{
    $empty = [
        'nights' => 0,
        'base_nights_total' => 0.0,
        'extra_guest_total' => 0.0,
        'length_discount' => 0.0,
        'subtotal_before_discount' => 0.0,
        'total' => 0.0,
    ];
    $d1 = DateTime::createFromFormat('Y-m-d', $check_in);
    $d2 = DateTime::createFromFormat('Y-m-d', $check_out);
    if (!$d1 || $d1->format('Y-m-d') !== $check_in || !$d2 || $d2->format('Y-m-d') !== $check_out) {
        return $empty;
    }
    if ($d2 <= $d1) {
        return $empty;
    }
    $pid = isset($property['id']) ? (int) $property['id'] : 0;
    $pricingPeriods = $property['_pricing_periods'] ?? null;
    if ($pricingPeriods === null && $pid > 0) {
        $pricingPeriods = lh_property_pricing_periods_load($pid);
    }
    if ($pricingPeriods === null) {
        $pricingPeriods = [];
    }
    $nights = (int) $d2->diff($d1)->days;
    $base = 0.0;
    $cur = clone $d1;
    $end = clone $d2;
    while ($cur < $end) {
        $base += lh_booking_night_rate_for_date($cur->format('Y-m-d'), $property, $pricingPeriods);
        $cur->modify('+1 day');
    }
    $extra = lh_booking_extra_guest_charge($guests, $nights, $property);
    $subtotal = $base + $extra;
    $globalRules = $property['_stay_discounts_global'] ?? null;
    if ($globalRules === null && $pid > 0) {
        $pack = lh_property_stay_discounts_load_by_property($pid);
        $globalRules = $pack['global'];
    }
    if (!is_array($globalRules)) {
        $globalRules = [];
    }
    $rules = lh_booking_select_stay_discount_rules($check_in, $check_out, $pricingPeriods, $globalRules);
    $discount = lh_booking_stay_length_discount_amount($nights, $subtotal, $rules);
    $total = max(0.0, $subtotal - $discount);

    return [
        'nights' => $nights,
        'base_nights_total' => $base,
        'extra_guest_total' => $extra,
        'length_discount' => $discount,
        'subtotal_before_discount' => $subtotal,
        'total' => $total,
    ];
}
