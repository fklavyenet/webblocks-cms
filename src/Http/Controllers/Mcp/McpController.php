<?php

namespace WebBlocks\Cms\Http\Controllers\Mcp;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\Mcp\McpGrants;
use WebBlocks\Cms\Support\Mcp\McpToolException;
use WebBlocks\Cms\Support\Mcp\McpTools;

final class McpController
{
  private const VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];

  private const REQUEST_LIMIT = 16_384;

  private const UPLOAD_REQUEST_LIMIT = 768 * 1024;

  public function __construct(
    private readonly McpConfiguration $config,
    private readonly McpGrants $grants,
    private readonly McpTools $tools,
    private readonly McpPagePolicy $policy,
  ) {}

  public function __invoke(Request $request): Response
  {
    $origin = $request->header('Origin');
    if ($origin !== null && $origin !== $this->config->origin()) {
      return $this->error(null, -32600, 'Invalid Origin.', 403);
    }
    if (! $request->isMethod('POST')) {
      return response()->noContent(405, ['Allow' => 'POST']);
    }
    $body = $request->getContent();
    $size = strlen($body);
    if (! $request->isJson() || $size > self::UPLOAD_REQUEST_LIMIT) {
      return $this->error(null, -32600, 'Invalid request.', 400);
    }
    $version = $request->header('MCP-Protocol-Version');
    if ($version !== null && ! in_array($version, self::VERSIONS, true)) {
      return $this->error(null, -32600, 'Unsupported protocol version.', 400);
    }

    try {
      $message = json_decode($body, false, 20, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      if ($size > self::REQUEST_LIMIT) {
        return $this->error(null, -32600, 'Invalid request.', 400);
      }

      return $this->error(null, -32700, 'Parse error.', 400);
    }

    if ($size > self::REQUEST_LIMIT
      && (($message->method ?? null) !== 'tools/call' || ($message->params->name ?? null) !== McpTools::UPLOAD)) {
      return $this->error(null, -32600, 'Invalid request.', 400);
    }

    if (! $message instanceof stdClass || ($message->jsonrpc ?? null) !== '2.0'
      || ! is_string($message->method ?? null)
      || (isset($message->params) && ! $message->params instanceof stdClass)) {
      return $this->error(null, -32600, 'Invalid request.', 400);
    }
    if (! property_exists($message, 'id')) {
      return response()->noContent(202);
    }
    if (! is_string($message->id) && ! is_int($message->id)) {
      return $this->error(null, -32600, 'Invalid request ID.', 400);
    }
    $id = $message->id;

    return match ($message->method) {
      'initialize' => $this->initialize($id, $message->params ?? new stdClass),
      'ping' => $this->result($id, new stdClass),
      'tools/list' => $this->result($id, ['tools' => $this->tools->descriptors($this->config->availableScopes())]),
      'tools/call' => $this->callTool($id, $message->params ?? new stdClass, $request),
      default => $this->error($id, -32601, 'Method not found.'),
    };
  }

  private function initialize(string|int $id, stdClass $params): JsonResponse
  {
    if (! is_string($params->protocolVersion ?? null)) {
      return $this->error($id, -32602, 'A protocol version is required.');
    }

    return $this->result($id, [
      'protocolVersion' => in_array($params->protocolVersion, self::VERSIONS, true) ? $params->protocolVersion : self::VERSIONS[0],
      'capabilities' => ['tools' => new stdClass],
      'serverInfo' => ['name' => 'webblocks-cms', 'version' => '1.0.0'],
    ]);
  }

  private function callTool(string|int $id, stdClass $params, Request $request): JsonResponse
  {
    $name = $params->name ?? null;
    $scope = is_string($name) ? $this->tools->scope($name) : null;
    if ($scope === null || (isset($params->arguments) && ! $params->arguments instanceof stdClass)) {
      return $this->error($id, -32602, 'Invalid tool name or arguments.');
    }

    $grant = $this->grants->authenticate($request->bearerToken());
    $available = $this->config->availableScopes();
    if (! in_array($scope, $available, true)) {
      return $this->toolError($id, new McpToolException('scope_disabled'))->setStatusCode(403);
    }
    if (! $grant || ! in_array($scope, $grant['scopes'], true)) {
      $error = $grant ? 'insufficient_scope' : 'invalid_token';
      $required = $scope === McpConfiguration::READ ? $scope : McpConfiguration::READ.' '.$scope;
      $challenge = 'Bearer resource_metadata="'.$this->config->origin().McpConfiguration::RESOURCE_METADATA
        .'", scope="'.$required.'", error="'.$error.'", error_description="WebBlocks CMS authorization is required"';

      return $this->toolError($id, new McpToolException('auth_required'), ['mcp/www_authenticate' => [$challenge]])
        ->setStatusCode($grant ? 403 : 401)->header('WWW-Authenticate', $challenge);
    }
    if (! $this->policy->allowsScopes($grant['user'], $grant['site'], [$scope])) {
      return $this->toolError($id, new McpToolException('forbidden'));
    }
    $grant['scopes'] = array_intersect($grant['scopes'], $available);

    try {
      $data = $this->tools->call($name, (array) ($params->arguments ?? []), $grant);
    } catch (McpToolException $exception) {
      return $this->toolError($id, $exception);
    }

    return $this->result($id, [
      'content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]],
      'structuredContent' => $data,
    ]);
  }

  private function toolError(string|int $id, McpToolException $exception, array $meta = []): JsonResponse
  {
    $result = [
      'isError' => true,
      'content' => [['type' => 'text', 'text' => $exception->getMessage()]],
      'structuredContent' => ['code' => $exception->reason],
    ];

    return $this->result($id, $meta === [] ? $result : $result + ['_meta' => $meta]);
  }

  private function result(string|int $id, array|stdClass $result): JsonResponse
  {
    return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
  }

  private function error(string|int|null $id, int $code, string $message, int $status = 200): JsonResponse
  {
    return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => compact('code', 'message')], $status);
  }
}
