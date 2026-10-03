<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores evidence files on the private disk under a random name. The original file name is kept
 * only as display metadata, so a crafted name can never influence the storage path.
 */
class AttachmentStore
{
    public const DISK = 'local';
    public const DIRECTORY = 'attachments';

    /** Allowed by content sniffing (finfo), not by the client-supplied extension. */
    public const ALLOWED_MIME_TYPES = ['application/pdf', 'image/png', 'image/jpeg'];
    public const MAX_KILOBYTES = 5120;
    public const MAX_FILES = 3;

    public function store(UploadedFile $file, Model $attachable, User $uploader): Attachment
    {
        $path = $file->storeAs(self::DIRECTORY, Str::uuid().'.'.$file->guessExtension(), self::DISK);

        $attachment = new Attachment();
        $attachment->attachable()->associate($attachable);
        $attachment->original_name = $this->safeName($file->getClientOriginalName());
        $attachment->stored_path = $path;
        $attachment->mime_type = $file->getMimeType();
        $attachment->size = $file->getSize();
        $attachment->checksum = hash_file('sha256', Storage::disk(self::DISK)->path($path));
        $attachment->uploaded_by = $uploader->id;
        $attachment->save();

        return $attachment;
    }

    public function absolutePath(Attachment $attachment): string
    {
        return Storage::disk(self::DISK)->path($attachment->stored_path);
    }

    /** Strip directories and control characters from a user-supplied file name. */
    private function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', basename($name)) ?? 'berkas';

        return Str::limit($name !== '' ? $name : 'berkas', 200, '');
    }
}
