<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Add new pricing columns
            $table->decimal('monthly_price', 10, 2)->nullable()->after('price');
            $table->decimal('yearly_price', 10, 2)->nullable()->after('monthly_price');
            
            // Add popular flag
            $table->boolean('popular')->default(false)->after('yearly_price');
            
            // Change description field to 'desc' by renaming (we'll keep description for backward compatibility)
            // Note: We'll add a new 'desc' column while keeping 'description' for now
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_price', 'yearly_price', 'popular']);
        });
    }
};
