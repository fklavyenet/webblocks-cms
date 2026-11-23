<?php

declare(strict_types=1);

use Illuminate\Support\Arr;
use WebBlocks\Cms\Support\Translations\NativeLanguageSource;
use WebBlocks\Cms\Support\Translations\PanelLanguageSource;
use WebBlocks\Cms\Support\WebBlocks;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$packRoot = $root.'/scripts/language-packs/ru';
$catalog = json_decode((string) file_get_contents($packRoot.'/messages.json'), true, flags: JSON_THROW_ON_ERROR);
$panelSource = new PanelLanguageSource;
$nativeSource = new NativeLanguageSource($panelSource);
$source = $nativeSource->groups();
$errors = [];
$native = [];
$total = 0;
foreach ($source as $group => $strings) {
  $target = $catalog[$group] ?? [];
  foreach ($strings as $key => $english) {
    $id = $group.'.'.$key;
    $row = $target[$key] ?? null;
    $sourceHash = hash('sha256', $english);
    if (! is_array($row) || ! array_is_list($row) || count($row) !== 2 || ! is_string($row[0]) || ! is_string($row[1])) {
      $errors[] = 'Missing or invalid translation record for '.$id.' (source SHA-256: '.$sourceHash.')';

      continue;
    }
    [$hash, $text] = $row;
    if (! hash_equals($sourceHash, $hash)) {
      $errors[] = 'Review changed English source for '.$id.' (source SHA-256: '.$sourceHash.')';
    }
    $hasPlural = $group === 'webblocks-cms::notifications' && str_contains($english, '|');
    if (! $nativeSource->validTranslation($group, $english, $text) || ($hasPlural && count(explode('|', $text)) !== 3)) {
      $errors[] = 'Invalid translation for '.$id;
    }
    $native[$group][$key] = $text;
    $total++;
  }
  foreach (array_keys(array_diff_key($target, $strings)) as $key) {
    $errors[] = 'Remove obsolete '.$group.'.'.$key;
  }
}
foreach (array_keys(array_diff_key($catalog, $source)) as $group) {
  $errors[] = 'Remove obsolete group '.$group;
}

$json = static fn (array $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

if ($errors !== []) {
  fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);

  exit(1);
}

printf("Russian Full CMS pack: %d messages across %d groups.\n", $total, count($native));

if ($argc === 1) {
  exit(0);
}
if ($argc !== 3 || $argv[1] !== '--build' || ! is_dir($argv[2])) {
  fwrite(STDERR, "Usage: php scripts/check-language-pack.php [--build EXISTING_OUTPUT_DIRECTORY]\n");

  exit(1);
}

$dir = rtrim($argv[2], '/');
$version = WebBlocks::VERSION;
$zipPath = $dir.'/webblocks-cms-ru-'.$version.'.zip';
$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
  throw new RuntimeException('Cannot create '.$zipPath);
}

$meta = [
  'format_version' => 1,
  'source_version' => $version,
  'locale' => 'ru',
  'name' => 'Русский',
  'requires_native_language_packs' => true,
  'message_count' => $total,
];
$files = [
  'LICENSE' => (string) file_get_contents($root.'/LICENSE'),
  'README.md' => (string) file_get_contents($packRoot.'/README.md'),
  'webblocks-language.json' => $json($meta),
  'lang/vendor/webblocks-cms/ru/webblocks-language.txt' => "Русский\n",
];
foreach ($native as $group => $messages) {
  [$namespace, $file] = explode('::', $group);
  $files['lang/vendor/'.$namespace.'/ru/'.$file.'.php'] = "<?php\n\nreturn ".var_export(Arr::undot($messages), true).";\n";
}
ksort($files);
foreach ($files as $file => $content) {
  if (! $zip->addFromString($file, $content) || ! $zip->setMtimeName($file, 946684800)) {
    throw new RuntimeException('Cannot write '.$file);
  }
}
if (! $zip->close()) {
  throw new RuntimeException('Cannot write language artifacts');
}

foreach ([$zipPath] as $path) {
  $sum = hash_file('sha256', $path);
  if ($sum === false || file_put_contents($path.'.sha256', $sum.'  '.basename($path)."\n") === false) {
    throw new RuntimeException('Cannot write checksum for '.$path);
  }
  echo $path.PHP_EOL;
}
