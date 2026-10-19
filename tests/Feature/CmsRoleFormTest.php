<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Queries\Access\CmsRoleQuery;
use WebBlocks\Cms\Support\Access\PermissionCatalog;
use WebBlocks\Cms\Tests\TestCase;

class CmsRoleFormTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $this->actingAs(User::forceCreate(['name' => 'Synthetic administrator', 'email' => 'admin@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]));
  }

  private function document(string $html): DOMXPath
  {
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
  }

  private function checkedPermissions(DOMXPath $xpath): array
  {
    $keys = [];
    foreach ($xpath->query('//input[@name="permissions[]"][@checked]') as $input) {
      $keys[] = $input->getAttribute('value');
    }
    sort($keys);

    return $keys;
  }

  #[Test]
  public function new_and_copied_roles_keep_explicit_selections_and_unique_labeled_controls(): void
  {
    $new = $this->get('/webadmin/access/roles/create')->assertOk()->assertSee('wb-page-header-title', false);
    $new->assertSee('data-wb-capability-field="permissions[]"', false)->assertSee('cms/js/admin/api-token-capabilities.js', false);
    $xpath = $this->document($new->getContent());
    $this->assertSame(count(array_unique(array_column(app(PermissionCatalog::class)->editable(), 'group'))), $xpath->query('//details[contains(@class, "wb-api-token-capability-group")]')->length);
    $this->assertSame(0, $xpath->query('//details[@open]')->length);
    $this->assertSame([], $this->checkedPermissions($xpath));
    $this->assertSame(count(app(PermissionCatalog::class)->editable()), $xpath->query('//input[@name="permissions[]"]')->length);
    foreach ($xpath->query('//input[@name="permissions[]"]') as $input) {
      $id = $input->getAttribute('id');
      $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
      $this->assertSame(1, $xpath->query('//label[@for="'.$id.'"]')->length);
    }
    $editor = CmsRole::where('handle', 'editor')->firstOrFail();
    $copy = $this->get('/webadmin/access/roles/create?copy='.$editor->id)->assertOk()->assertSee('Copy role: Editor');
    $expected = app(CmsRoleQuery::class)->permissions($editor);
    sort($expected);
    $this->assertSame($expected, $this->checkedPermissions($this->document($copy->getContent())));
    $copy->assertDontSee('cms.legacy-api.')->assertDontSee('cms.legacy.site-assets.');
  }

  #[Test]
  public function role_form_preserves_failed_input_and_cleared_permissions_without_writing_grants(): void
  {
    $role = CmsRole::create(['name' => 'News desk', 'handle' => 'custom-news']);
    foreach (['cms.panel.access', 'cms.pages.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $url = '/webadmin/access/roles/'.$role->id.'/edit';
    $this->from($url)->put('/webadmin/access/roles/'.$role->id, ['name' => '', 'description' => 'Submitted description', 'version' => $role->version])
      ->assertRedirect($url)->assertSessionHasErrors('name');
    $page = $this->get($url)->assertOk()->assertSee('Submitted description')->assertSee('id="acl-name-error"', false);
    $this->assertSame([], $this->checkedPermissions($this->document($page->getContent())));
    $this->assertSame(2, DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->count());
    $this->assertSame('News desk', $role->fresh()->name);
  }

  #[Test]
  public function protected_role_modal_is_contextual_read_only_and_uses_catalog_order(): void
  {
    $editor = CmsRole::where('handle', 'editor')->firstOrFail();
    $page = $this->get('/webadmin/access/roles')->assertOk()->assertSee('Role: Editor');
    $xpath = $this->document($page->getContent());
    $modal = $xpath->query('//div[@id="acl-role-'.$editor->id.'"]')->item(0);
    $this->assertSame(0, $xpath->query('.//input', $modal)->length);
    $labels = [];
    foreach ($xpath->query('.//div[@class="wb-api-token-capability-list"]//span[@class="wb-text-sm wb-text-muted"]', $modal) as $span) {
      $labels[] = trim($span->textContent);
    }
    $selected = app(CmsRoleQuery::class)->permissions($editor);
    $expected = array_column(array_intersect_key(app(PermissionCatalog::class)->editable(), array_flip($selected)), 'label');
    $this->assertSame($expected, $labels);
    $ids = [];
    foreach ($xpath->query('//*[@id]') as $node) {
      $ids[] = $node->getAttribute('id');
    }
    $this->assertSame($ids, array_values(array_unique($ids)));
    $protectedPage = $this->get('/webadmin/access/roles/'.$editor->id.'/edit')->assertOk()->assertDontSee('name="permissions[]"', false);
    $this->assertSame(0, $this->document($protectedPage->getContent())->query('//main//form//button[@type="submit"]')->length);
    $this->put('/webadmin/access/roles/'.$editor->id, ['name' => 'Escalation', 'version' => $editor->version])->assertForbidden();
  }
}
