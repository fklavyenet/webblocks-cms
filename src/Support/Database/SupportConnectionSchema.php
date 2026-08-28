<?php

namespace WebBlocks\Cms\Support\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class SupportConnectionSchema
{
  public function ensure(): void
  {
    if (Schema::hasTable(CmsTable::name('support_connections'))) {
      return;
    }

    Schema::create(CmsTable::name('support_connections'), function (Blueprint $table): void {
      $table->id();
      $table->string('provider_url', 2048);
      $table->string('provider_name')->nullable();
      $table->string('api_base_url', 2048);
      $table->string('protocol_version', 16)->default('1.0');
      $table->json('capabilities')->nullable();
      $table->string('status', 24)->default('pending');
      $table->string('activation_id')->nullable();
      $table->longText('activation_secret')->nullable();
      $table->string('activation_user_code')->nullable();
      $table->string('activation_url', 2048)->nullable();
      $table->timestamp('activation_expires_at')->nullable();
      $table->longText('credential')->nullable();
      $table->string('plan_name')->nullable();
      $table->timestamp('entitlement_expires_at')->nullable();
      $table->timestamp('activated_at')->nullable();
      $table->timestamp('last_verified_at')->nullable();
      $table->text('last_error')->nullable();
      $table->timestamps();
    });
  }
}
