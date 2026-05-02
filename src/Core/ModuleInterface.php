<?php
namespace Ouinpo\Suite\Core;

interface ModuleInterface
{
    public function id(): string;
    public function name(): string;
    public function boot(): void;
    public function activate(): void;
    public function deactivate(): void;
}
