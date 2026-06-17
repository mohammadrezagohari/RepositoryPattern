<?php

namespace Gohari\RepositoryPattern;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model)
    {
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
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

    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->query()->findOrFail($id, $columns);
    }

    public function create(array $data): Model
    {
        return $this->query()->create($data);
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

    public function searchByColumn(string $column, mixed $value): Builder
    {
        return $this->query()->where($column, 'like', '%'.$value.'%');
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
}
