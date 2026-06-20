<?php

namespace Gohari\RepositoryPattern\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class MakeRepositoryCommand extends Command
{
    protected $signature = 'repository:make
        {name : Repository name, for example User or UserRepository}
        {--model= : Model class name or FQCN, for example User or App\\Models\\User}
        {--path= : Repository output path}
        {--interface-path= : Repository interface output path}
        {--force : Overwrite existing repository files}
        {--bind : Bind the generated interface to the repository in App\\Providers\\RepositoryServiceProvider}
        {--service : Generate service layer, service interface, DTO, and config}
        {--service-path= : Service output path}
        {--dto : Generate DTO class}
        {--dto-path= : DTO output path}
        {--config : Copy the repository-pattern config file to the application}';

    protected $description = 'Create a repository and repository interface for a Laravel model.';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $repositoryName = $this->normalizeRepositoryName($this->argument('name'));
        $model = $this->normalizeModel($this->option('model') ?: $repositoryName);
        $shouldGenerateService = $this->option('service') || (bool) config('repository-pattern.generate_service', false);
        $shouldGenerateDto = $shouldGenerateService || $this->option('dto');
        $shouldBind = $this->option('bind') || (bool) config('repository-pattern.auto_bind', false);

        if ((bool) config('repository-pattern.generate_model_if_missing', true)) {
            $this->ensureModelExists($model['class']);
        }

        $basePath = $this->repositoryPath();
        $interfaceBasePath = $this->interfacePath();
        $repositoryPath = $basePath.DIRECTORY_SEPARATOR.$repositoryName.'Repository.php';
        $interfacePath = $interfaceBasePath.DIRECTORY_SEPARATOR.$repositoryName.'RepositoryInterface.php';
        $servicePath = $this->servicePath().DIRECTORY_SEPARATOR.$repositoryName.'Service.php';
        $serviceInterfacePath = $this->servicePath().DIRECTORY_SEPARATOR.$repositoryName.'ServiceInterface.php';
        $dtoPath = $this->dtoPath().DIRECTORY_SEPARATOR.$repositoryName.'Data.php';

        if (! $this->option('force') && ($this->files->exists($repositoryPath) || $this->files->exists($interfacePath))) {
            $this->components->error('Repository files already exist. Use --force to overwrite them.');

            return self::FAILURE;
        }

        if (! $this->option('force') && $shouldGenerateService && ($this->files->exists($servicePath) || $this->files->exists($serviceInterfacePath))) {
            $this->components->error('Service files already exist. Use --force to overwrite them.');

            return self::FAILURE;
        }

