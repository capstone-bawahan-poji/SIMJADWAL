<?php

namespace App\Http\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Base for list endpoints with dynamic filter/sort (spatie/laravel-query-builder).
 * per_page follows the API contract: default 20, max 100.
 */
abstract class Query extends QueryBuilder
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public int $perPage;

    public int $page;

    public function __construct(Builder|Relation $query, ?Request $request = null)
    {
        parent::__construct($query, $request);

        $this->perPage = min(max($this->request->integer('per_page', self::DEFAULT_PER_PAGE), 1), self::MAX_PER_PAGE);
        $this->page = max($this->request->integer('page', 1), 1);
    }
}
