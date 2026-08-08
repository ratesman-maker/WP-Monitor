<?php

declare(strict_types=1);

namespace WPMonitor\Core;

interface ModuleInterface
{
    public function register(ModuleRegistry $registry): void;
    public function getManifest(): ModuleManifest;
    public function boot(): void;
}
