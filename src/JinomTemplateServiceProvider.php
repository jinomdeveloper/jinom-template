<?php

namespace Jinom\JinomTemplate;

use Jinom\JinomTemplate\Commands\CreateResourceCommand;
use Jinom\JinomTemplate\Commands\SyncPermission;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class JinomTemplateServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('jinom-template')
            ->hasConfigFile()
            ->hasCommands([
                CreateResourceCommand::class,
                SyncPermission::class,
            ]);
    }
}
