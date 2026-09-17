<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const FRONTEND_ORIGIN = 'http://localhost:5173';

    public function test_login_requires_valid_input(): void
    {
        $response = $this->fromFrontend()->postJson('/api/login', [
            'email' => 'not-an-email',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Переданные данные некорректны.')
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'correct-password',
        ]);

        $this->fromFrontend()
            ->postJson('/api/login', [
                'email' => 'demo@example.com',
                'password' => 'wrong-password',
            ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Неверный email или пароль.',
                'errors' => [
                    'email' => ['Неверный email или пароль.'],
                ],
            ]);

        $this->assertGuest('web');
    }

    public function test_user_can_login_and_receive_own_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'demo-password',
        ]);

        $this->fromFrontend()->get('/sanctum/csrf-cookie')->assertNoContent();

        $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'demo-password',
        ])
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $user->id,
                    'name' => 'Demo User',
                    'email' => 'demo@example.com',
                ],
            ])
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token');

        $this->assertAuthenticatedAs($user, 'web');

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'demo@example.com');
    }

    public function test_private_route_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'Требуется авторизация.',
                'errors' => [],
            ]);
    }

    public function test_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'demo@example.com',
            'password' => 'demo-password',
        ]);

        $this->fromFrontend()->get('/sanctum/csrf-cookie');
        $this->postJson('/api/login', [
            'email' => 'demo@example.com',
            'password' => 'demo-password',
        ])->assertOk();

        $this->postJson('/api/logout')->assertNoContent();

        $this->assertGuest('web');
    }

    public function test_sanctum_csrf_cookie_is_available(): void
    {
        $this->fromFrontend()
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_frontend_origin_is_allowed_to_send_credentials(): void
    {
        $this->withHeaders([
            'Origin' => self::FRONTEND_ORIGIN,
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/login')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', self::FRONTEND_ORIGIN)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_database_seeder_creates_one_user_with_a_hashed_password(): void
    {
        config()->set('auth.seed_user', [
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => 'demo-password',
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);

        $user = User::query()->sole();

        $this->assertNotSame('demo-password', $user->password);
        $this->assertTrue(Hash::check('demo-password', $user->password));
    }

    private function fromFrontend(): static
    {
        return $this->withHeaders([
            'Origin' => self::FRONTEND_ORIGIN,
            'Referer' => self::FRONTEND_ORIGIN.'/',
        ]);
    }
}
