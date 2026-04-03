<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migration to populate new pricing columns from legacy data.
     * This migration should be run after 2025_12_03_000001_update_subscription_plans_new_pricing.php
     */
    public function up(): void
    {
        // Get all subscription plans
        $plans = DB::table('subscription_plans')->get();

        foreach ($plans as $plan) {
            $monthlyPrice = null;
            $yearlyPrice = null;

            // Populate new pricing columns based on interval and price
            if ($plan->interval === 'monthly' || $plan->interval === 'one-time') {
                $monthlyPrice = $plan->price;
            } elseif ($plan->interval === 'yearly') {
                $yearlyPrice = $plan->price;
            }

            // Update the plan with new prices
            DB::table('subscription_plans')
                ->where('id', $plan->id)
                ->update([
                    'monthly_price' => $monthlyPrice,
                    'yearly_price' => $yearlyPrice,
                    'popular' => false, // Set default popular to false
                ]);
        }
    }

    public function down(): void
    {
        // Reset new pricing columns to null
        DB::table('subscription_plans')->update([
            'monthly_price' => null,
            'yearly_price' => null,
            'popular' => false,
        ]);
    }
};
