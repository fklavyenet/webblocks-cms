<?php

namespace WebBlocks\Cms\Http\Controllers\Mcp;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use WebBlocks\Cms\Http\Requests\Mcp\AuthorizeMcpRequest;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Policies\McpPagePolicy;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\Mcp\McpGrants;

final class McpOAuthController
{
  public function __construct(
    private readonly McpConfiguration $config,
    private readonly McpGrants $grants,
    private readonly McpPagePolicy $policy,
  ) {}

  public function resourceMetadata(): JsonResponse
  {
    return response()->json([
      'resource' => $this->config->resource(),
      'authorization_servers' => [$this->config->resource()],
      'scopes_supported' => [McpConfiguration::READ, McpConfiguration::WRITE],
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
      'response_types_supported' => ['code'],
      'grant_types_supported' => ['authorization_code'],
      'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
      'code_challenge_methods_supported' => ['S256'],
      'scopes_supported' => [McpConfiguration::READ, McpConfiguration::WRITE],
      'authorization_response_iss_parameter_supported' => true,
    ]);
  }

  public function authorize(AuthorizeMcpRequest $request): Response
  {
    $data = $request->validated();
    $user = $request->user();
    abort_unless($user instanceof User, 403);
    $write = in_array(McpConfiguration::WRITE, explode(' ', $data['scope'] ?? ''), true);

    if ($request->isMethod('GET')) {
      $sites = Site::query()->whereIn('id', $user->accessibleSiteIds())->orderBy('name')->get(['id', 'name'])
        ->filter(fn (Site $site): bool => $this->allowed($user, $site->id, $write));
      abort_if($sites->isEmpty(), 403);

      $client = parse_url($this->config->redirectUri(), PHP_URL_HOST);

      return response()->view('webblocks-cms::auth.mcp-consent', compact('sites', 'write', 'data', 'client'), headers: [
        'Content-Security-Policy' => "frame-ancestors 'none'; form-action 'self'; base-uri 'none'",
        'X-Frame-Options' => 'DENY',
      ]);
    }

    $result = ['error' => 'access_denied'];
    if ($data['decision'] === 'allow') {
      $siteId = (int) $data['site_id'];
      abort_unless($this->allowed($user, $siteId, $write), 403);
      $result = ['code' => $this->grants->issue($user, $siteId, $write, $data['code_challenge'])];
    }
    $result += ['iss' => $this->config->resource(), 'state' => $data['state'] ?? null];

    return redirect()->away($this->config->redirectUri().'?'.http_build_query($result, '', '&', PHP_QUERY_RFC3986), 303);
  }

  public function token(Request $request): JsonResponse
  {
    // OAuth credentials are accepted in the Authorization header or form body.
    $form = $request->request->all();
    $basic = $request->getUser() !== null;
    $id = $basic ? urldecode((string) $request->getUser()) : ($form['client_id'] ?? '');
    $secret = $basic ? urldecode((string) $request->getPassword()) : ($form['client_secret'] ?? '');

    if (($basic && (isset($form['client_id']) || isset($form['client_secret'])))
      || ! is_string($id) || ! is_string($secret)
      || ! hash_equals($this->config->clientId(), $id)
      || ! hash_equals($this->config->clientSecret(), $secret)) {
      return response()->json(['error' => 'invalid_client'], 401, ['WWW-Authenticate' => 'Basic realm="WebBlocks CMS MCP"']);
    }

    if (($form['grant_type'] ?? null) !== 'authorization_code') {
      return response()->json(['error' => 'unsupported_grant_type'], 400);
    }

    $validator = Validator::make($form, [
      'code' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
      'code_verifier' => ['required', 'string', 'regex:/\A[A-Za-z0-9._~-]{43,128}\z/'],
      'redirect_uri' => ['required', 'string', Rule::in([$this->config->redirectUri()])],
      'resource' => ['nullable', 'string', Rule::in([$this->config->resource()])],
    ]);
    if ($validator->fails()) {
      return response()->json(['error' => 'invalid_grant'], 400);
    }

    $token = $this->grants->exchange($form['code'], $form['code_verifier']);

    return $token === null ? response()->json(['error' => 'invalid_grant'], 400) : response()->json($token);
  }

  private function allowed(User $user, int $siteId, bool $write): bool
  {
    return $write ? $this->policy->write($user, $siteId) : $this->policy->read($user, $siteId);
  }
}
