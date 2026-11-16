<?php

namespace WebBlocks\Cms\Support\System;

final class SystemBackupArchiveResolution
{
  public const STATUS_AVAILABLE = 'available';

  public const STATUS_MISSING = 'missing';

  public const STATUS_UNREADABLE = 'unreadable';

  public const STATUS_UNSAFE = 'unsafe';

  public const STATUS_UNAVAILABLE = 'unavailable';

  public function __construct(
    public readonly string $status,
    public readonly ?string $absolutePath = null,
    public readonly ?string $relativePath = null,
    public readonly ?string $message = null,
  ) {}

  public function isAvailable(): bool
  {
    return $this->status === self::STATUS_AVAILABLE && $this->absolutePath !== null;
  }

  public function isMissing(): bool
  {
    return $this->status === self::STATUS_MISSING;
  }

  public function isUnreadable(): bool
  {
    return $this->status === self::STATUS_UNREADABLE;
  }

  public function isUnsafe(): bool
  {
    return $this->status === self::STATUS_UNSAFE;
  }

  public function feedbackMessage(): string
  {
    $status = match ($this->status) {
      self::STATUS_MISSING, self::STATUS_UNREADABLE, self::STATUS_UNSAFE => $this->status,
      default => self::STATUS_UNAVAILABLE,
    };

    return $this->message ?? __('webblocks-cms::admin.backups.archive_feedback.'.$status);
  }

  public function uiLabel(): string
  {
    $status = match ($this->status) {
      self::STATUS_AVAILABLE, self::STATUS_MISSING, self::STATUS_UNREADABLE, self::STATUS_UNSAFE => $this->status,
      default => self::STATUS_UNAVAILABLE,
    };

    return __('webblocks-cms::admin.backups.archive_statuses.'.$status);
  }

  public function uiBadgeClass(): string
  {
    return match ($this->status) {
      self::STATUS_AVAILABLE => 'wb-status-active',
      self::STATUS_MISSING, self::STATUS_UNREADABLE => 'wb-status-warning',
      self::STATUS_UNSAFE => 'wb-status-danger',
      default => 'wb-status-muted',
    };
  }
}
