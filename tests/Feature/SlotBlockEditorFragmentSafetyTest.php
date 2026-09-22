<?php

namespace App\Models {

  use Illuminate\Foundation\Auth\User as Authenticatable;
  use WebBlocks\Cms\Models\Concerns\HasCmsAdminAccess;

  class User extends Authenticatable
  {
    use HasCmsAdminAccess;

    protected $guarded = [];
  }
}

namespace WebBlocks\Cms\Tests\Feature {

  use App\Models\User;
  use DOMDocument;
  use DOMElement;
  use DOMXPath;
  use Illuminate\Testing\TestResponse;
  use PHPUnit\Framework\Attributes\Test;
  use WebBlocks\Cms\Models\Block;
  use WebBlocks\Cms\Models\BlockType;
  use WebBlocks\Cms\Models\Locale;
  use WebBlocks\Cms\Models\Page;
  use WebBlocks\Cms\Models\PageSlot;
  use WebBlocks\Cms\Models\SharedSlot;
  use WebBlocks\Cms\Models\Site;
  use WebBlocks\Cms\Models\SlotType;
  use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
  use WebBlocks\Cms\Tests\TestCase;

  class SlotBlockEditorFragmentSafetyTest extends TestCase
  {
    protected function defineEnvironment($app): void
    {
      parent::defineEnvironment($app);

      $app['config']->set('auth.providers.users.model', User::class);
      $app['config']->set('webblocks-cms.routes.admin', true);
    }

    protected function defineDatabaseMigrations(): void
    {
      $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
    }

    protected function setUp(): void
    {
      parent::setUp();

      $user = new User;
      $user->forceFill([
        'name' => 'Fragment Safety Admin',
        'email' => 'fragment-safety@example.test',
        'password' => 'unused',
        'role' => User::ROLE_SUPER_ADMIN,
        'is_active' => true,
      ])->save();

      $this->actingAs($user);
    }

    #[Test]
    public function page_fragment_and_full_editor_keep_the_same_form_contract(): void
    {
      [$page, $slot, $block] = $this->seedPageBlockContext();
      $url = route('admin.pages.slots.blocks', [
        'page' => $page,
        'slot' => $slot,
        'edit' => $block->id,
      ]);

      $fullPage = $this->get($url)->assertOk();
      $fragment = $this->getFragment($url)->assertOk();

      $this->assertSame($this->formContract($fullPage), $this->formContract($fragment));
      $fragment->assertSee('id="slot-block-editor-form"', false);
      $fragment->assertSee('#'.$block->id);
      $fragment->assertSee('data-wb-slot-block-fragment-overlays', false);
      $fragment->assertDontSee('data-wb-slot-block-list', false);
    }

    #[Test]
    public function page_slot_editor_preloads_rich_text_assets_for_dynamically_fetched_modals(): void
    {
      [$page, $slot] = $this->seedPageBlockContext();

      $response = $this->get(route('admin.pages.slots.blocks', [
        'page' => $page,
        'slot' => $slot,
      ]))->assertOk();

      foreach (['rich-text-editor', 'table-editor', 'asset-picker', 'gallery-items', 'icon-picker', 'builder-items', 'inline-block-builder'] as $script) {
        $response->assertSee('cms/js/admin/'.$script.'.js', false);
      }
      $response->assertSee('data-wb-rich-text-link-modal', false);
    }

    #[Test]
    public function page_fragment_form_updates_only_its_target_and_captures_a_revision(): void
    {
      [$page, $slot, $block, $sibling] = $this->seedPageBlockContext(withSibling: true);
      $url = route('admin.pages.slots.blocks', [
        'page' => $page,
        'slot' => $slot,
        'edit' => $block->id,
      ]);
      $fragment = $this->getFragment($url)->assertOk();
      $payload = $this->fragmentUpdatePayload($fragment, 'Updated through the page fragment');
      $originalPlacement = $block->only(['page_id', 'parent_id', 'slot_type_id', 'sort_order']);
      $originalSibling = $sibling->only(['content', 'page_id', 'parent_id', 'slot_type_id', 'sort_order']);

      $this->put($this->formContract($fragment)['action'], $payload)
        ->assertRedirect(route('admin.pages.slots.blocks', ['page' => $page, 'slot' => $slot]));

      $this->assertDatabaseHas('wbcms_block_text_translations', [
        'block_id' => $block->id,
        'content' => 'Updated through the page fragment',
      ]);
      $this->assertSame($originalPlacement, $block->fresh()->only(array_keys($originalPlacement)));
      $this->assertSame($originalSibling, $sibling->fresh()->only(array_keys($originalSibling)));
      $this->assertDatabaseHas('wbcms_page_revisions', [
        'page_id' => $page->id,
        'event' => 'block_updated',
      ]);
    }

