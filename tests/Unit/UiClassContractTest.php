<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\WebBlocks;

/**
 * Every `wb-` class the admin writes has to be a class something defines.
 *
 * A class name that matches nothing fails silently: the markup renders, the
 * page looks nearly right, and the missing rules only surface when enough of
 * the element appears at once. `wb-checkbox` — the UI's primitive is
 * `wb-check` — sat in seventeen files that way, so every checkbox in the admin
 * had been unstyled, and it took a table of seventy of them collapsing into
 * wrapped text for anyone to notice.
 *
 * The admin loads the UI from a CDN, so this compares against a snapshot of
 * the pinned runtime's class names rather than the stylesheet itself.
 */
class UiClassContractTest extends TestCase
{
  private function fixtureDir(): string
  {
    return dirname(__DIR__).'/fixtures';
  }

  /**
   * @return list<string>
   */
  private function lines(string $file): array
  {
    $lines = file($this->fixtureDir().'/'.$file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_values(array_filter($lines, static fn (string $line) => ! str_starts_with($line, '#')));
  }

  /**
   * @return list<string>
   */
  private function definedClasses(): array
  {
    $defined = array_values(array_filter(
      $this->lines('webblocks-ui-classes.txt'),
      static fn (string $line) => ! str_starts_with($line, 'ui-version:')
    ));

    // The CMS ships its own admin stylesheets on top of the UI runtime.
    foreach (glob(dirname(__DIR__, 2).'/public/cms/css/*.css') ?: [] as $stylesheet) {
      preg_match_all('/\.(wb-[a-zA-Z0-9_-]+)/', (string) file_get_contents($stylesheet), $matches);
      $defined = array_merge($defined, $matches[1]);
    }

    return array_values(array_unique($defined));
  }

  /**
   * @return array<string, list<string>> class => views that use it
   */
  private function usedClasses(): array
  {
    $used = [];
    $root = dirname(__DIR__, 2).'/resources/views';
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
      if (! str_ends_with((string) $file, '.blade.php')) {
        continue;
      }

      preg_match_all('/class="([^"]*)"/', (string) file_get_contents((string) $file), $matches);

      foreach ($matches[1] as $attribute) {
        foreach (preg_split('/\s+/', $attribute) ?: [] as $class) {
          // Blade interpolation inside the attribute is not a literal class.
          if (preg_match('/^wb-[a-z0-9-]+$/', $class)) {
            $used[$class][] = str_replace($root.'/', '', (string) $file);
          }
        }
      }
    }

    return $used;
  }

  #[Test]
  public function the_class_snapshot_matches_the_pinned_ui_runtime(): void
  {
    $recorded = null;

    foreach ($this->lines('webblocks-ui-classes.txt') as $line) {
      if (str_starts_with($line, 'ui-version:')) {
        $recorded = trim(substr($line, strlen('ui-version:')));
        break;
      }
    }

    // Moving the pinned runtime without regenerating the snapshot would leave
    // this test checking the admin against a stylesheet it no longer loads.
    $this->assertSame(
      WebBlocks::UI_VERSION,
      $recorded,
      'tests/fixtures/webblocks-ui-classes.txt was built for a different UI runtime; regenerate it.'
    );
  }

  #[Test]
  public function the_admin_writes_no_class_name_that_nothing_defines(): void
  {
    $defined = $this->definedClasses();
    $known = $this->lines('known-unstyled-classes.txt');
    $offenders = [];

    foreach ($this->usedClasses() as $class => $views) {
      if (in_array($class, $defined, true) || in_array($class, $known, true)) {
        continue;
      }

      $offenders[] = sprintf('%s (%s)', $class, implode(', ', array_unique(array_slice($views, 0, 3))));
    }

    $this->assertSame(
      [],
      $offenders,
      "These classes match no stylesheet rule. Check whether the UI already has the primitive under\n"
      ."another name — wb-check, not wb-checkbox — before adding anything:\n  ".implode("\n  ", $offenders)
    );
  }

  #[Test]
  public function the_baseline_only_lists_classes_that_are_still_undefined(): void
  {
    $defined = $this->definedClasses();
    $used = array_keys($this->usedClasses());
    $stale = [];

    foreach ($this->lines('known-unstyled-classes.txt') as $class) {
      if (in_array($class, $defined, true)) {
        $stale[] = $class.' is defined now';
      } elseif (! in_array($class, $used, true)) {
        $stale[] = $class.' is no longer used';
      }
    }

    // The baseline is meant to shrink. Left alone it rots into a list that
    // excuses names nobody writes any more, and stops meaning anything.
    $this->assertSame([], $stale, "Remove these from known-unstyled-classes.txt:\n  ".implode("\n  ", $stale));
  }

  #[Test]
  public function the_checkbox_primitive_is_the_one_the_ui_ships(): void
  {
    $used = $this->usedClasses();

    $this->assertArrayNotHasKey('wb-checkbox', $used, 'The primitive is wb-check, alongside wb-radio and wb-switch.');
    $this->assertArrayHasKey('wb-check', $used);
    $this->assertContains('wb-check', $this->definedClasses());
  }
}
