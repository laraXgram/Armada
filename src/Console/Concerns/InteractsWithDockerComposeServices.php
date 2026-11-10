<?php

namespace LaraGram\Armada\Console\Concerns;

use Composer\InstalledVersions;
use LaraGram\Console\Process\Process;
use LaraGram\Support\Yaml\Yaml;

use function LaraGram\Console\Prompts\multiselect;
use function LaraGram\Console\Prompts\select;
use function LaraGram\Console\Prompts\text;

trait InteractsWithDockerComposeServices
{
    /**
     * Possible names for the compose file according to the spec.
     *
     * @var array<string>
     */
    protected $composePaths = [
        'compose.yaml',
        'compose.yml',
        'docker-compose.yaml',
        'docker-compose.yml',
    ];

    /**
     * The PHP versions Armada can build.
     *
     * @var array<string>
     */
    protected $phpVersions = ['8.3', '8.4', '8.5'];

    /**
     * The available services that may be installed.
     *
     * @var array<string>
     */
    protected $services = [
        'mysql',
        'pgsql',
        'mariadb',
        'mongodb',
        'redis',
        'valkey',
        'memcached',
        'docker-proxy',
    ];

    /**
     * The services that keep their data in a volume.
     *
     * @var array<string>
     */
    protected $servicesWithVolumes = ['mysql', 'pgsql', 'mariadb', 'mongodb', 'redis', 'valkey'];

    /**
     * The default services used when the user chooses non-interactive mode.
     *
     * @var string[]
     */
    protected $defaultServices = ['mysql', 'redis'];

    /**
     * The Bot API servers and the stubs that define them.
     *
     * @var array<string, string|null>
     */
    protected $botApiServers = [
        'cloud' => null,
        'official' => 'telegram-bot-api',
        'laragram' => 'apiserver',
    ];

    /**
     * The processes that may run next to the server in development.
     *
     * @var array<string>
     */
    protected $processNames = ['queue', 'schedule', 'watchdog', 'mtproto', 'ssr', 'sentinel-queue', 'sentinel-check', 'vite'];

    /**
     * The roles that may run in their own containers in production.
     *
     * @var array<string>
     */
    protected $productionRoleNames = ['queue', 'sentinel-queue', 'schedule', 'poller', 'mtproto', 'ssr', 'sentinel-check'];

    /**
     * The name of the service that runs the application.
     *
     * @var string
     */
    protected $appService = 'laragram.test';

    /**
     * Make sure the application has an environment file.
     *
     * @return bool
     */
    protected function ensureEnvironmentFileExists()
    {
        $path = $this->laragram->basePath('.env');

        if (file_exists($path)) {
            return true;
        }

        if (file_exists($example = $this->laragram->basePath('.env.example'))) {
            return copy($example, $path);
        }

        $this->components->error('The application does not have a .env file.');

        return false;
    }

    /**
     * Gather and validate everything the user wants Armada to set up.
     *
     * @param  string|null  $services
     * @param  bool  $adding
     * @return array{services: array<int, string>, botApi: string|null, server: string|null, updates: string|null, processes: array<int, string>|null}|null
     */
    protected function gatherSelection($services = null, $adding = false)
    {
        $hasOptions = $this->option('bot-api') !== null
            || $this->option('server') !== null
            || $this->option('updates') !== null
            || $this->option('processes') !== null;

        // Adding a Bot API server or changing a setting should not also ask for database services...
        $services = $adding && $services === null && $hasOptions
            ? []
            : $this->gatherServices($services ?? ($adding ? null : $this->option('with')), $this->defaultServices);

        $botApi = ! $adding || $this->option('bot-api') !== null ? $this->gatherBotApi() : null;
        $server = ! $adding || $this->option('server') !== null ? $this->gatherServer() : null;
        $updates = ! $adding || $this->option('updates') !== null ? $this->gatherUpdates($botApi ?? 'cloud', $server ?? 'serve', 'polling') : null;
        $processes = ! $adding || $this->option('processes') !== null ? $this->gatherProcesses($server ?? 'serve') : null;

        if ($invalid = array_diff($services, $this->services)) {
            $this->components->error('Invalid services ['.implode(',', $invalid).'].');

            return null;
        }

        if ($botApi !== null && ! array_key_exists($botApi, $this->botApiServers)) {
            $this->components->error("Invalid Bot API server [{$botApi}]. Use cloud, official or laragram.");

            return null;
        }

        if ($server !== null && ! in_array($server, ['serve', 'surge'], true)) {
            $this->components->error("Invalid server [{$server}]. Use serve or surge.");

            return null;
        }

        if ($updates !== null && ! in_array($updates, ['polling', 'webhook'], true)) {
            $this->components->error("Invalid update method [{$updates}]. Use polling or webhook.");

            return null;
        }

        if ($processes !== null && ($invalid = array_diff($processes, $this->processNames))) {
            $this->components->error('Invalid processes ['.implode(',', $invalid).'].');

            return null;
        }

        return compact('services', 'botApi', 'server', 'updates', 'processes');
    }

