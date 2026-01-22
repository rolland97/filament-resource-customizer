<?php

namespace Rolland\FilamentResourceCustomizer\Services\Rendering;

use Rolland\FilamentResourceCustomizer\Support\StubRepository;

class StubRenderer
{
    public function __construct(protected StubRepository $stubRepository) {}

    public function render(string $stubName, array $variables): string
    {
        $stub = $this->stubRepository->get($stubName);

        foreach ($variables as $key => $value) {
            $stub = str_replace('{{ '.$key.' }}', $value, $stub);
        }

        return $stub;
    }
}
