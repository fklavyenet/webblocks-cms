<?php

namespace WebBlocks\Cms\Support\SitePromotion;

use InvalidArgumentException;

class SitePromotionOptions
{
  public const STRATEGY_ADDITIVE_UPDATE = 'additive_update';

  public const STRATEGY_MIRROR = 'mirror';

  public function __construct(
    public readonly int $targetSiteId,
    public readonly string $strategy,
    public readonly bool $applyAssets,
  ) {}

  public static function fromArray(array $data): self
  {
    $targetSiteId = (int) ($data['target_site_id'] ?? 0);

    if ($targetSiteId < 1) {
      throw new InvalidArgumentException('Target site is required.');
    }

    $strategy = self::normalizeStrategy($data['strategy'] ?? null);

    return new self(
      targetSiteId: $targetSiteId,
      strategy: $strategy,
      applyAssets: (bool) ($data['apply_assets'] ?? false),
    );
  }

  public static function strategies(): array
  {
    return [self::STRATEGY_ADDITIVE_UPDATE, self::STRATEGY_MIRROR];
  }

  public static function normalizeStrategy(mixed $strategy): string
  {
    $normalized = trim((string) $strategy);

    if (! in_array($normalized, self::strategies(), true)) {
      return self::STRATEGY_ADDITIVE_UPDATE;
    }

    return $normalized;
  }

  public function isMirror(): bool
  {
    return $this->strategy === self::STRATEGY_MIRROR;
  }

  public function toArray(): array
  {
    return [
      'target_site_id' => $this->targetSiteId,
      'strategy' => $this->strategy,
      'apply_assets' => $this->applyAssets,
    ];
  }
}
