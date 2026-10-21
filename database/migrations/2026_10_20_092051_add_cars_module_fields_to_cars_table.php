<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('location_id')->constrained('vehicle_categories')->nullOnDelete();
            $table->foreignId('make_id')->nullable()->after('category_id')->constrained('makes')->nullOnDelete();
            $table->date('registration_date')->nullable()->after('registration_year');
            $table->unsignedInteger('engine_capacity_cc')->nullable()->after('transmission');
            $table->unsignedTinyInteger('seating_capacity')->nullable()->after('engine_capacity_cc');
            $table->foreignId('assigned_manager_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('walkaround_video_path')->nullable()->after('primary_image_path');

            // Approval workflow (Q6)
            $table->timestamp('submitted_for_review_at')->nullable()->after('walkaround_video_path');
            $table->timestamp('documents_approved_at')->nullable()->after('submitted_for_review_at');
            $table->foreignId('documents_approved_by')->nullable()->after('documents_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('documents_approved_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('make_id');
            $table->dropColumn('registration_date');
            $table->dropColumn('engine_capacity_cc');
            $table->dropColumn('seating_capacity');
            $table->dropConstrainedForeignId('assigned_manager_id');
            $table->dropColumn('walkaround_video_path');
            $table->dropColumn('submitted_for_review_at');
            $table->dropColumn('documents_approved_at');
            $table->dropConstrainedForeignId('documents_approved_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('rejection_reason');
        });
    }
};
