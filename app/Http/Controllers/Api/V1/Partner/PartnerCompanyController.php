<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PartnerCompanyController extends Controller
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasAnyRole(['partner_admin', 'partner_recruiter']),
            403,
            'You are not authorized to view the company profile.'
        );

        $partner = Partner::findOrFail($user->partner_id);

        return response()->json([
            'data' => $this->formatCompanyProfile($partner),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasRole('partner_admin'),
            403,
            'Only Partner Admins can update the company profile.'
        );

        $partner = Partner::findOrFail($user->partner_id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:100'],
            'company_size' => ['nullable', 'string', 'max:50'],
            'website' => ['required', 'string', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:5000'],
            'founded_year' => ['nullable', 'integer', 'min:1900', 'max:' . (date('Y') + 1)],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'twitter' => ['nullable', 'string', 'max:255'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:255'],
        ]);

        $socialLinks = $partner->social_links ?? [];
        if (isset($validated['linkedin'])) {
            $socialLinks['linkedin'] = $validated['linkedin'];
        }
        if (isset($validated['twitter'])) {
            $socialLinks['twitter'] = $validated['twitter'];
        }

        $partner->update([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? $partner->display_name,
            'tagline' => $validated['tagline'] ?? $partner->tagline,
            'industry' => $validated['industry'] ?? $partner->industry,
            'company_size' => $validated['company_size'] ?? $partner->company_size,
            'website' => $validated['website'],
            'email' => $validated['email'] ?? $partner->email,
            'phone' => $validated['phone'] ?? $partner->phone,
            'location' => $validated['location'] ?? $partner->location,
            'address' => $validated['address'] ?? $partner->address,
            'about' => $validated['description'] ?? $partner->about,
            'founded_year' => $validated['founded_year'] ?? $partner->founded_year,
            'benefits' => $validated['benefits'] ?? $partner->benefits,
            'social_links' => $socialLinks,
        ]);

        $this->auditLogService->log(
            action: 'partner.profile_updated',
            subject: $partner,
            meta: ['name' => $partner->name],
            partnerId: $partner->id
        );

        return response()->json([
            'message' => 'Company profile successfully updated.',
            'data' => $this->formatCompanyProfile($partner->fresh()),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null
                && $user->partner_id !== null
                && $user->hasRole('partner_admin'),
            403,
            'Only Partner Admins can upload company logo.'
        );

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:5120'],
        ]);

        $partner = Partner::findOrFail($user->partner_id);
        $disk = config('filesystems.partner_logo_disk');

        if ($partner->logo_path) {
            Storage::disk($disk)->delete($partner->logo_path);
        }

        $path = $request->file('logo')->storePublicly('partner-logos', $disk);
        $partner->update(['logo_path' => $path]);

        $logoUrl = Storage::disk($disk)->url($path);

        $this->auditLogService->log(
            action: 'partner.logo_uploaded',
            subject: $partner,
            meta: ['path' => $path],
            partnerId: $partner->id
        );

        return response()->json([
            'message' => 'Logo uploaded successfully.',
            'logo_url' => $logoUrl,
        ]);
    }

    private function formatCompanyProfile(Partner $partner): array
    {
        $disk = config('filesystems.partner_logo_disk');
        $logoUrl = $partner->logo_path
            ? Storage::disk($disk)->url($partner->logo_path)
            : null;

        $social = $partner->social_links ?? [];

        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'display_name' => $partner->display_name ?: $partner->name,
            'tagline' => $partner->tagline,
            'industry' => $partner->industry,
            'company_size' => $partner->company_size,
            'website' => $partner->website,
            'email' => $partner->email,
            'phone' => $partner->phone,
            'location' => $partner->location,
            'address' => $partner->address,
            'description' => $partner->about,
            'logo_url' => $logoUrl,
            'banner_url' => null,
            'founded_year' => $partner->founded_year,
            'linkedin' => $social['linkedin'] ?? null,
            'twitter' => $social['twitter'] ?? null,
            'benefits' => $partner->benefits ?? [],
            'updated_at' => $partner->updated_at?->toISOString(),
        ];
    }
}

