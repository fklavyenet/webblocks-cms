<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Support\Applications\ApplicationRegistry;
use WebBlocks\Cms\Support\Applications\ApplicationSettingsValidator;
use WebBlocks\Cms\Tests\TestCase;

class ApplicationRegistryTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();
    Schema::create('wbcms_embedded_applications', function (Blueprint $table): void {
      $table->id();
      $table->string('handle')->unique();
      $table->string('name');
      $table->text('description')->nullable();
      $table->string('version');
      $table->string('render_mode');
      $table->string('entry_url')->nullable();
      $table->string('mount_element')->nullable();
      $table->string('mount_classes')->nullable();
      $table->json('css_assets')->nullable();
      $table->json('js_assets')->nullable();
      $table->json('supports')->nullable();
      $table->json('settings_schema')->nullable();
      $table->boolean('is_enabled')->default(true);
      $table->unsignedBigInteger('created_by_user_id')->nullable();
      $table->unsignedBigInteger('updated_by_user_id')->nullable();
      $table->timestamps();
    });
  }

  public function test_it_reads_and_normalizes_a_ready_database_record(): void
  {
    EmbeddedApplication::query()->create([
      'handle' => 'typing-test', 'name' => 'Typing Test', 'version' => '1.0.0', 'render_mode' => 'inline',
      'mount_element' => 'div', 'mount_classes' => 'typing-app', 'css_assets' => ['/applications/typing/app.css'],
      'js_assets' => [['path' => '/applications/typing/app.js', 'type' => 'module', 'load_position' => 'body_end']],
      'supports' => ['locale' => true, 'theme' => true], 'settings_schema' => ['duration' => ['type' => 'integer', 'default' => 60]], 'is_enabled' => true,
    ]);
    $definition = app(ApplicationRegistry::class)->ready('typing-test');
    $this->assertSame('/applications/typing/app.css', $definition->assets['css'][0]['path']);
    $this->assertSame('module', $definition->assets['js'][0]['type']);
    $this->assertSame('database', $definition->toArray()['provider']['type']);
  }

  public function test_disabled_records_are_discoverable_but_not_ready(): void
  {
    EmbeddedApplication::query()->create(['handle' => 'paused-app', 'name' => 'Paused', 'version' => '1.0.0', 'render_mode' => 'iframe', 'entry_url' => '/applications/paused/index.html', 'is_enabled' => false]);
    $this->assertNull(app(ApplicationRegistry::class)->ready('paused-app'));
    $this->assertSame('invalid', app(ApplicationRegistry::class)->find('paused-app')->toArray()['readiness']['status']);
  }

  public function test_settings_are_normalized_against_the_database_schema(): void
  {
    EmbeddedApplication::query()->create([
      'handle' => 'typing-test', 'name' => 'Typing Test', 'version' => '1.0.0', 'render_mode' => 'inline', 'is_enabled' => true,
      'settings_schema' => ['duration' => ['type' => 'enum', 'values' => [30, 60], 'default' => 60], 'enabled' => ['type' => 'boolean', 'default' => true]],
    ]);
    $errors = [];
    $normalized = app(ApplicationSettingsValidator::class)->normalize(app(ApplicationRegistry::class)->ready('typing-test'), ['duration' => 60, 'enabled' => '0', 'unknown' => true], 'settings.application_settings', $errors);
    $this->assertSame(['duration' => 60, 'enabled' => false], $normalized);
    $this->assertSame('application_setting_unknown', $errors[0]['code']);
  }
}
