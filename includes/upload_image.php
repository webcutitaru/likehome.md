<?php

declare(strict_types=1);

const LH_PROPERTY_IMAGE_MAX_WIDTH = 1920;
/** Grid/cards + gallery thumbs; higher = sharper on Retina, larger files. */
const LH_PROPERTY_THUMB_MAX_WIDTH = 800;
const LH_PROPERTY_WEBP_QUALITY = 88;
/** Slightly higher than main: thumbs are smaller files; reduces banding on flat areas. */
const LH_PROPERTY_THUMB_WEBP_QUALITY = 90;

/**
 * Thumb filename for a main WebP basename (e.g. `ab.webp` → `ab_thumb.webp`).
 */
function lh_property_image_thumb_basename(string $mainBasename): string
{
    if (preg_match('/^(.+)\.webp$/i', $mainBasename, $m)) {
        return $m[1] . '_thumb.webp';
    }

    return '';
}

/**
 * Public URL for a property gallery image. Prefers `uploads/properties/{id}/`, falls back to `assets/img/`.
 *
 * @param 'full'|'thumb' $variant
 */
function lh_property_image_url(int $propertyId, string $basename, string $variant = 'full'): string
{
    $basename = trim($basename);
    if ($basename === '' || strpbrk($basename, "\\/") !== false) {
        return lh_public_url('assets/img/default.jpg');
    }

    $baseFs = __DIR__ . '/../uploads/properties/' . $propertyId;
    $baseUrlPath = 'uploads/properties/' . $propertyId . '/';

    if ($propertyId > 0 && $variant === 'thumb') {
        $thumbName = lh_property_image_thumb_basename($basename);
        if ($thumbName !== '' && is_file($baseFs . DIRECTORY_SEPARATOR . $thumbName)) {
            return lh_public_url($baseUrlPath . rawurlencode($thumbName));
        }
    }

    if ($propertyId > 0 && is_file($baseFs . DIRECTORY_SEPARATOR . $basename)) {
        return lh_public_url($baseUrlPath . rawurlencode($basename));
    }

    return lh_public_url('assets/img/' . rawurlencode($basename));
}

/**
 * @return resource|GdImage|false
 */
function lh_gd_load_uploaded_image(string $tmp, int $imageType)
{
    if ($imageType === IMAGETYPE_JPEG) {
        return imagecreatefromjpeg($tmp);
    }
    if ($imageType === IMAGETYPE_PNG) {
        $im = imagecreatefrompng($tmp);
        if ($im !== false) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
        }

        return $im;
    }
    if ($imageType === IMAGETYPE_WEBP) {
        if (!function_exists('imagecreatefromwebp')) {
            return false;
        }
        $im = imagecreatefromwebp($tmp);
        if ($im !== false) {
            imagealphablending($im, false);
            imagesavealpha($im, true);
        }

        return $im;
    }

    return false;
}

/**
 * @param resource|GdImage $im
 * @return resource|GdImage|null
 */
function lh_gd_scale_max_width($im, int $maxWidth)
{
    $w = imagesx($im);
    $h = imagesy($im);
    if ($w <= 0 || $h <= 0) {
        imagedestroy($im);

        return null;
    }
    if ($w <= $maxWidth) {
        return $im;
    }

    $scaled = imagescale($im, $maxWidth, -1, IMG_BILINEAR_FIXED);
    if ($scaled === false) {
        imagedestroy($im);

        return null;
    }
    imagedestroy($im);

    return $scaled;
}

/**
 * @param resource|GdImage $main
 * @return resource|GdImage|null
 */
function lh_gd_make_thumb($main, int $maxWidth)
{
    $w = imagesx($main);
    $h = imagesy($main);
    if ($w <= 0 || $h <= 0) {
        return null;
    }

    $targetW = min($maxWidth, $w);
    if ($targetW === $w) {
        $thumb = imagecreatetruecolor($w, $h);
        if ($thumb === false) {
            return null;
        }
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefilledrectangle($thumb, 0, 0, $w, $h, $transparent);
        imagealphablending($thumb, true);
        imagecopy($thumb, $main, 0, 0, 0, 0, $w, $h);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        return $thumb;
    }

    $thumb = imagescale($main, $targetW, -1, IMG_BILINEAR_FIXED);

    return $thumb === false ? null : $thumb;
}

/**
 * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file One element from a multi-file $_FILES structure
 * @return non-empty-string|null Stored main WebP basename or null on failure
 */
