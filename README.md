# Gohari Laravel Repository Pattern

Build clean repositories for Laravel models with one Artisan command.

`gohari/repository-pattern` provides a reusable `BaseRepository`, a matching `BaseRepositoryInterface`, and a generator command that creates repository classes inside the Laravel application that installs your package.

## Features

- Artisan repository generator
- Automatic Laravel package discovery
- Automatic model creation for missing `App\Models\...` classes
- Repository and interface stubs
- Custom output path support
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

## Quick Start

Create a repository for a model:

```bash
php artisan repository:make User --model=User
```

This creates:

```text
app/Repositories/UserRepository.php
app/Repositories/UserRepositoryInterface.php
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
| `--force` | No | Overwrite existing repository files. |

Examples:

```bash
php artisan repository:make User --model=User
php artisan repository:make UserRepository --model=User
php artisan repository:make Product --model="App\Models\Product"
php artisan repository:make Admin/User --model=User
```

Use a custom output path:

```bash
php artisan repository:make Product --model=Product --path=app/Domain/Repositories
```

Overwrite existing files:

```bash
php artisan repository:make Product --model=Product --force
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

namespace App\Repositories;

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
$repository->create($data);
$repository->update($id, $data);
$repository->delete($id);
$repository->searchByColumn('name', 'john');
```

Backward-compatible aliases:

```php
$repository->insertData($data);
$repository->updateItem($id, $data);
$repository->deleteData($id);
```

## Binding Interfaces

You can bind repositories in one of your app service providers:

```php
use App\Repositories\UserRepository;
use App\Repositories\UserRepositoryInterface;

public function register(): void
{
    $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
}
```

Then inject the interface anywhere:

```php
use App\Repositories\UserRepositoryInterface;

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
```

The tests cover:

- Repository generator command
- Package service provider command registration
- Generated repository and interface contents
- Missing model generation for `App\Models`
- `--force` overwrite behavior
- Shared `BaseRepository` CRUD/query behavior
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
```

For Packagist, push your repository to GitHub, submit the package, and tag releases with semantic versions:

```bash
git tag v1.0.0
git push origin v1.0.0
```

## License

The MIT License. See `LICENSE` for more information.
