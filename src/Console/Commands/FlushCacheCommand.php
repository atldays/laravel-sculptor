<?php

namespace Atldays\Sculptor\Console\Commands;

use Atldays\Sculptor\Contracts\WithCache;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Webmozart\Assert\Assert;

#[AsCommand(name: 'sculptor:flush-cache')]
class FlushCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sculptor:flush-cache {class : The class to flush cache for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush cache for a cacheable class';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        try {
            $class = $this->argument('class');

            Assert::classExists($class);
            Assert::implementsInterface($class, WithCache::class);

            /** @var WithCache $class */
            $class::flushBaseCache();

            $this->components->info(sprintf('Cache flushed successfully for class "%s"', $class));
        } catch (Throwable $e) {
            $this->error($e->getMessage());
        }
    }
}
