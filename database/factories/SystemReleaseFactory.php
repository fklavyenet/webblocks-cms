<?php

namespace Database\Factories;

use App\Models\SystemRelease;
use App\Support\System\Updates\SemanticVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemRelease>
 */
class SystemReleaseFactory extends Factory
{
    protected $model = SystemRelease::class;

    public function definition(): array
    {
        $version = fake()->randomElement(['0.1.0', '0.1.1', '0.2.0', '1.0.0']);

        return [
            'product' => 'webblocks-cms',
            'channel' => 'stable',
            'version' => $version,
            'version_normalized' => SemanticVersion::parse($version)->normalized(),
            'release_name' => 'WebBlocks CMS '.$version,
            'description' => fake()->sentence(),
            'changelog' => fake()->paragraph(),
            'download_url' => 'https://updates.webblocksui.com/downloads/webblocks-cms-'.$version.'.zip',
            'checksum_sha256' => hash('sha256', $version),
            'is_critical' => false,
            'is_security' => false,
            'published_at' => now()->subHour(),
            'supported_from_version' => null,
            'supported_until_version' => null,
            'min_php_version' => '8.3.0',
            'min_laravel_version' => '13.0.0',
            'metadata' => null,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn (): array => [
            'published_at' => now()->addDay(),
        ]);
    }
}
