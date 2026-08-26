<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    $this->createUsersTableColumns();
    $this->createCatalogTables();
    $this->createMediaTables();
    $this->createCoreSiteTables();
    $this->createLayoutAndPageTables();
    $this->createBlockAndNavigationTables();
    $this->createOperationalTables();
  }

  public function down(): void
  {
    foreach ([
      'wbcms_navigation_item_translations',
      'wbcms_block_gallery_item_translations',
      'wbcms_block_contact_form_translations',
      'wbcms_block_plugin_translations',
      'wbcms_block_image_translations',
      'wbcms_block_button_translations',
      'wbcms_block_text_translations',
      'wbcms_system_backup_restores',
      'wbcms_cms_api_token_activity_logs',
      'wbcms_cms_api_tokens',
      'wbcms_embedded_applications',
      'wbcms_system_backups',
      'wbcms_system_update_runs',
      'wbcms_public_search_index',
      'wbcms_visitor_events',
      'wbcms_contact_messages',
      'wbcms_site_imports',
      'wbcms_site_exports',
      'wbcms_icon_catalog_items',
      'wbcms_page_assets',
      'wbcms_page_revision_candidates',
      'wbcms_shared_slot_revisions',
      'wbcms_shared_slot_blocks',
      'wbcms_shared_slots',
      'wbcms_page_revisions',
      'wbcms_block_media',
      'wbcms_page_slots',
      'wbcms_blocks',
      'wbcms_page_translations',
      'wbcms_pages',
      'wbcms_page_layout_slots',
      'wbcms_page_layouts',
      'wbcms_layouts',
      'wbcms_site_variables',
      'wbcms_site_domains',
      'wbcms_site_user',
      'wbcms_site_locales',
      'wbcms_locales',
      'wbcms_sites',
      'wbcms_media',
      'wbcms_media_folders',
      'wbcms_navigation_items',
      'wbcms_system_settings',
      'wbcms_block_types',
      'wbcms_slot_types',
      'wbcms_layout_types',
      'wbcms_page_types',
    ] as $table) {
      Schema::dropIfExists($table);
    }

    if (Schema::hasTable('users')) {
      Schema::table('users', function (Blueprint $table): void {
        foreach (['role', 'is_admin', 'is_active', 'last_login_at', 'admin_locale'] as $column) {
          if (Schema::hasColumn('users', $column)) {
            $table->dropColumn($column);
          }
        }
      });
    }
  }

  private function createUsersTableColumns(): void
  {
    if (! Schema::hasTable('users')) {
      $this->createTableIfMissing('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->string('role', 32)->nullable();
        $table->boolean('is_admin')->default(false);
        $table->boolean('is_active')->default(true);
        $table->timestamp('last_login_at')->nullable();
        $table->string('admin_locale', 12)->nullable();
        $table->timestamps();
      });

      return;
    }

    Schema::table('users', function (Blueprint $table): void {
      if (! Schema::hasColumn('users', 'role')) {
        $table->string('role', 32)->nullable()->after('password');
      }

      if (! Schema::hasColumn('users', 'is_admin')) {
        $table->boolean('is_admin')->default(false)->after('password');
      }

      if (! Schema::hasColumn('users', 'is_active')) {
        $table->boolean('is_active')->default(true)->after('is_admin');
      }

      if (! Schema::hasColumn('users', 'last_login_at')) {
        $table->timestamp('last_login_at')->nullable()->after('remember_token');
      }

      if (! Schema::hasColumn('users', 'admin_locale')) {
        $table->string('admin_locale', 12)->nullable()->after(Schema::hasColumn('users', 'last_login_at') ? 'last_login_at' : 'remember_token');
      }
    });
  }

  private function createTableIfMissing(string $tableName, callable $callback): void
  {
    if (Schema::hasTable($tableName)) {
      return;
    }

    Schema::create($tableName, $callback);
  }

  private function createCatalogTables(): void
  {
    $this->createTableIfMissing('wbcms_page_types', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->text('description')->nullable();
      $table->boolean('is_system')->default(false);
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('status')->default('published');
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_layout_types', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->text('description')->nullable();
      $table->string('category')->nullable();
      $table->boolean('is_system')->default(false);
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('status')->default('published');
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_slot_types', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->text('description')->nullable();
      $table->string('axis')->nullable();
      $table->boolean('is_system')->default(false);
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('status')->default('published');
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_block_types', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->text('description')->nullable();
      $table->string('category')->nullable();
      $table->string('source_type')->nullable();
      $table->boolean('is_system')->default(false);
      $table->boolean('is_container')->default(false);
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('status')->default('published');
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_system_settings', function (Blueprint $table): void {
      $table->id();
      $table->string('key')->unique();
      $table->longText('value')->nullable();
      $table->timestamps();
    });
  }

  private function createCoreSiteTables(): void
  {
    $this->createTableIfMissing('wbcms_sites', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('handle')->unique();
      $table->string('domain')->nullable();
      $table->boolean('is_primary')->default(false);
      $table->string('display_name')->nullable();
      $table->string('tagline')->nullable();
      $table->foreignId('favicon_media_id')->nullable()->constrained('wbcms_media')->nullOnDelete();
      $table->foreignId('social_image_media_id')->nullable()->constrained('wbcms_media')->nullOnDelete();
      $table->string('contact_recipient_email')->nullable();
      $table->string('timezone')->nullable();
      $table->string('public_theme_preset')->nullable();
      $table->text('custom_head_html')->nullable();
      $table->string('brand_accent', 7)->nullable();
      $table->string('brand_accent_secondary', 7)->nullable();
      $table->string('brand_surface', 7)->nullable();
      $table->string('brand_text', 7)->nullable();
      $table->string('brand_font_heading', 180)->nullable();
      $table->string('brand_font_body', 180)->nullable();
      $table->string('seo_title')->nullable();
      $table->text('seo_description')->nullable();
      $table->text('seo_keywords')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_locales', function (Blueprint $table): void {
      $table->id();
      $table->string('code')->unique();
      $table->string('name');
      $table->boolean('is_default')->default(false);
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_site_locales', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();
      $table->unique(['site_id', 'locale_id']);
    });

    $this->createTableIfMissing('wbcms_site_user', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
      $table->timestamps();
      $table->unique(['user_id', 'site_id']);
    });

    $this->createTableIfMissing('wbcms_site_domains', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->string('domain');
      $table->boolean('is_primary')->default(false);
      $table->boolean('redirect_to_primary')->default(false);
      $table->string('status')->default('active');
      $table->timestamps();
      $table->unique(['site_id', 'domain']);
    });

    $this->createTableIfMissing('wbcms_site_variables', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->string('key');
      $table->string('label');
      $table->text('value')->nullable();
      $table->unsignedInteger('sort_order')->default(0);
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();
      $table->unique(['site_id', 'key']);
      $table->index(['site_id', 'sort_order', 'id']);
      $table->index(['site_id', 'is_enabled']);
    });
  }

  private function createLayoutAndPageTables(): void
  {
    $this->createTableIfMissing('wbcms_layouts', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('slug')->unique();
      $table->foreignId('layout_type_id')->nullable()->constrained('wbcms_layout_types')->nullOnDelete();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_page_layouts', function (Blueprint $table): void {
      $table->id();
      $table->string('handle')->unique();
      $table->string('name');
      $table->text('description')->nullable();
      $table->boolean('is_system')->default(false);
      $table->boolean('is_active')->default(true);
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('body_class')->nullable();
      $table->string('shell_type')->default('default');
      $table->json('slot_schema')->nullable();
      $table->json('wrapper_schema')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_page_layout_slots', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_layout_id')->constrained('wbcms_page_layouts')->cascadeOnDelete();
      $table->foreignId('slot_type_id')->constrained('wbcms_slot_types')->cascadeOnDelete();
      $table->string('slot_name');
      $table->string('label')->nullable();
      $table->text('description')->nullable();
      $table->string('html_element')->default('div');
      $table->string('html_id')->nullable();
      $table->string('css_classes')->nullable();
      $table->longText('before_html')->nullable();
      $table->longText('start_html')->nullable();
      $table->longText('end_html')->nullable();
      $table->longText('after_html')->nullable();
      $table->boolean('is_required')->default(false);
      $table->boolean('is_active')->default(true);
      $table->boolean('is_system')->default(false);
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamps();
      $table->unique(['page_layout_id', 'slot_name']);
    });

    $this->createTableIfMissing('wbcms_pages', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->string('title')->nullable();
      $table->string('slug')->nullable();
      $table->string('page_type')->default('default');
      $table->foreignId('page_type_id')->nullable()->constrained('wbcms_page_types')->nullOnDelete();
      $table->foreignId('layout_id')->nullable()->constrained('wbcms_layouts')->nullOnDelete();
      $table->json('settings')->nullable();
      $table->string('status')->default('draft');
      $table->timestamp('published_at')->nullable();
      $table->timestamp('review_requested_at')->nullable();
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('archived_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('review_requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
      $table->index(['site_id', 'status']);
      $table->unique(['id', 'site_id'], 'pages_id_site_id_unique');
    });

    $this->createTableIfMissing('wbcms_page_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('name');
      $table->string('slug');
      $table->string('path');
      $table->string('seo_title')->nullable();
      $table->text('seo_description')->nullable();
      $table->text('seo_keywords')->nullable();
      $table->text('list_excerpt')->nullable();
      $table->string('og_title')->nullable();
      $table->text('og_description')->nullable();
      $table->foreignId('og_image_media_id')->nullable()->constrained('wbcms_media')->nullOnDelete();
      $table->timestamps();
      $table->unique(['page_id', 'locale_id']);
      $table->unique(['site_id', 'locale_id', 'slug'], 'page_translations_site_locale_slug_unique');
      $table->unique(['site_id', 'locale_id', 'path'], 'page_translations_site_locale_path_unique');
      $table->index(['site_id', 'page_id'], 'page_translations_site_id_page_id_index');
      $table->index(['locale_id', 'site_id'], 'page_translations_locale_id_site_id_index');
      $table->foreign(['page_id', 'site_id'], 'page_translations_page_id_site_id_foreign')
        ->references(['id', 'site_id'])
        ->on('wbcms_pages')
        ->cascadeOnDelete();
    });

    $this->createTableIfMissing('wbcms_page_assets', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->string('type');
      $table->string('path');
      $table->string('load_position')->default('body_end');
      $table->boolean('is_defer')->default(true);
      $table->boolean('is_async')->default(false);
      $table->boolean('is_module')->default(false);
      $table->boolean('is_enabled')->default(true);
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_page_revisions', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->string('created_by')->nullable();
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->string('source')->nullable();
      $table->string('event')->nullable();
      $table->string('label')->nullable();
      $table->text('reason')->nullable();
      $table->json('snapshot');
      $table->foreignId('restored_from_page_revision_id')->nullable()->constrained('wbcms_page_revisions')->nullOnDelete();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_page_revision_candidates', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->foreignId('page_revision_id')->constrained('wbcms_page_revisions')->cascadeOnDelete();
      $table->foreignId('candidate_page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->string('status', 24)->default('ready');
      $table->timestamp('source_updated_at')->nullable();
      $table->timestamp('applied_at')->nullable();
      $table->timestamp('discarded_at')->nullable();
      $table->timestamps();
      $table->index(['page_id', 'status']);
      $table->index(['page_revision_id', 'status']);
    });

    $this->createTableIfMissing('wbcms_shared_slots', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->string('name');
      $table->string('handle');
      $table->string('slot_name')->nullable();
      $table->string('public_shell')->nullable();
      $table->boolean('is_active')->default(true);
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
      $table->unique(['site_id', 'handle']);
    });

    $this->createTableIfMissing('wbcms_page_slots', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->foreignId('slot_type_id')->constrained('wbcms_slot_types')->cascadeOnDelete();
      $table->string('source_type')->default('page');
      $table->foreignId('shared_slot_id')->nullable()->constrained('wbcms_shared_slots')->nullOnDelete();
      $table->unsignedInteger('sort_order')->default(0);
      $table->json('settings')->nullable();
      $table->timestamps();
      $table->index(['page_id', 'sort_order']);
    });
  }

  private function createMediaTables(): void
  {
    $this->createTableIfMissing('wbcms_media_folders', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('parent_id')->nullable()->constrained('wbcms_media_folders')->nullOnDelete();
      $table->string('name');
      $table->string('slug')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_media', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('folder_id')->nullable()->constrained('wbcms_media_folders')->nullOnDelete();
      $table->string('disk')->default('public');
      $table->string('path');
      $table->string('filename');
      $table->string('original_name')->nullable();
      $table->string('extension')->nullable();
      $table->string('mime_type')->nullable();
      $table->unsignedBigInteger('size')->nullable();
      $table->string('kind')->default('other');
      $table->string('visibility')->default('public');
      $table->string('title')->nullable();
      $table->string('alt_text')->nullable();
      $table->text('caption')->nullable();
      $table->text('description')->nullable();
      $table->unsignedInteger('width')->nullable();
      $table->unsignedInteger('height')->nullable();
      $table->decimal('focal_point_x', 5, 4)->nullable();
      $table->decimal('focal_point_y', 5, 4)->nullable();
      $table->unsignedInteger('duration')->nullable();
      $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
    });
  }

  private function createBlockAndNavigationTables(): void
  {
    $this->createTableIfMissing('wbcms_blocks', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->foreignId('parent_id')->nullable()->constrained('wbcms_blocks')->nullOnDelete();
      $table->string('type');
      $table->foreignId('block_type_id')->nullable()->constrained('wbcms_block_types')->nullOnDelete();
      $table->string('source_type')->default('static');
      $table->string('slot')->nullable();
      $table->foreignId('slot_type_id')->nullable()->constrained('wbcms_slot_types')->nullOnDelete();
      $table->unsignedInteger('sort_order')->default(0);
      $table->string('title')->nullable();
      $table->string('subtitle')->nullable();
      $table->longText('content')->nullable();
      $table->string('url')->nullable();
      $table->foreignId('media_id')->nullable()->constrained('wbcms_media')->nullOnDelete();
      $table->string('variant')->nullable();
      $table->longText('meta')->nullable();
      $table->json('settings')->nullable();
      $table->string('status')->default('published');
      $table->boolean('is_system')->default(false);
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_block_media', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('media_id')->constrained('wbcms_media')->cascadeOnDelete();
      $table->string('role')->nullable();
      $table->unsignedInteger('position')->default(0);
      $table->timestamps();
      $table->index(['block_id', 'role', 'position']);
    });

    $this->createTableIfMissing('wbcms_shared_slot_blocks', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('shared_slot_id')->constrained('wbcms_shared_slots')->cascadeOnDelete();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('parent_id')->nullable()->constrained('wbcms_shared_slot_blocks')->nullOnDelete();
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_shared_slot_revisions', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('shared_slot_id')->constrained('wbcms_shared_slots')->cascadeOnDelete();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->string('source')->nullable();
      $table->string('event')->nullable();
      $table->string('source_event')->nullable();
      $table->string('label')->nullable();
      $table->text('summary')->nullable();
      $table->json('snapshot');
      $table->foreignId('restored_from_shared_slot_revision_id')->nullable();
      $table->foreign('restored_from_shared_slot_revision_id', 'ss_revisions_restored_from_fk')
        ->references('id')
        ->on('wbcms_shared_slot_revisions')
        ->nullOnDelete();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_block_text_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('title')->nullable();
      $table->string('eyebrow')->nullable();
      $table->string('subtitle')->nullable();
      $table->longText('content')->nullable();
      $table->longText('meta')->nullable();
      $table->timestamps();
      $table->unique(['block_id', 'locale_id']);
    });

    $this->createTableIfMissing('wbcms_block_button_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('title')->nullable();
      $table->timestamps();
      $table->unique(['block_id', 'locale_id']);
    });

    $this->createTableIfMissing('wbcms_block_image_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('caption')->nullable();
      $table->string('alt_text')->nullable();
      $table->timestamps();
      $table->unique(['block_id', 'locale_id']);
    });

    $this->createTableIfMissing('wbcms_block_contact_form_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('title')->nullable();
      $table->longText('content')->nullable();
      $table->string('submit_label')->nullable();
      $table->longText('success_message')->nullable();
      $table->longText('consent_label')->nullable();
      $table->timestamps();
      $table->unique(['block_id', 'locale_id']);
    });

    $this->createTableIfMissing('wbcms_block_plugin_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->constrained('wbcms_blocks')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('field', 100);
      $table->text('value')->nullable();
      $table->timestamps();
      $table->unique(['block_id', 'locale_id', 'field'], 'wbcms_block_plugin_tr_unique');
    });

    $this->createTableIfMissing('wbcms_block_gallery_item_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_media_id')->constrained('wbcms_block_media')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('alt_text')->nullable();
      $table->string('caption')->nullable();
      $table->string('overlay_title')->nullable();
      $table->text('overlay_text')->nullable();
      $table->timestamps();
      $table->unique(['block_media_id', 'locale_id'], 'wbcms_bg_item_tr_media_locale_unique');
    });

    $this->createTableIfMissing('wbcms_navigation_items', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->nullable()->constrained('wbcms_sites')->nullOnDelete();
      $table->string('menu_key')->default('primary');
      $table->foreignId('parent_id')->nullable()->constrained('wbcms_navigation_items')->nullOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->string('title')->nullable();
      $table->string('link_type')->default('page');
      $table->string('url')->nullable();
      $table->string('target')->nullable();
      $table->string('icon')->nullable();
      $table->unsignedInteger('position')->default(0);
      $table->string('visibility')->default('visible');
      $table->boolean('is_system')->default(false);
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_navigation_item_translations', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('navigation_item_id')->constrained('wbcms_navigation_items')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->string('title')->nullable();
      $table->timestamps();
      $table->unique(['navigation_item_id', 'locale_id'], 'wbcms_nav_item_tr_item_locale_unique');
    });
  }

  private function createOperationalTables(): void
  {
    $this->createTableIfMissing('wbcms_public_search_index', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('locale_id')->constrained('wbcms_locales')->cascadeOnDelete();
      $table->foreignId('page_id')->constrained('wbcms_pages')->cascadeOnDelete();
      $table->string('title');
      $table->text('excerpt')->nullable();
      $table->string('url');
      $table->longText('content');
      $table->timestamp('indexed_at')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_site_exports', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->constrained('wbcms_sites')->cascadeOnDelete();
      $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->string('status')->default('running');
      $table->boolean('includes_media')->default(true);
      $table->string('archive_disk')->nullable();
      $table->string('archive_path')->nullable();
      $table->string('archive_name')->nullable();
      $table->unsignedBigInteger('archive_size_bytes')->nullable();
      $table->json('summary_json')->nullable();
      $table->json('manifest_json')->nullable();
      $table->longText('output_log')->nullable();
      $table->text('failure_message')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_site_imports', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->string('status')->default('running');
      $table->string('resume_phase')->nullable();
      $table->unsignedInteger('resume_offset')->default(0);
      $table->longText('resume_state')->nullable();
      $table->unsignedInteger('progress_done')->default(0);
      $table->unsignedInteger('progress_total')->default(0);
      $table->timestamp('heartbeat_at')->nullable();
      $table->string('source_archive_name')->nullable();
      $table->string('archive_disk')->nullable();
      $table->string('archive_path')->nullable();
      $table->foreignId('target_site_id')->nullable()->constrained('wbcms_sites')->nullOnDelete();
      $table->string('imported_site_handle')->nullable();
      $table->string('imported_site_domain')->nullable();
      $table->json('summary_json')->nullable();
      $table->json('manifest_json')->nullable();
      $table->longText('output_log')->nullable();
      $table->text('failure_message')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_contact_messages', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('block_id')->nullable()->constrained('wbcms_blocks')->nullOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->string('name');
      $table->string('email');
      $table->string('subject')->nullable();
      $table->longText('message');
      $table->string('status')->default('new');
      $table->string('source_url')->nullable();
      $table->string('ip_address')->nullable();
      $table->text('user_agent')->nullable();
      $table->text('referer')->nullable();
      $table->unsignedSmallInteger('spam_score')->default(0);
      $table->json('spam_reasons')->nullable();
      $table->boolean('notification_enabled')->default(true);
      $table->string('notification_recipient')->nullable();
      $table->string('notification_recipient_source')->nullable();
      $table->string('notification_status')->nullable();
      $table->timestamp('notification_sent_at')->nullable();
      $table->text('notification_error')->nullable();
      $table->text('notification_reason')->nullable();
      $table->timestamp('consent_accepted_at')->nullable();
      $table->longText('consent_label')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_visitor_events', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('site_id')->nullable()->constrained('wbcms_sites')->nullOnDelete();
      $table->foreignId('page_id')->nullable()->constrained('wbcms_pages')->nullOnDelete();
      $table->foreignId('locale_id')->nullable()->constrained('wbcms_locales')->nullOnDelete();
      $table->string('path');
      $table->string('tracking_mode')->default('basic');
      $table->text('referrer')->nullable();
      $table->string('referrer_host')->nullable();
      $table->string('referrer_type', 24)->nullable();
      $table->string('utm_source')->nullable();
      $table->string('utm_medium')->nullable();
      $table->string('utm_campaign')->nullable();
      $table->string('device_type')->nullable();
      $table->string('browser_family')->nullable();
      $table->string('os_family')->nullable();
      $table->boolean('is_bot')->nullable();
      $table->string('session_key')->nullable();
      $table->string('ip_hash')->nullable();
      $table->timestamp('visited_at')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_icon_catalog_items', function (Blueprint $table): void {
      $table->id();
      $table->string('source')->default('webblocks-ui');
      $table->string('slug');
      $table->string('label');
      $table->string('css_class');
      $table->json('categories')->nullable();
      $table->json('contexts')->nullable();
      $table->json('keywords')->nullable();
      $table->boolean('is_active')->default(true);
      $table->unsignedInteger('sort_order')->default(0);
      $table->timestamp('synced_at')->nullable();
      $table->timestamps();
      $table->unique(['source', 'slug']);
    });

    $this->createTableIfMissing('wbcms_system_update_runs', function (Blueprint $table): void {
      $table->id();
      $table->string('from_version')->nullable();
      $table->string('to_version')->nullable();
      $table->string('status')->default('pending');
      $table->text('summary')->nullable();
      $table->longText('output')->nullable();
      $table->unsignedInteger('warning_count')->default(0);
      $table->timestamp('started_at')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->unsignedInteger('duration_ms')->nullable();
      $table->foreignId('triggered_by_user_id')->nullable();
      $table->foreign('triggered_by_user_id', 'wb_update_runs_triggered_by_fk')
        ->references('id')
        ->on('users')
        ->nullOnDelete();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_cms_api_tokens', function (Blueprint $table): void {
      $table->id();
      $table->string('name');
      $table->string('token_hash', 128)->unique();
      $table->string('token_preview', 32);
      $table->json('capabilities')->nullable();
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamp('last_used_at')->nullable();
      $table->string('last_used_ip', 45)->nullable();
      $table->string('last_used_user_agent', 255)->nullable();
      $table->timestamp('revoked_at')->nullable();
      $table->timestamps();

      $table->index(['revoked_at', 'created_at']);
    });

    $this->createTableIfMissing('wbcms_embedded_applications', function (Blueprint $table): void {
      $table->id();
      $table->string('handle', 64)->unique();
      $table->string('name');
      $table->text('description')->nullable();
      $table->string('version', 64)->default('1.0.0');
      $table->string('render_mode', 16);
      $table->string('entry_url', 2048)->nullable();
      $table->string('mount_element', 16)->nullable();
      $table->string('mount_classes', 512)->nullable();
      $table->json('css_assets')->nullable();
      $table->json('js_assets')->nullable();
      $table->json('supports')->nullable();
      $table->json('settings_schema')->nullable();
      $table->boolean('is_enabled')->default(true);
      $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->timestamps();
      $table->index(['is_enabled', 'name']);
    });

    $this->createTableIfMissing('wbcms_cms_api_token_activity_logs', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('cms_api_token_id')->constrained('wbcms_cms_api_tokens')->cascadeOnDelete();
      $table->timestamp('occurred_at')->useCurrent();
      $table->string('status', 32);
      $table->string('method', 12);
      $table->string('path', 512);
      $table->string('route_name')->nullable();
      $table->string('required_capability')->nullable();
      $table->string('ip', 45)->nullable();
      $table->string('user_agent', 255)->nullable();
      $table->timestamps();

      $table->index(['cms_api_token_id', 'occurred_at'], 'wbcms_api_token_activity_token_time_idx');
    });

    $this->createTableIfMissing('wbcms_system_backups', function (Blueprint $table): void {
      $table->id();
      $table->string('type')->default('manual');
      $table->string('status')->default('running');
      $table->string('label')->nullable();
      $table->boolean('includes_database')->default(true);
      $table->boolean('includes_uploads')->default(true);
      $table->string('archive_disk')->nullable();
      $table->string('archive_path')->nullable();
      $table->string('archive_filename')->nullable();
      $table->unsignedBigInteger('archive_size_bytes')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->unsignedInteger('duration_ms')->nullable();
      $table->text('summary')->nullable();
      $table->longText('output')->nullable();
      $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->text('error_message')->nullable();
      $table->timestamps();
    });

    $this->createTableIfMissing('wbcms_system_backup_restores', function (Blueprint $table): void {
      $table->id();
      $table->foreignId('source_backup_id')->nullable()->constrained('wbcms_system_backups')->nullOnDelete();
      $table->string('source_archive_disk')->nullable();
      $table->string('source_archive_path')->nullable();
      $table->string('source_archive_filename')->nullable();
      $table->foreignId('safety_backup_id')->nullable()->constrained('wbcms_system_backups')->nullOnDelete();
      $table->string('status')->default('completed');
      $table->json('restored_parts')->nullable();
      $table->json('manifest')->nullable();
      $table->timestamp('started_at')->nullable();
      $table->timestamp('finished_at')->nullable();
      $table->unsignedInteger('duration_ms')->nullable();
      $table->text('summary')->nullable();
      $table->longText('output')->nullable();
      $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
      $table->text('error_message')->nullable();
      $table->timestamps();
    });

  }
};
