<?php

namespace Dan\Core;

use Dan\Addons\AddonLoader;
use Dan\Commands\CommandManager;
use Dan\Commands\CommandServiceProvider;
use Dan\Config\ConfigServiceProvider;
use Dan\Connection\Handler as ConnectionHandler;
use Dan\Console\ConsoleServiceProvider;
use Dan\Contracts\DatabaseContract;
use Dan\Contracts\PluginContract;
use Dan\Core\Traits\Database;
use Dan\Core\Traits\Paths;
use Dan\Database\DatabaseServiceProvider;
use Dan\Events\EventServiceProvider;
use Dan\Irc\IrcServiceProvider;
use Dan\Log\Logger;
use Dan\Setup\Setup;
use Dan\Update\UpdateServiceProvider;
use Dan\Web\WebServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use ReflectionObject;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Dan extends Container implements DatabaseContract
{
    use Paths, Database;

    const VERSION = '6.0.0';

    /**
     * @var array
     */
    protected $providers = [];

    /**
     * @var array
     */
    protected $coreProviders = [
        ConsoleServiceProvider::class,
        DatabaseServiceProvider::class,
        EventServiceProvider::class,
        ExceptionServiceProvider::class,
        UpdateServiceProvider::class,
        CommandServiceProvider::class,
        WebServiceProvider::class,
    ];

    /**
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * Dan constructor. Loads all the low-level providers and bindings.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param bool $command
     */
    public function __construct(InputInterface $input, OutputInterface $output, $command = false)
    {
        $input->setInteractive(true);

        if (!Setup::isSetup() && noInteractionSetup()) {
            (new Setup($input, $output))->silentSetup();
        }

        $this->instance('logger', new Logger('logger'));
        $this->instance('input', $input);
        $this->instance('output', $output);

        $this->bindPathsInContainer();
        $this->registerCoreAliases();
        $this->registerCoreBindings();

        $this->make('logger')->beginSession(['error', 'debug', 'logger']);

        $this->loadProvider(ConfigServiceProvider::class);

        $this->createPaths();

        if (!$command) {
            $this->registerCoreProviders();

            if (console()->option('debug', false)) {
                config()->set('dan.debug', true);
            }
        }
    }

    /**
     * This is where we boot non-core providers. Like IRC, Web listener, plugins, etc.
     */
    public function boot()
    {
        $this->registerProviders();
        $this->loadProvider(IrcServiceProvider::class);

        $this->make('addons')->loadAll();
    }

    /**
     * Starts the connection reader.
     */
    public function run()
    {
        $this['connections']->start();
        $this['connections']->readConnections();
    }

    /**
     * Register Dan's core aliases.
     */
    protected function registerCoreAliases()
    {
        $aliases = [
            'dan'           => [self::class, Container::class],
            'filesystem'    => ['Illuminate\Filesystem\Filesystem', 'Illuminate\Contracts\Filesystem\Filesystem'],
            'connections'   => [ConnectionHandler::class],
            'commands'      => [CommandManager::class],
        ];

        foreach ($aliases as $key => $list) {
            foreach ($list as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     *  Load all core bindings.
     */
    protected function registerCoreBindings()
    {
        static::setInstance($this);

        $this->instance('dan', $this);
        $this->instance('Illuminate\Container\Container', $this);
        $this->instance('connections', new ConnectionHandler());
        $this->instance('filesystem', new Filesystem());
        $this->instance('addons', new AddonLoader());
    }

    /**
     * Loads all core service providers.
     */
    protected function registerCoreProviders()
    {
        loop($this->coreProviders, function ($provider) {
            /** @var ServiceProvider $provider */
            $provider = new $provider($this);
            $provider->register();
        });
    }

    /**
     * Loads all non-critical providers.
     */
    protected function registerProviders()
    {
        $providers = config('dan.providers', []);

        foreach ($providers as $provider) {
            if (in_array($provider, [IrcServiceProvider::class, CommandServiceProvider::class, WebServiceProvider::class])) {
                console()->warn("{{$provider} is in the providers array. Please remove it.");
                continue;
            }

            if (!class_exists($provider)) {
                console()->warn("Class {$provider} does not exist.");
                continue;
            }

            console()->info("Loading provider {$provider}");

            $this->loadProvider($provider);
        }
    }

    /**
     * @param $provider
     */
    protected function loadProvider($provider)
    {
        /** @var ServiceProvider|PluginContract $provider */
        $provider = new $provider($this);

        if ($provider instanceof PluginContract) {
            $this->registerPlugin($provider);
        }

        $provider->register();
        $this->providers[get_class($provider)] = $provider;
    }

    /**
     * @param \Dan\Contracts\PluginContract $contract
     */
    protected function registerPlugin(PluginContract $contract)
    {
        $config = $contract->config();
        $name = $contract->getName();

        $object = new ReflectionObject($contract);
        $path = dirname(dirname($object->getFileName()));

        $pluginConfig = json_decode(file_get_contents("{$path}/plugin.json"), true);

        if (isset($pluginConfig['commands']) && $pluginConfig['commands']) {
            console()->warn("Plugin {$name} uses an outdated addon loading method. Please use <b>addons</b> instead.");
            $this->make('addons')->addPath("{$path}/commands");
        }

        if (isset($pluginConfig['addons']) && $pluginConfig['addons']) {
            $this->make('addons')->addPath("{$path}/addons");
        }

        if (empty($config) || empty($name)) {
            return;
        }

        if (filesystem()->exists(configPath("{$name}.json"))) {
            return;
        }

        file_put_contents(configPath("{$name}.json"), json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * @param $class
     *
     * @return \Illuminate\Support\ServiceProvider
     */
    public function provider($class) : ServiceProvider
    {
        return $this->providers[$class];
    }

    /**
     * @return string
     */
    public function versionHash()
    {
        if (!file_exists(ROOT_DIR.'/.git')) {
            return false;
        }

        return trim(shell_exec('git rev-parse --short HEAD'));
    }
}
