<?php

namespace App\Modules\Workspace\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workspace\Models\Workspace;
use App\Modules\Workspace\Requests\CreateWorkspaceRequest;
use App\Modules\Workspace\Requests\InviteMemberRequest;
use App\Modules\Workspace\Requests\UpdateWorkspaceRequest;
use App\Modules\Workspace\Resources\WorkspaceResource;
use App\Modules\Workspace\Services\WorkspaceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WorkspaceService $workspaceService) {}

    public function index(Request $request): JsonResponse
    {
        $workspaces = $this->workspaceService->listForUser($request->user());

        return $this->success(WorkspaceResource::collection($workspaces));
    }

    public function store(CreateWorkspaceRequest $request): JsonResponse
    {
        $workspace = $this->workspaceService->create($request->user(), $request->validated());

        return $this->created(new WorkspaceResource($workspace));
    }

    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->hasMember($request->user()), 403);

        return $this->success(new WorkspaceResource($workspace->load('members')));
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->hasMember($request->user()), 403);

        $workspace = $this->workspaceService->update($workspace, $request->validated());

        return $this->success(new WorkspaceResource($workspace));
    }

    public function inviteMember(InviteMemberRequest $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->owner_id === $request->user()->id, 403, 'Only workspace owner can invite members.');

        $this->workspaceService->inviteMember(
            $workspace,
            $request->validated('email'),
            $request->validated('role', 'viewer'),
        );

        return $this->success(['message' => 'Member invited successfully.']);
    }

    public function removeMember(Request $request, Workspace $workspace, int $userId): JsonResponse
    {
        abort_unless($workspace->owner_id === $request->user()->id, 403, 'Only workspace owner can remove members.');

        $this->workspaceService->removeMember($workspace, $userId);

        return $this->noContent();
    }
}
