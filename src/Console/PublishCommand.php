<?php

namespace LaraGram\Armada\Console;

use LaraGram\Console\Command;
use LaraGram\Armada\Console\Concerns\InteractsWithDockerComposeServices;
use LaraGram\Console\Attribute\AsCommand;

#[AsCommand(name: 'armada:publish')]
class PublishCommand extends Command
{
    use InteractsWithDockerComposeServices;

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
     * @return void
     */
    public function handle()
    {
        $this->call('vendor:publish', ['--tag' => 'armada-docker']);
        $this->call('vendor:publish', ['--tag' => 'armada-database']);

        file_put_contents(
            $this->composePath(),
            str_replace(
                [
                    './vendor/laraxgram/armada/runtimes/8.4',
                    './vendor/laraxgram/armada/runtimes/8.3',
                    './vendor/laraxgram/armada/runtimes/8.2',
                    './vendor/laraxgram/armada/database/mysql',
                    './vendor/laraxgram/armada/database/pgsql'
                ],
                [
                    './docker/8.4',
                    './docker/8.3',
                    './docker/8.2',
                    './docker/mysql',
                    './docker/pgsql'
                ],
                file_get_contents($this->composePath())
            )
        );
    }
}
