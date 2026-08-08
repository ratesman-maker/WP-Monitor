<?php

declare(strict_types=1);

namespace WPMonitor\Core;

final class ModuleManifest
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description = '',
        public readonly string $author = '',
        public readonly string $minCoreVersion = '0.1.0',
        /** @var array<string> */
        public readonly array $dependencies = [],
        /** @var array<string> */
        public readonly array $permissions = [],
        /** @var array<string, mixed> */
        public readonly array $configSchema = [],
        /** @var array<string, mixed> */
        public readonly array $frontend = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            version: $data['version'] ?? '0.0.0',
            description: $data['description'] ?? '',
            author: $data['author'] ?? '',
            minCoreVersion: $data['minCoreVersion'] ?? '0.1.0',
            dependencies: $data['dependencies'] ?? [],
            permissions: $data['permissions'] ?? [],
            configSchema: $data['configSchema'] ?? [],
            frontend: $data['frontend'] ?? [],
        );
    }
}
