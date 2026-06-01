<?php

namespace App\Modules\Workspace\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkspaceUser extends Pivot
{
    public $timestamps = false;

    protected $fillable = ['workspace_id', 'user_id', 'role'];
}
