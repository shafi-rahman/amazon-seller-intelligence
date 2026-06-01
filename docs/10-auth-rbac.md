# Auth & RBAC

---

## Authentication

### Technology
**Laravel Sanctum** — SPA session-based authentication (cookie + CSRF token).

Not token-based (no Bearer tokens). This is the recommended approach for a first-party Vue SPA on the same domain.

### Session Flow

```
1. Frontend loads app at http://localhost (or domain)
2. GET /sanctum/csrf-cookie → sets XSRF-TOKEN cookie
3. POST /api/v1/auth/login
   → validates credentials
   → creates session
   → sets session cookie (laravel_session)
4. All subsequent requests send:
   - Cookie: laravel_session=...
   - X-XSRF-TOKEN: {value from XSRF-TOKEN cookie}
5. POST /api/v1/auth/logout → destroys session
```

### Auth Middleware

```php
// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    // All authenticated routes
});
```

### Session Config

```php
// config/session.php
'driver'         => 'redis',
'lifetime'       => 120,        // minutes
'expire_on_close' => false,
'encrypt'        => true,
'http_only'      => true,
'same_site'      => 'lax',
```

---

## Roles

The platform uses **Spatie Laravel Permission** for RBAC.

### Roles

| Role | Description |
|------|-------------|
| `platform_admin` | Full system access. Can manage all workspaces and users. |
| `workspace_admin` | Full access within their workspace. Can invite users and manage workspace settings. |
| `seller` | Default role. Can do everything within their workspace except admin actions. |
| `accountant` | Read-only access to financial data. Can run reconciliation and export reports. |
| `agency` | Can manage listings and run product analysis. Cannot access financial data. |

### Permissions

| Permission | platform_admin | workspace_admin | seller | accountant | agency |
|-----------|:--------------:|:---------------:|:------:|:----------:|:------:|
| `workspace.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `workspace.manage` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `workspace.invite` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `imports.upload` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `imports.view` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `orders.view` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `settlements.view` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `bank.view` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `gst.view` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `reconciliation.run` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `reconciliation.view` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `reports.export` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `products.view` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `products.manage` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `competitors.manage` | ✅ | ✅ | ✅ | ❌ | ✅ |
| `ai.chat` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `admin.users` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `admin.platform` | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## Permission Seeder

```php
// database/seeders/RolePermissionSeeder.php

$permissions = [
    'workspace.view', 'workspace.manage', 'workspace.invite',
    'imports.upload', 'imports.view',
    'orders.view', 'settlements.view', 'bank.view', 'gst.view',
    'reconciliation.run', 'reconciliation.view',
    'reports.export',
    'products.view', 'products.manage',
    'competitors.manage',
    'ai.chat',
    'admin.users', 'admin.platform',
];

foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission]);
}

$roles = [
    'platform_admin' => $permissions,  // all
    'workspace_admin' => array_diff($permissions, ['admin.users', 'admin.platform']),
    'seller' => [
        'workspace.view', 'imports.upload', 'imports.view',
        'orders.view', 'settlements.view', 'bank.view', 'gst.view',
        'reconciliation.run', 'reconciliation.view', 'reports.export',
        'products.view', 'products.manage', 'competitors.manage', 'ai.chat',
    ],
    'accountant' => [
        'workspace.view', 'imports.upload', 'imports.view',
        'orders.view', 'settlements.view', 'bank.view', 'gst.view',
        'reconciliation.run', 'reconciliation.view', 'reports.export', 'ai.chat',
    ],
    'agency' => [
        'workspace.view', 'imports.upload', 'imports.view',
        'products.view', 'products.manage', 'competitors.manage',
        'reports.export', 'ai.chat',
    ],
];

foreach ($roles as $roleName => $rolePermissions) {
    $role = Role::firstOrCreate(['name' => $roleName]);
    $role->syncPermissions($rolePermissions);
}
```

---

## Controller Usage

### Gate Check (preferred for single permissions)
```php
public function runReconciliation(Request $request): JsonResponse
{
    $this->authorize('reconciliation.run');
    // ...
}
```

### Policy Check (preferred for model-scoped checks)
```php
// In ReconciliationPolicy
public function run(User $user, Workspace $workspace): bool
{
    return $user->can('reconciliation.run')
        && $workspace->hasMember($user);
}
```

### Workspace Scoping Middleware
All workspace-scoped routes go through this middleware:

```php
// app/Http/Middleware/ScopeToWorkspace.php
public function handle(Request $request, Closure $next): Response
{
    $workspaceId = $request->route('workspace_id');
    $workspace = Workspace::findOrFail($workspaceId);

    if (!$workspace->hasMember($request->user())) {
        abort(403, 'You do not have access to this workspace.');
    }

    $request->attributes->set('workspace', $workspace);
    return $next($request);
}
```

---

## Audit Logging

Every significant action is recorded in `audit_logs`.

### Auto-logged Events

```php
// In App\Observers\AuditObserver
// Registered for: Order, Settlement, ImportBatch, ReconciliationRun, Product, Competitor

public function created(Model $model): void
{
    AuditLog::create([
        'user_id'     => auth()->id(),
        'action'      => 'created',
        'entity_type' => get_class($model),
        'entity_id'   => $model->id,
        'new_values'  => $model->getAttributes(),
        'ip_address'  => request()->ip(),
        'user_agent'  => request()->userAgent(),
    ]);
}
```

### Manually Logged Events

| Event | Action String |
|-------|--------------|
| Login | `auth.login` |
| Logout | `auth.logout` |
| File upload | `import.upload` |
| Reconciliation run | `reconciliation.run` |
| Report export | `report.export` |
| AI conversation started | `ai.conversation_started` |

---

## Security Headers

Applied globally via middleware:

```php
// Content-Security-Policy
// X-Frame-Options: DENY
// X-Content-Type-Options: nosniff
// Referrer-Policy: strict-origin-when-cross-origin
// Strict-Transport-Security (HSTS) — production only
```

---

## Password Policy

- Minimum 8 characters
- Must contain at least one letter and one number
- Bcrypt hashing (Laravel default, cost factor 12)
- Password reset via email link (60-minute expiry)

---

## Default Seeded Users

For development only (seeded by `DatabaseSeeder`):

| Email | Password | Role |
|-------|----------|------|
| admin@asip.local | password | platform_admin |
| seller@asip.local | password | seller |
| accountant@asip.local | password | accountant |
| agency@asip.local | password | agency |
