<?php
namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Only Super Admin can view platform-wide audit logs.
        abort_unless(
            $request->user()?->hasRole('super_admin'),
            403,
            'You are not authorized to view audit logs.'
        );

        $validated = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'action' => ['sometimes', 'string', 'max:100'],
            'actor_id' => ['sometimes', 'integer', 'min:1'],
            'partner_id' => ['sometimes', 'integer', 'min:1'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditLog::query()
            ->with([
                'actor:id,name,email',
                'partner:id,name',
            ])
            ->latest('created_at');

        if (isset($validated['search'])) {
            $search = $validated['search'];

            $query->where(function ($builder) use ($search) {
                $builder->where('action', 'like', "%{$search}%")
                    ->orWhere('subject_type', 'like', "%{$search}%")
                    ->orWhereHas('actor', fn ($actor) => $actor
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('partner', fn ($partner) => $partner
                        ->where('name', 'like', "%{$search}%"));
            });
        }

        if (isset($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (isset($validated['actor_id'])) {
            $query->where('actor_id', $validated['actor_id']);
        }

        if (isset($validated['partner_id'])) {
            $query->where('partner_id', $validated['partner_id']);
        }

        if (isset($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }

        if (isset($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $logs = $query
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return response()->json($logs);
    }
}
