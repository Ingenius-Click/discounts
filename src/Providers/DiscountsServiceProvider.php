<?php

namespace Ingenius\Discounts\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Ingenius\Core\Services\FeatureManager;
use Ingenius\Core\Services\PackageHookManager;
use Ingenius\Core\Traits\RegistersMigrations;
use Ingenius\Core\Traits\RegistersConfigurations;
use Ingenius\Discounts\Extensions\DiscountExtensionForOrderCreation;
use Ingenius\Discounts\Features\CalculateDiscountsFeature;
use Ingenius\Discounts\Features\CreateDiscountFeature;
use Ingenius\Discounts\Features\DeleteDiscountFeature;
use Ingenius\Discounts\Features\ListDiscountsFeature;
use Ingenius\Discounts\Features\UpdateDiscountFeature;
use Ingenius\Discounts\Features\ViewDiscountFeature;
use Ingenius\Discounts\Models\DiscountCampaign;
use Ingenius\Discounts\Policies\DiscountCampaignPolicy;
use Ingenius\Discounts\Actions\QueryProductibleWithAvailableDiscountsAction;
use Ingenius\Discounts\Services\DiscountApplicationService;
use Ingenius\Discounts\Services\ProductDiscountService;
use Ingenius\Discounts\Services\ShipmentDiscountService;
use Ingenius\Discounts\Services\ShopCartDiscountService;
use Ingenius\Orders\Services\OrderExtensionManager;

class DiscountsServiceProvider extends ServiceProvider
{
    use RegistersMigrations, RegistersConfigurations;

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/discounts.php', 'discounts');
        
        // Register configuration with the registry
        $this->registerConfig(__DIR__.'/../../config/discounts.php', 'discounts', 'discounts');
        
        // Register the route service provider
        $this->app->register(RouteServiceProvider::class);

        // Register the permission service provider
        $this->app->register(PermissionServiceProvider::class);

        // Register features
        $this->app->afterResolving(FeatureManager::class, function (FeatureManager $manager) {
            $manager->register(new CalculateDiscountsFeature());
            $manager->register(new ListDiscountsFeature());
            $manager->register(new ViewDiscountFeature());
            $manager->register(new CreateDiscountFeature());
            $manager->register(new UpdateDiscountFeature());
            $manager->register(new DeleteDiscountFeature());
        });

        // Register the product discount service as a singleton
        $this->app->singleton(ProductDiscountService::class);

        // Register order extension (with feature check inside the extension)
        $this->app->afterResolving(OrderExtensionManager::class, function (OrderExtensionManager $manager) {
            $manager->register(new DiscountExtensionForOrderCreation($this->app->make(DiscountApplicationService::class)));
        });

        // Register hooks (with feature check inside each service method)
        $this->app->afterResolving(PackageHookManager::class, function (PackageHookManager $manager) {
            $productDiscountService = $this->app->make(ProductDiscountService::class);
            $cartDiscountService = $this->app->make(ShopCartDiscountService::class);
            $shipmentDiscountService = $this->app->make(ShipmentDiscountService::class);

            // Hook: Calculate product showcase price with discounts
            $manager->register(
                'product.showcase_price',
                [$productDiscountService, 'calculateShowcasePrice'],
                10
            );

            $manager->register(
                'product.final_price',
                [$productDiscountService, 'calculateFinalPrice'],
                10
            );

            // Hook: Extend product array with discount information
            $manager->register(
                'product.array.extend',
                [$productDiscountService, 'extendProductArray'],
                10
            );

            $manager->register(
                'product.cart.array.extend',
                [$productDiscountService, 'extendProductCartArray'],
                10
            );

            $manager->register(
                'cart.discounts.get',
                [$cartDiscountService, 'applyDiscountsToCart'],
                20
            );

            $manager->register(
                'shipping.cost.calculated',
                [$shipmentDiscountService, 'applyDiscountsToShipment'],
                10
            );

            // Hook: Query products with available discounts
            $manager->register(
                'products.query.with_discounts',
                function ($data, $context) {
                    $action = $this->app->make(QueryProductibleWithAvailableDiscountsAction::class);
                    return $action->handle($data);
                },
                10
            );

            // Hook: Bulk price calculation for multiple products
            $manager->register(
                'product.bulk.prices',
                [$productDiscountService, 'calculateBulkPrices'],
                10
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(DiscountCampaign::class, DiscountCampaignPolicy::class);

        // Register migrations with the registry
        $this->registerMigrations(__DIR__.'/../../database/migrations', 'discounts');
        
        // Check if there's a tenant migrations directory and register it
        $tenantMigrationsPath = __DIR__.'/../../database/migrations/tenant';
        if (is_dir($tenantMigrationsPath)) {
            $this->registerTenantMigrations($tenantMigrationsPath, 'discounts');
        }
        
        // Load views
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'discounts');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        
        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/discounts.php' => config_path('discounts.php'),
        ], 'discounts-config');
        
        // Publish views
        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/discounts'),
        ], 'discounts-views');
        
        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'discounts-migrations');
    }
}