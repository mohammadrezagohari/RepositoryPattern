<?php

namespace Gohari\RepositoryPattern;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseRepository implements BaseRepositoryInterface
{
    protected ?Builder $query = null;

    public function __construct(protected Model $model) {}

    public function query(): Builder
    {
        return $this->query ? clone $this->query : $this->model->newQuery();
    }

    public function getAll(array $columns = ['*']): Collection
    {
        return $this->query()->get($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->query()->paginate($perPage, $columns);
    }

    public function findById(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->query()->find($id, $columns);
    }

    public function firstWhere(string $column, mixed $value): ?Model
    {
        return $this->query()->firstWhere($column, $value);
    }

    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    public function findBy(string $column, mixed $value): ?Model
    {
        return $this->firstWhere($column, $value);
    }

    public function exists(int|string $id): bool
    {
        return $this->query()->whereKey($id)->exists();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->query()->updateOrCreate($attributes, $values);
    }

    public function update(int|string $id, array $data): bool
    {
        $model = $this->findById($id);

        if (! $model) {
            return false;
        }

        return $model->fill($data)->save();
    }

    public function delete(int|string $id): bool
    {
        $model = $this->findById($id);

        return $model ? (bool) $model->delete() : false;
    }

    public function deleteMany(array $ids): int
    {
        return $this->query()->whereKey($ids)->delete();
    }

    public function search(string $column, string $term, string $operator = 'like'): Builder
    {
        return $this->query()->where($column, $operator, $operator === 'like' ? "%{$term}%" : $term);
    }

    public function searchByColumn(string $column, mixed $value): Builder
    {
        return $this->search($column, (string) $value);
    }

    public function withRelations(array|string $relations): Builder
    {
        return $this->query()->with($relations);
    }

    public function with(array|string $relations): static
    {
        return $this->newScopedInstance($this->query()->with($relations));
    }

    public function sortBy(string $column, string $direction = 'asc'): Builder
    {
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        return $this->query()->orderBy($column, $direction);
    }

    public function withTrashed(): static
    {
        $query = $this->query();

        if ($this->supportsSoftDeletes()) {
            /** @phpstan-ignore-next-line SoftDeletes adds this method to the Eloquent builder at runtime. */
            $query = $query->withTrashed();
        }

        return $this->newScopedInstance($query);
    }

    public function onlyTrashed(): static
    {
        $query = $this->query();

        if ($this->supportsSoftDeletes()) {
            /** @phpstan-ignore-next-line SoftDeletes adds this method to the Eloquent builder at runtime. */
            $query = $query->onlyTrashed();
        } else {
            $query = $query->whereRaw('1 = 0');
        }

        return $this->newScopedInstance($query);
    }

    public function restore(int|string $id): bool
    {
        if (! $this->supportsSoftDeletes()) {
            return false;
        }

        $model = $this->withTrashed()->findById($id);

        return $model ? (bool) $model->{'restore'}() : false;
    }

    public function forceDelete(int|string $id): bool
    {
        if (! $this->supportsSoftDeletes()) {
            return $this->delete($id);
        }

        $model = $this->withTrashed()->findById($id);

        return $model ? (bool) $model->forceDelete() : false;
    }

    public function insertData(array $data): Model
    {
        return $this->create($data);
    }

    public function updateItem(int|string $identity, array $data): bool
    {
        return $this->update($identity, $data);
    }

    public function deleteData(int|string $identity): bool
    {
        return $this->delete($identity);
    }

    private function supportsSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    private function newScopedInstance(Builder $query): static
    {
        $repository = clone $this;
        $repository->query = $query;

        return $repository;
    }
}
