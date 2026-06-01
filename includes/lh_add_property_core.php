<?php

declare(strict_types=1);

if (!defined('LH_ADD_PROPERTY_IMAGE_BATCH_MAX')) {
    define('LH_ADD_PROPERTY_IMAGE_BATCH_MAX', 10);
}

/**
 * Create a property row and related pricing / iCal side effects (no gallery files).
 *
 * @param array<string, mixed> $post Same shape as $_POST from the add-property form
 * @return array{ok: true, property_id: int}|array{ok: false, error: string}
 */
function lh_add_property_create_from_post(mysqli $conn, array $post): array
{
    require_once __DIR__ . '/ical_importer.php';
    require_once __DIR__ . '/booking_pricing.php';
require_once __DIR__ . '/lh_property_translations_save.php';

    $parsedPeriodsPost = lh_pricing_periods_from_post($post);
    if ($parsedPeriodsPost['error'] !== null) {
        return ['ok' => false, 'error' => (string) $parsedPeriodsPost['error']];
    }
    $parsedGlobalSd = lh_stay_discount_global_rules_from_post($post);
    if ($parsedGlobalSd['error'] !== null) {
        return ['ok' => false, 'error' => (string) $parsedGlobalSd['error']];
    }

    $title_raw = trim((string) ($post['title'] ?? ''));
    $lot_id = mysqli_real_escape_string($conn, trim((string) ($post['lot_id'] ?? '')));
    $city_raw = trim((string) ($post['city'] ?? 'Chișinău'));
    $district_raw = trim((string) ($post['district'] ?? ''));
    $address_raw = trim((string) ($post['address'] ?? ''));
    $description_long_raw = trim((string) ($post['description_long'] ?? ''));
    $pre_checkin_email_raw = trim((string) ($post['pre_checkin_email_message'] ?? ''));
    $property_type = mysqli_real_escape_string($conn, trim((string) ($post['property_type'] ?? 'Apartament')));
    $ical_link = mysqli_real_escape_string($conn, trim((string) ($post['ical_import_link'] ?? '')));
    $ical_export_token = bin2hex(random_bytes(16));

    $title = mysqli_real_escape_string($conn, $title_raw);
    $city = mysqli_real_escape_string($conn, $city_raw !== '' ? $city_raw : 'Chișinău');
    $district = mysqli_real_escape_string($conn, $district_raw);
    $address = mysqli_real_escape_string($conn, $address_raw);
    $description_long = mysqli_real_escape_string($conn, $description_long_raw);
    $pre_checkin_email_message = mysqli_real_escape_string($conn, $pre_checkin_email_raw);
    $description_short = mysqli_real_escape_string($conn, mb_substr($description_long_raw, 0, 220));
    $location_raw = trim(implode(', ', array_filter([$city_raw !== '' ? $city_raw : 'Chișinău', $district_raw])));
    $location = mysqli_real_escape_string($conn, $location_raw);
    $slug_base = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title_raw)), '-'));
    $slug = mysqli_real_escape_string($conn, $slug_base !== '' ? $slug_base : 'property-' . time());

    $sleep_capacity = isset($post['sleep_capacity']) && $post['sleep_capacity'] !== '' ? intval($post['sleep_capacity']) : 'NULL';

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

    $rooms = !empty($post['rooms']) ? intval($post['rooms']) : 0;
    $area_sqm = !empty($post['area_sqm']) ? intval($post['area_sqm']) : 0;
    $floor = !empty($post['floor']) ? intval($post['floor']) : 0;
    $min_stay = !empty($post['min_stay']) ? intval($post['min_stay']) : 1;

    $c_in_s = mysqli_real_escape_string($conn, (string) ($post['check_in_start'] ?? '14:00'));
    $c_in_e = mysqli_real_escape_string($conn, (string) ($post['check_in_end'] ?? '21:00'));
    $c_out_s = mysqli_real_escape_string($conn, (string) ($post['check_out_start'] ?? '08:00'));
    $c_out_e = mysqli_real_escape_string($conn, (string) ($post['check_out_end'] ?? '11:00'));

    $amenities = isset($post['amenities']) ? json_encode($post['amenities'], JSON_UNESCAPED_UNICODE) : '[]';

    $sql = "INSERT INTO properties (
                title, lot_id, slug, price, price_weekend, guests_included, extra_guest_price, extra_guest_unit,
                location, description, city, district, address, description_long,
                pre_checkin_email_message,
                property_type, rooms, sleep_capacity, area_sqm, floor, min_stay,
                check_in_start, check_in_end, check_out_start, check_out_end,
                amenities, ical_import_link, ical_export_token, image_name
            ) VALUES (
                '$title', '$lot_id', '$slug', '$price', $price_weekend_sql, $guests_included_sql, $extra_guest_price_sql, $extra_guest_unit_sql,
                '$location', '$description_short', '$city', '$district', '$address', '$description_long',
                '$pre_checkin_email_message',
                '$property_type', '$rooms', " . $sleep_capacity . ", '$area_sqm', '$floor', '$min_stay',
                '$c_in_s', '$c_in_e', '$c_out_s', '$c_out_e',
                '$amenities', '$ical_link', '$ical_export_token', ''
            )";

    if (!mysqli_query($conn, $sql)) {
        return ['ok' => false, 'error' => 'Eroare SQL: ' . mysqli_error($conn)];
    }

    $new_property_id = (int) mysqli_insert_id($conn);

    $logDetails = ['title' => $title_raw, 'lot_id' => trim((string) ($post['lot_id'] ?? ''))];
    if (!empty(trim((string) ($post['ical_import_link'] ?? '')))) {
        $icalResult = importPropertyIcal($new_property_id);
        lh_ical_set_import_feedback($icalResult);
        $logDetails['ical_import_success'] = !empty($icalResult['success']);
        $logDetails['ical_imported'] = (int) ($icalResult['imported'] ?? 0);
        if (empty($icalResult['success']) && !empty($icalResult['error'])) {
            $logDetails['ical_error'] = (string) $icalResult['error'];
        }
    }
    lh_admin_log_activity($conn, 'property_create', 'property', $new_property_id, $logDetails);

    try {
        lh_property_pricing_periods_save(
            getPDO(),
            $new_property_id,
            $parsedPeriodsPost['periods'],
            $parsedGlobalSd['rules']
        );
    } catch (Throwable $e) {
        error_log('add-property pricing periods/discounts: ' . $e->getMessage());
    }

    try {
        $trErr = lh_property_translations_save_from_post(getPDO(), $new_property_id, $post);
        if ($trErr !== null) {
            return ['ok' => false, 'error' => $trErr];
        }
    } catch (Throwable $e) {
        error_log('add-property translations: ' . $e->getMessage());
    }

    return ['ok' => true, 'property_id' => $new_property_id];
}
