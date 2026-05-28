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
    return $this->message ?? match ($this->status) {
      self::STATUS_MISSING => 'Backup file not found.',
      self::STATUS_UNREADABLE => 'Backup file is not readable.',
      self::STATUS_UNSAFE => 'Backup archive path is invalid.',
      default => 'Backup archive is unavailable.',
    };
  }

  public function uiLabel(): string
  {
    return match ($this->status) {
      self::STATUS_AVAILABLE => 'Archive ready',
      self::STATUS_MISSING => 'File missing',
      self::STATUS_UNREADABLE => 'File unreadable',
      self::STATUS_UNSAFE => 'Unsafe path',
      default => 'Archive unavailable',
    };
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
