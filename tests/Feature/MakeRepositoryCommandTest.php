<?php

namespace Gohari\RepositoryPattern\Tests\Feature;

use Gohari\RepositoryPattern\Tests\Fixtures\CommandUser;
use Gohari\RepositoryPattern\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class MakeRepositoryCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->app['config']->set('repository-pattern.auto_bind', false);
        $this->app['config']->set('repository-pattern.generate_service', false);
        $this->app['config']->set('repository-pattern.generate_model_if_missing', true);
        $this->cleanGeneratedFiles();
    }

    protected function tearDown(): void
    {
        $this->cleanGeneratedFiles();

        parent::tearDown();
    }

    public function test_it_registers_the_repository_make_command(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('repository:make', $commands);
    }

    public function test_it_creates_repository_and_interface_for_a_model(): void
    {
        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--path' => 'tests/Fixtures/Generated',
        ])->assertSuccessful();

        $repositoryPath = base_path('tests/Fixtures/Generated/CommandUserRepository.php');
        $interfacePath = base_path('tests/Fixtures/Generated/CommandUserRepositoryInterface.php');

        $this->assertFileExists($repositoryPath);
        $this->assertFileExists($interfacePath);

        $this->assertStringContainsString('namespace Tests\Fixtures\Generated;', $this->files->get($repositoryPath));
        $this->assertStringContainsString('use '.CommandUser::class.';', $this->files->get($repositoryPath));
        $this->assertStringContainsString('class CommandUserRepository extends BaseRepository implements CommandUserRepositoryInterface', $this->files->get($repositoryPath));
        $this->assertStringContainsString('interface CommandUserRepositoryInterface extends BaseRepositoryInterface', $this->files->get($interfacePath));
    }

    public function test_it_prevents_overwriting_existing_repository_files_without_force(): void
    {
        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--path' => 'tests/Fixtures/Generated',
        ])->assertSuccessful();

        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--path' => 'tests/Fixtures/Generated',
        ])->assertFailed();
    }

    public function test_it_overwrites_existing_repository_files_with_force(): void
    {
        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--path' => 'tests/Fixtures/Generated',
        ])->assertSuccessful();

        $this->files->put(base_path('tests/Fixtures/Generated/CommandUserRepository.php'), 'old content');

        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--path' => 'tests/Fixtures/Generated',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertStringNotContainsString('old content', $this->files->get(base_path('tests/Fixtures/Generated/CommandUserRepository.php')));
    }

    public function test_it_binds_repository_interface_when_bind_option_is_used(): void
    {
        $bootstrapProvidersPath = base_path('bootstrap/providers.php');

        $this->files->ensureDirectoryExists(dirname($bootstrapProvidersPath));
        $this->files->put($bootstrapProvidersPath, <<<'PHP'
<?php

return [
    App\Providers\AppServiceProvider::class,
];
PHP);

        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--bind' => true,
        ])->assertSuccessful();

        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        $this->assertFileExists($providerPath);
        $this->assertFileExists($bootstrapProvidersPath);

        $providerContents = $this->files->get($providerPath);

        $this->assertStringContainsString('class RepositoryServiceProvider extends ServiceProvider', $providerContents);
        $this->assertStringContainsString('$this->app->bind(', $providerContents);
        $this->assertStringContainsString('\App\Repositories\Contracts\CommandUserRepositoryInterface::class', $providerContents);
        $this->assertStringContainsString('\App\Repositories\CommandUserRepository::class', $providerContents);
        $this->assertStringContainsString('App\Providers\RepositoryServiceProvider::class', $this->files->get($bootstrapProvidersPath));
    }

    public function test_it_creates_service_layer_dto_config_and_bindings(): void
    {
        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--service' => true,
            '--bind' => true,
        ])->assertSuccessful();

        $servicePath = base_path('app/Services/CommandUserService.php');
        $serviceInterfacePath = base_path('app/Services/CommandUserServiceInterface.php');
        $dtoPath = base_path('app/DTOs/CommandUserData.php');
        $configPath = config_path('repository-pattern.php');
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        $this->assertFileExists($servicePath);
        $this->assertFileExists($serviceInterfacePath);
        $this->assertFileExists($dtoPath);
        $this->assertFileExists($configPath);

        $serviceContents = $this->files->get($servicePath);
        $providerContents = $this->files->get($providerPath);

        $this->assertStringContainsString('class CommandUserService implements CommandUserServiceInterface', $serviceContents);
        $this->assertStringContainsString('use App\DTOs\CommandUserData;', $serviceContents);
        $this->assertStringContainsString('use App\Repositories\Contracts\CommandUserRepositoryInterface;', $serviceContents);
        $this->assertStringContainsString('function paginate(', $serviceContents);
        $this->assertStringContainsString('function restore(int|string $id): bool', $serviceContents);
        $this->assertStringContainsString('\App\Services\CommandUserServiceInterface::class', $providerContents);
        $this->assertStringContainsString('\App\Services\CommandUserService::class', $providerContents);
    }

    public function test_it_uses_configured_paths_and_namespaces_by_default(): void
    {
        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
        ])->assertSuccessful();

        $repositoryPath = base_path('app/Repositories/CommandUserRepository.php');
        $interfacePath = base_path('app/Repositories/Contracts/CommandUserRepositoryInterface.php');

        $this->assertFileExists($repositoryPath);
        $this->assertFileExists($interfacePath);
        $this->assertStringContainsString('namespace App\Repositories;', $this->files->get($repositoryPath));
        $this->assertStringContainsString('implements \App\Repositories\Contracts\CommandUserRepositoryInterface', $this->files->get($repositoryPath));
        $this->assertStringContainsString('namespace App\Repositories\Contracts;', $this->files->get($interfacePath));
    }

    public function test_it_uses_published_stubs_when_available(): void
    {
        $stubPath = resource_path('stubs/vendor/repository-pattern/repository.stub');

        $this->files->ensureDirectoryExists(dirname($stubPath));
        $this->files->put($stubPath, <<<'PHP'
<?php

namespace {{ namespace }};

// custom published repository stub
class {{ repository }}
{
}
PHP);

        $this->artisan('repository:make', [
            'name' => 'CommandUser',
            '--model' => CommandUser::class,
            '--path' => 'tests/Fixtures/Generated',
        ])->assertSuccessful();

        $this->assertStringContainsString(
            'custom published repository stub',
            $this->files->get(base_path('tests/Fixtures/Generated/CommandUserRepository.php'))
        );
    }

    public function test_it_registers_publishable_config_and_stubs(): void
    {
        $configPublishes = ServiceProvider::pathsToPublish(null, 'repository-pattern-config');
        $stubPublishes = ServiceProvider::pathsToPublish(null, 'repository-pattern-stubs');

        $this->assertContains(config_path('repository-pattern.php'), $configPublishes);
        $this->assertContains(resource_path('stubs/vendor/repository-pattern'), $stubPublishes);
    }

    public function test_it_creates_missing_app_model_classes(): void
    {
        $modelPath = app_path('Models/GeneratedArticle.php');

        if ($this->files->exists($modelPath)) {
            $this->files->delete($modelPath);
        }

        $this->artisan('repository:make', [
            'name' => 'GeneratedArticle',
            '--model' => 'GeneratedArticle',
            '--path' => 'tests/Fixtures/Generated',
        ])->assertSuccessful();

        $this->assertFileExists($modelPath);

        $this->files->delete($modelPath);
    }

    private function cleanGeneratedFiles(): void
    {
        $paths = [
            base_path('tests/Fixtures/Generated'),
            base_path('app/Repositories'),
            base_path('app/Services'),
            base_path('app/DTOs'),
            resource_path('stubs/vendor/repository-pattern'),
            app_path('Providers/RepositoryServiceProvider.php'),
            app_path('Models/GeneratedArticle.php'),
            base_path('bootstrap/providers.php'),
            config_path('repository-pattern.php'),
        ];

        foreach ($paths as $path) {
            if ($this->files->isDirectory($path)) {
                $this->files->deleteDirectory($path);
            }

            if ($this->files->isFile($path)) {
                $this->files->delete($path);
            }
        }
    }
}
