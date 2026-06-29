<?php

namespace Tests\Feature\MaintenanceRequests;

use App\Filament\Resources\Equipment\Pages\ListEquipment;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Location;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\EquipmentQrCodeGenerator;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceRequestModelTest extends TestCase
{
    use RefreshDatabase;

    private Equipment $equipment;

    private User $submitter;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(RolePermissionSeeder::class);
        $this->equipment = $this->createEquipment();
        $this->submitter = User::factory()->create();
    }

    public function test_maintenance_request_can_be_created_with_required_fields(): void
    {
        $request = $this->createRequest();

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'equipment_id' => $this->equipment->id,
            'submitted_by' => $this->submitter->id,
            'problem_description' => 'Test problem description.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ]);
    }

    public function test_request_belongs_to_equipment_and_submitter(): void
    {
        $request = $this->createRequest();

        $this->assertTrue($request->equipment->is($this->equipment));
        $this->assertTrue($request->submittedBy->is($this->submitter));
        $this->assertTrue($this->equipment->maintenanceRequests->contains($request));
        $this->assertTrue($this->equipment->openMaintenanceRequests->contains($request));
    }

    public function test_request_number_is_generated_automatically_and_is_unique(): void
    {
        $first = $this->createRequest();
        $second = $this->createRequest(['problem_description' => 'Second request.']);

        $this->assertMatchesRegularExpression('/^MR-'.now()->format('Ymd').'-\\d{4}$/', $first->request_number);
        $this->assertNotSame($first->request_number, $second->request_number);
    }

    public function test_allowed_severities_and_statuses_are_available(): void
    {
        $this->assertSame(['Low', 'Moderate', 'High', 'Critical'], MaintenanceRequest::SEVERITIES);
        $this->assertSame(['Submitted', 'For review', 'Approved', 'Converted', 'Rejected', 'Cancelled'], MaintenanceRequest::STATUSES);
    }

    public function test_status_scopes_work(): void
    {
        $submitted = $this->createRequest(['status' => 'Submitted']);
        $approved = $this->createRequest(['status' => 'Approved']);
        $converted = $this->createRequest(['status' => 'Converted']);
        $rejected = $this->createRequest(['status' => 'Rejected']);
        $cancelled = $this->createRequest(['status' => 'Cancelled']);

        $this->assertTrue(MaintenanceRequest::submitted()->pluck('id')->contains($submitted->id));
        $this->assertTrue(MaintenanceRequest::approved()->pluck('id')->contains($approved->id));
        $this->assertTrue(MaintenanceRequest::converted()->pluck('id')->contains($converted->id));
        $this->assertTrue(MaintenanceRequest::rejected()->pluck('id')->contains($rejected->id));
        $this->assertTrue(MaintenanceRequest::cancelled()->pluck('id')->contains($cancelled->id));

        $open = MaintenanceRequest::open()->pluck('id');
        $closed = MaintenanceRequest::closed()->pluck('id');

        $this->assertTrue($open->contains($submitted->id));
        $this->assertTrue($open->contains($approved->id));
        $this->assertFalse($open->contains($converted->id));
        $this->assertTrue($closed->contains($converted->id));
        $this->assertTrue($closed->contains($rejected->id));
        $this->assertTrue($closed->contains($cancelled->id));
    }

    public function test_request_can_be_approved_from_submitted_or_for_review(): void
    {
        $reviewer = User::factory()->create();
        $submitted = $this->createRequest(['status' => 'Submitted']);
        $forReview = $this->createRequest(['status' => 'For review']);

        $approvedSubmitted = $submitted->approve($reviewer, 'Approved from submitted.');
        $approvedForReview = $forReview->approve($reviewer, 'Approved from review.');

        $this->assertTrue($approvedSubmitted->isApproved());
        $this->assertTrue($approvedForReview->isApproved());
        $this->assertSame($reviewer->id, $approvedSubmitted->reviewed_by);
        $this->assertNotNull($approvedSubmitted->reviewed_at);
        $this->assertSame('Approved from submitted.', $approvedSubmitted->review_remarks);
    }

    public function test_converted_request_cannot_be_approved_again(): void
    {
        $request = $this->createRequest(['status' => 'Converted']);

        $this->expectException(InvalidArgumentException::class);
        $request->approve(User::factory()->create());
    }

    public function test_request_can_be_rejected_from_submitted_and_requires_reason(): void
    {
        $reviewer = User::factory()->create();
        $request = $this->createRequest(['status' => 'Submitted']);

        try {
            $request->reject('', $reviewer);
            $this->fail('Rejection reason should be required.');
        } catch (InvalidArgumentException) {
            $this->assertSame('Submitted', $request->fresh()->status);
        }

        $rejected = $request->fresh()->reject('Not enough details.', $reviewer);

        $this->assertTrue($rejected->isRejected());
        $this->assertSame($reviewer->id, $rejected->rejected_by);
        $this->assertNotNull($rejected->rejected_at);
        $this->assertSame('Not enough details.', $rejected->rejection_reason);
    }

    public function test_converted_request_cannot_be_rejected(): void
    {
        $request = $this->createRequest(['status' => 'Converted']);

        $this->expectException(InvalidArgumentException::class);
        $request->reject('No.', User::factory()->create());
    }

    public function test_approved_request_can_be_marked_as_converted(): void
    {
        $converter = User::factory()->create();
        $request = $this->createRequest(['status' => 'Approved']);

        $converted = $request->markAsConverted($converter);

        $this->assertTrue($converted->isConverted());
        $this->assertSame($converter->id, $converted->converted_by);
        $this->assertNotNull($converted->converted_at);
    }

    public function test_submitted_request_cannot_be_converted_directly(): void
    {
        $request = $this->createRequest(['status' => 'Submitted']);

        $this->expectException(InvalidArgumentException::class);
        $request->markAsConverted(User::factory()->create());
    }

    public function test_open_requests_can_be_cancelled_but_closed_or_approved_requests_cannot(): void
    {
        $submitted = $this->createRequest(['status' => 'Submitted']);
        $forReview = $this->createRequest(['status' => 'For review']);

        $this->assertTrue($submitted->cancel('No longer needed.')->isCancelled());
        $this->assertTrue($forReview->cancel('No longer needed.')->isCancelled());

        foreach (['Approved', 'Converted', 'Rejected', 'Cancelled'] as $status) {
            $request = $this->createRequest(['status' => $status]);

            try {
                $request->cancel('Try cancel.');
                $this->fail($status.' request should not be cancellable.');
            } catch (InvalidArgumentException) {
                $this->assertSame($status, $request->fresh()->status);
            }
        }
    }

    public function test_regression_existing_equipment_qr_schedule_and_archive_still_work(): void
    {
        $equipment = $this->createEquipment(['equipment_code' => 'EQ-6A-REGRESSION']);
        $administrator = $this->userWithRole('Administrator');

        app(EquipmentQrCodeGenerator::class)->generate($equipment);
        Storage::disk('public')->assertExists($equipment->fresh()->qr_code_path);

        $this->actingAs($administrator);

        Livewire::test(ListEquipment::class)
            ->callTableAction('archive', $equipment)
            ->assertHasNoTableActionErrors();

        $this->assertTrue($equipment->fresh()->is_archived);
    }

    private function createRequest(array $overrides = []): MaintenanceRequest
    {
        return MaintenanceRequest::create(array_merge([
            'equipment_id' => $this->equipment->id,
            'submitted_by' => $this->submitter->id,
            'problem_description' => 'Test problem description.',
            'severity' => 'Moderate',
            'status' => 'Submitted',
        ], $overrides));
    }

    private function createEquipment(array $overrides = []): Equipment
    {
        $category = EquipmentCategory::create(['name' => 'Test Category '.uniqid()]);
        $location = Location::create(['name' => 'Test Location '.uniqid(), 'type' => 'Room']);

        return Equipment::create(array_merge([
            'equipment_code' => 'EQ-TEST-'.uniqid(),
            'equipment_name' => 'Test Equipment',
            'equipment_category_id' => $category->id,
            'current_location_id' => $location->id,
            'condition' => 'Good',
            'operational_status' => 'Available',
        ], $overrides));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName($role));

        return $user;
    }
}
