<?php

namespace App\Support\Release;

class ChecksumFactory
{
    public function sha256(string $absolutePath): string
    {
        $checksum = hash_file('sha256', $absolutePath);

        if (! is_string($checksum) || $checksum === '') {
            throw new ReleaseException('Unable to generate SHA-256 checksum for the release package.');
        }

        return $checksum;
    }
}
