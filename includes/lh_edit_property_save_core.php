<?php

declare(strict_types=1);

require_once __DIR__ . '/ical_importer.php';
require_once __DIR__ . '/booking_pricing.php';
require_once __DIR__ . '/lh_property_translations_save.php';

function lh_edit_property_save_debug_timing_enabled(): bool
{
    return in_array(strtolower(lh_env('LH_DEBUG_SAVE_TIMINGS', '0')), ['1', 'true', 'yes', 'on'], true);
}

/**
 * @param array<string, float|int> $timings
 */
function lh_edit_property_save_timing_tick(float &$marker, array &$timings, string $key): void
{
    $now = microtime(true);
    $timings[$key] = round(($now - $marker) * 1000, 3);
    $marker = $now;
}

/**
 * Apply full property update from admin edit form (optionally merges new multi-file uploads).
 *
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files Same shape as $_FILES
 * @return array{ok: true, debug_timings?: array<string, float|int>}|array{ok: false, error: string}
 */
function lh_edit_property_save_from_post(mysqli $conn, PDO $pdo, int $id, array $post, array $files): array
{
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'ID invalid.'];
    }

    $dbgTime = lh_edit_property_save_debug_timing_enabled();
    $timeMark = microtime(true);
    $timings = [];

    $parsedPeriodsPost = lh_pricing_periods_from_post($post);
    if ($parsedPeriodsPost['error'] !== null) {
        return ['ok' => false, 'error' => (string) $parsedPeriodsPost['error']];
    }
    $parsedGlobalSd = lh_stay_discount_global_rules_from_post($post);
    if ($parsedGlobalSd['error'] !== null) {
        return ['ok' => false, 'error' => (string) $parsedGlobalSd['error']];
    }

    if ($dbgTime) {
        lh_edit_property_save_timing_tick($timeMark, $timings, 'parse_post_ms');
    }

    $title_raw = trim((string) ($post['title'] ?? ''));
    $lot_id = mysqli_real_escape_string($conn, trim((string) ($post['lot_id'] ?? '')));
    $price = !empty($post['price']) ? floatval($post['price']) : 0;
    $price_weekend_sql = (
        isset($post['price_weekend'])
        && $post['price_weekend'] !== ''
        && floatval($post['price_weekend']) > 0
    )
        ? "'" . mysqli_real_escape_string($conn, (string) floatval($post['price_weekend'])) . "'"
        : 'NULL';
    $guests_included_sql = (
        isset($post['guests_included'])
        && $post['guests_included'] !== ''
        && (int) $post['guests_included'] > 0
    )
        ? (string) (int) $post['guests_included']
        : 'NULL';
    $extra_guest_price_sql = (
        isset($post['extra_guest_price'])
        && $post['extra_guest_price'] !== ''
        && floatval($post['extra_guest_price']) > 0
    )
        ? "'" . mysqli_real_escape_string($conn, (string) floatval($post['extra_guest_price'])) . "'"
        : 'NULL';
    $extra_guest_unit_sql = "'" . mysqli_real_escape_string($conn, 'per_guest_per_night') . "'";

    $city_raw = trim((string) ($post['city'] ?? 'Chișinău'));
    $district_raw = trim((string) ($post['district'] ?? ''));
    $address_raw = trim((string) ($post['address'] ?? ''));
    $description_raw = trim((string) ($post['description_long'] ?? ''));
    $pre_checkin_raw = trim((string) ($post['pre_checkin_email_message'] ?? ''));
    $property_type = mysqli_real_escape_string($conn, trim((string) ($post['property_type'] ?? 'Apartament')));
    $rooms = intval($post['rooms'] ?? 0);
    $sleep_capacity = isset($post['sleep_capacity']) && $post['sleep_capacity'] !== '' ? intval($post['sleep_capacity']) : 'NULL';
    $area_sqm = intval($post['area_sqm'] ?? 0);
    $floor = intval($post['floor'] ?? 0);
    $min_stay = intval($post['min_stay'] ?? 1);
    $ical_link = mysqli_real_escape_string($conn, trim((string) ($post['ical_import_link'] ?? '')));
    $c_in_s = mysqli_real_escape_string($conn, (string) ($post['check_in_start'] ?? '14:00'));
    $c_in_e = mysqli_real_escape_string($conn, (string) ($post['check_in_end'] ?? '21:00'));
    $c_out_s = mysqli_real_escape_string($conn, (string) ($post['check_out_start'] ?? '08:00'));
    $c_out_e = mysqli_real_escape_string($conn, (string) ($post['check_out_end'] ?? '11:00'));

    $title = mysqli_real_escape_string($conn, $title_raw);
    $city = mysqli_real_escape_string($conn, $city_raw !== '' ? $city_raw : 'Chișinău');
    $district = mysqli_real_escape_string($conn, $district_raw);
    $address = mysqli_real_escape_string($conn, $address_raw);
    $description_long = mysqli_real_escape_string($conn, $description_raw);
    $pre_checkin_email_message = mysqli_real_escape_string($conn, $pre_checkin_raw);
    $description_short = mysqli_real_escape_string($conn, mb_substr($description_raw, 0, 220));
    $location_raw = trim(implode(', ', array_filter([$city_raw !== '' ? $city_raw : 'Chișinău', $district_raw])));
    $location = mysqli_real_escape_string($conn, $location_raw);
    $slug_base = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title_raw)), '-'));
    $slug = mysqli_real_escape_string($conn, $slug_base !== '' ? $slug_base : 'property-' . $id);

    $amenities = isset($post['amenities']) ? json_encode($post['amenities'], JSON_UNESCAPED_UNICODE) : '[]';

    $stmtPrevImg = $pdo->prepare('SELECT image_name FROM properties WHERE id = ? LIMIT 1');
    $stmtPrevImg->execute([$id]);
    $rowPrevImg = $stmtPrevImg->fetch();
    $previous_image_csv = (string) ($rowPrevImg['image_name'] ?? '');

    $existingRaw = $post['existing_images'] ?? [];
    $final_images = is_array($existingRaw) ? $existingRaw : [];

    $imagesBlock = $files['images'] ?? null;
    if (is_array($imagesBlock) && !empty($imagesBlock['name']) && is_array($imagesBlock['name'])) {
        foreach ($imagesBlock['tmp_name'] as $key => $tmp) {
            $file = [
                'name' => $imagesBlock['name'][$key] ?? '',
                'type' => $imagesBlock['type'][$key] ?? '',
                'tmp_name' => $tmp,
                'error' => (int) ($imagesBlock['error'][$key] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($imagesBlock['size'][$key] ?? 0),
            ];
            $stored = lh_store_property_image($file, $id);
            if ($stored !== null) {
                $final_images[] = $stored;
            }
        }
    }

    $final_unique = [];
    foreach ($final_images as $raw) {
        $bn = trim((string) $raw);
        if ($bn === '' || strpbrk($bn, "\\/") !== false) {
            continue;
        }
        $final_unique[$bn] = $bn;
    }
    $final_unique = array_values($final_unique);

    $old_names = array_filter(array_map('trim', explode(',', $previous_image_csv)));
    foreach ($old_names as $on) {
        if ($on === '' || strpbrk($on, "\\/") !== false) {
            continue;
        }
        if (!in_array($on, $final_unique, true)) {
            lh_delete_property_image_from_disk($id, $on);
        }
    }

    $image_string = mysqli_real_escape_string($conn, implode(',', $final_unique));

    if ($dbgTime) {
        lh_edit_property_save_timing_tick($timeMark, $timings, 'prepare_images_ms');
    }

    $sql = "UPDATE properties SET 
            title='$title', lot_id='$lot_id', slug='$slug', price='$price', price_weekend=$price_weekend_sql, guests_included=$guests_included_sql, extra_guest_price=$extra_guest_price_sql, extra_guest_unit=$extra_guest_unit_sql, location='$location', description='$description_short', city='$city', district='$district', address='$address', 
            description_long='$description_long', pre_checkin_email_message='$pre_checkin_email_message', property_type='$property_type', rooms='$rooms', 
            sleep_capacity=" . $sleep_capacity . ", area_sqm='$area_sqm', floor='$floor', min_stay='$min_stay', 
            check_in_start='$c_in_s', check_in_end='$c_in_e', check_out_start='$c_out_s', check_out_end='$c_out_e',
            amenities='$amenities', ical_import_link='$ical_link', image_name='$image_string'
            WHERE id=$id";

    if (!mysqli_query($conn, $sql)) {
        return ['ok' => false, 'error' => mysqli_error($conn)];
    }

    if ($dbgTime) {
        lh_edit_property_save_timing_tick($timeMark, $timings, 'update_properties_ms');
    }

    lh_property_pricing_periods_save($pdo, $id, $parsedPeriodsPost['periods'], $parsedGlobalSd['rules']);

    if ($dbgTime) {
        lh_edit_property_save_timing_tick($timeMark, $timings, 'pricing_periods_save_ms');
    }

    $updDetails = ['title' => $title_raw, 'lot_id' => trim((string) ($post['lot_id'] ?? ''))];
    if (!empty(trim((string) ($post['ical_import_link'] ?? '')))) {
        $icalResult = importPropertyIcal($id);
        lh_ical_set_import_feedback($icalResult);
        $updDetails['ical_import_success'] = !empty($icalResult['success']);
        $updDetails['ical_imported'] = (int) ($icalResult['imported'] ?? 0);
        if (empty($icalResult['success']) && !empty($icalResult['error'])) {
            $updDetails['ical_error'] = (string) $icalResult['error'];
        }
    } else {
        mysqli_query($conn, 'DELETE FROM blocked_dates WHERE property_id = ' . (int) $id . " AND source = 'ical_import'");
        $updDetails['ical_cleared'] = true;
    }

    if ($dbgTime) {
        lh_edit_property_save_timing_tick($timeMark, $timings, 'ical_or_blocked_delete_ms');
    }

    lh_admin_log_activity($conn, 'property_update', 'property', $id, $updDetails);

    $trErr = lh_property_translations_save_from_post($pdo, $id, $post);
    if ($trErr !== null) {
        return ['ok' => false, 'error' => $trErr];
    }

    if ($dbgTime) {
        lh_edit_property_save_timing_tick($timeMark, $timings, 'activity_log_ms');
        $timings['total_ms'] = round(
            ($timings['parse_post_ms'] ?? 0)
            + ($timings['prepare_images_ms'] ?? 0)
            + ($timings['update_properties_ms'] ?? 0)
            + ($timings['pricing_periods_save_ms'] ?? 0)
            + ($timings['ical_or_blocked_delete_ms'] ?? 0)
            + ($timings['activity_log_ms'] ?? 0),
            3
        );

        return ['ok' => true, 'debug_timings' => $timings];
    }

    return ['ok' => true];
}
