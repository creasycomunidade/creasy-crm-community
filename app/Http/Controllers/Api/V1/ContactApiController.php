<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\ContactResource;
use App\Modules\Shared\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactApiController extends WorkspaceScopedController
{
    /**
     * GET /api/v1/contacts
     * Cursor-paginated; filterable by segment_id, tag, search, opt_in_whatsapp.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'segment_id' => ['sometimes', 'integer', 'min:1'],
            'tag' => ['sometimes', 'string', 'max:100'],
            'opt_in_whatsapp' => ['sometimes', 'boolean'],
        ]);

        $wsId = $this->workspaceId($request);

        $query = Contact::with('tags')
            ->where('workspace_id', $wsId)
            ->latest('id');

        if ($request->filled('search')) {
            $q = '%'.$request->search.'%';
            $query->where(function ($q2) use ($q) {
                $q2->where('first_name', 'like', $q)
                    ->orWhere('last_name', 'like', $q)
                    ->orWhere('email', 'like', $q)
                    ->orWhere('phone_e164', 'like', $q);
            });
        }

        if ($request->filled('segment_id')) {
            $query->whereHas('segments', fn ($s) => $s->where('segments.id', (int) $request->segment_id));
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn ($t) => $t->where('name', $request->tag));
        }

        if ($request->filled('opt_in_whatsapp')) {
            $query->where('opt_in_whatsapp', filter_var($request->opt_in_whatsapp, FILTER_VALIDATE_BOOLEAN));
        }

        return ContactResource::collection($query->cursorPaginate(25));
    }

    /**
     * POST /api/v1/contacts
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_e164' => ['nullable', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'language' => ['nullable', 'string', 'max:10'],
            'opt_in_whatsapp' => ['nullable', 'boolean'],
            'opt_in_sms' => ['nullable', 'boolean'],
            'opt_in_email' => ['nullable', 'boolean'],
            'custom_fields' => ['nullable', 'array', 'max:50'],
            'custom_fields.*' => ['nullable', 'string', 'max:1000'],
            'social_profiles' => ['nullable', 'array:instagram,telegram,facebook,linkedin,tiktok,x,youtube'],
            'social_profiles.instagram' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._-]+$/'],
            'social_profiles.telegram' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'social_profiles.tiktok' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._-]+$/'],
            'social_profiles.x' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'social_profiles.facebook' => ['nullable', 'url:http,https', 'max:2048'],
            'social_profiles.linkedin' => ['nullable', 'url:http,https', 'max:2048'],
            'social_profiles.youtube' => ['nullable', 'url:http,https', 'max:2048'],
            'contact_links' => ['nullable', 'array', 'max:10'],
            'contact_links.*.label' => ['required_with:contact_links.*.url', 'nullable', 'string', 'max:64'],
            'contact_links.*.url' => ['required_with:contact_links.*.label', 'nullable', 'url:http,https', 'max:2048'],
            'source' => ['nullable', 'string', 'max:100'],
        ]);

        $contact = Contact::create(array_merge($validated, ['workspace_id' => $this->workspaceId($request)]));
        $contact->load('tags');

        return (new ContactResource($contact))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/contacts/{id}
     */
    public function show(Request $request, int $id): ContactResource|JsonResponse
    {
        $contact = Contact::with('tags')
            ->where('workspace_id', $this->workspaceId($request))
            ->find($id);

        if (! $contact) {
            return response()->json(['error' => 'Contact not found.'], 404);
        }

        return new ContactResource($contact);
    }

    /**
     * PATCH /api/v1/contacts/{id}
     */
    public function update(Request $request, int $id): ContactResource|JsonResponse
    {
        $contact = Contact::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $contact) {
            return response()->json(['error' => 'Contact not found.'], 404);
        }

        $validated = $request->validate([
            'phone_e164' => ['sometimes', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
            'email' => ['sometimes', 'email', 'max:255'],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'country' => ['sometimes', 'string', 'size:2'],
            'language' => ['sometimes', 'string', 'max:10'],
            'opt_in_whatsapp' => ['sometimes', 'boolean'],
            'opt_in_sms' => ['sometimes', 'boolean'],
            'opt_in_email' => ['sometimes', 'boolean'],
            'custom_fields' => ['sometimes', 'array', 'max:50'],
            'custom_fields.*' => ['sometimes', 'string', 'max:1000'],
            'social_profiles' => ['sometimes', 'nullable', 'array:instagram,telegram,facebook,linkedin,tiktok,x,youtube'],
            'social_profiles.instagram' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._-]+$/'],
            'social_profiles.telegram' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'social_profiles.tiktok' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9._-]+$/'],
            'social_profiles.x' => ['nullable', 'string', 'max:64', 'regex:/^@?[A-Za-z0-9_]+$/'],
            'social_profiles.facebook' => ['nullable', 'url:http,https', 'max:2048'],
            'social_profiles.linkedin' => ['nullable', 'url:http,https', 'max:2048'],
            'social_profiles.youtube' => ['nullable', 'url:http,https', 'max:2048'],
            'contact_links' => ['sometimes', 'nullable', 'array', 'max:10'],
            'contact_links.*.label' => ['required_with:contact_links.*.url', 'nullable', 'string', 'max:64'],
            'contact_links.*.url' => ['required_with:contact_links.*.label', 'nullable', 'url:http,https', 'max:2048'],
            'source' => ['sometimes', 'string', 'max:100'],
        ]);

        $contact->update($validated);
        $contact->load('tags');

        return new ContactResource($contact);
    }

    /**
     * DELETE /api/v1/contacts/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $contact = Contact::where('workspace_id', $this->workspaceId($request))->find($id);

        if (! $contact) {
            return response()->json(['error' => 'Contact not found.'], 404);
        }

        $contact->delete();

        return response()->json(['ok' => true]);
    }
}
