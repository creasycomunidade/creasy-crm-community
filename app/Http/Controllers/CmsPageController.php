<?php

namespace App\Http\Controllers;

use App\Models\CmsPage;
use App\Support\SafeHtml;
use Inertia\Inertia;
use Inertia\Response;

class CmsPageController extends Controller
{
    public function show(string $slug): Response
    {
        $page = CmsPage::where('slug', $slug)
            ->where('published', true)
            ->firstOrFail();

        return Inertia::render('marketing/CmsPage', [
            'page' => [
                'title' => $page->title,
                'content' => SafeHtml::sanitize($page->content),
                'meta_title' => $page->meta_title,
                'meta_description' => $page->meta_description,
                'layout' => $page->layout,
            ],
        ]);
    }
}
