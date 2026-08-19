<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Actions\Applications\DeleteEmbeddedApplication;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Support\Applications\ApplicationDefinition;
use WebBlocks\Cms\Support\Applications\ApplicationRegistry;

class InternalApplicationController extends Controller
{
  public function __construct(
    private readonly ApplicationRegistry $registry,
  ) {}

  public function index(Request $request): JsonResponse
  {
    $renderMode = trim((string) $request->query('render_mode'));
    $readiness = trim((string) $request->query('readiness'));
    $search = mb_strtolower(trim((string) $request->query('search')));

    $applications = $this->registry->all()
      ->when($renderMode !== '', fn ($items) => $items->filter(fn (ApplicationDefinition $definition): bool => $definition->renderMode === $renderMode))
      ->when($readiness !== '', fn ($items) => $items->filter(fn (ApplicationDefinition $definition): bool => ($definition->isReady() ? 'ready' : 'invalid') === $readiness))
      ->when($search !== '', fn ($items) => $items->filter(fn (ApplicationDefinition $definition): bool => str_contains(mb_strtolower($definition->handle.' '.$definition->name.' '.$definition->description), $search)))
      ->map(fn (ApplicationDefinition $definition): array => $this->present($definition, includeSchema: false))
      ->values();

    return $this->ok([
      'applications' => $applications,
      'count' => $applications->count(),
    ]);
  }

  public function show(string $application): JsonResponse
  {
    $definition = $this->registry->find($application);

    if (! $definition) {
      return $this->notFound($application);
    }

    return $this->ok(['application' => $this->present($definition)]);
  }

  public function schema(string $application): JsonResponse
  {
    $definition = $this->registry->find($application);

    if (! $definition) {
      return $this->notFound($application);
    }

    return $this->ok([
      'application' => [
        'handle' => $definition->handle,
        'name' => $definition->name,
        'version' => $definition->version,
        'readiness' => $definition->toArray(false)['readiness'],
      ],
      'settings_schema' => $definition->settingsSchema,
    ]);
  }

  public function store(Request $request): JsonResponse
  {
    $application = EmbeddedApplication::query()->create($this->validatedPayload($request));

    return response()->json(['ok' => true, 'application' => $this->present($this->registry->find($application->handle)), 'warnings' => [], 'errors' => []], 201);
  }

  public function update(Request $request, string $application): JsonResponse
  {
    $record = EmbeddedApplication::query()->where('handle', $application)->first();
    if (! $record) {
      return $this->notFound($application);
    }
    $record->update($this->validatedPayload($request, $record, partial: true));

    return $this->ok(['application' => $this->present($this->registry->find($record->handle))]);
  }

  public function destroy(string $application, DeleteEmbeddedApplication $action): JsonResponse
  {
    $record = EmbeddedApplication::query()->where('handle', $application)->first();
    if (! $record) {
      return $this->notFound($application);
    }
    $action->handle($record);

    return $this->ok(['deleted_application' => $application]);
  }

  private function validatedPayload(Request $request, ?EmbeddedApplication $application = null, bool $partial = false): array
  {
    $required = $partial ? 'sometimes' : 'required';
    $data = $request->validate([
      'handle' => [$required, 'string', 'max:64', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', Rule::unique(EmbeddedApplication::class, 'handle')->ignore($application)],
      'name' => [$required, 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
      'version' => [$required, 'string', 'max:64'], 'render_mode' => [$required, Rule::in(['inline', 'iframe'])],
      'entry_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:/^\/(?!\/)[^\s]*$/'],
      'mount_element' => ['sometimes', 'nullable', Rule::in(['div', 'section', 'canvas'])],
      'mount_classes' => ['sometimes', 'nullable', 'string', 'max:512', 'regex:/^[A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*$/'],
      'css_assets' => ['sometimes', 'array', 'max:20'], 'css_assets.*' => ['string', 'max:2048', 'regex:/^\/(?!\/)[^\s]*$/'],
      'js_assets' => ['sometimes', 'array', 'max:20'], 'js_assets.*.path' => ['required', 'string', 'max:2048', 'regex:/^\/(?!\/)[^\s]*$/'],
      'js_assets.*.type' => ['required', Rule::in(['classic', 'module'])], 'js_assets.*.load_position' => ['required', Rule::in(['head', 'body_end'])],
      'supports' => ['sometimes', 'array:locale,theme,fullscreen'],
      'supports.*' => ['boolean'],
      'settings_schema' => ['sometimes', 'array', 'max:20'],
      'settings_schema.*' => ['array:type,default,values,min,max,max_length'],
      'settings_schema.*.type' => ['required', Rule::in(['boolean', 'enum', 'integer', 'string'])],
      'settings_schema.*.values' => ['required_if:settings_schema.*.type,enum', 'array', 'min:1', 'max:50'],
      'settings_schema.*.min' => ['integer'], 'settings_schema.*.max' => ['integer'],
      'settings_schema.*.max_length' => ['integer', 'min:1', 'max:10000'],
      'is_enabled' => ['sometimes', 'boolean'],
    ]);

    foreach (array_keys($data['settings_schema'] ?? []) as $key) {
      if (! is_string($key) || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1) {
        abort(422, 'Application setting keys must use safe snake_case identifiers.');
      }
    }

    $mode = $data['render_mode'] ?? $application?->render_mode;
    if ($mode === 'iframe' && trim((string) ($data['entry_url'] ?? $application?->entry_url)) === '') {
      abort(422, 'Iframe applications require entry_url.');
    }

    return $data;
  }

  private function present(ApplicationDefinition $definition, bool $includeSchema = true): array
  {
    return [
      ...$definition->toArray($includeSchema),
      '_links' => [
        'self' => '/webadmin/api/applications/'.$definition->handle,
        'schema' => '/webadmin/api/applications/'.$definition->handle.'/schema',
      ],
    ];
  }

  private function notFound(string $handle): JsonResponse
  {
    return response()->json([
      'ok' => false,
      'code' => 'application_not_found',
      'message' => 'Embedded Application is not registered.',
      'application_handle' => $handle,
      'warnings' => [],
      'errors' => [
        [
          'path' => 'application',
          'message' => 'Select a handle returned by GET /webadmin/api/applications.',
        ],
      ],
    ], 404);
  }

  private function ok(array $data): JsonResponse
  {
    return response()->json([
      'ok' => true,
      ...$data,
      'warnings' => [],
      'errors' => [],
    ]);
  }
}