    /**
     * Gather the desired database and cache services.
     *
     * @param  string|null  $given
     * @param  array<int, string>  $defaults
     * @return array<int, string>
     */
    protected function gatherServices($given, array $defaults)
    {
        if ($given !== null && $given !== '') {
            return $given === 'none' ? [] : $this->optionList($given);
        }

        if ($this->option('no-interaction')) {
            return $defaults;
        }

        return multiselect(
            label: 'Which services would you like to install?',
            options: $this->services,
            default: ['mysql'],
        );
    }

    /**
     * Gather the desired Bot API server.
     *
     * @return string
     */
    protected function gatherBotApi()
    {
        if ($this->option('bot-api') !== null) {
            return $this->option('bot-api');
        }

        if ($this->option('no-interaction')) {
            return 'cloud';
        }

        return select(
            label: 'Which Telegram Bot API server should your bot use?',
            options: [
                'cloud' => 'Telegram cloud (api.telegram.org)',
                'official' => 'Official telegram-bot-api, in a container',
                'laragram' => 'LaraGram API Server, in a container',
            ],
            default: 'cloud',
        );
    }

    /**
     * Gather the server that runs the application.
     *
     * @return string
     */
    protected function gatherServer()
    {
        if ($this->option('server') !== null) {
            return $this->option('server');
        }

        return InstalledVersions::isInstalled('laraxgram/surge') ? 'surge' : 'serve';
    }

    /**
     * Gather how the bot should receive its updates.
     *
     * A local Bot API server can reach the application directly, so it does not need a public URL.
     *
     * @param  string  $botApi
     * @param  string  $server
     * @param  string  $cloudDefault
     * @return string
     */
    protected function gatherUpdates($botApi, $server, $cloudDefault)
    {
        if ($this->option('updates') !== null) {
            return $this->option('updates');
        }

        $default = $botApi === 'cloud' ? $cloudDefault : 'webhook';

        if ($this->option('no-interaction')) {
            return $default;
        }

        return select(
            label: 'How should the bot receive updates?',
            options: [
                'polling' => 'Long polling',
                'webhook' => 'Webhook',
            ],
            default: $default,
        );
    }

    /**
     * Gather the processes that should run next to the server.
     *
     * @param  string  $server
     * @return array<int, string>
     */
    protected function gatherProcesses($server)
    {
        if ($this->option('processes') !== null) {
            return $this->option('processes') === 'none' ? [] : $this->optionList($this->option('processes'));
        }

        $available = $this->availableProcesses($server);
        $defaults = array_values(array_intersect(array_keys($available), ['queue', 'watchdog', 'mtproto']));

        if ($this->option('no-interaction')) {
            return $defaults;
        }

        return multiselect(
            label: 'Which processes should run next to the server?',
            options: $available,
            default: $defaults,
        );
    }

