<?php

namespace Tests\Feature;

use App\Models\Car;
use App\Models\User;
use App\Services\CarMediaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CarMediaManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_a_primary_image_updates_the_car_and_keeps_one_primary(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cars/images/front.jpg', 'front image');
        Storage::disk('local')->put('cars/images/rear.jpg', 'rear image');
        $manager = $this->createInventoryManager();
        $this->actingAs($manager);

        $car = Car::factory()->create();
        $frontImage = $car->images()->create([
            'path' => 'cars/images/front.jpg',
            'sort_order' => 0,
        ]);
        $rearImage = $car->images()->create([
            'path' => 'cars/images/rear.jpg',
            'sort_order' => 1,
        ]);
        $service = new CarMediaService;

        $service->setPrimaryImage($frontImage);
        $service->setPrimaryImage($rearImage->fresh());

        $this->assertFalse($frontImage->fresh()->is_primary);
        $this->assertTrue($rearImage->fresh()->is_primary);
        $this->assertSame('cars/images/rear.jpg', $car->fresh()->primary_image_path);
        $this->assertInstanceOf(HasMany::class, $car->images());
        $this->assertInstanceOf(HasMany::class, $car->documents());

        $service->deleteImage($rearImage->fresh());

        $this->assertTrue($frontImage->fresh()->is_primary);
        $this->assertSame('cars/images/front.jpg', $car->fresh()->primary_image_path);
        $this->assertFalse(Storage::disk('local')->exists('cars/images/rear.jpg'));
    }

    public function test_deleting_a_car_document_removes_its_private_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cars/documents/rc.pdf', 'vehicle registration');
        $this->actingAs($this->createInventoryManager());

        $car = Car::factory()->create();
        $document = $car->documents()->create([
            'type' => 'rc',
            'path' => 'cars/documents/rc.pdf',
            'file_name' => 'registration.pdf',
        ]);

        (new CarMediaService)->deleteDocument($document);

        $this->assertDatabaseMissing('car_documents', ['id' => $document->id]);
        $this->assertFalse(Storage::disk('local')->exists('cars/documents/rc.pdf'));
    }

    public function test_non_inventory_staff_cannot_change_primary_image(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cars/images/front.jpg', 'front image');
        $this->seed(RolesAndPermissionsSeeder::class);
        $auctionManager = User::factory()->create();
        $auctionManager->assignRole(Role::findByName('Auction Manager', 'web'));
        $this->actingAs($auctionManager);

        $car = Car::factory()->create();
        $image = $car->images()->create(['path' => 'cars/images/front.jpg']);

        $this->expectException(AuthorizationException::class);
        (new CarMediaService)->setPrimaryImage($image);
    }

    private function createInventoryManager(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $manager = User::factory()->create();
        $manager->assignRole(Role::findByName('Car / Inventory Manager', 'web'));

        return $manager;
    }
}
