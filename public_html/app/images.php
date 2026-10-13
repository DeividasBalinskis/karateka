<?php
// Nuotraukų įkėlimas: patikrinama, pasukama pagal EXIF, sumažinama ir išsaugoma kaip JPEG.
// Išsaugomi du failai: <vardas>.jpg (iki 1600 px) ir thumb_<vardas>.jpg (iki 640 px).

const IMAGE_MAX = 1600;
const THUMB_MAX = 640;
const IMAGE_MAX_BYTES = 15 * 1024 * 1024;

/** Grąžina failo vardą arba išmeta RuntimeException su klaida lietuviškai */
function save_uploaded_image(array $file, string $dir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Nepavyko įkelti failo' . ($file['error'] === UPLOAD_ERR_INI_SIZE ? ' - per didelis.' : '.'));
    }
    if ($file['size'] > IMAGE_MAX_BYTES) {
        throw new RuntimeException('Nuotrauka per didelė (daugiausia 15 MB).');
    }
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!$info || !isset($types[$info[2]])) {
        throw new RuntimeException('Netinkamas formatas: tinka JPG, PNG arba WEBP.');
    }
    if ($info[0] * $info[1] > 50000000) {
        throw new RuntimeException('Nuotraukos raiška per didelė.');
    }
    $src = @$types[$info[2]]($file['tmp_name']);
    if (!$src) {
        throw new RuntimeException('Nepavyko nuskaityti nuotraukos.');
    }

    // Telefonų nuotraukos dažnai būna pasuktos tik EXIF žyma
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($file['tmp_name']);
        $rotate = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rotate) {
            $src = imagerotate($src, $rotate, 0);
        }
    }

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Nepavyko sukurti aplanko nuotraukoms.');
    }
    $name = bin2hex(random_bytes(12)) . '.jpg';
    write_resized_jpeg($src, IMAGE_MAX, $dir . '/' . $name, 82);
    write_resized_jpeg($src, THUMB_MAX, $dir . '/thumb_' . $name, 78);
    imagedestroy($src);
    return $name;
}

function write_resized_jpeg($src, int $max, string $path, int $quality): void
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    // Permatomas PNG fonas -> baltas
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($dst, true);
    if (!imagejpeg($dst, $path, $quality)) {
        throw new RuntimeException('Nepavyko išsaugoti nuotraukos.');
    }
    imagedestroy($dst);
}

/** Pertvarko $_FILES['x'] su multiple į paprastą masyvų sąrašą */
function uploaded_files(string $field): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['name'])) {
        return $f && $f['error'] !== UPLOAD_ERR_NO_FILE ? [$f] : [];
    }
    $out = [];
    foreach ($f['name'] as $i => $_) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}
