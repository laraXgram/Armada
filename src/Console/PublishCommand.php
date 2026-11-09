<?php

namespace LaraGram\Armada\Console;

use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Command;

#[AsCommand(name: 'armada:publish')]
class PublishCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'armada:publish';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish the LaraGram Armada Docker files';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->call('vendor:publish', ['--tag' => 'armada-docker']);
        $this->call('vendor:publish', ['--tag' => 'armada-database']);

        $composePath = $this->composePath();

        if (file_exists($composePath)) {
            file_put_contents($composePath, str_replace(
                ['./vendor/laraxgram/armada/runtimes/', './vendor/laraxgram/armada/database/'],
                ['./docker/', './docker/'],
                file_get_contents($composePath)
            ));
        }

        return self::SUCCESS;
    }
}
