<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Basic access with limited features',
                'price' => 0.00,
                'currency' => 'USD',
                'interval' => 'monthly',
                'trial_days' => 0,
                'features' => [
                    'Basic AI queries (10/day)',
                    'Standard support',
                    'Community access',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Full access for professionals',
                'price' => 19.99,
                'currency' => 'USD',
                'interval' => 'monthly',
                'trial_days' => 14,
                'features' => [
                    'Unlimited AI queries',
                    'Priority support',
                    'Advanced analytics',
                    'API access',
                    'Custom integrations',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Pro Yearly',
                'slug' => 'pro-yearly',
                'description' => 'Full access for professionals - billed yearly (save 20%)',
                'price' => 191.88,
                'currency' => 'USD',
                'interval' => 'yearly',
                'trial_days' => 14,
                'features' => [
                    'Unlimited AI queries',
                    'Priority support',
                    'Advanced analytics',
                    'API access',
                    'Custom integrations',
                    '20% discount',
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions for large teams',
                'price' => 99.99,
                'currency' => 'USD',
                'interval' => 'monthly',
                'trial_days' => 30,
                'features' => [
                    'Everything in Pro',
                    'Dedicated account manager',
                    'SLA guarantee',
                    'Custom model training',
                    'White-label options',
                    'On-premise deployment',
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