    /**
     * Get the processes that make sense for the installed packages.
     *
     * @param  string  $server
     * @return array<string, string>
     */
    protected function availableProcesses($server)
    {
        $sentinel = InstalledVersions::isInstalled('laraxgram/sentinel');

        return array_filter([
            'queue' => 'Queue worker',
            'schedule' => 'Task scheduler',
            'watchdog' => InstalledVersions::isInstalled('laraxgram/watchdog') ? 'Watchdog log tail' : null,
            // Surge already runs the MTProto client as one of its own processes...
            'mtproto' => $server !== 'surge' && InstalledVersions::isInstalled('laraxgram/mtproto') ? 'MTProto client' : null,
            'ssr' => InstalledVersions::isInstalled('laraxgram/luna') ? 'Luna SSR server' : null,
            'sentinel-queue' => $sentinel ? 'Sentinel queue supervisor (Redis queues)' : null,
            'sentinel-check' => $sentinel ? 'Sentinel server checks' : null,
            'vite' => file_exists($this->laragram->basePath('package.json')) ? 'Vite development server' : null,
        ]);
    }

    /**
     * Split a comma-separated option into a list.
     *
     * @param  string  $value
     * @return array<int, string>
     */
    protected function optionList($value)
    {
        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    /**
     * Build the Docker Compose file.
     *
     * @param  array  $selection
     * @return void
     */
    protected function buildDockerCompose(array $selection)
    {
        $composePath = $this->composePath();
        $services = $selection['services'];

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents(__DIR__.'/../../../stubs/compose.stub'));

        $app = $this->appService;

        if ($this->hasOption('php') && isset($compose['services'][$app])) {
            $compose['services'][$app]['build']['args']['PHP_VERSION'] = $this->option('php');
            $compose['services'][$app]['image'] = 'armada-'.$this->option('php').'/app';
        }

        // Prepare the installation of the "mariadb-client" package if the MariaDB service is used...
        if (in_array('mariadb', $services)) {
            $compose['services'][$app]['build']['args']['MYSQL_CLIENT'] = 'mariadb-client';
        }

        // Adds the new services as dependencies of the application service...
        if (! array_key_exists($app, $compose['services'])) {
            $this->warn("Couldn't find the {$app} service. Make sure you add [".implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services'][$app]['depends_on'] = collect($compose['services'][$app]['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        // Add the services to the compose.yaml...
        foreach ($services as $service) {
            if (! array_key_exists($service, $compose['services'] ?? [])) {
                $compose['services'][$service] = Yaml::parseFile(__DIR__."/../../../stubs/{$service}.stub")[$service];
            }
        }

        // Merge volumes...
        foreach (array_intersect($services, $this->servicesWithVolumes) as $service) {
            $compose['volumes']["armada-{$service}"] ??= ['driver' => 'local'];
        }

        // The MongoDB service also persists the search index in a dedicated volume...
        if (in_array('mongodb', $services)) {
            $compose['volumes']['armada-mongodb-config'] ??= ['driver' => 'local'];
        }

        if ($selection['botApi'] !== null) {
            $this->configureBotApiService($compose, $selection['botApi']);
        }

        // If the list of volumes is empty, we can remove it...
        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        file_put_contents($composePath, Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP));
    }

    /**
     * Add, replace or remove the Bot API server container.
     *
     * @param  array  $compose
     * @param  string  $botApi
     * @return void
     */
    protected function configureBotApiService(array &$compose, $botApi)
    {
        $app = $this->appService;
        $volume = 'armada-bot-api:/var/lib/bot-api';

        unset($compose['services']['bot-api']);

        $compose['services'][$app]['depends_on'] = array_values(array_diff($compose['services'][$app]['depends_on'] ?? [], ['bot-api']));
        $compose['services'][$app]['volumes'] = array_values(array_diff($compose['services'][$app]['volumes'] ?? [], [$volume]));

        if ($botApi === 'cloud') {
            unset($compose['volumes']['armada-bot-api']);

            return;
        }

        $compose['services']['bot-api'] = Yaml::parseFile(__DIR__.'/../../../stubs/'.$this->botApiServers[$botApi].'.stub')['bot-api'];
        $compose['volumes']['armada-bot-api'] = ['driver' => 'local'];

        // Both containers mount this volume at the same path so the paths returned by getFile can be read directly...
        $compose['services'][$app]['volumes'][] = $volume;
        $compose['services'][$app]['depends_on'][] = 'bot-api';
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     *
     * @param  array  $selection
     * @return void
     */
    protected function replaceEnvVariables(array $selection)
    {
        $services = $selection['services'];
        $environment = file_get_contents($this->laragram->basePath('.env'));

        if (array_intersect(['mysql', 'mariadb', 'pgsql'], $services)) {
            foreach (['# DB_HOST=127.0.0.1', '# DB_PORT=3306', '# DB_DATABASE=laragram', '# DB_USERNAME=root', '# DB_PASSWORD='] as $default) {
                $environment = str_replace($default, substr($default, 2), $environment);
            }

            $environment = str_replace('DB_USERNAME=root', 'DB_USERNAME=armada', $environment);
            $environment = preg_replace('/^DB_PASSWORD=(.*)/m', 'DB_PASSWORD=password', $environment);
        }

        if (in_array('mysql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=mysql', $environment);
        } elseif (in_array('pgsql', $services)) {
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=pgsql', $environment);
            $environment = str_replace('DB_PORT=3306', 'DB_PORT=5432', $environment);
        } elseif (in_array('mariadb', $services)) {
            if ($this->laragram->config->has('database.connections.mariadb')) {
                $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mariadb', $environment);
            }

            $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=mariadb', $environment);
        }

        if (in_array('memcached', $services)) {
            $environment = str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
        }

        if (in_array('redis', $services)) {
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);
        }

        // Sentinel's Armada page reaches the Docker daemon through the socket proxy...
        if (in_array('docker-proxy', $services)) {
            $environment = $this->withEnvironmentValue($environment, 'SENTINEL_DOCKER_HOST', 'tcp://docker-proxy:2375');
        }

        if (in_array('valkey', $services)) {
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=valkey', $environment);
        }

        if (in_array('mongodb', $services)) {
            $username = $this->environmentValue($environment, 'MONGODB_USERNAME');
            $password = $this->environmentValue($environment, 'MONGODB_PASSWORD');

            // The MongoDB image enables authentication only when both credentials are provided...
            $credentials = ($username !== '' && $password !== '')
                ? rawurlencode($username).':'.rawurlencode($password).'@'
                : '';

            $environment = $this->withEnvironmentValue($environment, 'MONGODB_URI', "mongodb://{$credentials}mongodb:27017".($credentials === '' ? '' : '/?authSource=admin'));
            $environment = $this->withEnvironmentValue($environment, 'MONGODB_DATABASE', 'laragram');
        }

        $environment = str_replace('# PHP_CLI_SERVER_WORKERS=4', 'PHP_CLI_SERVER_WORKERS=4', $environment);

        foreach (['server' => 'ARMADA_SERVER', 'updates' => 'ARMADA_UPDATES'] as $key => $name) {
            if ($selection[$key] !== null) {
                $environment = $this->withEnvironmentValue($environment, $name, $selection[$key]);
            }
        }

        if ($selection['processes'] !== null) {
            $environment = $this->withEnvironmentValue($environment, 'ARMADA_PROCESSES', implode(',', $selection['processes']));
        }

        if ($selection['botApi'] !== null) {
            $environment = $this->replaceBotApiVariables($environment, $selection);
        }

        file_put_contents($this->laragram->basePath('.env'), $environment);
    }

    /**
     * Point the application at the selected Bot API server.
     *
     * @param  string  $environment
     * @param  array  $selection
     * @return string
     */
    protected function replaceBotApiVariables($environment, array $selection)
    {
        if ($selection['botApi'] === 'cloud') {
            return $this->withEnvironmentValue($environment, 'API_ENDPOINT', 'https://api.telegram.org');
        }

        $environment = $this->withEnvironmentValue($environment, 'API_ENDPOINT', 'http://bot-api:8081');

        if (InstalledVersions::isInstalled('laraxgram/sentinel')) {
            $environment = $this->withEnvironmentValue($environment, 'SENTINEL_API_SERVER_STATS_URL', 'http://bot-api:8082');
        }

        if ($selection['updates'] === 'webhook') {
            $environment = $this->withEnvironmentValue($environment, 'BOT_URL', "http://{$this->appService}");
        }

        foreach (['API_ID' => 'Telegram API ID', 'API_HASH' => 'Telegram API hash'] as $key => $label) {
            if ($this->environmentValue($environment, $key) === '' && ! $this->option('no-interaction')) {
                $value = text(label: "{$label} (from my.telegram.org)", hint: 'Leave it empty to add it to your .env file later.');

                if ($value !== '') {
                    $environment = $this->withEnvironmentValue($environment, $key, $value);
                }
            }
        }

        return $environment;
    }

    /**
     * Get the value of an environment variable from the ".env" file contents.
     *
     * @param  string  $environment
     * @param  string  $key
     * @return string
     */
    protected function environmentValue($environment, $key)
    {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $environment, $matches)) {
            return trim($matches[1], " \t\"'");
        }

