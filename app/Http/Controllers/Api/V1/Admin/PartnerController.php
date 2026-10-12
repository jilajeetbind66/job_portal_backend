<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerAdminRequest;
use App\Http\Requests\Admin\StorePartnerRequest;
use App\Http\Requests\Admin\UpdatePartnerRequest;
use App\Http\Resources\PartnerResource;
use App\Http\Resources\UserResource;
use App\Models\Partner;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class PartnerController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Partner::class);

        $user = $request->user();

        $query = Partner::query()
            ->withCount('users')
            ->latest();

        if (!$user->hasRole('super_admin')) {
            $query->where('id', $user->partner_id);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('industry', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $partners = $query
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return PartnerResource::collection($partners);
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $this->authorize('create', Partner::class);
        $logoPath = $request->file('logo')?->storePublicly(
            'partner-logos',
            config('filesystems.partner_logo_disk')
        );

        $partner = Partner::create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'logo_path' => $logoPath,
            'industry' => $request->validated('industry'),
            'website' => $request->validated('website'),
            'about' => $request->validated('about'),
            'status' => 'active',
            'max_active_jobs' => $request->validated('max_active_jobs'),
            'created_by' => $request->user()->id,
        ]);

        $this->auditLogService->log(
            action: 'partner.created',
            subject: $partner,
            meta: [
                'name' => $partner->name,
                'slug' => $partner->slug,
                'status' => $partner->status,
            ],
            partnerId: $partner->id,
        );

        $partner->loadCount('users');

        return (new PartnerResource($partner))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Partner $partner): PartnerResource
    {
        $this->authorize('view', $partner);

        $partner->loadCount('users');

        return new PartnerResource($partner);
    }

    public function update(
        UpdatePartnerRequest $request,
        Partner $partner
    ): PartnerResource {
        $this->authorize('update', $partner);
        $validated = $request->validated();
        $logo = $request->file('logo');
        $logoDisk = config('filesystems.partner_logo_disk');
        $oldLogoPath = $partner->logo_path;

        unset($validated['logo']);

        if ($logo !== null) {
            $validated['logo_path'] = $logo->storePublicly(
                'partner-logos',
                $logoDisk
            );
        }

        $oldValues = $partner->only([
            'name',
            'slug',
            'logo_path',
            'industry',
            'website',
            'about',
            'max_active_jobs',
        ]);

        $partner->update($validated);

        if ($logo !== null && $oldLogoPath !== null) {
            Storage::disk($logoDisk)->delete($oldLogoPath);
        }

        $partner->refresh();

        $newValues = $partner->only(array_keys($oldValues));

        $changes = [];

        foreach ($newValues as $field => $newValue) {
            if (($oldValues[$field] ?? null) != $newValue) {
                $changes[$field] = [
                    'old' => $oldValues[$field] ?? null,
                    'new' => $newValue,
                ];
            }
        }

        if ($changes !== []) {
            $this->auditLogService->log(
                action: 'partner.updated',
                subject: $partner,
                meta: ['changes' => $changes],
                partnerId: $partner->id,
            );
        }

        $partner->loadCount('users');

        return new PartnerResource($partner);
    }

    public function changeStatus(
        Request $request,
        Partner $partner
    ): PartnerResource {
        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,suspended',
            ],
        ]);

        return $this->setStatus($partner, $validated['status']);
    }

    public function suspend(Partner $partner): PartnerResource
    {
        return $this->setStatus($partner, 'suspended');
    }

    public function reactivate(Partner $partner): PartnerResource
    {
        return $this->setStatus($partner, 'active');
    }

    public function createAdminUser(
        StorePartnerAdminRequest $request,
        Partner $partner
    ): JsonResponse {
        $this->authorize('createAdminUser', $partner);

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make(Str::random(64)),
            'partner_id' => $partner->id,
            'status' => 'active',
        ]);

        $user->assignRole('partner_admin');
        $user->load(['partner', 'roles']);

        Password::sendResetLink(['email' => $user->email]);

        $this->auditLogService->log(
            action: 'partner.admin_user_created',
            subject: $user,
            meta: ['role' => 'partner_admin'],
            partnerId: $partner->id,
        );

        return (new UserResource($user))
            ->additional(['message' => 'Partner Admin created and set-password link sent.'])
            ->response()
            ->setStatusCode(201);
    }

    private function setStatus(Partner $partner, string $newStatus): PartnerResource
    {
        $this->authorize('changeStatus', $partner);

        $oldStatus = $partner->status;

        DB::transaction(function () use ($partner, $newStatus, $oldStatus): void {
            $partner->update(['status' => $newStatus]);

            if ($newStatus === 'suspended' && $oldStatus !== 'suspended') {
                $userIds = $partner->users()->pluck('users.id');
                $sessionTable = config('session.table');

                if ($userIds->isNotEmpty()) {
                    if (Schema::hasTable($sessionTable)) {
                        DB::table($sessionTable)
                            ->whereIn('user_id', $userIds)
                            ->delete();
                    }

                    PersonalAccessToken::query()
                        ->where('tokenable_type', (new User())->getMorphClass())
                        ->whereIn('tokenable_id', $userIds)
                        ->delete();
                }
            }

            if ($oldStatus !== $newStatus) {
                $this->auditLogService->log(
                    action: 'partner.status_changed',
                    subject: $partner,
                    meta: [
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                    ],
                    partnerId: $partner->id,
                );
            }
        });

        $partner->loadCount('users');

        return new PartnerResource($partner);
    }
}
