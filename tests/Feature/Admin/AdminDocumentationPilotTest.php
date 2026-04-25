<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDocumentationPilotTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_are_redirected_to_login_for_the_docs_index(): void
    {
        $response = $this->get(route('admin.docs.index'));

        $response->assertRedirect(route('login'));
    }

    #[Test]
    public function authenticated_admin_users_can_open_the_docs_index(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get(route('admin.docs.index'));

        $response->assertOk();
        $response->assertSee('Docs migration pilot');
        $response->assertSee('href="'.route('admin.docs.show', 'readme').'"', false);
        $response->assertSee('href="'.route('admin.docs.show', 'changelog').'"', false);
    }

    #[Test]
    public function readme_document_renders_inside_the_admin_docs_surface(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get(route('admin.docs.show', 'readme'));

        $response->assertOk();
        $response->assertSee('Read-only pilot');
        $response->assertSee('Feature Summary');
        $response->assertSee('<code>README.md</code>', false);
        $response->assertSee('href="'.route('admin.docs.index').'"', false);
    }

    #[Test]
    public function unknown_documents_return_not_found(): void
    {
        $user = User::factory()->editor()->create();

        $response = $this->actingAs($user)->get(route('admin.docs.show', 'unknown-doc'));

        $response->assertNotFound();
    }
}
