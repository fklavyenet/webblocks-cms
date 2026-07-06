<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class AdminTranslationAuditCommand extends Command
{
  protected $signature = 'webblocks:admin-translation-audit
    {--locale=de : Locale to audit against}
    {--limit=25 : Number of files or phrases to show}
    {--strict : Return a failure exit code when any admin Blade UI phrase is not covered}
    {--baseline= : JSON file of accepted existing missing admin UI phrase records}
    {--json : Output the audit as JSON}';

  protected $description = 'Audit hard-coded admin Blade UI copy against the admin translation contract';

  private const IGNORE_EXACT = [
    '-',
    '/',
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
    'WebBlocks CMS',
  ];

  public function handle(): int
  {
    $locale = (string) $this->option('locale');
    $limit = max(1, (int) $this->option('limit'));
    $phrases = $this->htmlPhrases($locale);
    $files = $this->auditFiles($phrases);
    $summary = $this->summary($files);
    $baselinePath = (string) $this->option('baseline');

    try {
      $baselineRecords = $baselinePath !== ''
        ? $this->baselineRecords($baselinePath)
        : null;
    } catch (\RuntimeException $exception) {
      $this->error($exception->getMessage());

      return self::FAILURE;
    }

    $newMissing = $baselineRecords === null
      ? $this->missingRecords($files)
      : $this->newMissingRecords($files, $baselineRecords);

    $summary['new_missing'] = count($newMissing);

    if ($this->option('json')) {
      $this->line(json_encode([
        'locale' => $locale,
        'summary' => $summary,
        'baseline' => $baselinePath !== '' ? $baselinePath : null,
        'new_missing' => $newMissing,
        'files' => $files,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

      return $this->strictExitCode($summary, $baselineRecords !== null);
    }

    $this->info('Admin translation audit for locale ['.$locale.']');
    $this->line('Files: '.$summary['files']);
    $this->line('Phrases: '.$summary['phrases']);
    $this->line('Covered by admin.html fallback: '.$summary['covered']);
    $this->line('Missing: '.$summary['missing']);
    $this->line('Coverage: '.$summary['coverage'].'%');

    if ($baselineRecords !== null) {
      $this->line('New missing outside baseline: '.$summary['new_missing']);
    }

    $this->newLine();

    $this->table(
      ['Coverage', 'Missing', 'Phrases', 'File'],
      collect($files)
        ->sortBy([['coverage', 'asc'], ['missing_count', 'desc']])
        ->take($limit)
        ->map(fn (array $file): array => [
          $file['coverage'].'%',
          $file['missing_count'],
          $file['phrase_count'],
          $file['file'],
        ])
        ->values()
        ->all(),
    );

    $missing = collect($files)
      ->flatMap(fn (array $file): array => $file['missing'])
      ->countBy()
      ->sortDesc()
      ->take($limit);

    if ($missing->isNotEmpty()) {
      $this->newLine();
      $this->line('Most common missing phrases:');

      foreach ($missing as $phrase => $count) {
        $this->line('['.$count.'] '.$phrase);
      }
    }

    if ($baselineRecords !== null && count($newMissing) > 0) {
      $this->newLine();
      $this->line('New missing phrases outside baseline:');

      foreach (array_slice($newMissing, 0, $limit) as $record) {
        $this->line($record['file'].': '.$record['phrase']);
      }
    }

    if ($this->strictExitCode($summary, $baselineRecords !== null) === self::FAILURE) {
      $this->newLine();
      $this->error($baselineRecords !== null
        ? 'Strict admin translation audit failed: '.$summary['new_missing'].' new UI phrases are not covered by structured translations or the accepted baseline.'
        : 'Strict admin translation audit failed: '.$summary['missing'].' UI phrases are not covered by structured translations or the reviewed fallback map.');

      return self::FAILURE;
    }

    return self::SUCCESS;
  }

  private function htmlPhrases(string $locale): array
  {
    $phrases = trans(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::admin.html', [], $locale);

    if (! is_array($phrases)) {
      return [];
    }

    return collect($phrases)
      ->filter(fn ($value, $key): bool => is_string($key) && is_string($value) && $key !== '' && $value !== '')
      ->keys()
      ->values()
      ->all();
  }

  private function auditFiles(array $phrases): array
  {
    $basePath = dirname(__DIR__, 2).'/resources/views';
    $phraseLookup = array_fill_keys($phrases, true);

    return collect($this->adminViewFiles($basePath))
      ->map(function (string $file) use ($basePath, $phraseLookup): ?array {
        $path = $basePath.'/'.$file;

        if (! is_file($path)) {
          return null;
        }

        $candidates = $this->candidatePhrases((string) file_get_contents($path));
        $covered = collect($candidates)
          ->filter(fn (string $phrase): bool => isset($phraseLookup[$phrase]))
          ->values()
          ->all();
        $missing = array_values(array_diff($candidates, $covered));
        $phraseCount = count($candidates);
        $coveredCount = count($covered);

        return [
          'file' => $file,
          'phrase_count' => $phraseCount,
          'covered_count' => $coveredCount,
          'missing_count' => count($missing),
          'coverage' => $phraseCount === 0 ? 100 : round(($coveredCount / $phraseCount) * 100, 1),
          'missing' => $missing,
        ];
      })
      ->filter()
      ->values()
      ->all();
  }

  private function adminViewFiles(string $basePath): array
  {
    $files = ['layouts/admin.blade.php'];
    $adminPath = $basePath.'/admin';

    if (is_dir($adminPath)) {
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($adminPath, \FilesystemIterator::SKIP_DOTS),
      );

      foreach ($iterator as $file) {
        if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
          continue;
        }

        $path = $file->getPathname();

        if (! str_ends_with($path, '.blade.php')) {
          continue;
        }

        $files[] = str_replace($basePath.'/', '', $path);
      }
    }

    return collect($files)
      ->merge(WebBlocksCmsServiceProvider::ADMIN_RUNTIME_VIEW_FILES)
      ->unique()
      ->sort()
      ->values()
      ->all();
  }

  private function summary(array $files): array
  {
    $phraseCount = array_sum(array_column($files, 'phrase_count'));
    $coveredCount = array_sum(array_column($files, 'covered_count'));
    $missingCount = array_sum(array_column($files, 'missing_count'));

    return [
      'files' => count($files),
      'phrases' => $phraseCount,
      'covered' => $coveredCount,
      'missing' => $missingCount,
      'coverage' => $phraseCount === 0 ? 100 : round(($coveredCount / $phraseCount) * 100, 1),
    ];
  }

  private function strictExitCode(array $summary, bool $usesBaseline): int
  {
    $missing = $usesBaseline ? $summary['new_missing'] : $summary['missing'];

    return $this->option('strict') && $missing > 0
      ? self::FAILURE
      : self::SUCCESS;
  }

  private function baselineRecords(string $path): array
  {
    $resolvedPath = str_starts_with($path, '/')
      ? $path
      : base_path($path);

    if (! is_file($resolvedPath)) {
      throw new \RuntimeException('Admin translation audit baseline was not found: '.$path);
    }

    $decoded = json_decode((string) file_get_contents($resolvedPath), true);

    if (! is_array($decoded) || ! isset($decoded['accepted_missing']) || ! is_array($decoded['accepted_missing'])) {
      throw new \RuntimeException('Admin translation audit baseline must contain an accepted_missing array: '.$path);
    }

    return collect($decoded['accepted_missing'])
      ->filter(fn ($record): bool => is_array($record) && is_string($record['file'] ?? null) && is_string($record['phrase'] ?? null))
      ->mapWithKeys(fn (array $record): array => [$this->recordKey($record['file'], $record['phrase']) => true])
      ->all();
  }

  private function missingRecords(array $files): array
  {
    return collect($files)
      ->flatMap(fn (array $file): array => collect($file['missing'])
        ->map(fn (string $phrase): array => [
          'file' => $file['file'],
          'phrase' => $phrase,
        ])
        ->all())
      ->values()
      ->all();
  }

  private function newMissingRecords(array $files, array $baselineRecords): array
  {
    return collect($this->missingRecords($files))
      ->reject(fn (array $record): bool => isset($baselineRecords[$this->recordKey($record['file'], $record['phrase'])]))
      ->values()
      ->all();
  }

  private function recordKey(string $file, string $phrase): string
  {
    return $file."\n".$phrase;
  }

  private function candidatePhrases(string $contents): array
  {
    $contents = preg_replace('/<script\b.*?<\/script>/is', '', $contents) ?? $contents;
    $contents = preg_replace('/<style\b.*?<\/style>/is', '', $contents) ?? $contents;

    $phrases = [];

    preg_match_all('/>([^<]*[A-Z][^<]*)</', $contents, $textMatches);

    foreach ($textMatches[1] ?? [] as $value) {
      $this->pushPhrase($phrases, $value);
    }

    preg_match_all('/\b(?:title|heading|description|label|placeholder|aria-label|delete-label|submit-label|cancel-label|data-[\w-]+)="([^"]*[A-Z][^"]*)"/', $contents, $attributeMatches);

    foreach ($attributeMatches[1] ?? [] as $value) {
      $this->pushPhrase($phrases, $value);
    }

    preg_match_all('/=>\s*[\'"]([^\'"]*[A-Z][^\'"]*)[\'"]/', $contents, $arrayMatches);

    foreach ($arrayMatches[1] ?? [] as $value) {
      $this->pushPhrase($phrases, $value);
    }

    preg_match_all('/[\'"]([^\'"]*[A-Z][^\'"]*)[\'"]\s*=>/', $contents, $keyMatches);

    foreach ($keyMatches[1] ?? [] as $value) {
      $this->pushPhrase($phrases, $value);
    }

    return collect($phrases)
      ->unique()
      ->sort()
      ->values()
      ->all();
  }

  private function pushPhrase(array &$phrases, string $value): void
  {
    $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    if (! $this->isPhraseCandidate($value)) {
      return;
    }

    $phrases[] = $value;
  }

  private function isPhraseCandidate(string $value): bool
  {
    if ($value === '' || in_array($value, self::IGNORE_EXACT, true)) {
      return false;
    }

    if (Str::startsWith($value, ['@', ':', '<'])) {
      return false;
    }

    if (Str::contains($value, ['{{', '}}', '$', '::', '->', '=>', '))>', 'route(', 'asset(', 'admin.', 'webblocks-cms::', '@csrf', '@method'])) {
      return false;
    }

    if (preg_match('/^[a-z][A-Za-z0-9_]*$/', $value) === 1) {
      return false;
    }

    if (preg_match('/[a-z][A-Z]/', $value) === 1) {
      return false;
    }

    if (preg_match('/^[A-Z0-9_\/.-]+$/', $value) === 1) {
      return false;
    }

    if (preg_match('/^(wb-|cms\/|admin\/|data-|aria-|#|\.|https?:)/', $value) === 1) {
      return false;
    }

    if (preg_match('/[A-Za-z]/', $value) !== 1) {
      return false;
    }

    return Str::length($value) <= 180;
  }
}
