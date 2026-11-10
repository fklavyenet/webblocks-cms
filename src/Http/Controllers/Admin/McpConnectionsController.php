<?php

namespace WebBlocks\Cms\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\Mcp\McpGrants;

final class McpConnectionsController
{
  public function index(Request $request, McpConfiguration $config, McpGrants $grants): View
  {
    return view('webblocks-cms::admin.profile.mcp-connections', [
      'connections' => $config->hasOAuthSchema() ? $grants->connections($request->user()) : null,
      'enabled' => $config->enabled(),
    ]);
  }

  public function destroy(Request $request, string $connection, McpConfiguration $config, McpGrants $grants): RedirectResponse
  {
    $grantId = filter_var($connection, FILTER_VALIDATE_INT);
    abort_unless($grantId !== false && $config->hasOAuthSchema() && $grants->revoke($request->user(), $grantId), 404);

    return redirect()->route('admin.profile.mcp-connections.index')->with('status', __('webblocks-cms::mcp.revoked'));
  }
}
