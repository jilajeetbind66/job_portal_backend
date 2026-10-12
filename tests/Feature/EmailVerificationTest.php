<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['sanctum.stateful' => ['localhost:5173']]);
        Role::findOrCreate('candidate', 'web');
    }

    public function test_registration_sends_email_verification_notification(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test Candidate',
            'email' => 'candidate@example.com',
            'phone' => '9876543210',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ], ['Origin' => 'http://localhost:5173']);

        $response->assertCreated()
            ->assertJsonPath('user.email_verified', false);

        $user = User::where('email', 'candidate@example.com')->firstOrFail();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_user_can_verify_email_using_valid_signed_link(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->getJson($url);

        $response->assertOk()
            ->assertJson([
                'message' => 'Email address verified successfully.',
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_rejects_invalid_signature(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $tamperedUrl = $url . '&tampered=1';

        $response = $this->getJson($tamperedUrl);

        $response->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_authenticated_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
            'status' => 'active',
        ]);

        $user->assignRole('candidate');

        
        $response = $this->actingAs($user)->postJson(
            '/api/v1/auth/email/verification-notification'
        );

        

        $response->assertOk()
            ->assertJson([
                'message' => 'Verification email sent successfully.',
            ]);

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

public function test_unverified_super_admin_can_access_admin_routes(): void
{
    $user = User::factory()->unverified()->create([
        'status' => 'active',
    ]);

    Permission::findOrCreate('partners.view', 'web');
    Role::findOrCreate('super_admin', 'web')->givePermissionTo('partners.view');
    $user->assignRole('super_admin');

    $response = $this->actingAs($user)
        ->getJson('/api/v1/admin/partners');

    $response->assertOk();
}
    }