function lh_store_property_image(array $file, int $propertyId): ?string
{
    if ($propertyId <= 0) {
        return null;
    }

    if (!function_exists('imagewebp')) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $maxBytes = (int) lh_env('UPLOAD_MAX_IMAGE_BYTES', '5242880');
    if ($maxBytes < 10240) {
        $maxBytes = 5242880;
    }
    if (($file['size'] ?? 0) > $maxBytes) {
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $allowedMime = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];
    if (!isset($allowedMime[$mime])) {
        return null;
    }

    $sizeInfo = @getimagesize($tmp);
    if ($sizeInfo === false) {
        return null;
    }
    $type = (int) ($sizeInfo[2] ?? 0);
    if (
        $type !== IMAGETYPE_JPEG
        && $type !== IMAGETYPE_PNG
        && $type !== IMAGETYPE_WEBP
    ) {
        return null;
    }

    $pixelW = (int) ($sizeInfo[0] ?? 0);
    $pixelH = (int) ($sizeInfo[1] ?? 0);

    $dir = __DIR__ . '/../uploads/properties/' . $propertyId;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }
    if (!is_writable($dir)) {
        return null;
    }

    $stem = bin2hex(random_bytes(16));
    $mainName = $stem . '.webp';
    $thumbName = $stem . '_thumb.webp';
    $mainPath = $dir . DIRECTORY_SEPARATOR . $mainName;
    $thumbPath = $dir . DIRECTORY_SEPARATOR . $thumbName;

    // Fast path for pre-processed WebP from browser: keep main as-is, only build thumb.
    if (
        $type === IMAGETYPE_WEBP
        && $pixelW > 0
        && $pixelH > 0
        && $pixelW <= LH_PROPERTY_IMAGE_MAX_WIDTH
        && function_exists('imagecreatefromwebp')
    ) {
        if (!move_uploaded_file($tmp, $mainPath)) {
            return null;
        }

        $mainForThumb = imagecreatefromwebp($mainPath);
        if ($mainForThumb === false) {
            @unlink($mainPath);

            return null;
        }

        if (function_exists('imagepalettetotruecolor') && !imageistruecolor($mainForThumb)) {
            if (!imagepalettetotruecolor($mainForThumb)) {
                imagedestroy($mainForThumb);
                @unlink($mainPath);

                return null;
            }
        }

        imagealphablending($mainForThumb, false);
        imagesavealpha($mainForThumb, true);
        $thumbFast = lh_gd_make_thumb($mainForThumb, LH_PROPERTY_THUMB_MAX_WIDTH);
        imagedestroy($mainForThumb);
        if ($thumbFast === null) {
            @unlink($mainPath);

            return null;
        }

        imagealphablending($thumbFast, false);
        imagesavealpha($thumbFast, true);
        $okThumbFast = imagewebp($thumbFast, $thumbPath, LH_PROPERTY_THUMB_WEBP_QUALITY);
        imagedestroy($thumbFast);

        if (!$okThumbFast) {
            @unlink($mainPath);
            @unlink($thumbPath);

            return null;
        }

        return $mainName;
    }

    $src = lh_gd_load_uploaded_image($tmp, $type);
    if ($src === false) {
        return null;
    }

    if (function_exists('imagepalettetotruecolor') && !imageistruecolor($src)) {
        if (!imagepalettetotruecolor($src)) {
            imagedestroy($src);

            return null;
        }
    }

    $main = lh_gd_scale_max_width($src, LH_PROPERTY_IMAGE_MAX_WIDTH);
    if ($main === null) {
        return null;
    }

    $thumb = lh_gd_make_thumb($main, LH_PROPERTY_THUMB_MAX_WIDTH);
    if ($thumb === null) {
        imagedestroy($main);

        return null;
    }

    imagealphablending($main, false);
    imagesavealpha($main, true);
    $okMain = imagewebp($main, $mainPath, LH_PROPERTY_WEBP_QUALITY);
    imagedestroy($main);

    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $okThumb = imagewebp($thumb, $thumbPath, LH_PROPERTY_THUMB_WEBP_QUALITY);
    imagedestroy($thumb);

    if (!$okMain || !$okThumb) {
        if (is_file($mainPath)) {
            @unlink($mainPath);
        }
        if (is_file($thumbPath)) {
            @unlink($thumbPath);
        }

        return null;
    }

    return $mainName;
}

/**
 * Recursively delete a directory and all contents. Caller must pass a trusted path.
 */
function lh_remove_directory(string $path): void
{
    if ($path === '' || !is_dir($path)) {
        return;
    }
    $items = @scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $path . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            lh_remove_directory($full);
        } elseif (is_file($full)) {
            @unlink($full);
        }
    }
    @rmdir($path);
}

/**
 * Remove one gallery image from disk: main + thumb under uploads/properties/{id}, and legacy assets/img copy.
 */
function lh_delete_property_image_from_disk(int $propertyId, string $name): void
{
    $name = trim($name);
    if ($name === '' || strpbrk($name, "\\/") !== false) {
        return;
    }
    if ($propertyId > 0) {
        $dir = __DIR__ . '/../uploads/properties/' . $propertyId;
        $mainPath = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($mainPath)) {
            @unlink($mainPath);
        }
        $thumbName = lh_property_image_thumb_basename($name);
        if ($thumbName !== '') {
            $thumbPath = $dir . DIRECTORY_SEPARATOR . $thumbName;
            if (is_file($thumbPath)) {
                @unlink($thumbPath);
            }
        }
    }
    $legacyDir = realpath(__DIR__ . '/../assets/img');
    if ($legacyDir !== false) {
        $legacyPath = $legacyDir . DIRECTORY_SEPARATOR . $name;
        if (is_file($legacyPath)) {
            @unlink($legacyPath);
        }
    }
}
