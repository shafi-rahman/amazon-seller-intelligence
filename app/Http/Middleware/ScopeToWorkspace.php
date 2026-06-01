<?php

namespace App\Http\Middleware;

use App\Modules\Workspace\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeToWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        $workspaceId = $request->route('workspace_id') ?? $request->route('workspace');

        if ($workspaceId instanceof Workspace) {
            $workspace = $workspaceId;
        } else {
            $workspace = Workspace::findOrFail($workspaceId);
        }

        if (!$workspace->hasMember($request->user())) {
            abort(403, 'You do not have access to this workspace.');
        }

        $request->attributes->set('workspace', $workspace);

        return $next($request);
    }
}
