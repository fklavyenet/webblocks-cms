<?php

namespace App\Support\System\Updates;

use InvalidArgumentException;

class SemanticVersion
{
    private function __construct(
        public readonly string $value,
        public readonly int $major,
        public readonly int $minor,
        public readonly int $patch,
        public readonly string $preRelease,
        public readonly string $build,
    ) {}

    public static function parse(string $version): self
    {
        $version = trim($version);

        if (! preg_match('/^(\d+)\.(\d+)\.(\d+)(?:\-([0-9A-Za-z\.-]+))?(?:\+([0-9A-Za-z\.-]+))?$/', $version, $matches)) {
            throw new InvalidArgumentException('Invalid semantic version: '.$version);
        }

        return new self(
            value: $version,
            major: (int) $matches[1],
            minor: (int) $matches[2],
            patch: (int) $matches[3],
            preRelease: $matches[4] ?? '',
            build: $matches[5] ?? '',
        );
    }

    public function normalized(): string
    {
        return sprintf('%08d.%08d.%08d.%s', $this->major, $this->minor, $this->patch, $this->preReleaseWeight());
    }

    public function compare(self $other): int
    {
        foreach (['major', 'minor', 'patch'] as $part) {
            $comparison = $this->{$part} <=> $other->{$part};

            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $this->comparePreRelease($other);
    }

    private function comparePreRelease(self $other): int
    {
        if ($this->preRelease === '' && $other->preRelease === '') {
            return 0;
        }

        if ($this->preRelease === '') {
            return 1;
        }

        if ($other->preRelease === '') {
            return -1;
        }

        $left = explode('.', $this->preRelease);
        $right = explode('.', $other->preRelease);
        $length = max(count($left), count($right));

        for ($index = 0; $index < $length; $index++) {
            $leftIdentifier = $left[$index] ?? null;
            $rightIdentifier = $right[$index] ?? null;

            if ($leftIdentifier === null) {
                return -1;
            }

            if ($rightIdentifier === null) {
                return 1;
            }

            $leftNumeric = ctype_digit($leftIdentifier);
            $rightNumeric = ctype_digit($rightIdentifier);

            if ($leftNumeric && $rightNumeric) {
                $comparison = ((int) $leftIdentifier) <=> ((int) $rightIdentifier);
            } elseif ($leftNumeric) {
                $comparison = -1;
            } elseif ($rightNumeric) {
                $comparison = 1;
            } else {
                $comparison = strcmp($leftIdentifier, $rightIdentifier);
            }

            if ($comparison !== 0) {
                return $comparison < 0 ? -1 : 1;
            }
        }

        return 0;
    }

    private function preReleaseWeight(): string
    {
        if ($this->preRelease === '') {
            return 'zzzzzzzz';
        }

        return strtolower($this->preRelease);
    }
}
