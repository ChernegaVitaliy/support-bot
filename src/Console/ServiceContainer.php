<?php

namespace App\Console;

class ServiceContainer
{
    private array $services = [];
    private array $factories = [];

    public function register(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id)
    {
        if (!isset($this->services[$id])) {
            if (!isset($this->factories[$id])) {
                throw new \RuntimeException("Service '$id' not found in container.");
            }
            $this->services[$id] = ($this->factories[$id])($this);
        }
        return $this->services[$id];
    }
}
