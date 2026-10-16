<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsPageTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): AdminUser
    {
        return $this->createSuperAdmin();
    }

    public function test_published_page_is_publicly_accessible(): void
    {
        CmsPage::factory()->create([
            'slug'      => 'privacy',
            'title'     => 'Privacy Policy',
            'content'   => '<p>We care about your privacy.</p>',
            'published' => true,
        ]);

        $this->get('/p/privacy')
             ->assertOk()
             ->assertSee('Privacy Policy');
    }

    public function test_unpublished_page_returns_404(): void
    {
        CmsPage::factory()->create([
            'slug'      => 'draft-page',
            'published' => false,
        ]);

        $this->get('/p/draft-page')->assertNotFound();
    }

    public function test_admin_can_create_cms_page(): void
    {
        $this->actingAs($this->adminUser(), 'admin')
            ->post(route('admin.cms-pages.store'), [
                'slug'      => 'terms',
                'title'     => 'Terms of Service',
                'content'   => '<p>Terms.</p>',
                'published' => true,
                'layout'    => 'legal',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cms_pages', ['slug' => 'terms']);
    }

    public function test_admin_can_delete_cms_page(): void
    {
        $page = CmsPage::factory()->create();

        $this->actingAs($this->adminUser(), 'admin')
            ->delete(route('admin.cms-pages.destroy', $page))
            ->assertRedirect();

        $this->assertDatabaseMissing('cms_pages', ['id' => $page->id]);
    }
}
