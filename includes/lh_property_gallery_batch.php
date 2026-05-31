<?php

declare(strict_types=1);

require_once __DIR__ . '/lh_add_property_core.php';

/**
 * Append newly uploaded gallery images to a property (reads $_FILES-like structure).
 *
 * @param array<string, mixed> $files Typically $_FILES (must contain 'images' multi-upload shape)
 * @return array{ok: true, added: int, names: list<string>}|array{ok: false, error: string}
 */
function lh_property_append_images_batch(mysqli $conn, int $propertyId, array $files): array
{
    if ($propertyId <= 0) {
        return ['ok' => false, 'error' => 'ID proprietate invalid.'];
    }

    $images = $files['images'] ?? null;
    if (!is_array($images) || empty($images['name']) || !is_array($images['name'])) {
        return ['ok' => false, 'error' => 'Nu s-au primit imagini.'];
    }

    $n = count($images['name']);
    if ($n > LH_ADD_PROPERTY_IMAGE_BATCH_MAX) {
        return [
            'ok' => false,
            'error' => 'Prea multe fișiere într-un lot (max ' . LH_ADD_PROPERTY_IMAGE_BATCH_MAX . ').',
        ];
    }

    $resPrev = mysqli_query($conn, 'SELECT image_name FROM properties WHERE id=' . $propertyId . ' LIMIT 1');
    if (!$resPrev) {
        return ['ok' => false, 'error' => 'Eroare la citirea proprietății.'];
    }
    $rowPrev = mysqli_fetch_assoc($resPrev);
    if (!$rowPrev) {
        return ['ok' => false, 'error' => 'Proprietatea nu există.'];
    }

    $existing = trim((string) ($rowPrev['image_name'] ?? ''));
    $uploaded = [];
    foreach ($images['tmp_name'] as $key => $tmp) {
        $file = [
            'name' => $images['name'][$key] ?? '',
            'type' => $images['type'][$key] ?? '',
            'tmp_name' => $tmp,
            'error' => (int) ($images['error'][$key] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($images['size'][$key] ?? 0),
        ];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $stored = lh_store_property_image($file, $propertyId);
        if ($stored !== null) {
            $uploaded[] = $stored;
        }
    }

    if ($uploaded === []) {
        return ['ok' => false, 'error' => 'Nicio imagine validă în acest lot. Verifică formatul și mărimea.'];
    }

    $parts = $existing !== '' ? array_filter(array_map('trim', explode(',', $existing))) : [];
    foreach ($uploaded as $name) {
        $parts[] = $name;
    }
    $image_string = mysqli_real_escape_string($conn, implode(',', $parts));
    $okUp = mysqli_query($conn, "UPDATE properties SET image_name='$image_string' WHERE id=" . $propertyId);
    if (!$okUp) {
        return ['ok' => false, 'error' => 'Eroare la salvarea galeriei: ' . mysqli_error($conn)];
    }

    return ['ok' => true, 'added' => count($uploaded), 'names' => $uploaded];
}
