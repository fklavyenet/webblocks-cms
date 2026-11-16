<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class SqlDumpContentValidator
{
  public function assertValidFile(string $path, ?string $label = null): void
  {
    $label ??= __('webblocks-cms::admin-feedback.backup_archive.sql_file');

    if (! File::isFile($path)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.sql_missing', ['label' => $label]));
    }

    $stream = fopen($path, 'rb');

    if ($stream === false) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.sql_open', ['label' => $label]));
    }

    try {
      $this->assertValidStream($stream, $label);
    } finally {
      fclose($stream);
    }
  }

  public function assertValidArchiveEntry(ZipArchive $archive, string $entryPath): void
  {
    $stream = $archive->getStream($entryPath);

    if ($stream === false) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.entry_missing', ['entry' => $entryPath]));
    }

    try {
      $this->assertValidStream($stream, __('webblocks-cms::admin-feedback.backup_archive.sql_archive_entry', ['entry' => $entryPath]));
    } finally {
      fclose($stream);
    }
  }

  private function assertValidStream($stream, string $label): void
  {
    $lineNumber = 0;
    $sawContent = false;
    $sawSqlStatement = false;
    $insideBlockComment = false;
    $insideSqlStatement = false;

    while (($line = fgets($stream)) !== false) {
      $lineNumber++;

      if ($lineNumber === 1) {
        $line = $this->stripUtf8Bom($line);
      }

      $result = $this->inspectLine($line, $insideBlockComment, $insideSqlStatement, $label);
      $insideBlockComment = $result['inside_block_comment'];
      $insideSqlStatement = $result['inside_sql_statement'];
      $sawContent = $sawContent || $result['saw_content'];
      $sawSqlStatement = $sawSqlStatement || $result['saw_sql_statement'];
    }

    if (! $sawContent) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.sql_empty', ['label' => $label]));
    }

    if (! $sawSqlStatement) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.sql_invalid', ['label' => $label]));
    }
  }

  private function inspectLine(string $line, bool $insideBlockComment, bool $insideSqlStatement, string $label): array
  {
    $trimmed = trim($line);

    if ($trimmed === '') {
      return [
        'inside_block_comment' => $insideBlockComment,
        'inside_sql_statement' => $insideSqlStatement,
        'saw_content' => false,
        'saw_sql_statement' => false,
      ];
    }

    if ($insideBlockComment) {
      return [
        'inside_block_comment' => ! str_contains($trimmed, '*/'),
        'inside_sql_statement' => $insideSqlStatement,
        'saw_content' => true,
        'saw_sql_statement' => false,
      ];
    }

    if ($this->isCommentOnlyLine($trimmed)) {
      return [
        'inside_block_comment' => str_starts_with($trimmed, '/*') && ! str_contains($trimmed, '*/'),
        'inside_sql_statement' => $insideSqlStatement,
        'saw_content' => true,
        'saw_sql_statement' => false,
      ];
    }

    if ($insideSqlStatement) {
      return [
        'inside_block_comment' => false,
        'inside_sql_statement' => ! $this->endsStatement($trimmed),
        'saw_content' => true,
        'saw_sql_statement' => true,
      ];
    }

    if ($this->startsWithSqlStatement($trimmed)) {
      return [
        'inside_block_comment' => false,
        'inside_sql_statement' => ! $this->endsStatement($trimmed),
        'saw_content' => true,
        'saw_sql_statement' => true,
      ];
    }

    if ($this->containsCommandOutput($trimmed)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.sql_command_output', ['label' => $label]));
    }

    throw new RuntimeException(__('webblocks-cms::admin-feedback.backup_archive.sql_invalid', ['label' => $label]));
  }

  private function containsCommandOutput(string $line): bool
  {
    if (preg_match('/\bYou executed\b/i', $line) === 1) {
      return true;
    }

    if (preg_match('/^\s*(?:[$>]\s*)?(?:mysqldump|mariadb-dump)\b/i', $line) === 1) {
      return true;
    }

    if (preg_match('/^\s*(?:[$>]\s*)?(?:mysql|mariadb)>/i', $line) === 1) {
      return true;
    }

    return false;
  }

  private function isCommentOnlyLine(string $line): bool
  {
    if (str_starts_with($line, '--') || str_starts_with($line, '#')) {
      return true;
    }

    if (str_starts_with($line, '/*') && ! $this->startsWithSqlStatement($line)) {
      return true;
    }

    return false;
  }

  private function startsWithSqlStatement(string $line): bool
  {
    return preg_match('/^(?:\/\*![0-9]*|CREATE\b|INSERT\b|DROP\b|SET\b|LOCK\s+TABLES\b|UNLOCK\s+TABLES\b|PRAGMA\b|BEGIN(?:\s+TRANSACTION)?\b|COMMIT\b|ROLLBACK\b|START\s+TRANSACTION\b|ALTER\b|USE\b|REPLACE\b|DELETE\b|TRUNCATE\b|ANALYZE\b|VACUUM\b|ATTACH\b|DETACH\b|DELIMITER\b|SELECT\b)/i', $line) === 1;
  }

  private function endsStatement(string $line): bool
  {
    return str_ends_with(rtrim($line), ';');
  }

  private function stripUtf8Bom(string $line): string
  {
    return preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
  }
}
