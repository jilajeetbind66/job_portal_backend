<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PartnerRecruiterController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasAnyRole(['partner_admin', 'partner_recruiter']),
            403,
            'You are not authorized to view partner recruiters.'
        );

        $query = User::query()
            ->where('partner_id', $user->partner_id)
            ->with('roles')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role') && $request->get('role') !== 'all') {
            $role = $request->string('role')->toString();
            $query->whereHas('roles', fn ($q) => $q->where('name', $role));
        }

        if ($request->filled('status') && $request->get('status') !== 'all') {
            $query->where('status', $request->string('status')->toString());
        }

        $perPage = $request->integer('per_page', 10);
        $paginated = $query->paginate($perPage)->withQueryString();

        // Compute counts
        $allMembers = User::where('partner_id', $user->partner_id)->with('roles')->get();
        $counts = [
            'total' => $allMembers->count(),
            'admins' => $allMembers->filter(fn ($u) => $u->hasRole('partner_admin'))->count(),
            'recruiters' => $allMembers->filter(fn ($u) => $u->hasRole('partner_recruiter'))->count(),
            'active' => $allMembers->where('status', 'active')->count(),
            'suspended' => $allMembers->where('status', 'suspended')->count(),
        ];

        // Format members
        $firstAdminId = $allMembers->filter(fn ($u) => $u->hasRole('partner_admin'))->sortBy('id')->first()?->id;

        $items = collect($paginated->items())->map(function ($member) use ($firstAdminId) {
            $role = $member->roles->first()?->name ?? 'partner_recruiter';
            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $role,
                'status' => $member->status,
                'avatar' => null,
                'phone' => $member->phone,
                'department' => 'Talent Acquisition',
                'created_at' => $member->created_at?->toISOString(),
                'is_owner' => $member->id === $firstAdminId,
            ];
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'counts' => $counts,
            ],
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasRole('partner_admin'),
            403,
            'Only Partner Admins can invite team members.'
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['sometimes', 'string', Rule::in(['partner_admin', 'partner_recruiter'])],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $role = $validated['role'] ?? 'partner_recruiter';

        $newMember = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make(Str::random(32)),
            'partner_id' => $user->partner_id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $newMember->assignRole($role);

        // Send reset / setup password link if configured
        try {
            Password::sendResetLink(['email' => $newMember->email]);
        } catch (\Throwable) {
            // Ignored in test / demo environments
        }

        $this->auditLogService->log(
            action: 'partner.recruiter_invited',
            subject: $newMember,
            meta: [
                'name' => $newMember->name,
                'email' => $newMember->email,
                'role' => $role,
            ],
            partnerId: $user->partner_id
        );

        return response()->json([
            'message' => "Invitation successfully sent to {$newMember->email}",
            'data' => [
                'id' => $newMember->id,
                'name' => $newMember->name,
                'email' => $newMember->email,
                'role' => $role,
                'status' => 'active',
                'avatar' => null,
                'department' => $validated['department'] ?? 'Talent Acquisition',
                'created_at' => $newMember->created_at?->toISOString(),
                'is_owner' => false,
            ],
        ], 201);
    }

    public function updateRole(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasRole('partner_admin'),
            403,
            'Only Partner Admins can change member roles.'
        );

        $member = User::where('partner_id', $user->partner_id)->findOrFail($id);

        if ($member->id === $user->id) {
            return response()->json([
                'message' => 'You cannot change your own role.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(['partner_admin', 'partner_recruiter'])],
        ]);

        $oldRole = $member->roles()->value('name');
        $member->syncRoles([$validated['role']]);

        $this->auditLogService->log(
            action: 'partner.recruiter_role_updated',
            subject: $member,
            meta: [
                'old_role' => $oldRole,
                'new_role' => $validated['role'],
            ],
            partnerId: $user->partner_id
        );

        return response()->json([
            'message' => 'Role updated successfully.',
            'data' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $validated['role'],
                'status' => $member->status,
            ],
        ]);
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasRole('partner_admin'),
            403,
            'Only Partner Admins can change member status.'
        );

        $member = User::where('partner_id', $user->partner_id)->findOrFail($id);

        if ($member->id === $user->id) {
            return response()->json([
                'message' => 'Primary account owner cannot be suspended.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['active', 'suspended'])],
        ]);

        $member->update(['status' => $validated['status']]);

        $this->auditLogService->log(
            action: 'partner.recruiter_status_updated',
            subject: $member,
            meta: ['status' => $validated['status']],
            partnerId: $user->partner_id
        );

        return response()->json([
            'message' => "User status changed to {$validated['status']}.",
            'data' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->roles()->value('name'),
                'status' => $member->status,
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasRole('partner_admin'),
            403,
            'Only Partner Admins can remove members.'
        );

        $member = User::where('partner_id', $user->partner_id)->findOrFail($id);

        if ($member->id === $user->id) {
            return response()->json([
                'message' => 'Cannot remove primary partner owner.',
            ], 403);
        }

        $this->auditLogService->log(
            action: 'partner.recruiter_removed',
            subject: $member,
            meta: ['email' => $member->email],
            partnerId: $user->partner_id
        );

        $member->delete();

        return response()->json([
            'message' => 'Recruiter removed from organization.',
        ], 200);
    }
}

