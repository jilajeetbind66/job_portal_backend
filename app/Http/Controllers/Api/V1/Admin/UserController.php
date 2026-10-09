<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with(['partner', 'roles'])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $request->validate([
                'status' => [
                    'string',
                    Rule::in(['active', 'suspended']),
                ],
            ]);

            $query->where(
                'status',
                $request->string('status')->toString()
            );
        }

        if ($request->filled('role')) {
            $request->validate([
                'role' => [
                    'string',
                    Rule::in([
                        'candidate',
                        'partner_recruiter',
                        'partner_admin',
                        'super_admin',
                    ]),
                ],
            ]);

            $role = $request->string('role')->toString();

            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($request->filled('partner_id')) {
            $query->where(
                'partner_id',
                $request->integer('partner_id')
            );
        }

        $users = $query
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $role = $validated['role'];
        $partnerId = $validated['partner_id'] ?? null;

        /*
        |--------------------------------------------------------------------------
        | Partner Admin restrictions
        |--------------------------------------------------------------------------
        */

        if ($request->user()->hasRole('partner_admin')) {
            $partnerId = $request->user()->partner_id;

            if (!in_array($role, [
                'candidate',
                'partner_recruiter',
            ], true)) {
                return response()->json([
                    'message' => 'Partner Admin cannot assign this role.',
                ], 403);
            }

            if ($partnerId === null) {
                return response()->json([
                    'message' => 'Partner users must belong to a partner.',
                ], 422);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Partner role must have a partner
        |--------------------------------------------------------------------------
        */

        if ($role !== 'candidate' && $partnerId === null) {
            return response()->json([
                'message' => 'This role requires a partner.',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Candidate is a global user
        |--------------------------------------------------------------------------
        */

        if ($role === 'candidate') {
            $partnerId = null;
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'partner_id' => $partnerId,
            'status' => 'active',
        ]);

        $user->assignRole($role);

        $user->load(['partner', 'roles']);

        $this->auditLogService->log(
            action: 'user.created',
            subject: $user,
            meta: [
                'role' => $role,
                'status' => $user->status,
            ],
            partnerId: $user->partner_id,
        );

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        $user->load(['partner', 'roles']);

        return new UserResource($user);
    }

    public function update(
        UpdateUserRequest $request,
        User $user
    ): UserResource {
        $this->authorize('update', $user);

        $validated = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Partner Admin can only manage own partner
        |--------------------------------------------------------------------------
        */

        if ($request->user()->hasRole('partner_admin')) {
            $validated['partner_id'] = $request->user()->partner_id;
        }

        $role = $validated['role'] ?? null;

        if ($role !== null) {

            /*
            |--------------------------------------------------------------------------
            | Partner Admin role restrictions
            |--------------------------------------------------------------------------
            */

            if (
                $request->user()->hasRole('partner_admin')
                && !in_array($role, [
                    'partner_recruiter',
                    'candidate',
                ], true)
            ) {
                abort(
                    403,
                    'Partner Admin cannot assign this role.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Candidate must remain global
            |--------------------------------------------------------------------------
            */

            if ($role === 'candidate') {
                $validated['partner_id'] = null;
            }

            /*
            |--------------------------------------------------------------------------
            | Partner roles require partner
            |--------------------------------------------------------------------------
            */

            if (
                $role !== 'candidate'
                && ($validated['partner_id'] ?? $user->partner_id) === null
            ) {
                abort(
                    422,
                    'This role requires a partner.'
                );
            }
        }

        $user->update([
            'name' => $validated['name'] ?? $user->name,

            'email' => $validated['email'] ?? $user->email,

            'phone' => array_key_exists('phone', $validated)
                ? $validated['phone']
                : $user->phone,

            'partner_id' => array_key_exists('partner_id', $validated)
                ? $validated['partner_id']
                : $user->partner_id,
        ]);

        if ($role !== null) {
            $user->syncRoles([$role]);
        }

        $user->load(['partner', 'roles']);

        $this->auditLogService->log(
            action: 'user.updated',
            subject: $user,
            meta: [
                'role' => $role,
                'status' => $user->status,
            ],
            partnerId: $user->partner_id,
        );

        return new UserResource($user);
    }

    public function changeStatus(
        Request $request,
        User $user
    ): UserResource|JsonResponse {
        $this->authorize('changeStatus', $user);

        $validated = $request->validate([
            'status' => [
                'required',
                Rule::in(['active', 'suspended']),
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | User cannot suspend own account
        |--------------------------------------------------------------------------
        */

        if (
            $request->user()->id === $user->id
            && $validated['status'] === 'suspended'
        ) {
            return response()->json([
                'message' => 'You cannot suspend your own account.',
            ], 422);
        }

        $oldStatus = $user->status;

        $user->update([
            'status' => $validated['status'],
        ]);

        $user->load(['partner', 'roles']);

        $this->auditLogService->log(
            action: 'user.status_changed',
            subject: $user,
            meta: [
                'old_status' => $oldStatus,
                'new_status' => $user->status,
            ],
            partnerId: $user->partner_id,
        );

        return new UserResource($user);
    }
}