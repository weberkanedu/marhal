<?php

/**
 * Marhal işareti "Tavaf"tan raster simgeleri üretir (public/favicon.svg ile aynı çizim):
 *   public/apple-touch-icon.png (180 px) ve public/favicon.ico (16, 32, 48 px; ICO içinde PNG).
 * Çalıştırma (konteynerde): php scripts/make-icons.php
 * Yalnız GD kullanır; kenarlar yumuşak olsun diye 8 kat büyük çizilip küçültülür.
 */
const BG = [0x0B, 0x2F, 0x24];
const GOLD = [0xD9, 0xB4, 0x5F];
const INK = [0xE8, 0xDC, 0xC0];

/** 48 birimlik çizim (favicon.svg ile aynı ölçüler). */
const DOTS = [[24, 6.8, 3.2], [36.2, 11.8, 2.9], [41.2, 24, 2.6], [36.2, 36.2, 2.3], [24, 41.2, 2.1], [11.8, 36.2, 1.9], [6.8, 24, 1.7]];

function draw(int $size, bool $rounded): GdImage
{
    $k = 8;
    $big = $size * $k;
    $u = $big / 48;
    $img = imagecreatetruecolor($big, $big);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));

    $bg = imagecolorallocate($img, ...BG);
    $gold = imagecolorallocate($img, ...GOLD);
    $ink = imagecolorallocate($img, ...INK);

    // Zemin: köşeleri yuvarlatılmış kare (telefon simgesinde köşeyi sistem keser, düz kare).
    $r = $rounded ? (int) round(11 * $u) : 0;
    imagefilledrectangle($img, $r, 0, $big - 1 - $r, $big - 1, $bg);
    imagefilledrectangle($img, 0, $r, $big - 1, $big - 1 - $r, $bg);
    if ($r > 0) {
        foreach ([[$r, $r], [$big - 1 - $r, $r], [$r, $big - 1 - $r], [$big - 1 - $r, $big - 1 - $r]] as [$cx, $cy]) {
            imagefilledellipse($img, $cx, $cy, 2 * $r, 2 * $r, $bg);
        }
    }

    // Kare ve kuşak.
    $p = fn (float $v) => (int) round($v * $u);
    imagefilledrectangle($img, $p(16.5), $p(16.5), $p(31.5), $p(31.5), $gold);
    imagefilledrectangle($img, $p(16.5), $p(20.4), $p(31.5), $p(22.3), $bg);

    foreach (DOTS as [$x, $y, $rad]) {
        imagefilledellipse($img, $p($x), $p($y), $p(2 * $rad), $p(2 * $rad), $ink);
    }

    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $big, $big);

    return $out;
}

function png(GdImage $img): string
{
    ob_start();
    imagepng($img, null, 9);

    return (string) ob_get_clean();
}

$public = dirname(__DIR__).'/public';

file_put_contents("{$public}/apple-touch-icon.png", png(draw(180, false)));

// ICO: başlık + her boy için dizin girdisi + PNG verileri.
$sizes = [16, 32, 48];
$pngs = array_map(fn (int $s) => png(draw($s, true)), $sizes);
$ico = pack('vvv', 0, 1, count($sizes));
$offset = 6 + 16 * count($sizes);
foreach ($sizes as $i => $s) {
    $ico .= pack('CCCCvvVV', $s % 256, $s % 256, 0, 0, 1, 32, strlen($pngs[$i]), $offset);
    $offset += strlen($pngs[$i]);
}
file_put_contents("{$public}/favicon.ico", $ico.implode('', $pngs));

echo "apple-touch-icon.png ve favicon.ico üretildi.\n";
