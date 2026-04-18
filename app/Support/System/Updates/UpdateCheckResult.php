<?php

namespace App\Support\System\Updates;

use Carbon\CarbonImmutable;

class UpdateCheckResult
{
    public function __construct(
        public readonly string $state,
        public readonly string $label,
        public readonly string $message,
        public readonly string $badgeClass,
        public readonly bool $serverReachable,
        public readonly ?string $apiVersion,
        public readonly string $serverUrl,
        public readonly string $product,
        public readonly string $channel,
        public readonly string $installedVersion,
        public readonly ?string $latestVersion,
        public readonly bool $updateAvailable,
        public readonly array $compatibility,
        public readonly ?array $release,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly CarbonImmutable $checkedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'state' => $this->state,
            'label' => $this->label,
            'message' => $this->message,
            'badge_class' => $this->badgeClass,
            'server_reachable' => $this->serverReachable,
            'api_version' => $this->apiVersion,
            'server_url' => $this->serverUrl,
            'product' => $this->product,
            'channel' => $this->channel,
            'installed_version' => $this->installedVersion,
            'latest_version' => $this->latestVersion,
            'update_available' => $this->updateAvailable,
            'compatibility' => $this->compatibility,
            'release' => $this->release,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'checked_at' => $this->checkedAt,
        ];
    }
}
