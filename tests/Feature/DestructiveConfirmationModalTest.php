<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Destructive admin actions used to go through the browser's own confirm()
 * dialog: a bare "Delete this Shared Slot?" with no name, no handle, and no
 * hint that the server rejects the delete while a page slot still points at
 * it. Every one of them opens the CMS confirmation modal now, and the sweep
 * below is what keeps the next one from slipping back to window.confirm.
 */
class DestructiveConfirmationModalTest extends TestCase
{
  private function viewSource(string $view): string
  {
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/shared-slots/'.$view);
  }

  #[Test]
  public function no_admin_view_falls_back_to_the_browser_confirm_dialog(): void
  {
    $root = dirname(__DIR__, 2).'/resources/views';
    $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
    $offenders = [];

    foreach ($views as $file) {
      if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
      }

      $source = (string) file_get_contents($file->getPathname());

      // "confirm(" only, so translation keys such as delete_confirm and the
      // confirm_delete_all_blocks guard field are not mistaken for a dialog.
      if (preg_match('/(?<![a-z_])confirm\s*\(/i', $source) === 1) {
        $offenders[] = str_replace($root.'/', '', $file->getPathname());
      }
    }

    sort($offenders);

    $this->assertSame(
      [],
      $offenders,
      'Destructive actions must confirm through the CMS modal, not window.confirm: '.implode(', ', $offenders)
    );
  }

  #[Test]
  public function the_list_and_the_danger_zone_both_open_the_same_modal(): void
  {
    foreach (['index.blade.php', 'edit.blade.php'] as $view) {
      $source = $this->viewSource($view);

      $this->assertStringContainsString(
        'data-wb-target="#delete-shared-slot-{{ $sharedSlot->id }}"',
        $source,
        $view.' must point its delete trigger at the per-Shared-Slot modal.'
      );
      $this->assertStringContainsString(
        'webblocks-cms::admin.shared-slots.partials.delete-modal',
        $source,
        $view.' must render the shared delete modal partial.'
      );
    }

    // Both screens include the same partial, so the id the trigger targets and the
    // id the modal registers can only drift if this expression changes in one place.
    $this->assertStringContainsString(
      "'delete-shared-slot-'.\$sharedSlot->id",
      $this->viewSource('partials/delete-modal.blade.php')
    );
  }

  #[Test]
  public function the_modal_names_what_is_being_deleted(): void
  {
    $source = $this->viewSource('partials/delete-modal.blade.php');

    foreach (['$sharedSlot->name', '$sharedSlot->handle', '$sharedSlot->slotLabel()', '$sharedSlot->publicShellLabel()'] as $field) {
      $this->assertStringContainsString(
        $field,
        $source,
        'The confirmation must identify the Shared Slot by '.$field.'.'
      );
    }
  }

  #[Test]
  public function a_referenced_shared_slot_cannot_be_submitted_for_deletion(): void
  {
    $source = $this->viewSource('partials/delete-modal.blade.php');

    // SharedSlotController::destroy() refuses while pageSlots() exist, so the
    // modal disables the submit and says why rather than bouncing the operator
    // off a validation error.
    $this->assertStringContainsString("'submitAttributes' => \$referenceCount > 0 ? ['disabled' => true] : []", $source);
    $this->assertStringContainsString('delete_blocked_warning', $source);
  }

  #[Test]
  public function the_edit_screen_knows_the_reference_count(): void
  {
    $controller = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Http/Controllers/Admin/SharedSlotController.php'
    );

    // The list already carries withCount('pageSlots'); without the matching
    // loadCount the edit screen would always read zero and offer a submit the
    // server then rejects.
    $this->assertStringContainsString("loadCount('pageSlots')", $controller);
  }

  #[Test]
  public function the_views_compile_with_no_directive_left_behind(): void
  {
    $compiler = app('blade.compiler');

    foreach (['index.blade.php', 'partials/delete-modal.blade.php'] as $view) {
      $compiled = $compiler->compileString($this->viewSource($view));

      foreach (['@php', '@endphp', '@if', '@endif', '@foreach', '@endforeach', '@push', '@endpush', '@component', '@endcomponent', '@include'] as $directive) {
        $this->assertStringNotContainsString(
          $directive,
          $compiled,
          sprintf('%s survived compilation in %s, so the view renders it as text.', $directive, $view)
        );
      }
    }
  }

  #[Test]
  public function restoring_a_revision_also_confirms_through_the_modal(): void
  {
    foreach (['revisions/index.blade.php', 'revisions/show.blade.php'] as $view) {
      $source = $this->viewSource($view);

      $this->assertStringContainsString(
        'data-wb-target="#restore-shared-slot-revision-{{ $revision->id }}"',
        $source,
        $view.' must open the restore modal rather than submitting straight away.'
      );
      $this->assertStringContainsString(
        'webblocks-cms::admin.shared-slots.partials.restore-revision-modal',
        $source,
        $view.' must render the shared restore modal partial.'
      );
    }

    $this->assertStringContainsString(
      "'restore-shared-slot-revision-'.\$revision->id",
      $this->viewSource('partials/restore-revision-modal.blade.php')
    );
  }

  #[Test]
  public function every_converted_screen_opens_a_modal_it_also_registers(): void
  {
    $root = dirname(__DIR__, 2).'/resources/views/';
    $screens = [
      'admin/blocks/index.blade.php' => 'delete-block-',
      'admin/locales/index.blade.php' => 'delete-locale-',
      'admin/navigation/partials/tree-list.blade.php' => 'delete-navigation-item-',
      'admin/pages/partials/block-outline-item.blade.php' => 'delete-outline-block-',
      'admin/pages/revisions/index.blade.php' => 'restore-page-revision-',
      'admin/system/backups/show.blade.php' => 'restore-backup-',
    ];

    foreach ($screens as $view => $idPrefix) {
      $source = (string) file_get_contents($root.$view);

      $this->assertStringContainsString(
        'data-wb-target="#'.$idPrefix,
        $source,
        $view.' must open a modal rather than submitting straight away.'
      );
      // A trigger whose modal is never pushed is a dead button, so each screen
      // has to register the id it targets.
      $this->assertStringContainsString(
        "'id' => '".$idPrefix,
        $source,
        $view.' targets #'.$idPrefix.'… but never registers that modal.'
      );
      $this->assertStringContainsString(
        'destructive-confirmation-modal',
        $source,
        $view.' must use the shared confirmation modal partial.'
      );
    }
  }

  #[Test]
  public function the_backup_restore_acknowledgement_moved_into_the_modal(): void
  {
    $source = (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/system/backups/show.blade.php'
    );

    // The server requires acknowledge_restore_risk, so it has to be inside the
    // modal's own form now that the page-level form is gone.
    $modal = substr($source, (int) strpos($source, "'id' => 'restore-backup-"));
    $this->assertStringContainsString('name="acknowledge_restore_risk"', $modal);
    $this->assertStringContainsString('data-wb-restore-ack', $modal);
    $this->assertStringContainsString("'data-wb-restore-form' => true", $modal);
    $this->assertStringContainsString("'data-wb-restore-submit' => true", $modal);
  }

  #[Test]
  public function the_shared_form_actions_component_no_longer_offers_a_confirm_hook(): void
  {
    $source = (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/components/admin/form-actions.blade.php'
    );

    // The prop had no call site anywhere in the package; leaving it in place
    // would be a supported way back to window.confirm.
    $this->assertStringNotContainsString('deleteConfirm', $source);
  }

  #[Test]
  public function the_list_reports_usage_and_can_show_which_pages(): void
  {
    $index = $this->viewSource('index.blade.php');

    $this->assertStringContainsString("\$adminText('usage_count', ['count' => \$usageCount])", $index);
    $this->assertStringContainsString('data-wb-target="#usage-shared-slot-{{ $sharedSlot->id }}"', $index);
    // Nothing to show at zero, so the action is inert rather than opening an
    // empty modal from the list.
    $this->assertStringContainsString('@disabled($usageCount === 0)', $index);
    $this->assertStringContainsString('webblocks-cms::admin.shared-slots.partials.usage-modal', $index);

    $modal = $this->viewSource('partials/usage-modal.blade.php');

    $this->assertStringContainsString("'usage-shared-slot-'.\$sharedSlot->id", $modal);
    $this->assertStringContainsString("route('admin.pages.edit', \$pageSlot->page)", $modal);
    // A Shared Slot's own hidden source page is internal plumbing and has no
    // slot source an operator could change, so it must not be offered as a link.
    $this->assertStringContainsString('isSharedSlotSourcePage()', $modal);
  }

  #[Test]
  public function the_usage_list_does_not_query_once_per_row(): void
  {
    $controller = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Http/Controllers/Admin/SharedSlotController.php'
    );

    // Every row renders its own usage modal, so the page slots and their pages
    // have to travel with the paginated collection.
    $this->assertStringContainsString("'pageSlots.slotType'", $controller);
    $this->assertStringContainsString("'pageSlots.page.translations.locale'", $controller);
  }

  #[Test]
  public function every_admin_locale_carries_the_new_confirmation_copy(): void
  {
    $keys = [
      'delete_title',
      'delete_description',
      'delete_confirm_prefix',
      'cannot_be_undone',
      'delete_blocks_warning',
      'delete_blocked_warning',
      'cancel',
      'restore_title',
      'restore_description',
      'restore_confirm_prefix',
      'restore_confirm_infix',
      'restore_warning',
      'usage_count',
      'view_usage',
      'usage_title',
      'usage_description',
      'usage_page',
      'usage_path',
      'usage_help',
      'usage_empty_title',
      'usage_empty_text',
      'close_usage',
      'close',
    ];

    foreach (['en', 'de', 'tr', 'es', 'it'] as $locale) {
      $strings = require dirname(__DIR__, 2)."/resources/lang/{$locale}/admin.php";
      $sharedSlots = $strings['shared_slots'] ?? [];

      foreach ($keys as $key) {
        $this->assertArrayHasKey(
          $key,
          $sharedSlots,
          "admin.shared_slots.{$key} is missing from the {$locale} translations."
        );
      }

      $this->assertStringContainsString(
        ':count',
        (string) $sharedSlots['delete_blocked_warning'],
        "The {$locale} blocked warning must report how many page slots still reference the Shared Slot."
      );
    }
  }
}
