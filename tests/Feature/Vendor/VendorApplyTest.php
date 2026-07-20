<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    public function test_the_idempotency_key_header_is_required(): void
    {
        $user = $this->customer();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/vendor/apply', [
            'store_name' => 'Doda Store',
        ]);

        $response->assertStatus(400);
    }

    public function test_a_customer_can_apply_to_become_a_vendor(): void
    {
        $user = $this->customer();

        $response = $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'key-1'])
            ->postJson('/api/vendor/apply', ['store_name' => 'Doda Store']);

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('vendors', [
            'user_id' => $user->id,
            'store_name' => 'Doda Store',
            'status' => 'pending',
        ]);
    }

    public function test_retrying_the_same_idempotency_key_does_not_create_a_second_vendor(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'key-1'])
            ->postJson('/api/vendor/apply', ['store_name' => 'Doda Store']);

        $second = $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'key-1'])
            ->postJson('/api/vendor/apply', ['store_name' => 'Doda Store']);

        $second->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertDatabaseCount('vendors', 1);
    }

    public function test_reusing_the_same_idempotency_key_with_a_different_payload_is_rejected(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'key-1'])
            ->postJson('/api/vendor/apply', ['store_name' => 'Doda Store']);

        $second = $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'key-1'])
            ->postJson('/api/vendor/apply', ['store_name' => 'A Totally Different Store']);

        $second->assertStatus(422);
        $this->assertDatabaseCount('vendors', 1);
    }

    public function test_a_user_cannot_apply_twice(): void
    {
        $user = $this->customer();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'key-1'])
            ->postJson('/api/vendor/apply', ['store_name' => 'Doda Store']);

        $second = $this->actingAs($user, 'sanctum')
            ->withHeaders(['Idempotency-Key' => 'key-2'])
            ->postJson('/api/vendor/apply', ['store_name' => 'Second Store']);

        $second->assertStatus(422);
        $this->assertDatabaseCount('vendors', 1);
    }
}
