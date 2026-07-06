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
    {--json : Output the audit as JSON}';

  protected $description = 'Audit hard-coded admin Blade UI copy against the admin HTML localization fallback map';

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

    if ($this->option('json')) {
      $this->line(json_encode([
        'locale' => $locale,
        'summary' => $summary,
        'files' => $files,
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

      return self::SUCCESS;
    }

    $this->info('Admin translation audit for locale ['.$locale.']');
    $this->line('Files: '.$summary['files']);
    $this->line('Phrases: '.$summary['phrases']);
    $this->line('Covered by admin.html fallback: '.$summary['covered']);
    $this->line('Missing: '.$summary['missing']);
    $this->line('Coverage: '.$summary['coverage'].'%');
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

    return collect(WebBlocksCmsServiceProvider::ADMIN_RUNTIME_VIEW_FILES)
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
