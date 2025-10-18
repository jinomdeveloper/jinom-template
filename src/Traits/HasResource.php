<?php

namespace Jinom\JinomTemplate\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

trait HasResource
{
    private Model $model;

    /**
     * Retrieve all records for the authenticated user, with optional search.
     *
     * @param array $search Search options.
     * @return Collection The collection of records.
     */
    public function all(array $search = []): Collection
    {
        return $this->model
            ->when(isset($search['fields']) && isset($search['value']), function (Builder $query) use ($search) {
                $sql = "";
                for ($i = 0; $i < count($search['fields']); $i++) {
                    if ($i > 0) {
                        $sql .= ' OR ';
                    }
                    $sql .= $search['fields'][$i] . ' LIKE ?';
                }

                $query->whereRaw("($sql)", array_fill(0, count($search['fields']), '%' . $search['value'] . '%'));
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function applyFilters(Builder $query, array $filters = [])
    {
        return $query;
    }

    public function applyOrder(Builder $query, array $sort = [])
    {
        return $query
            ->when(empty($sort), function (Builder $query) {
                return $query->orderBy('created_at', 'desc');
            })
            ->when(!empty($sort), function (Builder $query) use ($sort) {
                return $query->orderBy($sort['column'], $sort['direction']);
            });
    }

    /**
     * Paginate records for the authenticated user, with optional search and sorting.
     *
     * @param int $perPage Number of records per page.
     * @param array $search Search options.
     * @param array $sort Sort options.
     * @return LengthAwarePaginator Paginated result set.
     */
    public function paginate(int $perPage = 15, $filters = [], array $sort = []): LengthAwarePaginator
    {
        $builder = $this->model
            ->query();

        $builder = $this->applyFilters($builder, $filters);
        $builder = $this->applyOrder($builder, $sort);

        return $builder->paginate($perPage);
    }

    /**
     * Find a record by ID for the authenticated user.
     *
     * @param int $id The record ID.
     * @return Model|null The found model or null.
     */
    public function find(int $id): ?Model
    {
        return $this->model->find($id);
    }

    /**
     * Find a record by a specific field and value.
     *
     * @param string $field The field name.
     * @param mixed $value The value to search for.
     * @return Model|null The found model or null.
     */
    public function findBy(string $field, mixed $value): ?Model
    {
        return $this->model::where($field, $value)->first();
    }

    /**
     * Get records by a specific field and value.
     *
     * @param string $field The field name.
     * @param mixed $value The value to search for.
     * @return Collection The collection of records.
     */
    public function getBy(string $field, mixed $value): Collection
    {
        return $this->model::where($field, $value)->get();
    }

    /**
     * Create a new record with the given data.
     *
     * @param array $data The data for the new record.
     * @return Model The created model.
     */
    public function create(array $data): Model
    {
        return $this->model::create($data);
    }

    /**
     * Update a record by ID with the given data.
     *
     * @param int $id The record ID.
     * @param array $data The data to update.
     * @return Model|null The updated model or null.
     */
    public function update($id, array $data): ?Model
    {
        $model = $this->model::find($id);
        if ($model) {
            $model->update($data);
            return $model;
        }
        return null;
    }

    /**
     * Delete a record by ID.
     *
     * @param int $id The record ID.
     * @return bool True if deleted, false otherwise.
     */
    public function delete($id): bool
    {
        $model = $this->model->find($id);
        if ($model) {
            $model->delete();
            return true;
        }
        return false;
    }

    /**
     * Get a new query builder instance for the model.
     *
     * @return Builder The query builder instance.
     */
    public function query(): Builder
    {
        return $this->model->query();
    }
}
