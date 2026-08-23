<?php

namespace WebBlocks\Cms\Tests\Unit;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Support\Pages\PageRevisionInspector;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;

class PageRevisionExperienceStructureTest extends TestCase
{
  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  #[Test]
  public function version_list_requires_review_instead_of_offering_direct_restore(): void
  {
    $view = $this->view('index');

    $this->assertStringContainsString("route('admin.pages.revisions.show'", $view);
    $this->assertStringContainsString("\$adminText('review')", $view);
    $this->assertStringNotContainsString('pages.revisions.restore', $view);
    $this->assertStringNotContainsString('restore-page-revision-', $view);
  }

  #[Test]
  public function review_screen_explains_scope_and_guards_restore(): void
  {
    $view = $this->view('show');

    $this->assertStringContainsString("\$text('current_vs_version')", $view);
    $this->assertStringContainsString("\$text('restore_scope')", $view);
    $this->assertStringContainsString("\$text('shared_slot_boundary')", $view);
    $this->assertStringContainsString("\$inspection['health']['status'] === 'blocked'", $view);
    $this->assertStringContainsString("route('admin.pages.revisions.candidate.prepare'", $view);
    $this->assertStringContainsString('data-wb-target="#apply-page-version-candidate-modal"', $view);
    $this->assertStringContainsString("route('admin.pages.revisions.candidate.apply'", $view);
    $this->assertStringNotContainsString('pages.revisions.restore', $view);
  }

  #[Test]
  public function list_summary_names_changed_categories(): void
  {
    $inspector = new PageRevisionInspector(Mockery::mock(PageRevisionManager::class));
    $previous = new PageRevision(['snapshot' => ['page' => ['title' => 'Old'], 'translations' => [], 'slots' => [], 'blocks' => [], 'page_assets' => []]]);
    $revision = new PageRevision(['snapshot' => ['page' => ['title' => 'New'], 'translations' => [], 'slots' => [], 'blocks' => [], 'page_assets' => []]]);

    $this->assertSame([
      'type' => 'changed',
      'categories' => ['category_page'],
      'extra' => 0,
    ], $inspector->listSummary($revision, $previous));
  }

  private function view(string $name): string
  {
    return (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/pages/revisions/'.$name.'.blade.php');
  }
}
