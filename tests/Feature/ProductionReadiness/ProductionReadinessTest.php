<?php

namespace Tests\Feature\ProductionReadiness;

use App\Filament\Pages\ProductionReadiness;
use App\Models\User;
use App\Services\ProductionReadinessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_service_returns_readable_checklists_and_score(): void
    {
        $service = app(ProductionReadinessService::class);

        $this->assertChecklist($service->getSecurityChecklist(), 'APP_DEBUG disabled');
        $this->assertChecklist($service->getPerformanceChecklist(), 'Config cache ready');
        $this->assertChecklist($service->getDeploymentChecklist(), 'Document root set to public');
        $this->assertChecklist($service->getBackupChecklist(), 'Database backup planned');
        $this->assertChecklist($service->getQueueChecklist(), 'Scheduler cron ready');
        $this->assertChecklist($service->getStorageChecklist(), 'Public disk configured');
        $this->assertChecklist($service->getPwaChecklist(), 'Service worker exists');

        $score = $service->getOverallReadinessScore();
        $this->assertIsInt($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(100, $score);

        $this->assertContains('Production readiness checks are advisory and do not replace manual server verification.', $service->getProductionWarnings());
        $this->assertContains('Create and test a database plus uploaded-file restore before accepting live data.', $service->getRecommendedActions());
    }

    public function test_administrator_can_access_production_readiness_page_and_sections_render(): void
    {
        $this->actingAs($this->userWithRole('Administrator'))
            ->get('/admin/production-readiness')
            ->assertOk()
            ->assertSee('Production Readiness')
            ->assertSee('Overall Readiness Score')
            ->assertSee('Security Checklist')
            ->assertSee('Performance Checklist')
            ->assertSee('Deployment Checklist')
            ->assertSee('Backup and Restore Checklist')
            ->assertSee('Queue and Notification Checklist')
            ->assertSee('Storage and File Upload Checklist')
            ->assertSee('PWA Readiness Checklist')
            ->assertSee('Production Warnings')
            ->assertSee('Recommended Actions')
            ->assertSee('APP_DEBUG should be false in production.')
            ->assertSee('The public/storage link must expose public uploaded files.')
            ->assertSee('Service worker should exclude admin, Filament, and Livewire routes.');
    }

    public function test_staff_technician_and_user_without_permission_cannot_access_page(): void
    {
        $this->actingAs($this->userWithRole('Staff'))
            ->get('/admin/production-readiness')
            ->assertForbidden();

        $this->actingAs($this->userWithRole('Technician'))
            ->get('/admin/production-readiness')
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get('/admin/production-readiness')
            ->assertForbidden();
    }

    public function test_permission_required_and_navigation_visibility_follows_authorization(): void
    {
        $this->actingAs($this->userWithRole('Administrator'));
        $this->assertTrue(ProductionReadiness::canAccess());
        $this->assertTrue(ProductionReadiness::shouldRegisterNavigation());

        $this->actingAs($this->userWithRole('Staff'));
        $this->assertFalse(ProductionReadiness::canAccess());
        $this->assertFalse(ProductionReadiness::shouldRegisterNavigation());

        $this->actingAs($this->userWithRole('Technician'));
        $this->assertFalse(ProductionReadiness::canAccess());
        $this->assertFalse(ProductionReadiness::shouldRegisterNavigation());

        $user = User::factory()->create();
        $user->givePermissionTo('access admin panel', 'production-readiness.view');

        $this->actingAs($user);
        $this->assertTrue(ProductionReadiness::canAccess());
        $this->assertTrue(ProductionReadiness::shouldRegisterNavigation());
    }

    public function test_role_seeder_assigns_production_readiness_to_administrator_only(): void
    {
        $this->assertTrue($this->userWithRole('Administrator')->can('production-readiness.view'));
        $this->assertFalse($this->userWithRole('Staff')->can('production-readiness.view'));
        $this->assertFalse($this->userWithRole('Technician')->can('production-readiness.view'));
    }

    /**
     * @param  array<int, array<string, string>>  $items
     */
    private function assertChecklist(array $items, string $expectedTitle): void
    {
        $this->assertNotEmpty($items);
        $this->assertContains($expectedTitle, collect($items)->pluck('title')->all());

        foreach ($items as $item) {
            $this->assertIsString($item['title']);
            $this->assertIsString($item['status']);
            $this->assertIsString($item['description']);
            $this->assertIsString($item['action']);
        }
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
