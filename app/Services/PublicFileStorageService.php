<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class PublicFileStorageService
{
    public function store(UploadedFile $file, string $directory): string
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Uploaded file is not valid.');
        }

        return $file->store($directory, 'public');
    }

    public function storeAs(UploadedFile $file, string $directory, string $filename): string
    {
        if (! $file->isValid()) {
            throw new InvalidArgumentException('Uploaded file is not valid.');
        }

        return $file->storeAs($directory, $filename, 'public');
    }

    public function url(string $path): string
    {
        return Storage::url($path);
    }

    public function deleteMany(array $paths): void
    {
        foreach (array_filter($paths) as $path) {
            Storage::disk('public')->delete($path);
        }
    }
}
