<?php

namespace Tests\Feature\Auth;

use App\Models\HandoffCode;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoffTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();
    $this->seed(RoleSeeder::class);
  }

  private function vendor(): User
  {
    $user = User::factory()->create();
    $user->assignRole('vendor');

    return $user;
  }

  private function issueCodeFor(User $user): string
  {
    return $this->actingAs($user, 'sanctum')
      ->postJson('/api/auth/handoff')
      ->assertOk()
      ->json('data.code');
  }

  public function test_issuing_a_code_requires_authentication(): void
  {
    $this->postJson('/api/auth/handoff')->assertUnauthorized();
  }

  public function test_an_authenticated_user_can_issue_a_code(): void
  {
    $user = $this->vendor();

    $this->actingAs($user, 'sanctum')
      ->postJson('/api/auth/handoff')
      ->assertOk()
      ->assertJsonStructure(['data' => ['code', 'expires_in']]);

    $this->assertDatabaseCount('handoff_codes', 1);
  }

  public function test_the_plaintext_code_is_never_stored(): void
  {
    $user = $this->vendor();
    $code = $this->issueCodeFor($user);

    $this->assertDatabaseMissing('handoff_codes', ['code_hash' => $code]);
    $this->assertDatabaseHas('handoff_codes', ['code_hash' => hash('sha256', $code)]);
  }

  public function test_a_code_can_be_redeemed_for_a_token(): void
  {
    $user = $this->vendor();
    $code = $this->issueCodeFor($user);

    $response = $this->postJson('/api/auth/handoff/redeem', ['code' => $code])
      ->assertOk()
      ->assertJsonPath('data.user.id', $user->id);

    $token = $response->json('data.token');
    $this->assertNotEmpty($token);

    // The issued token must actually work against a protected route.
    $this->withHeader('Authorization', "Bearer {$token}")
      ->getJson('/api/auth/me')
      ->assertOk()
      ->assertJsonPath('data.id', $user->id);
  }

  public function test_a_code_cannot_be_redeemed_twice(): void
  {
    $user = $this->vendor();
    $code = $this->issueCodeFor($user);

    $this->postJson('/api/auth/handoff/redeem', ['code' => $code])->assertOk();
    $this->postJson('/api/auth/handoff/redeem', ['code' => $code])->assertUnauthorized();
  }

  public function test_an_expired_code_is_rejected(): void
  {
    $user = $this->vendor();
    $code = $this->issueCodeFor($user);

    $this->travel(HandoffCode::TTL_SECONDS + 5)->seconds();

    $this->postJson('/api/auth/handoff/redeem', ['code' => $code])->assertUnauthorized();
  }

  public function test_an_unknown_code_is_rejected(): void
  {
    $this->postJson('/api/auth/handoff/redeem', ['code' => str_repeat('a', 64)])
      ->assertUnauthorized();
  }

  public function test_the_code_is_required(): void
  {
    $this->postJson('/api/auth/handoff/redeem', [])->assertStatus(422);
  }

  public function test_redeeming_yields_the_issuing_users_identity_only(): void
  {
    $mine = $this->vendor();
    $other = $this->vendor();

    $code = $this->issueCodeFor($mine);

    $this->postJson('/api/auth/handoff/redeem', ['code' => $code])
      ->assertOk()
      ->assertJsonPath('data.user.id', $mine->id)
      ->assertJsonPath('data.user.email', $mine->email);

    $this->assertNotSame($other->id, $mine->id);
  }

  public function test_a_deleted_user_cannot_be_handed_off(): void
  {
    $user = $this->vendor();
    $code = $this->issueCodeFor($user);

    $user->delete();

    $this->postJson('/api/auth/handoff/redeem', ['code' => $code])
      ->assertUnauthorized();
  }
}
