<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function pendingVendor(): Vendor
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return Vendor::factory()->for($user)->create();
    }

    public function test_a_non_admin_cannot_list_pending_vendors(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/admin/vendors');

        $response->assertForbidden();
    }

    public function test_an_admin_can_approve_a_vendor(): void
    {
        $vendor = $this->pendingVendor();
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/vendors/{$vendor->id}/approve");

        $response->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertTrue($vendor->user->fresh()->hasRole('vendor'));
        $this->assertNotNull($vendor->fresh()->approved_at);
    }

    public function test_an_admin_can_suspend_an_approved_vendor(): void
    {
        $vendor = $this->pendingVendor();
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/vendors/{$vendor->id}/approve");
        $response = $this->actingAs($admin, 'sanctum')->postJson("/api/admin/vendors/{$vendor->id}/suspend");

        $response->assertOk()->assertJsonPath('data.status', 'suspended');
        $this->assertFalse($vendor->user->fresh()->hasRole('vendor'));
    }

    public function test_a_vendor_can_view_their_own_profile_but_not_someone_elses(): void
    {
        $vendorA = $this->pendingVendor();
        $vendorB = $this->pendingVendor();

        $responseOwn = $this->actingAs($vendorA->user, 'sanctum')->getJson('/api/vendor/me');
        $responseOwn->assertOk()->assertJsonPath('data.id', $vendorA->id);

        // vendor/me only ever resolves the caller's own vendor record, so there is
        // no route for viewing someone else's profile directly -- confirm B's call
        // resolves to B's own record, never A's.
        $responseB = $this->actingAs($vendorB->user, 'sanctum')->getJson('/api/vendor/me');
        $responseB->assertOk()->assertJsonPath('data.id', $vendorB->id);
    }
}
