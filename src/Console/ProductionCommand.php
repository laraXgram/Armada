<?php

namespace LaraGram\Armada\Console;

use Composer\InstalledVersions;
use LaraGram\Console\Attribute\AsCommand;
use LaraGram\Console\Command;
use LaraGram\Support\Yaml\Yaml;

#[AsCommand(name: 'armada:production')]
class ProductionCommand extends Command
{
    use Concerns\InteractsWithDockerComposeServices;

    /**
     * The name of the production Compose file.
     *
     * @var string
     */
    protected const COMPOSE_FILE = 'compose.production.yaml';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'armada:production
                {--with= : The services that should be included}
                {--bot-api= : The Bot API server to use (cloud, official or laragram)}
                {--server= : The server that runs the application (fpm or surge)}
                {--updates= : How the bot receives updates (webhook or polling)}
                {--roles= : The roles that run in their own containers}
                {--php=8.5 : The PHP version that should be used}
                {--force : Overwrite an existing production Compose file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the production Dockerfile and Compose file';

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

        $path = $this->laragram->basePath(static::COMPOSE_FILE);

        if (file_exists($path) && ! $this->option('force')) {
            $this->components->error('The ['.static::COMPOSE_FILE.'] file already exists. Use the --force option to overwrite it.');

            return self::FAILURE;
        }

        $services = $this->gatherServices($this->option('with'), ['mysql', 'redis']);
        $botApi = $this->gatherBotApi();
        $server = $this->productionServer();
        $updates = $this->gatherUpdates($botApi, $server, 'webhook');
        $roles = $this->productionRoles($server, $updates);

        if ($invalid = array_diff($services, $this->services)) {
            $this->components->error('Invalid services ['.implode(',', $invalid).'].');

            return self::FAILURE;
        }

        if (! array_key_exists($botApi, $this->botApiServers)) {
            $this->components->error("Invalid Bot API server [{$botApi}].");

            return self::FAILURE;
        }

        if (! in_array($server, ['fpm', 'surge'], true)) {
            $this->components->error("Invalid server [{$server}]. Production images use fpm or surge.");

            return self::FAILURE;
        }

        if (! in_array($updates, ['webhook', 'polling'], true)) {
            $this->components->error("Invalid update method [{$updates}].");

            return self::FAILURE;
        }

        if ($invalid = array_diff($roles, $this->productionRoleNames)) {
            $this->components->error('Invalid roles ['.implode(',', $invalid).'].');

            return self::FAILURE;
        }

        $this->call('vendor:publish', ['--tag' => 'armada-production']);

        $yaml = Yaml::dump(
            $this->productionCompose($services, $botApi, $server, $updates, $roles),
            Yaml::DUMP_OBJECT_AS_MAP
        );

        file_put_contents($path, $yaml);

        $this->output->writeln('');
        $this->components->info('The production files were created. Review the Compose file, then build and start the application:');
        $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada prod build app</>');
        $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada prod up -d</>');
        $this->output->writeln('');

        if ($botApi !== 'cloud') {
            $this->components->warn('Log the bot out of the cloud Bot API once before switching to your own server:');
            $this->output->writeln('<fg=gray>➜</> <options=bold>./vendor/bin/armada bot-api logout</>');
            $this->output->writeln('');
        }

        return self::SUCCESS;
    }

    /**
     * Get the server that runs the application in production.
     *
     * @return string
     */
    protected function productionServer()
    {
        return $this->option('server') ?: (InstalledVersions::isInstalled('laraxgram/surge') ? 'surge' : 'fpm');
    }

    /**
     * Get the roles that run in their own containers.
     *
     * @param  string  $server
     * @param  string  $updates
     * @return array<int, string>
     */
    protected function productionRoles($server, $updates)
    {
        if ($this->option('roles') !== null) {
            return $this->optionList($this->option('roles'));
        }

        $sentinel = InstalledVersions::isInstalled('laraxgram/sentinel');

        return array_values(array_filter([
            'queue',
            'schedule',
            $updates === 'polling' && $server !== 'surge' ? 'poller' : null,
            $server !== 'surge' && InstalledVersions::isInstalled('laraxgram/mtproto') ? 'mtproto' : null,
            InstalledVersions::isInstalled('laraxgram/luna') && file_exists($this->laragram->basePath('package.json')) ? 'ssr' : null,
            $sentinel ? 'sentinel-check' : null,
        ]));
    }

