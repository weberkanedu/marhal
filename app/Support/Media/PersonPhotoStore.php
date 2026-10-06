<?php

namespace App\Support\Media;

use App\Models\Person;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Yolcu fotoğrafları. Dosyalar herkese açık değildir; yalnızca yetkili kullanıcıya
 * PersonController@photo üzerinden verilir. Disk config('marhal.media_disk') ile
 * değiştirilebilir (ileride S3 uyumlu depolama).
 */
class PersonPhotoStore
{
    public function store(Person $person, UploadedFile $file): void
    {
        $this->delete($person);

        $path = $file->storeAs(
            "tenants/{$person->tenant_id}/persons",
            Str::uuid().'.'.$file->extension(),
            ['disk' => config('marhal.media_disk')],
        );

        $person->forceFill(['photo_path' => $path ?: null])->save();
    }

    public function delete(Person $person): void
    {
        if ($person->photo_path) {
            $this->disk()->delete($person->photo_path);
            $person->forceFill(['photo_path' => null])->save();
        }
    }

    public function response(Person $person): ?StreamedResponse
    {
        if (! $person->photo_path || ! $this->disk()->exists($person->photo_path)) {
            return null;
        }

        return $this->disk()->response($person->photo_path, headers: [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * PDF'e gömmek için küçültülmüş JPEG (data URI). Yaka kartı gibi toplu çıktılarda dosya boyutu
     * makul kalsın diye fotoğraf en fazla $maxSize piksele indirilir. Fotoğraf yoksa / okunamazsa null.
     */
    public function dataUri(Person $person, int $maxSize = 320): ?string
    {
        if (! $person->photo_path || ! $this->disk()->exists($person->photo_path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) $this->disk()->get($person->photo_path));

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSize / max($width, $height));

        if ($scale < 1) {
            $resized = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
            imagedestroy($image);

            if ($resized === false) {
                return null;
            }

            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, 82);
        imagedestroy($image);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }

    /**
     * Yaka kartı için ortadan kare kesilmiş, daire biçimli (köşeleri saydam) PNG; dompdf resmi
     * kendisi yuvarlak kesemediği için daire burada hazırlanır.
     */
    public function circleDataUri(Person $person, int $size = 240): ?string
    {
        if (! $person->photo_path || ! $this->disk()->exists($person->photo_path)) {
            return null;
        }

        $source = @imagecreatefromstring((string) $this->disk()->get($person->photo_path));

        if ($source === false) {
            return null;
        }

        $side = min(imagesx($source), imagesy($source));
        $size = max(1, $size);
        $square = imagecreatetruecolor($size, $size);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagefill($square, 0, 0, (int) imagecolorallocatealpha($square, 0, 0, 0, 127));
        imagecopyresampled(
            $square, $source, 0, 0,
            intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2),
            $size, $size, $side, $side,
        );
        imagedestroy($source);

        // Dairenin dışını saydam yap.
        $transparent = (int) imagecolorallocatealpha($square, 0, 0, 0, 127);
        $r = $size / 2;
        for ($x = 0; $x < $size; $x++) {
            for ($y = 0; $y < $size; $y++) {
                if ((($x - $r + 0.5) ** 2) + (($y - $r + 0.5) ** 2) > $r ** 2) {
                    imagesetpixel($square, $x, $y, $transparent);
                }
            }
        }

        ob_start();
        imagepng($square);
        imagedestroy($square);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('marhal.media_disk'));
    }
}
