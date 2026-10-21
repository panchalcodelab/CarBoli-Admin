<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->string('auction_type')->nullable()->after('minimum_bid_increment');
            $table->string('bidder_eligibility')->nullable()->after('auction_type');
            $table->string('listing_visibility')->nullable()->after('bidder_eligibility');
            $table->decimal('buyer_premium_percent', 5, 2)->nullable()->after('listing_visibility');
            $table->decimal('seller_commission_percent', 5, 2)->nullable()->after('buyer_premium_percent');
            $table->unsignedInteger('payment_deadline_days')->nullable()->after('seller_commission_percent');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn([
                'auction_type',
                'bidder_eligibility',
                'listing_visibility',
                'buyer_premium_percent',
                'seller_commission_percent',
                'payment_deadline_days',
            ]);
        });
    }
};