    #[Test]
    public function shared_slot_fragment_and_full_editor_keep_the_same_form_contract(): void
    {
      [$sharedSlot, , , $block] = $this->seedSharedSlotBlockContext();
      $url = route('admin.shared-slots.blocks.edit', [
        'shared_slot' => $sharedSlot,
        'edit' => $block->id,
      ]);

      $fullPage = $this->get($url)->assertOk();
      $fragment = $this->getFragment($url)->assertOk();

      $this->assertSame($this->formContract($fullPage), $this->formContract($fragment));
      $this->assertSame((string) $sharedSlot->id, $this->hiddenValues($fragment)['shared_slot_id']);
      $fragment->assertSee('id="slot-block-editor-form"', false);
      $fragment->assertSee('#'.$block->id);
      $fragment->assertSee('data-wb-slot-block-fragment-overlays', false);
      $fragment->assertDontSee('data-wb-slot-block-list', false);
    }

    #[Test]
    public function shared_slot_editor_preloads_rich_text_assets_for_dynamically_fetched_modals(): void
    {
      [$sharedSlot] = $this->seedSharedSlotBlockContext();

      $response = $this->get(route('admin.shared-slots.blocks.edit', [
        'shared_slot' => $sharedSlot,
      ]))->assertOk();

      foreach (['rich-text-editor', 'table-editor', 'asset-picker', 'gallery-items', 'icon-picker', 'builder-items', 'inline-block-builder'] as $script) {
        $response->assertSee('cms/js/admin/'.$script.'.js', false);
      }
      $response->assertSee('data-wb-rich-text-link-modal', false);
    }

    #[Test]
    public function shared_slot_fragment_form_updates_only_its_target_and_captures_a_revision(): void
    {
      [$sharedSlot, $sourcePage, $slot, $block, $sibling] = $this->seedSharedSlotBlockContext(withSibling: true);
      $url = route('admin.shared-slots.blocks.edit', [
        'shared_slot' => $sharedSlot,
        'edit' => $block->id,
      ]);
      $fragment = $this->getFragment($url)->assertOk();
      $payload = $this->fragmentUpdatePayload($fragment, 'Updated through the Shared Slot fragment');
      $originalPlacement = $block->only(['page_id', 'parent_id', 'slot_type_id', 'sort_order']);
      $originalSibling = $sibling->only(['content', 'page_id', 'parent_id', 'slot_type_id', 'sort_order']);

      $this->put($this->formContract($fragment)['action'], $payload)
        ->assertRedirect(route('admin.shared-slots.blocks.edit', ['shared_slot' => $sharedSlot]));

      $this->assertDatabaseHas('wbcms_block_text_translations', [
        'block_id' => $block->id,
        'content' => 'Updated through the Shared Slot fragment',
      ]);
      $this->assertSame($originalPlacement, $block->fresh()->only(array_keys($originalPlacement)));
      $this->assertSame($originalSibling, $sibling->fresh()->only(array_keys($originalSibling)));
      $this->assertDatabaseHas('wbcms_shared_slot_blocks', [
        'shared_slot_id' => $sharedSlot->id,
        'block_id' => $block->id,
      ]);
      $this->assertDatabaseHas('wbcms_shared_slot_revisions', [
        'shared_slot_id' => $sharedSlot->id,
        'source_event' => 'block_updated',
      ]);
      $this->assertSame($sourcePage->id, $block->fresh()->page_id);
      $this->assertSame($slot->slot_type_id, $block->fresh()->slot_type_id);
    }

    private function getFragment(string $url): TestResponse
    {
      return $this->withHeader('X-WebBlocks-Modal-Fragment', 'slot-block-editor')->get($url);
    }

    /**
     * @return array{action: string, method: string, fields: list<string>, hidden: array<string, string>}
     */
    private function formContract(TestResponse $response): array
    {
      $xpath = $this->formXPath($response);
      $form = $xpath->query('//*[@id="slot-block-editor-form"]')->item(0);

      $this->assertInstanceOf(DOMElement::class, $form);
      $fields = [];

      foreach ($xpath->query('.//*[@name]', $form) as $field) {
        $fields[] = $field->getAttribute('name');
      }

      sort($fields);

      return [
        'action' => $form->getAttribute('action'),
        'method' => strtoupper($form->getAttribute('method')),
        'fields' => array_values(array_unique($fields)),
        'hidden' => $this->hiddenValues($response),
      ];
    }

