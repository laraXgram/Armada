<?php

namespace LaraGram\Armada\Console;

use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Command;

#[AsCommand(name: 'armada:add')]
class AddCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'armada:add
                {services? : The services that should be added}
                {--bot-api= : Add or switch the Bot API server (cloud, official or laragram)}
                {--server= : Change the server that runs the application (serve or surge)}
                {--updates= : Change how the bot receives updates (polling or webhook)}
                {--processes= : The processes that should run next to the server}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add services to an existing Armada installation';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (! $this->ensureEnvironmentFileExists()) {
            return self::FAILURE;
        }

        if (($selection = $this->gatherSelection($this->argument('services'), adding: true)) === null) {
            return self::FAILURE;
        }

        $this->buildDockerCompose($selection);
        $this->replaceEnvVariables($selection);
        $this->prepareInstallation($selection);

        $this->output->writeln('');
        $this->components->info('Armada has been updated. Run "armada up" to apply the changes.');

        $this->displayNextSteps($selection);

        $this->output->writeln('');

        return self::SUCCESS;
    }
}
