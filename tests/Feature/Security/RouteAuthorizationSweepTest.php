<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\Concerns\CreatesSampleRecords;
use Tests\TestCase;

/**
 * Calls every signed-in route with real records to prove nothing is reachable without the right permission.
 *
 * A route added later without an authorization check fails here, so authorization never depends on
 * someone remembering to write a test for the new screen.
 */
class RouteAuthorizationSweepTest extends TestCase
{
    use CreatesSampleRecords, RefreshDatabase;

    /**
     * Routes any signed-in user may use, with the status a user without permissions gets.
     *
     * These are the user's own space: their dashboard, notifications, approval inbox and the
     * requisition and payslip pages that show only their own records.
     *
     * @var array<string, int>
     */
    private const OPEN_TO_EVERY_USER = [
        'GET dashboard' => 200,
        'GET notifications' => 200,
        'POST notifications/read-all' => 302,
        'POST notifications/{notification}/open' => 404,
        'GET approvals' => 200,
    ];

    public function test_a_user_without_permissions_is_refused_on_every_protected_route(): void
    {
        $this->seedRolesAndPermissions();
        $parameters = $this->sampleRouteParameters();
        $user = User::factory()->create();
        $unexpected = [];
        $checked = 0;

        foreach ($this->protectedRoutes() as $route) {
            foreach ($this->methods($route) as $method) {
                $checked++;
                $key = "{$method} {$route->uri()}";
                $status = $this->actingAs($user)->call($method, $this->sampleUri($route, $parameters))->getStatusCode();
                $expected = self::OPEN_TO_EVERY_USER[$key] ?? 403;

                if ($status !== $expected) {
                    $unexpected[] = "{$key}: expected {$expected}, got {$status}";
                }
            }
        }

        $this->assertGreaterThan(200, $checked);
        $this->assertSame([], $unexpected);
    }

    public function test_a_guest_is_sent_to_the_login_page_from_every_protected_route(): void
    {
        $this->seedRolesAndPermissions();
        $parameters = $this->sampleRouteParameters();
        $unexpected = [];

        foreach ($this->protectedRoutes() as $route) {
            foreach ($this->methods($route) as $method) {
                $response = $this->call($method, $this->sampleUri($route, $parameters));

                if (! $response->isRedirect(route('login'))) {
                    $unexpected[] = "{$method} {$route->uri()}: got {$response->getStatusCode()}";
                }
            }
        }

        $this->assertSame([], $unexpected);
    }

    public function test_every_page_opens_for_the_super_administrator_without_a_server_error(): void
    {
        $this->seedRolesAndPermissions();
        $parameters = $this->sampleRouteParameters();
        $admin = User::query()->whereKey($parameters['user'])->sole();
        $failures = [];

        foreach ($this->protectedRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            $status = $this->actingAs($admin)->get($this->sampleUri($route, $parameters))->getStatusCode();

            if ($status >= 500) {
                $failures[] = "GET {$route->uri()}: got {$status}";
            }
        }

        $this->assertSame([], $failures);
    }

    /**
     * @return list<string>
     */
    private function methods(Route $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD']));
    }
}
