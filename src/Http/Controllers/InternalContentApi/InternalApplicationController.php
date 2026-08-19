<?php

namespace WebBlocks\Cms\Http\Controllers\InternalContentApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