    /**
     * Build the production Compose file.
     *
     * @param  array<int, string>  $services
     * @param  string  $botApi
     * @param  string  $server
     * @param  string  $updates
     * @param  array<int, string>  $roles
     * @return array
     */
    protected function productionCompose(array $services, $botApi, $server, $updates, array $roles)
    {
        $compose = Yaml::parse(file_get_contents(__DIR__.'/../../stubs/compose.production.stub'));

        $base = $compose['services']['app'];
        $base['build']['args']['PHP_VERSION'] = $this->option('php');
        $base['build']['args']['INSTALL_NODE'] = in_array('ssr', $roles, true) ? '1' : '0';
        $base['environment']['ARMADA_SERVER'] = $server;
        $base['environment']['ARMADA_UPDATES'] = $updates;

        $this->applyServiceHosts($base, $services);

        foreach ($services as $service) {
            $base['depends_on'][$service] = ['condition' => $this->hasHealthCheck($service) ? 'service_healthy' : 'service_started'];

            $compose['services'][$service] = $this->productionService(
                Yaml::parseFile(__DIR__."/../../stubs/{$service}.stub")[$service]
            );
        }

        foreach (array_intersect($services, $this->servicesWithVolumes) as $service) {
            $compose['volumes']["armada-{$service}"] = ['driver' => 'local'];
        }

        if (in_array('mongodb', $services, true)) {
            $compose['volumes']['armada-mongodb-config'] = ['driver' => 'local'];
        }

        if ($botApi !== 'cloud') {
            $compose['services']['bot-api'] = $this->productionService(
                Yaml::parseFile(__DIR__.'/../../stubs/'.$this->botApiServers[$botApi].'.stub')['bot-api']
            );

            $compose['services']['bot-api']['build']['context'] = './docker/'.$this->botApiServers[$botApi];
            $compose['services']['bot-api']['environment']['WWWUSER'] = '1000';
            $compose['services']['bot-api']['environment']['WWWGROUP'] = '1000';
            $compose['services']['bot-api']['environment']['TELEGRAM_API_ID'] = '${API_ID:?Set API_ID in your .env file}';
            $compose['services']['bot-api']['environment']['TELEGRAM_API_HASH'] = '${API_HASH:?Set API_HASH in your .env file}';
            $compose['volumes']['armada-bot-api'] = ['driver' => 'local'];

            $base['volumes'][] = 'armada-bot-api:/var/lib/bot-api';
            $base['environment']['API_ENDPOINT'] = 'http://bot-api:8081';

            // A Bot API server in the same network delivers webhooks straight to the application...
            if ($updates === 'webhook') {
                $base['environment']['BOT_URL'] = 'http://app:8000';
            }

            $base['depends_on']['bot-api'] = ['condition' => 'service_healthy'];
        }

        $compose['services']['app'] = $base;

        foreach ($roles as $role) {
            $service = $base;
            $service['environment']['ARMADA_ROLE'] = $role;
            $service['healthcheck'] = ['disable' => true];

            unset($service['ports'], $service['build']);

            $service['depends_on']['app'] = ['condition' => 'service_healthy'];

            $compose['services'][$role] = $service;
        }

        return $compose;
    }

    /**
     * Point the application at the containers of the selected services.
     *
     * @param  array  $base
     * @param  array<int, string>  $services
     * @return void
     */
    protected function applyServiceHosts(array &$base, array $services)
    {
        foreach (['mysql', 'mariadb', 'pgsql'] as $database) {
            if (in_array($database, $services, true)) {
                $base['environment']['DB_CONNECTION'] = $database === 'mariadb' ? 'mariadb' : $database;
                $base['environment']['DB_HOST'] = $database;
                $base['environment']['DB_PORT'] = $database === 'pgsql' ? '5432' : '3306';

                break;
            }
        }

        foreach (['redis', 'valkey'] as $redis) {
            if (in_array($redis, $services, true)) {
                $base['environment']['REDIS_HOST'] = $redis;

                break;
            }
        }

        if (in_array('memcached', $services, true)) {
            $base['environment']['MEMCACHED_HOST'] = 'memcached';
        }
    }

    /**
     * Determine if the given service has a health check.
     *
     * @param  string  $service
     * @return bool
     */
    protected function hasHealthCheck($service)
    {
        return in_array($service, ['mysql', 'mariadb', 'pgsql', 'redis', 'valkey', 'mongodb'], true);
    }

    /**
     * Harden a development service definition for production.
     *
     * @param  array  $service
     * @return array
     */
    protected function productionService(array $service)
    {
        unset($service['ports'], $service['build']['args']);

        $service['restart'] = 'unless-stopped';

        // Testing databases have no place on a production server...
        $service['volumes'] = array_values(array_filter(
            $service['volumes'] ?? [],
            fn ($volume) => ! str_contains($volume, 'create-testing-database')
        ));

        if (empty($service['volumes'])) {
            unset($service['volumes']);
        }

        unset($service['environment']['MYSQL_ALLOW_EMPTY_PASSWORD'], $service['environment']['MARIADB_ALLOW_EMPTY_ROOT_PASSWORD']);

        if (isset($service['environment'])) {
            array_walk_recursive($service['environment'], function (&$value) {
                $value = is_string($value)
                    ? preg_replace('/\$\{DB_PASSWORD(:-[^}]*)?\}/', '${DB_PASSWORD:?Set DB_PASSWORD in your .env file}', $value)
                    : $value;
            });
        }

        return $service;
    }
}
