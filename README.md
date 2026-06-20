# Gohari Laravel Repository Pattern

Build clean repositories for Laravel models with one Artisan command.

`gohari/repository-pattern` provides a reusable `BaseRepository`, a matching `BaseRepositoryInterface`, and a generator command that creates repository classes inside the Laravel application that installs your package.

Current release: `v1.1.0`

## Features

- Artisan repository and service layer generator
- Automatic Laravel package discovery
- Automatic model creation for missing `App\Models\...` classes
- Repository and interface stubs
- Custom output path support
- Optional automatic interface binding
- Optional Service, ServiceInterface, DTO, and config generation
- Relationship loading, sorting, and soft delete helpers
- Shared base methods for common Eloquent operations
- Backward-compatible method aliases like `insertData`, `updateItem`, and `deleteData`
- PHPUnit and Orchestra Testbench coverage for package behavior

## Requirements

- PHP `^8.3`
- Laravel `^10.0`, `^11.0`, `^12.0`, or `^13.0`

## Installation

Install the package with Composer:

```bash
composer require gohari/repository-pattern
```

Laravel will discover the service provider automatically:

```php
Gohari\RepositoryPattern\RepositoryPatternServiceProvider::class
```

If package discovery is disabled in your app, register the provider manually in `config/app.php`:

```php
'providers' => [
    Gohari\RepositoryPattern\RepositoryPatternServiceProvider::class,
],
```

Publish the config or generator stubs when you want to customize package defaults:

```bash
php artisan vendor:publish --tag=repository-pattern-config
php artisan vendor:publish --tag=repository-pattern-stubs
```

Published stubs live in:

```text
resources/stubs/vendor/repository-pattern
```

## Quick Start

Create a repository for a model:

```bash
php artisan repository:make User --model=User
```

This creates:

```text
app/Repositories/UserRepository.php
app/Repositories/Contracts/UserRepositoryInterface.php
```

If `App\Models\User` does not exist, the package will create it with Laravel's `make:model` command.

## Command Usage

```bash
php artisan repository:make {name} --model={model}
```

Arguments and options:

| Name | Required | Description |
| --- | --- | --- |
| `name` | Yes | Repository name. `User` and `UserRepository` both generate `UserRepository`. |
| `--model` | No | Model class name or FQCN. Defaults to the repository name. |
| `--path` | No | Output directory. Defaults to `app/Repositories`. |
| `--interface-path` | No | Repository interface output directory. Defaults to `app/Repositories/Contracts`. |
| `--force` | No | Overwrite existing repository files. |
| `--bind` | No | Bind the generated interface to the repository in `App\Providers\RepositoryServiceProvider`. |
| `--service` | No | Generate Service, ServiceInterface, DTO, and config alongside the repository. |
| `--service-path` | No | Service output directory. Defaults to `app/Services`. |
| `--dto` | No | Generate only the DTO alongside the repository. |
| `--dto-path` | No | DTO output directory. Defaults to `app/DTOs`. |
| `--config` | No | Copy `config/repository-pattern.php` into the application. |

Examples:

```bash
php artisan repository:make User --model=User
php artisan repository:make UserRepository --model=User
php artisan repository:make Product --model="App\Models\Product"
php artisan repository:make Admin/User --model=User
php artisan repository:make User --model=User --service
```

Use a custom output path:

```bash
php artisan repository:make Product --model=Product --path=app/Domain/Repositories
```

Overwrite existing files:

```bash
php artisan repository:make Product --model=Product --force
```

Create files and register the interface binding:

```bash
php artisan repository:make User --model=User --bind
```

Generate repository and service layers together:

```bash
php artisan repository:make User --model=User --service --bind
```

For fully-qualified model classes outside `App\Models`, the command will warn you if the model file is missing, but it will not generate it automatically.

## Generated Repository

For this command:

```bash
php artisan repository:make User --model=User
```

The package generates:

```php
<?php

namespace App\Repositories;

use Gohari\RepositoryPattern\BaseRepository;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }
}
```

And:

```php
<?php

namespace App\Repositories\Contracts;

use Gohari\RepositoryPattern\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    //
}
```

## Base Repository Methods

Every generated repository extends `BaseRepository`, so these methods are available immediately:

