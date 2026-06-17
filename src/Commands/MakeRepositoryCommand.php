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
        {--path=app/Repositories : Repository output path}
        {--force : Overwrite existing repository files}';

    protected $description = 'Create a repository and repository interface for a Laravel model.';

    public function __construct(private readonly Filesystem $files)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $repositoryName = $this->normalizeRepositoryName($this->argument('name'));
        $model = $this->normalizeModel($this->option('model') ?: $repositoryName);

        $this->ensureModelExists($model['class']);

        $basePath = base_path($this->option('path'));
        $repositoryPath = $basePath.DIRECTORY_SEPARATOR.$repositoryName.'Repository.php';
        $interfacePath = $basePath.DIRECTORY_SEPARATOR.$repositoryName.'RepositoryInterface.php';

        if (! $this->option('force') && ($this->files->exists($repositoryPath) || $this->files->exists($interfacePath))) {
            $this->components->error('Repository files already exist. Use --force to overwrite them.');

            return self::FAILURE;
        }

        $this->files->ensureDirectoryExists($basePath);

        $namespace = $this->namespaceFromPath($this->option('path'));

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ repository }}' => $repositoryName.'Repository',
            '{{ interface }}' => $repositoryName.'RepositoryInterface',
            '{{ modelClass }}' => $model['class'],
            '{{ model }}' => $model['short'],
            '{{ modelVariable }}' => Str::camel($model['short']),
        ];

        $this->files->put($repositoryPath, $this->buildStub('repository.stub', $replacements));
        $this->files->put($interfacePath, $this->buildStub('repositoryInterface.stub', $replacements));

        $this->components->info($repositoryName.' repository created successfully.');
        $this->line('Repository: '.$repositoryPath);
        $this->line('Interface:  '.$interfacePath);

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

    /**
     * @param array<string, string> $replacements
     */
    private function buildStub(string $stub, array $replacements): string
    {
        $contents = $this->files->get(__DIR__.'/../stubs/'.$stub);

        return str_replace(array_keys($replacements), array_values($replacements), $contents);
    }
}
