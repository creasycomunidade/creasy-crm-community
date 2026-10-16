<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Support\SafeHtml;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/CmsPages/Index', [
            'pages' => CmsPage::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:cms_pages,slug'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'published' => ['boolean'],
            'layout' => ['required', 'in:marketing,legal'],
        ]);

        $validated['content'] = SafeHtml::sanitize($validated['content'] ?? '');
        CmsPage::create($validated);

        return redirect()->route('admin.cms-pages.index')->with('success', __('Page created.'));
    }

    public function update(Request $request, CmsPage $cmsPage): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100', "unique:cms_pages,slug,{$cmsPage->id}"],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'published' => ['boolean'],
            'layout' => ['required', 'in:marketing,legal'],
        ]);

        $validated['content'] = SafeHtml::sanitize($validated['content'] ?? '');
        $cmsPage->update($validated);

        return redirect()->route('admin.cms-pages.index')->with('success', __('Page updated.'));
    }

    public function destroy(CmsPage $cmsPage): RedirectResponse
    {
        $cmsPage->delete();

        return redirect()->route('admin.cms-pages.index')->with('success', __('Page deleted.'));
    }
}
