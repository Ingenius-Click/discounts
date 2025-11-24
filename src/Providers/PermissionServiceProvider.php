<?php

namespace Ingenius\Discounts\Providers;

use Illuminate\Support\ServiceProvider;
use Ingenius\Core\Support\PermissionsManager;
use Ingenius\Core\Traits\RegistersConfigurations;
use Ingenius\Discounts\Constants\DiscountPermissions;

class PermissionServiceProvider extends ServiceProvider
{
    use RegistersConfigurations;

    /**
     * The package name.
     *
     * @var string
     */
    protected string $packageName = 'Discounts';

    /**
     * Boot the application events.
     */
    public function boot(PermissionsManager $permissionsManager): void
    {
        $this->registerPermissions($permissionsManager);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        // Register package-specific permission config
        $configPath = __DIR__ . '/../../config/permissions.php';

        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'discounts.permissions');
            $this->registerConfig($configPath, 'discounts.permissions', 'discounts');
        }
    }

    /**
     * Register the package's permissions.
     */
    protected function registerPermissions(PermissionsManager $permissionsManager): void
    {
        $permissionsManager->register(
            DiscountPermissions::DISCOUNTS_VIEW,
            'View discounts',
            $this->packageName,
            'tenant',
            'View discounts',
            'Discounts'
        );

        $permissionsManager->register(
            DiscountPermissions::DISCOUNTS_CREATE,
            'Create discounts',
            $this->packageName,
            'tenant',
            'Create discounts',
            'Discounts'
        );

        $permissionsManager->register(
            DiscountPermissions::DISCOUNTS_EDIT,
            'Edit discounts',
            $this->packageName,
            'tenant',
            'Edit discounts',
            'Discounts'
        );

        $permissionsManager->register(
            DiscountPermissions::DISCOUNTS_DELETE,
            'Delete discounts',
            $this->packageName,
            'tenant',
            'Delete discounts',
            'Discounts'
        );
    }
}
