<?php

namespace Gohari\RepositoryPattern\Tests\Feature;

use Gohari\RepositoryPattern\Tests\Fixtures\CommandUser;
use Gohari\RepositoryPattern\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

class MakeRepositoryCommandTest extends TestCase
{
    private Filesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
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
            app_path('Models/GeneratedArticle.php'),
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