        return '';
    }

    /**
     * Set a variable in the ".env" file contents, adding it when it is missing.
     *
     * @param  string  $environment
     * @param  string  $key
     * @param  string  $value
     * @return string
     */
    protected function withEnvironmentValue($environment, $key, $value)
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $environment)) {
            return preg_replace_callback($pattern, fn () => "{$key}={$value}", $environment);
        }

        return rtrim($environment, "\n")."\n{$key}={$value}\n";
    }

    /**
     * Install the devcontainer.json configuration file.
     *
     * @return void
     */
    protected function installDevContainer()
    {
        if (! is_dir($this->laragram->basePath('.devcontainer'))) {
            mkdir($this->laragram->basePath('.devcontainer'), 0755, true);
        }

        file_put_contents(
            $this->laragram->basePath('.devcontainer/devcontainer.json'),
            file_get_contents(__DIR__.'/../../../stubs/devcontainer.stub')
        );

        $environment = file_get_contents($this->laragram->basePath('.env'));

        $environment = $this->withEnvironmentValue($environment, 'WWWGROUP', '1000');
        $environment = $this->withEnvironmentValue($environment, 'WWWUSER', '1000');

        file_put_contents($this->laragram->basePath('.env'), $environment);
    }

    /**
     * Prepare the installation by pulling and building any necessary images.
     *
     * @param  array  $selection
     * @return void
     */
    protected function prepareInstallation(array $selection)
    {
        // Ensure docker is installed...
        if ($this->runCommands(['docker info > /dev/null 2>&1']) !== 0) {
            return;
        }

        if (count($selection['services']) > 0) {
            $this->runCommands(['./vendor/bin/armada pull '.implode(' ', $selection['services'])]);
        }

        // Prefer the published Bot API image and only compile it when it is not available...
        if (in_array($selection['botApi'], ['official', 'laragram'], true)
            && $this->runCommands(['./vendor/bin/armada pull bot-api']) !== 0) {
            $this->components->warn('The Bot API image could not be pulled, so it will be built from source. This may take a while.');

            $this->runCommands(['./vendor/bin/armada build bot-api']);
        }

        $this->runCommands(['./vendor/bin/armada build '.$this->appService]);
    }

    /**
     * Display what the user should do after the containers have started.
     *
     * @param  array  $selection
     * @return void
     */
    protected function displayNextSteps(array $selection)
    {
        if (array_intersect(['mysql', 'mariadb', 'pgsql'], $selection['services'])) {
            $this->components->warn('A database service was installed. Run "laragram migrate" to prepare your database:');
            $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada laragram migrate</>');
        }

        if (in_array($selection['botApi'], ['official', 'laragram'], true)) {
            $this->components->warn('Log the bot out of the cloud Bot API once before using your own server:');
            $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada bot-api logout</>');
        }

        if ($selection['updates'] === 'webhook') {
            $this->components->warn('Register the webhook after the containers have started:');
            $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada laragram webhook:set</>');
        }

        if (in_array('mtproto', $selection['processes'] ?? [], true) || ($selection['server'] === 'surge' && InstalledVersions::isInstalled('laraxgram/mtproto'))) {
            $this->components->warn('Authorize your MTProto session, then restart Armada:');
            $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada laragram client:auth</>');
        }
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @return int
     */
    protected function runCommands($commands)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) {
            $this->output->write('    '.$line);
        });
    }

    /**
     * Get the path to an existing Compose file or fall back to a default of `compose.yaml`.
     *
     * @return string
     */
    protected function composePath()
    {
        return collect($this->composePaths)
            ->map(fn ($path) => $this->laragram->basePath($path))
            ->first(fn ($path) => file_exists($path), $this->laragram->basePath('compose.yaml'));
    }
}
