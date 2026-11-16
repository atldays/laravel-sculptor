<?php

namespace Atldays\Sculptor;

use Atldays\Sculptor\Console\Commands\FlushCacheCommand;
use Spatie\LaravelPackageTools\{Package, PackageServiceProvider};

class SculptorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('sculptor')
            ->hasConfigFile()
            ->hasCommand(FlushCacheCommand::class);
    }
}
