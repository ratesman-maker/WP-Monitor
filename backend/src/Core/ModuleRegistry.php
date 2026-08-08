<?php

declare(strict_types=1);

namespace WPMonitor\Core;

final class ModuleRegistry
{
    /** @var array<string, ModuleInterface> */
    private array $modules = [];

    public function register(ModuleInterface $module): void
    {
        $id = $module->getManifest()->id;
        $this->modules[$id] = $module;
    }

    public function get(string $id): ?ModuleInterface
    {
        return $this->modules[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->modules[$id]);
    }

    /** @return array<string, ModuleInterface> */
    public function all(): array
    {
        return $this->modules;
    }

    public function boot(): void
    {
        foreach ($this->modules as $module) {
            $module->boot();
        }
    }
}
