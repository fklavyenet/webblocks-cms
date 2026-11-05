<?php

namespace WebBlocks\Cms\Http\Controllers\Mcp;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Http\Requests\Mcp\AuthorizeMcpRequest;
use WebBlocks\Cms\Http\Requests\Mcp\RegisterMcpClientRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\Mcp\McpGrants;
use WebBlocks\Cms\Support\Mcp\McpOAuthClient;
use WebBlocks\Cms\Support\Mcp\McpOAuthClients;

final class McpOAuthController
{
  public function __construct(
    private readonly McpConfiguration $config,
    private readonly McpGrants $grants,
    private readonly McpOAuthClients $clients,
    private readonly McpPagePolicy $policy,
  ) {}

  public function resourceMetadata(): JsonResponse
  {
    return response()->json([
      'resource' => $this->config->resource(),
      'authorization_servers' => [$this->config->resource()],
      'scopes_supported' => McpConfiguration::SCOPES,
      'bearer_methods_supported' => ['header'],
    ]);
  }

  public function serverMetadata(): JsonResponse
  {
    $issuer = $this->config->resource();

    return response()->json([
      'issuer' => $issuer,
      'authorization_endpoint' => $issuer.'/authorize',
      'token_endpoint' => $issuer.'/token',
      'registration_endpoint' => $issuer.'/register',
      'response_types_supported' => ['code'],
      'grant_types_supported' => ['authorization_code'],
      'token_endpoint_auth_methods_supported' => McpOAuthClient::AUTH_METHODS,
      'code_challenge_methods_supported' => ['S256'],
      'scopes_supported' => McpConfiguration::SCOPES,
      'authorization_response_iss_parameter_supported' => true,
    ]);
  }

  public function register(RegisterMcpClientRequest $request): JsonResponse
  {
    $data = $request->validated();
    $name = $data['client_name'] ?? null;
    $method = $data['token_endpoint_auth_method'] ?? McpOAuthClient::PUBLIC_AUTH;
    $secret = $method === McpOAuthClient::SECRET_POST ? bin2hex(random_bytes(32)) : null;
    $id = $this->clients->register($name, $data['redirect_uris'], $secret);

    return response()->json(array_filter([
      'client_id' => $id,
      'client_secret' => $secret,
      'client_secret_expires_at' => $secret === null ? null : 0,
      'client_name' => $name,
      'redirect_uris' => $data['redirect_uris'],
      // RFC 7591 allows the server to return only the grants it registered.
      'grant_types' => ['authorization_code'],
      'response_types' => ['code'],
      'token_endpoint_auth_method' => $method,
    ], fn (mixed $value): bool => $value !== null), 201);
  }

  public function authorize(AuthorizeMcpRequest $request): Response
  {
    $data = $request->validated();
    $user = $request->user();
    $client = $request->client();
    $redirect = $data['redirect_uri'];
    abort_unless($user instanceof User, 403);
    $scopes = explode(' ', $data['scope'] ?? '');
    $write = in_array(McpConfiguration::WRITE, $scopes, true);
    $editContent = in_array(McpConfiguration::CONTENT, $scopes, true);
    $createDraft = in_array(McpConfiguration::CREATE, $scopes, true);

    if ($request->isMethod('GET')) {
      $sites = Site::query()->whereIn('id', $user->accessibleSiteIds())->orderBy('name')->get(['id', 'name'])
        ->filter(fn (Site $site): bool => $this->allowed($user, $site->id, $write || $editContent, $createDraft));
      abort_if($sites->isEmpty(), 403);

      $callbackHost = parse_url($redirect, PHP_URL_HOST);
      $clientName = $client->name ?: $callbackHost;
      $port = parse_url($redirect, PHP_URL_PORT);
      $callbackAddress = $callbackHost.($port === null ? '' : ':'.$port);
      $callbackOrigin = parse_url($redirect, PHP_URL_SCHEME).'://'.$callbackAddress;

      return response()->view('webblocks-cms::auth.mcp-consent', compact('sites', 'write', 'editContent', 'createDraft', 'data', 'clientName', 'callbackAddress'), headers: [
        // Chromium applies form-action to the redirect after the consent POST.
        'Content-Security-Policy' => "frame-ancestors 'none'; form-action 'self' {$callbackOrigin}; base-uri 'none'",
        'X-Frame-Options' => 'DENY',
      ]);
    }

    $result = ['error' => 'access_denied'];
    if ($data['decision'] === 'allow') {
      $siteId = (int) $data['site_id'];
      abort_unless($this->allowed($user, $siteId, $write || $editContent, $createDraft), 403);
      $result = ['code' => $this->grants->issue($user, $siteId, $write, $editContent, $createDraft, $data['code_challenge'], $client, $redirect)];
    }
    $result += ['iss' => $this->config->resource(), 'state' => $data['state'] ?? null];

    $separator = str_contains($redirect, '?') ? '&' : '?';

    return redirect()->away($redirect.$separator.http_build_query($result, '', '&', PHP_QUERY_RFC3986), 303);
  }

  public function token(Request $request): JsonResponse
  {
    $form = $request->request->all();
    $id = $form['client_id'] ?? null;
    $client = is_string($id) ? $this->clients->find($id) : null;
    $secret = array_key_exists('client_secret', $form) ? ($form['client_secret'] ?? '') : null;

    if (! $client || $request->headers->has('Authorization') || ! $client->acceptsSecret($secret)) {
      return response()->json(['error' => 'invalid_client'], 400);
    }

    if (($form['grant_type'] ?? null) !== 'authorization_code') {
      return response()->json(['error' => 'unsupported_grant_type'], 400);
    }

    $validator = Validator::make($form, [
      'code' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
      'code_verifier' => ['required', 'string', 'regex:/\A[A-Za-z0-9._~-]{43,128}\z/'],
      'redirect_uri' => ['required', 'string', Rule::in($client->redirectUris)],
      'resource' => ['nullable', 'string', Rule::in([$this->config->resource()])],
    ]);
    if ($validator->fails()) {
      return response()->json(['error' => 'invalid_grant'], 400);
    }

    $token = $this->grants->exchange($form['code'], $form['code_verifier'], $client, $form['redirect_uri']);

    return $token === null ? response()->json(['error' => 'invalid_grant'], 400) : response()->json($token);
  }

  private function allowed(User $user, int $siteId, bool $write, bool $createDraft): bool
  {
    return ($write ? $this->policy->write($user, $siteId) : $this->policy->read($user, $siteId))
      && (! $createDraft || $this->policy->createDraft($user, $siteId));
  }
}
