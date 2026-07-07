<?php

namespace Rolland\FilamentResourceCustomizer\Contracts;

interface PermissionGateResolver
{
    /**
     * Build the full authorization gate string for a permission method on a resource,
     * including any panel prefix and case transformation.
     */
    public function key(string $method, string $resource): string;
}
