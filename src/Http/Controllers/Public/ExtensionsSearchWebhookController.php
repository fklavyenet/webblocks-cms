<?php

namespace WebBlocks\Cms\Http\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContract;
use WebBlocks\Cms\Support\Search\ExtensionsSearchIndex;

final class ExtensionsSearchWebhookController
{
  public function __invoke(Request $request, ExtensionsSearchIndex $index): Response
  {
    $site = config('cms.extensions_search.site');
    $secret = config('cms.extensions_search.secret');
    abort_unless(is_string($site) && $site !== '' && is_string($secret) && strlen($secret) >= 32, 404);

    $body = $request->getContent();
    abort_unless(strlen($body) <= 2_000_000, 413);
    $timestamp = (string) $request->header('X-WebBlocks-Timestamp');
    abort_unless(preg_match('/^[0-9]{10}$/D', $timestamp) && abs(now()->timestamp - (int) $timestamp) <= 300
      && hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), (string) $request->header('X-WebBlocks-Signature')), 401);

    $payload = json_decode($body, true, 12);
    abort_unless(is_array($payload), 422);
    $locales = ContentSourceContract::languages();
    $key = ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'];
    $validated = Validator::make($payload, [
      'schema' => ['required', 'integer', 'in:1'],
      'sequence' => ['required', 'integer', 'min:1'],
      'pending' => ['present', 'array', 'list', 'max:100'],
      'pending.*' => [...$key, 'distinct'],
      'records' => ['present', 'array', 'list', 'max:100'],
      'records.*' => ['required', 'array:key,translations'],
      'records.*.key' => [...$key, 'distinct'],
      'records.*.translations' => ['required', 'array:'.implode(',', $locales), 'required_array_keys:'.implode(',', $locales)],
      'records.*.translations.*' => ['required', 'array:title,description'],
      'records.*.translations.*.title' => ['required', 'string', 'max:500'],
      'records.*.translations.*.description' => ['present', 'string', 'max:4000'],
    ])->validate();
    if (array_intersect($validated['pending'], array_column($validated['records'], 'key')) !== []) {
      throw ValidationException::withMessages(['records' => 'An entry cannot also be pending.']);
    }

    abort_unless(Schema::hasTable('wbcms_extensions_search_publications') && Schema::hasTable('wbcms_extensions_search_entries'), 503);
    $rule = AutomaticPageRule::query()->whereHas('site', fn ($sites) => $sites->where('handle', $site))
      ->where('status', 'active')->where('base_path', '/extensions')
      ->where('source_handle', ExtensionsSearchIndex::SOURCE)->firstOrFail();
    $index->apply($rule, $validated);

    return response()->noContent();
  }
}
