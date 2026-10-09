<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerRequest;
use App\Http\Requests\Admin\UpdatePartnerRequest;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $partner = Partner::create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug'),
            'logo_path' => $request->validated('logo_path'),
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

        $oldValues = $partner->only([
            'name',
            'slug',
            'logo_path',
            'industry',
            'website',
            'about',
            'max_active_jobs',
        ]);

        $partner->update($request->validated());

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
        $this->authorize('changeStatus', $partner);

        $validated = $request->validate([
            'status' => [
                'required',
                'in:active,suspended',
            ],
        ]);

        $oldStatus = $partner->status;
        $newStatus = $validated['status'];

        $partner->update([
            'status' => $newStatus,
        ]);

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

        $partner->loadCount('users');

        return new PartnerResource($partner);
    }
}