```php
$repository->query();
$repository->getAll();
$repository->paginate(15);
$repository->findById($id);
$repository->findOrFail($id);
$repository->firstWhere('email', 'user@example.com');
$repository->findBy('email', 'user@example.com');
$repository->exists($id);
$repository->count();
$repository->create($data);
$repository->updateOrCreate(['email' => $email], $data);
$repository->update($id, $data);
$repository->delete($id);
$repository->deleteMany([$firstId, $secondId]);
$repository->search('email', 'user@example.com', '=');
$repository->searchByColumn('name', 'john');
$repository->with(['roles', 'profile'])->paginate();
$repository->withRelations(['roles', 'profile'])->get();
$repository->sortBy('created_at', 'desc')->get();
$repository->withTrashed()->getAll();
$repository->onlyTrashed()->count();
$repository->restore($id);
$repository->forceDelete($id);
```

Backward-compatible aliases:

```php
$repository->insertData($data);
$repository->updateItem($id, $data);
$repository->deleteData($id);
```

## Generated Service Layer

Use `--service` to generate Repository, RepositoryInterface, Service, ServiceInterface, DTO, and config in one command:

```bash
php artisan repository:make User --model=User --service
```

This creates:

```text
app/Repositories/UserRepository.php
app/Repositories/Contracts/UserRepositoryInterface.php
app/Services/UserService.php
app/Services/UserServiceInterface.php
app/DTOs/UserData.php
config/repository-pattern.php
```

The generated service supports pagination with relationships and sorting, plus create/update through a DTO or array:

```php
$users->paginate(['roles'], 'created_at', 'desc', 20);
$users->find($id, ['profile']);
$users->create(UserData::fromArray($data));
$users->restore($id);
$users->forceDelete($id);
```

## Binding Interfaces

You can bind repositories automatically with `--bind`:

```bash
php artisan repository:make User --model=User --bind
```

This creates or updates `app/Providers/RepositoryServiceProvider.php`:

```php
public function register(): void
{
    $this->app->bind(
        \App\Repositories\Contracts\UserRepositoryInterface::class,
        \App\Repositories\UserRepository::class
    );
}
```

The command also registers `App\Providers\RepositoryServiceProvider::class` in `bootstrap/providers.php` for modern Laravel apps, or in `config/app.php` when that is the available provider registration file.

## Configuration

After publishing `repository-pattern-config`, you can control paths, namespaces, and default generator behavior:

```php
return [
    'paths' => [
        'repositories' => app_path('Repositories'),
        'interfaces' => app_path('Repositories/Contracts'),
        'services' => app_path('Services'),
    ],

    'namespaces' => [
        'repositories' => 'App\\Repositories',
        'interfaces' => 'App\\Repositories\\Contracts',
        'services' => 'App\\Services',
    ],

    'auto_bind' => true,
    'generate_service' => false,
    'generate_model_if_missing' => true,
];
```

Then inject the interface anywhere:

```php
use App\Repositories\Contracts\UserRepositoryInterface;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users
    ) {
    }

    public function activeUsers()
    {
        return $this->users
            ->query()
            ->where('is_active', true)
            ->get();
    }
}
```

## Custom Repository Methods

Add domain-specific methods to the interface:

```php
use Gohari\RepositoryPattern\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email);
}
```

Then implement them in the repository:

```php
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Gohari\RepositoryPattern\BaseRepository;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    public function findByEmail(string $email)
    {
        return $this->query()->where('email', $email)->first();
    }
}
```

## Testing

Install development dependencies and run the package test suite:

```bash
composer install
composer test
composer analyse
composer format:test
```

The tests cover:

- Repository generator command
- Package service provider command registration
- Generated repository and interface contents
- Missing model generation for `App\Models`
- `--force` overwrite behavior
- `--bind`, `--service`, DTO, config, and custom stub generation
- Shared `BaseRepository` CRUD/query behavior
- Search, relationship loading, sorting, soft delete, and fluent repository scopes
- Legacy aliases: `insertData`, `updateItem`, `deleteData`

## Package Development

For packages, `vendor/` and `composer.lock` should stay out of the repository. Install dependencies locally when developing:

```bash
composer install
```

Before tagging a release, run:

```bash
composer validate --strict --no-check-lock
composer test
composer analyse
composer format:test
```

For Packagist, push your repository to GitHub, submit the package, and tag releases with semantic versions:

```bash
git tag v1.1.0
git push origin v1.1.0
```

## Security

Please review [SECURITY.md](SECURITY.md) before reporting vulnerabilities.

## License

The MIT License. See `LICENSE` for more information.
