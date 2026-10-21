<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Location;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_business_tables_exist_and_are_writable(): void
    {
        $requiredTables = [
            'roles',
            'permissions',
            'customers',
            'customer_kyc',
            'locations',
            'cars',
            'inspections',
            'auctions',
            'auction_participants',
            'bids',
            'auction_results',
            'payments',
            'notifications',
            'audit_logs',
        ];

        foreach ($requiredTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $role = Role::create([
            'name' => 'Auction Manager',
            'guard_name' => 'web',
        ]);

        $this->assertNotNull($role->id);

        $location = Location::create([
            'name' => 'Main Yard',
            'address' => '123 Test Lane',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'latitude' => 12.9716,
            'longitude' => 77.5946,
        ]);

        $customer = Customer::create([
            'full_name' => 'Test Customer',
            'email' => 'customer@example.com',
            'mobile_number' => '9876543210',
            'account_status' => 'active',
            'kyc_status' => 'pending',
            'address' => 'Main Street',
            'city' => 'Bengaluru',
            'state' => 'Karnataka',
            'pincode' => '560001',
            'country' => 'India',
        ]);

        $this->assertDatabaseHas('customers', ['email' => 'customer@example.com']);
        $this->assertDatabaseHas('locations', ['name' => 'Main Yard']);
        $this->assertSame('Main Yard', $location->name);
        $this->assertSame('Test Customer', $customer->full_name);
    }
}
