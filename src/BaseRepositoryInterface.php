<?php

namespace Gohari\RepositoryPattern;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function query(): Builder;

    public function getAll(array $columns = ['*']): Collection;

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    public function findById(int|string $id, array $columns = ['*']): ?Model;

    public function firstWhere(string $column, mixed $value): ?Model;

    public function findOrFail(int|string $id, array $columns = ['*']): Model;

    public function findBy(string $column, mixed $value): ?Model;

    public function exists(int|string $id): bool;

    public function count(): int;

    public function create(array $data): Model;

    public function updateOrCreate(array $attributes, array $values = []): Model;

    public function update(int|string $id, array $data): bool;

    public function delete(int|string $id): bool;

    public function deleteMany(array $ids): int;

    public function search(string $column, string $term, string $operator = 'like'): Builder;

    public function searchByColumn(string $column, mixed $value): Builder;

    public function withRelations(array|string $relations): Builder;

    public function with(array|string $relations): static;

    public function sortBy(string $column, string $direction = 'asc'): Builder;

    public function withTrashed(): static;

    public function onlyTrashed(): static;

    public function restore(int|string $id): bool;

    public function forceDelete(int|string $id): bool;
}
