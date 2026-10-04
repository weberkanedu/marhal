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

    private function disk(): Filesystem
    {
        return Storage::disk(config('marhal.media_disk'));
    }
}
