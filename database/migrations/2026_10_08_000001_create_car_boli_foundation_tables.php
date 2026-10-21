<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mobile')->nullable()->after('email');
            $table->string('status')->default('active')->after('mobile');
            $table->timestamp('last_login_at')->nullable()->after('status');
            $table->softDeletes()->after('updated_at');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('mobile_number')->nullable();
            $table->string('account_status')->default('active');
            $table->string('kyc_status')->default('pending');
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_approved_at')->nullable();
            $table->text('kyc_rejection_reason')->nullable();
            $table->longText('profile_information')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('country')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->boolean('is_blocked')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index('account_status');
            $table->index('kyc_status');
            $table->index('email');
        });

        Schema::create('customer_kyc', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('pan')->nullable();
            $table->string('identity_document_type')->nullable();
            $table->string('identity_document_number')->nullable();
            $table->string('address_proof')->nullable();
            $table->string('document_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('customer_id');
            $table->index('status');
            $table->index('reviewer_id');
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['city', 'state']);
        });

        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stock_inventory_id')->unique();
            $table->string('make');
            $table->string('model');
            $table->string('variant')->nullable();
            $table->unsignedInteger('manufacturing_year')->nullable();
            $table->unsignedInteger('registration_year')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('transmission')->nullable();
            $table->string('body_type')->nullable();
            $table->string('color')->nullable();
            $table->decimal('kilometer', 12, 2)->nullable();
            $table->unsignedTinyInteger('number_of_owners')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('vin')->nullable();
            $table->string('engine_number')->nullable();
            $table->decimal('expected_price', 12, 2)->nullable();
            $table->decimal('reserve_price', 12, 2)->nullable();
            $table->decimal('starting_auction_price', 12, 2)->nullable();
            $table->string('status')->default('draft');
            $table->text('short_description')->nullable();
            $table->longText('detailed_description')->nullable();
            $table->longText('specifications')->nullable();
            $table->string('primary_image_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['location_id', 'status']);
            $table->index('status');
            $table->index('registration_number');
        });

        Schema::create('car_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['car_id', 'is_primary']);
        });

        Schema::create('car_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('path');
            $table->string('file_name')->nullable();
            $table->timestamps();
            $table->index('car_id');
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('inspection_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->default('requested');
            $table->text('notes')->nullable();
            $table->string('inspection_report_path')->nullable();
            $table->timestamps();
            $table->index(['car_id', 'status']);
            $table->index(['inspection_date', 'status']);
        });

        Schema::create('inspection_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('file_name')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
            $table->index('inspection_id');
        });

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->decimal('starting_price', 12, 2)->default(0);
            $table->decimal('minimum_bid_increment', 12, 2)->default(0);
            $table->decimal('reserve_price', 12, 2)->nullable();
            $table->decimal('participation_fee', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->boolean('published')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('winner_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['car_id', 'status']);
            $table->index(['start_at', 'end_at']);
            $table->index('published');
        });

        Schema::create('auction_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('registration_status')->default('requested');
            $table->decimal('entry_fee_amount', 12, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->string('kyc_status')->default('pending');
            $table->timestamp('participation_date')->nullable();
            $table->string('eligibility_status')->default('pending');
            $table->timestamps();
            $table->index(['auction_id', 'payment_status']);
            $table->index(['customer_id', 'eligibility_status']);
            $table->unique(['auction_id', 'customer_id']);
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->decimal('bid_amount', 12, 2);
            $table->unsignedInteger('bid_sequence')->default(0);
            $table->string('status')->default('valid');
            $table->json('device_metadata')->nullable();
            $table->timestamps();
            $table->index(['auction_id', 'status']);
            $table->index(['auction_id', 'customer_id']);
        });

        Schema::create('auction_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('winning_bid_id')->nullable()->constrained('bids')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->string('settlement_status')->default('pending');
            $table->timestamps();
            $table->unique('auction_id');
            $table->index('status');
            $table->index('settlement_status');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('INR');
            $table->string('gateway')->nullable();
            $table->string('gateway_reference_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_type')->default('entry_fee');
            $table->timestamp('successful_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['auction_id', 'status']);
            $table->index('gateway_reference_id');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('channel');
            $table->string('event');
            $table->string('template_identifier')->nullable();
            $table->string('status')->default('queued');
            $table->string('provider_reference')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['event', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('module');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'module']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('auction_results');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('auction_participants');
        Schema::dropIfExists('auctions');
        Schema::dropIfExists('inspection_documents');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('car_documents');
        Schema::dropIfExists('car_images');
        Schema::dropIfExists('cars');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('customer_kyc');
        Schema::dropIfExists('customers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['mobile', 'status', 'last_login_at']);
        });
    }
};
