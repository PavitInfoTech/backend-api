<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    private function adminSession(): array
    {
        return ['admin' => 1];
    }

    public function test_admin_can_create_plan_from_monthly_and_yearly_form_values(): void
    {
        $response = $this->withSession($this->adminSession())->post(
            route('settings.store-subscription-plan'),
            [
                'name' => 'Starter Pilot',
                'slug' => 'starter-pilot',
                'description' => 'For small teams',
                'monthly_price' => '49',
                'yearly_price' => '399',
                'currency' => 'usd',
                'trial_days' => '14',
                'features' => "Feature one\nFeature two",
                'is_active' => '1',
                'popular' => '0',
            ],
        );

        $response->assertRedirect(route('settings.subscription-plans'));

        $this->assertDatabaseHas('subscription_plans', [
            'name' => 'Starter Pilot',
            'slug' => 'starter-pilot',
            'price' => '49.00',
            'monthly_price' => '49.00',
            'yearly_price' => '399.00',
            'interval' => 'monthly',
            'currency' => 'USD',
        ]);
    }

    public function test_admin_can_update_plan_and_keeps_legacy_price_columns_in_sync(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 19,
            'monthly_price' => 19,
            'yearly_price' => 190,
            'currency' => 'USD',
            'interval' => 'monthly',
            'is_active' => true,
            'popular' => false,
        ]);

        $response = $this->withSession($this->adminSession())->put(
            route('settings.update-subscription-plan', $plan),
            [
                'name' => 'Starter Annual',
                'slug' => 'starter-annual',
                'description' => 'Annual plan',
                'monthly_price' => '',
                'yearly_price' => '199',
                'currency' => 'USD',
                'trial_days' => '7',
                'features' => 'Annual billing',
                'is_active' => '1',
                'popular' => '1',
            ],
        );

        $response->assertRedirect(route('settings.subscription-plans'));

        $this->assertDatabaseHas('subscription_plans', [
            'id' => $plan->id,
            'slug' => 'starter-annual',
            'price' => '199.00',
            'monthly_price' => null,
            'yearly_price' => '199.00',
            'interval' => 'yearly',
            'popular' => 1,
        ]);
    }

    public function test_subscription_plan_page_explains_pricing_requirement(): void
    {
        $this->withSession($this->adminSession())
            ->get(route('settings.subscription-plans'))
            ->assertOk()
            ->assertSee('Enter at least one price.')
            ->assertSee('Create Plan')
            ->assertSee('Update Plan');
    }
}
