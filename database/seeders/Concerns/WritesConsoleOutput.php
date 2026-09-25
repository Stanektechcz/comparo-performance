<?php

namespace Database\Seeders\Concerns;

use Illuminate\Console\Command;

/**
 * Console output when a seeder runs through Artisan; silent when it is
 * invoked directly (e.g. `app(SomeSeeder::class)()` in a test).
 */
trait WritesConsoleOutput
{
    private ?Command $console = null;

    public function setCommand(Command $command): static
    {
        $this->console = $command;

        return parent::setCommand($command);
    }

    protected function info(string $message): void
    {
        $this->console?->info($message);
    }

    protected function warn(string $message): void
    {
        $this->console?->warn($message);
    }
}
