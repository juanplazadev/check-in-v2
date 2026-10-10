<?php

declare(strict_types=1);

namespace App\Dashboard;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Case-insensitive "column contains term" for the dashboard searches.
 *
 * whereLike() binds the value but leaves LIKE wildcards live, so a search for
 * "%%" matched every row and "_" matched any character. Here the term is
 * escaped and the escape character is declared explicitly, which Postgres and
 * SQLite (the test suite) then treat identically.
 *
 * The term only ever travels as a binding. The column is the one piece of SQL
 * we write, and it is typed literal-string, so PHPStan rejects any caller that
 * passes something other than a name written in our own code. The columns are
 * plain snake_case and every query using this is single-table (a whereHas()
 * subquery resolves to its own table), so they need no qualifying or quoting.
 */
trait MatchesSearchTerm
{
    /**
     * @param  Builder<static>  $query
     * @param  literal-string  $column
     */
    #[Scope]
    protected function whereContains(Builder $query, string $column, string $term): void
    {
        $this->addContains($query, $column, $term, 'and');
    }

    /**
     * @param  Builder<static>  $query
     * @param  literal-string  $column
     */
    #[Scope]
    protected function orWhereContains(Builder $query, string $column, string $term): void
    {
        $this->addContains($query, $column, $term, 'or');
    }

    /**
     * The LIKE pattern for "contains $term", with its wildcards escaped.
     */
    private static function containsPattern(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }

    /**
     * @param  Builder<static>  $query
     * @param  literal-string  $column
     * @param  'and'|'or'  $boolean
     */
    private function addContains(Builder $query, string $column, string $term, string $boolean): void
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query->whereRaw("{$column} {$operator} ? escape '\\'", [self::containsPattern($term)], $boolean);
    }
}