        if (! $this->option('force') && $shouldGenerateDto && $this->files->exists($dtoPath)) {
            $this->components->error('DTO file already exists. Use --force to overwrite it.');

            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists($basePath);
        $this->files->ensureDirectoryExists($interfaceBasePath);

        $namespace = $this->repositoryNamespace();
        $interfaceNamespace = $this->interfaceNamespace();
        $interfaceClass = $repositoryName.'RepositoryInterface';
        $service = null;

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ repository }}' => $repositoryName.'Repository',
            '{{ interface }}' => $namespace === $interfaceNamespace ? $interfaceClass : '\\'.$interfaceNamespace.'\\'.$interfaceClass,
            '{{ modelClass }}' => $model['class'],
            '{{ model }}' => $model['short'],
            '{{ modelVariable }}' => Str::camel($model['short']),
        ];

        $this->files->put($repositoryPath, $this->buildStub('repository.stub', $replacements));
        $this->files->put($interfacePath, $this->buildStub('repositoryInterface.stub', array_merge($replacements, [
            '{{ namespace }}' => $interfaceNamespace,
            '{{ interface }}' => $interfaceClass,
        ])));

        $this->components->info($repositoryName.' repository created successfully.');
        $this->line('Repository: '.$repositoryPath);
        $this->line('Interface:  '.$interfacePath);

        if ($shouldGenerateService) {
            $service = $this->generateServiceLayer($repositoryName, $interfaceNamespace);
        } elseif ($shouldGenerateDto) {
            $this->generateDto($repositoryName);
        }

        if ($shouldGenerateService || $this->option('config')) {
            $this->publishConfig();
        }

        if ($shouldBind) {
            $this->bindRepository($namespace, $repositoryName.'Repository', $interfaceNamespace, $repositoryName.'RepositoryInterface');

            if ($service !== null) {
                $this->bindRepository($service['namespace'], $service['service'], $service['namespace'], $service['interface'], 'Service');
            }
        }

        return self::SUCCESS;
    }

    private function normalizeRepositoryName(string $name): string
    {
        $name = class_basename(str_replace('/', '\\', $name));

        return Str::studly(Str::beforeLast($name, 'Repository'));
    }

    /**
     * @return array{class: string, short: string}
     */
    private function normalizeModel(string $model): array
    {
        $model = trim(str_replace('/', '\\', $model), '\\');
        $class = str_contains($model, '\\') ? $model : 'App\\Models\\'.Str::studly($model);

        return [
            'class' => $class,
            'short' => class_basename($class),
        ];
    }

    private function ensureModelExists(string $modelClass): void
    {
        $path = $this->pathFromAppClass($modelClass);

        if ($this->files->exists($path)) {
            return;
        }

        if (! Str::startsWith($modelClass, 'App\\Models\\')) {
            $this->components->warn('Model '.$modelClass.' was not found. Only App\\Models classes can be generated automatically.');

            return;
        }

        $this->callSilent('make:model', [
            'name' => Str::after($modelClass, 'App\\Models\\'),
        ]);

        $this->components->info('Model '.$modelClass.' created.');
    }

    private function pathFromAppClass(string $class): string
    {
        if (Str::startsWith($class, 'App\\')) {
            return app_path(str_replace('\\', DIRECTORY_SEPARATOR, Str::after($class, 'App\\')).'.php');
        }

        return base_path(str_replace('\\', DIRECTORY_SEPARATOR, $class).'.php');
    }

    private function namespaceFromPath(string $path): string
    {
        $path = trim(str_replace(['/', DIRECTORY_SEPARATOR], '\\', $path), '\\');

        if (Str::startsWith($path, 'app\\')) {
            $path = Str::after($path, 'app\\');

            return 'App\\'.collect(explode('\\', $path))
                ->filter()
                ->map(fn (string $part) => Str::studly($part))
                ->implode('\\');
        }

        return collect(explode('\\', $path))
            ->filter()
            ->map(fn (string $part) => Str::studly($part))
            ->implode('\\');
    }

    private function repositoryPath(): string
    {
        return $this->resolvePath($this->option('path') ?: config('repository-pattern.paths.repositories', app_path('Repositories')));
    }

    private function interfacePath(): string
    {
        if ($this->option('interface-path')) {
            return $this->resolvePath($this->option('interface-path'));
        }

        if ($this->option('path')) {
            return $this->repositoryPath();
        }

        return $this->resolvePath(config('repository-pattern.paths.interfaces', app_path('Repositories/Contracts')));
    }

    private function servicePath(): string
    {
        return $this->resolvePath($this->option('service-path') ?: config('repository-pattern.paths.services', app_path('Services')));
    }

    private function dtoPath(): string
    {
        return $this->resolvePath($this->option('dto-path') ?: config('repository-pattern.paths.dtos', app_path('DTOs')));
    }

    private function repositoryNamespace(): string
    {
        return $this->option('path')
            ? $this->namespaceFromPath($this->option('path'))
            : config('repository-pattern.namespaces.repositories', 'App\\Repositories');
    }

    private function interfaceNamespace(): string
    {
        if ($this->option('interface-path')) {
            return $this->namespaceFromPath($this->option('interface-path'));
        }

        if ($this->option('path')) {
            return $this->repositoryNamespace();
        }

        return config('repository-pattern.namespaces.interfaces', 'App\\Repositories\\Contracts');
    }

    private function serviceNamespace(): string
    {
        return $this->option('service-path')
            ? $this->namespaceFromPath($this->option('service-path'))
            : config('repository-pattern.namespaces.services', 'App\\Services');
    }

    private function dtoNamespace(): string
    {
        return $this->option('dto-path')
            ? $this->namespaceFromPath($this->option('dto-path'))
            : config('repository-pattern.namespaces.dtos', 'App\\DTOs');
    }

    private function resolvePath(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return Str::startsWith($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:'.preg_quote(DIRECTORY_SEPARATOR, '/').'/', $path) === 1;
    }

    private function bindRepository(string $namespace, string $repository, string $interfaceNamespace, string $interface, string $label = 'Repository'): void
    {
        $repositoryClass = $namespace.'\\'.$repository;
        $interfaceClass = $interfaceNamespace.'\\'.$interface;
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        $this->ensureRepositoryServiceProviderExists($providerPath);
        $this->addBindingToRepositoryServiceProvider($providerPath, $interfaceClass, $repositoryClass);
        $this->registerRepositoryServiceProvider();

        $this->components->info($label.' binding registered in App\\Providers\\RepositoryServiceProvider.');
    }

    /**
     * @return array{namespace: string, service: string, interface: string}
     */
    private function generateServiceLayer(string $repositoryName, string $repositoryInterfaceNamespace): array
    {
        $dto = $this->generateDto($repositoryName);
        $serviceBasePath = $this->servicePath();
        $serviceNamespace = $this->serviceNamespace();
        $service = $repositoryName.'Service';
        $interface = $repositoryName.'ServiceInterface';

        $this->files->ensureDirectoryExists($serviceBasePath);

        $replacements = [
            '{{ namespace }}' => $serviceNamespace,
            '{{ service }}' => $service,
            '{{ interface }}' => $interface,
            '{{ dto }}' => $dto['class'],
            '{{ dtoClass }}' => $dto['namespace'].'\\'.$dto['class'],
            '{{ repositoryInterface }}' => $repositoryName.'RepositoryInterface',
            '{{ repositoryInterfaceClass }}' => $repositoryInterfaceNamespace.'\\'.$repositoryName.'RepositoryInterface',
            '{{ repositoryVariable }}' => Str::camel($repositoryName).'Repository',
        ];

        $servicePath = $serviceBasePath.DIRECTORY_SEPARATOR.$service.'.php';
        $interfacePath = $serviceBasePath.DIRECTORY_SEPARATOR.$interface.'.php';

        $this->files->put($servicePath, $this->buildStub('service.stub', $replacements));
        $this->files->put($interfacePath, $this->buildStub('serviceInterface.stub', $replacements));

        $this->components->info($repositoryName.' service layer created successfully.');
        $this->line('Service:   '.$servicePath);
        $this->line('Interface: '.$interfacePath);

        return [
            'namespace' => $serviceNamespace,
            'service' => $service,
            'interface' => $interface,
        ];
    }

    /**
     * @return array{namespace: string, class: string}
     */
    private function generateDto(string $repositoryName): array
    {
        $dtoBasePath = $this->dtoPath();
        $dtoNamespace = $this->dtoNamespace();
        $dto = $repositoryName.'Data';
        $dtoPath = $dtoBasePath.DIRECTORY_SEPARATOR.$dto.'.php';

        $this->files->ensureDirectoryExists($dtoBasePath);

        $this->files->put($dtoPath, $this->buildStub('dto.stub', [
            '{{ namespace }}' => $dtoNamespace,
            '{{ dto }}' => $dto,
        ]));

        $this->components->info($repositoryName.' DTO created successfully.');
        $this->line('DTO:       '.$dtoPath);

        return [
            'namespace' => $dtoNamespace,
            'class' => $dto,
        ];
    }

    private function publishConfig(): void
    {
        $target = config_path('repository-pattern.php');

        if ($this->files->exists($target) && ! $this->option('force')) {
            return;
        }

        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy(__DIR__.'/../../config/repository-pattern.php', $target);

        $this->components->info('Repository pattern config published.');
        $this->line('Config:    '.$target);
    }

    private function ensureRepositoryServiceProviderExists(string $providerPath): void
    {
        if ($this->files->exists($providerPath)) {
            return;
        }

        $this->files->ensureDirectoryExists(dirname($providerPath));

        $this->files->put($providerPath, <<<'PHP'
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        //
    }
}
PHP);
    }

    private function addBindingToRepositoryServiceProvider(string $providerPath, string $interfaceClass, string $repositoryClass): void
    {
        $contents = $this->files->get($providerPath);

        if (str_contains($contents, '\\'.$interfaceClass.'::class')) {
            return;
        }

        $binding = <<<PHP
        \$this->app->bind(
            \\{$interfaceClass}::class,
            \\{$repositoryClass}::class
        );

PHP;

        if (preg_match('/    public function register\(\): void\s*\{\R/', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1] + strlen($matches[0][0]);
            $contents = substr($contents, 0, $position).$binding.substr($contents, $position);
        } else {
            $position = strrpos($contents, '}');

            if ($position === false) {
                $this->components->warn('Could not automatically add repository binding to RepositoryServiceProvider.');

                return;
            }

            $method = <<<PHP
    public function register(): void
    {
{$binding}    }

PHP;

            $contents = substr($contents, 0, $position).$method.substr($contents, $position);
        }

        $this->files->put($providerPath, $contents);
    }

    private function registerRepositoryServiceProvider(): void
    {
        $providerClass = 'App\\Providers\\RepositoryServiceProvider::class';
        $bootstrapProvidersPath = base_path('bootstrap/providers.php');

        if ($this->files->exists($bootstrapProvidersPath)) {
            $this->addProviderToBootstrapProviders($bootstrapProvidersPath, $providerClass);

            return;
        }

        $configAppPath = config_path('app.php');

        if ($this->files->exists($configAppPath)) {
            $this->addProviderToConfigApp($configAppPath, $providerClass);

            return;
        }

        $this->files->ensureDirectoryExists(dirname($bootstrapProvidersPath));
        $this->files->put($bootstrapProvidersPath, <<<PHP
<?php

return [
    {$providerClass},
];
PHP);
    }

    private function addProviderToBootstrapProviders(string $path, string $providerClass): void
    {
        $contents = $this->files->get($path);

        if (str_contains($contents, $providerClass)) {
            return;
        }

        $position = strrpos($contents, '];');

        if ($position === false) {
            $this->components->warn('Could not automatically register RepositoryServiceProvider in bootstrap/providers.php.');

            return;
        }

        $contents = substr($contents, 0, $position)."    {$providerClass},\n".substr($contents, $position);

        $this->files->put($path, $contents);
    }

    private function addProviderToConfigApp(string $path, string $providerClass): void
    {
        $contents = $this->files->get($path);

        if (str_contains($contents, $providerClass)) {
            return;
        }

        if (! preg_match("/'providers'\\s*=>\\s*\\[\R/", $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $this->components->warn('Could not automatically register RepositoryServiceProvider in config/app.php.');

            return;
        }

        $position = $matches[0][1] + strlen($matches[0][0]);
        $contents = substr($contents, 0, $position)."        {$providerClass},\n".substr($contents, $position);

        $this->files->put($path, $contents);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function buildStub(string $stub, array $replacements): string
    {
        $publishedStub = $this->resolvePath(config('repository-pattern.paths.stubs', resource_path('stubs/vendor/repository-pattern'))).DIRECTORY_SEPARATOR.$stub;
        $packageStub = __DIR__.'/../stubs/'.$stub;
        $contents = $this->files->get($this->files->exists($publishedStub) ? $publishedStub : $packageStub);

        return str_replace(array_keys($replacements), array_values($replacements), $contents);
    }
}
