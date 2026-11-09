<?php

namespace LaraGram\Armada\Console;

use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Command;

#[AsCommand(name: 'armada:install')]
class InstallCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'armada:install
                {--with= : The services that should be included in the installation}
                {--bot-api= : The Bot API server to use (cloud, official or laragram)}
                {--server= : The server that runs the application (serve or surge)}
                {--updates= : How the bot receives updates (polling or webhook)}
                {--processes= : The processes that should run next to the server}
                {--devcontainer : Create a .devcontainer configuration directory}
                {--php=8.5 : The PHP version that should be used}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install LaraGram Armada\'s default Docker Compose file';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! in_array($this->option('php'), $this->phpVersions, true)) {
            $this->components->error('Invalid PHP version ['.$this->option('php').']. Supported versions: '.implode(', ', $this->phpVersions).'.');

            return self::FAILURE;
        }

        if (! $this->ensureEnvironmentFileExists()) {
            return self::FAILURE;
        }

        if (($selection = $this->gatherSelection()) === null) {
            return self::FAILURE;
        }

        $this->buildDockerCompose($selection);
        $this->replaceEnvVariables($selection);

        if ($this->option('devcontainer')) {
            $this->installDevContainer();
        }

        $this->prepareInstallation($selection);

        $this->output->writeln('');
        $this->components->info('Armada scaffolding installed successfully. You may run your Docker containers using Armada\'s "up" command.');

        $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada up</>');

        $this->displayNextSteps($selection);

        $this->output->writeln('');

        return self::SUCCESS;
    }
}
