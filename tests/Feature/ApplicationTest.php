<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_axcess_login_page(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_uses_axcess_branding(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Axcess')
            ->assertSee('Staff Scheduling')
            ->assertSee('Sign in');
    }

    public function test_authenticated_staff_can_open_the_dashboard(): void
    {
        $department = Department::factory()->create(['name' => 'Operations', 'code' => 'OPS']);
        $user = User::factory()->create([
            'department_id' => $department->id,
            'role' => 'admin',
            'employee_id' => 'EMP-0001',
            'email' => 'admin@axcess.test',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Axcess');
    }
}