    /**
     * @return array<string, string>
     */
    private function hiddenValues(TestResponse $response): array
    {
      $xpath = $this->formXPath($response);
      $form = $xpath->query('//*[@id="slot-block-editor-form"]')->item(0);

      $this->assertInstanceOf(DOMElement::class, $form);
      $values = [];

      foreach ($xpath->query('.//input[@type="hidden"][@name]', $form) as $input) {
        $name = $input->getAttribute('name');

        if ($name !== '_token') {
          $values[$name] = $input->getAttribute('value');
        }
      }

      ksort($values);

      return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private function fragmentUpdatePayload(TestResponse $fragment, string $text): array
    {
      $payload = $this->hiddenValues($fragment);
      unset($payload['_method'], $payload['_slot_block_tab']);

      return $payload + [
        'parent_id' => null,
        'sort_order' => 0,
        'status' => 'published',
        'text' => $text,
      ];
    }

    private function formXPath(TestResponse $response): DOMXPath
    {
      $document = new DOMDocument;
      @$document->loadHTML($response->getContent());

      return new DOMXPath($document);
    }

    /**
     * @return array{0: Page, 1: PageSlot, 2: Block, 3?: Block}
     */
    private function seedPageBlockContext(bool $withSibling = false): array
    {
      [$site, $slotType, $blockType] = $this->seedCatalog();
      $page = Page::query()->create([
        'site_id' => $site->id,
        'title' => 'Fragment Safety Page',
        'slug' => 'fragment-safety-page',
        'status' => Page::STATUS_DRAFT,
      ]);
      $slot = PageSlot::query()->create([
        'page_id' => $page->id,
        'slot_type_id' => $slotType->id,
        'source_type' => PageSlot::SOURCE_TYPE_PAGE,
        'sort_order' => 0,
      ]);
      $block = $this->createBlock($page, $slotType, $blockType, 'Original target');
      $result = [$page, $slot, $block];

      if ($withSibling) {
        $result[] = $this->createBlock($page, $slotType, $blockType, 'Untouched sibling', 1);
      }

      return $result;
    }

    /**
     * @return array{0: SharedSlot, 1: Page, 2: PageSlot, 3: Block, 4?: Block}
     */
    private function seedSharedSlotBlockContext(bool $withSibling = false): array
    {
      [$site, , $blockType] = $this->seedCatalog();
      $sharedSlot = SharedSlot::query()->create([
        'site_id' => $site->id,
        'name' => 'Fragment Safety Header',
        'handle' => 'fragment-safety-header',
        'slot_name' => 'main',
        'is_active' => true,
      ]);
      $sourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);
      $slot = $sourcePage->slots()->with('slotType')->firstOrFail();
      $block = $this->createBlock($sourcePage, $slot->slotType, $blockType, 'Original shared target');
      $result = [$sharedSlot, $sourcePage, $slot, $block];

      if ($withSibling) {
        $result[] = $this->createBlock($sourcePage, $slot->slotType, $blockType, 'Untouched shared sibling', 1);
      }

      app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);

      return $result;
    }

    /**
     * @return array{0: Site, 1: SlotType, 2: BlockType}
     */
    private function seedCatalog(): array
    {
      $site = Site::query()->firstOrCreate(['handle' => 'fragment-safety'], [
        'name' => 'Fragment Safety',
        'is_primary' => true,
      ]);
      $locale = Locale::query()->firstOrCreate(['code' => 'en'], [
        'name' => 'English',
        'is_default' => true,
        'is_enabled' => true,
      ]);
      $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
      $slotType = SlotType::query()->firstOrCreate(['slug' => 'main'], [
        'name' => 'Main',
        'status' => 'published',
        'sort_order' => 0,
      ]);
      $blockType = BlockType::query()->firstOrCreate(['slug' => 'plain_text'], [
        'name' => 'Plain Text',
        'category' => 'content',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => false,
        'sort_order' => 0,
        'status' => 'published',
      ]);

      return [$site, $slotType, $blockType];
    }

    private function createBlock(
      Page $page,
      SlotType $slotType,
      BlockType $blockType,
      string $content,
      int $sortOrder = 0,
    ): Block {
      return Block::query()->create([
        'page_id' => $page->id,
        'type' => $blockType->slug,
        'block_type_id' => $blockType->id,
        'source_type' => $blockType->source_type,
        'slot' => $slotType->slug,
        'slot_type_id' => $slotType->id,
        'sort_order' => $sortOrder,
        'content' => $content,
        'status' => 'published',
      ]);
    }
  }
}
