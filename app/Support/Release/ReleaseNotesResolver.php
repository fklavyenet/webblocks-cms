<?php

namespace App\Support\Release;

use Illuminate\Support\Facades\File;

class ReleaseNotesResolver
{
    public function resolve(?string $inlineNotes, ?string $notesFile): ?string
    {
        if (is_string($inlineNotes) && $inlineNotes !== '') {
            return $inlineNotes;
        }

        if ($notesFile === null || trim($notesFile) === '') {
            return null;
        }

        $absolutePath = base_path($notesFile);

        if (! File::exists($absolutePath) || ! File::isFile($absolutePath)) {
            throw new ReleaseException('Release notes file was not found: '.$notesFile);
        }

        return trim((string) File::get($absolutePath));
    }
}
