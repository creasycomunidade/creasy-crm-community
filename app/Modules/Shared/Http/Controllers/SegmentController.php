<?php

namespace App\Modules\Shared\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Shared\Models\Contact;
use App\Modules\Shared\Models\Segment;
use App\Modules\Shared\Services\SegmentResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    public function __construct(private SegmentResolver $resolver) {}

    public function index(Request $request): Response
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $segments = Segment::where('workspace_id', $workspaceId)->latest()->get();

        return Inertia::render('Contacts/Segments', ['segments' => $segments]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'type' => ['required', 'in:static,dynamic'],
            'rules_json' => ['nullable', 'array'],
        ]);

        $segment = Segment::create(array_merge($validated, ['workspace_id' => $workspaceId]));

        if ($segment->type === 'dynamic') {
            $this->resolver->materialise($segment);
        }

        return back()->with('success', 'Segmento criado.');
    }

    public function update(Request $request, Segment $segment): RedirectResponse
    {
        $this->authorise($request, $segment);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:128'],
            'rules_json' => ['nullable', 'array'],
        ]);
        $segment->update($validated);

        if ($segment->type === 'dynamic') {
            $this->resolver->materialise($segment);
        }

        return back()->with('success', 'Segmento atualizado.');
    }

    public function destroy(Request $request, Segment $segment): RedirectResponse
    {
        $this->authorise($request, $segment);
        $segment->contacts()->detach();
        $segment->delete();

        return back()->with('success', 'Segmento excluído.');
    }

    public function manageContacts(Request $request, Segment $segment): Response
    {
        $this->authorise($request, $segment);
        abort_if($segment->type !== 'static', 403, 'Only static segments support manual contact management.');

        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;

        $segmentContacts = $segment->contacts()
            ->orderBy('first_name')
            ->get(['contacts.id', 'contacts.uuid', 'first_name', 'last_name', 'phone_e164', 'email', 'avatar']);

        $allContacts = Contact::where('workspace_id', $workspaceId)
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%'.$request->search.'%')
                    ->orWhere('last_name', 'like', '%'.$request->search.'%')
                    ->orWhere('phone_e164', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%');
            }))
            ->whereNotIn('id', $segmentContacts->pluck('id'))
            ->orderBy('first_name')
            ->limit(50)
            ->get(['id', 'uuid', 'first_name', 'last_name', 'phone_e164', 'email', 'avatar']);

        return Inertia::render('Contacts/SegmentContacts', [
            'segment' => $segment,
            'segmentContacts' => $segmentContacts,
            'availableContacts' => $allContacts,
            'filters' => $request->only('search'),
        ]);
    }

    public function attachContacts(Request $request, Segment $segment): RedirectResponse
    {
        $this->authorise($request, $segment);
        abort_if($segment->type !== 'static', 403);

        $validated = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['integer', 'exists:contacts,id'],
        ]);

        $segment->contacts()->syncWithoutDetaching($validated['contact_ids']);
        $segment->update(['contact_count' => $segment->contacts()->count()]);

        return back()->with('success', count($validated['contact_ids']).' contact(s) added to segment.');
    }

    public function detachContact(Request $request, Segment $segment, Contact $contact): RedirectResponse
    {
        $this->authorise($request, $segment);
        abort_if($segment->type !== 'static', 403);

        $segment->contacts()->detach($contact->id);
        $segment->update(['contact_count' => $segment->contacts()->count()]);

        return back()->with('success', 'Contact removed from segment.');
    }

    private function authorise(Request $request, Segment $segment): void
    {
        $workspaceId = $request->user()->current_workspace_id ?? $request->user()->workspace_id;
        abort_unless((int) $segment->workspace_id === (int) $workspaceId, 403);
    }
}
