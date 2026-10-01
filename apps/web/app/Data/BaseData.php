<?php

namespace App\Data;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\LaravelData\Data;

/**
 * Base for output DTOs. Each DTO declares the relations and aggregates it reads,
 * and services call prepareQuery()/loadRelations() so eager loading always matches the DTO.
 */
class BaseData extends Data
{
    public function defaultWrap(): string
    {
        return 'data';
    }

    /** @return list<string>|array<string, \Closure> Relations for ->with(). */
    public static function relations(): array
    {
        return [];
    }

    /** @return list<string> Relations for ->withCount(). */
    public static function countRelations(): array
    {
        return [];
    }

    /** @return list<string> Relations for ->withExists(). */
    public static function existRelations(): array
    {
        return [];
    }

    /** @return array<string, string> relation => column for ->withSum(). */
    public static function sumRelations(): array
    {
        return [];
    }

    /** @return array<string, string> relation => column for ->withAvg(). */
    public static function avgRelations(): array
    {
        return [];
    }

    /** @return list<string|Expression> Extra columns for ->addSelect(). */
    public static function additionalSelects(): array
    {
        return [];
    }

    /**
     * @template TQuery of Builder|Relation
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function prepareQuery(Builder|Relation $query): Builder|Relation
    {
        $query->with(static::relations())->withCount(static::countRelations());

        foreach (static::existRelations() as $relation) {
            $query->withExists($relation);
        }

        foreach (static::sumRelations() as $relation => $column) {
            $query->withSum($relation, $column);
        }

        foreach (static::avgRelations() as $relation => $column) {
            $query->withAvg($relation, $column);
        }

        if (static::additionalSelects() !== []) {
            $query->addSelect(static::additionalSelects());
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $model
     * @return TModel
     */
    public static function loadRelations(Model $model): Model
    {
        $model->loadMissing(static::relations())->loadCount(static::countRelations());

        foreach (static::existRelations() as $relation) {
            $model->loadExists($relation);
        }

        foreach (static::sumRelations() as $relation => $column) {
            $model->loadSum($relation, $column);
        }

        foreach (static::avgRelations() as $relation => $column) {
            $model->loadAvg($relation, $column);
        }

        return $model;
    }

    /**
     * Prefix nested DTO relations, e.g. relationsFromNested('course', CourseData::relations()).
     *
     * @param  list<string>  $relations
     * @return list<string>
     */
    public static function relationsFromNested(string $name, array $relations): array
    {
        return [$name, ...array_map(fn (string $relation) => "{$name}.{$relation}", $relations)];
    }
}
