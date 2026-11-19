<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PatientProfilePhoto
{
    public const RULES = ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png', 'dimensions:max_width=4096,max_height=4096'];

    public static function stage(Request $request): void
    {
        $upload = $request->file('profile_photo');
        if (! $upload instanceof UploadedFile || $request->session()->has('intake.patient_id')) {
            return;
        }

        $contents = $upload->get();
        $source = is_string($contents) && function_exists('imagecreatefromstring') ? @imagecreatefromstring($contents) : false;
        if (! $source) {
            throw ValidationException::withMessages(['profile_photo' => 'We could not read this photo. Choose a JPEG or PNG, or continue without a photo.']);
        }

        $side = min(imagesx($source), imagesy($source));
        $thumb = imagecreatetruecolor(192, 192);
        imagefill($thumb, 0, 0, 0xFFFFFF);
        imagecopyresampled($thumb, $source, 0, 0, intdiv(imagesx($source) - $side, 2), intdiv(imagesy($source) - $side, 2), 192, 192, $side, $side);
        ob_start();
        imagejpeg($thumb, null, 85);
        $bytes = ob_get_clean();
        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages(['profile_photo' => 'We could not prepare this photo. Choose another image or continue without a photo.']);
        }

        $key = 'intake-photo:'.Str::uuid();
        Cache::put($key, Crypt::encryptString(base64_encode($bytes)), now()->addMinutes((int) config('session.lifetime')));
        $request->session()->put('intake.photo_key', $key);
    }
}
