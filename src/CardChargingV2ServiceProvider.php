<?php

namespace Dinhdjj\CardChargingV2;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Dinhdjj\CardChargingV2\Commands\CardChargingV2Command;

class CardChargingV2ServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('card-charging-v2')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_card-charging-v2_table')
            ->hasCommand(CardChargingV2Command::class);
    }
}