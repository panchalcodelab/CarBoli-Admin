<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('exterior_condition')->nullable()->after('status');
            $table->string('interior_condition')->nullable()->after('exterior_condition');
            $table->string('engine_condition')->nullable()->after('interior_condition');
            $table->string('transmission_condition')->nullable()->after('engine_condition');
            $table->string('tyre_condition')->nullable()->after('transmission_condition');
            $table->string('battery_condition')->nullable()->after('tyre_condition');
            $table->string('accident_history')->nullable()->after('battery_condition');
            $table->string('flood_damage')->nullable()->after('accident_history');
            $table->unsignedTinyInteger('inspection_score')->nullable()->after('flood_damage');
            $table->text('inspector_remarks')->nullable()->after('inspection_score');
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropColumn([
                'exterior_condition',
                'interior_condition',
                'engine_condition',
                'transmission_condition',
                'tyre_condition',
                'battery_condition',
                'accident_history',
                'flood_damage',
                'inspection_score',
                'inspector_remarks',
            ]);
        });
    }
};
