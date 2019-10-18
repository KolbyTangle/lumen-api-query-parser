<?php

namespace LumenApiQueryParser;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use LumenApiQueryParser\Params\Filter;
use LumenApiQueryParser\Params\RequestParamsInterface;
use LumenApiQueryParser\Params\Sort;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait BuilderParamsApplierTrait
{
    public function applyParams(Builder $query, RequestParamsInterface $params): LengthAwarePaginator
    {
        if ($params->hasFilter()) {
            foreach ($params->getFilters() as $filter) {
                $this->applyFilter($query, $filter);
            }
        }

        if ($params->hasSort()) {
            foreach ($params->getSorts() as $sort) {
                $this->applySort($query, $sort);
            }
        }

        if ($params->hasConnection()) {
            $with = [];
            foreach ($params->getConnections() as $connection) {
                $with[] = $connection->getName();
            }
            $query->with($with);
        }

        if ($params->hasPagination()) {
            $pagination = $params->getPagination();
            $query->limit($pagination->getLimit());
            $query->offset($pagination->getPage() * $pagination->getLimit());

            $paginator = $query->paginate($params->getPagination()->getLimit(), ['*'], 'page', $params->getPagination()->getPage());
        } else {
            $paginator = $query->paginate($query->count(), ['*'], 'page', 1);
        }


        return $paginator;
    }

    protected function applyFilter(Builder $query, Filter $filter): void
    {
        $table = $query->getModel()->getTable();
        $field = sprintf('%s.%s', $table, $filter->getField());
        $operator = $filter->getOperator();
        $value = $filter->getValue();
        $method = ($filter->getMethod() ?: 'where');
        $clauseOperator = null;
        $databaseField = null;

        switch ($operator) {
            case 'ct':
                $value = '%' . $value . '%';
                $clauseOperator = 'LIKE';
                break;
            case 'nct':
                $value = '%' . $value . '%';
                $clauseOperator = 'NOT LIKE';
                break;
            case 'sw':
                $value = $value . '%';
                $clauseOperator = 'LIKE';
                break;
            case 'ew':
                $value = '%' . $value;
                $clauseOperator = 'LIKE';
                break;
            case 'eq':
                $clauseOperator = '=';
                break;
            case 'ne':
                $clauseOperator = '!=';
                break;
            case 'gt':
                $clauseOperator = '>';
                break;
            case 'ge':
                $clauseOperator = '>=';
                break;
            case 'lt':
                $clauseOperator = '<';
                break;
            case 'le':
                $clauseOperator = '<=';
                break;
            default:
                throw new BadRequestHttpException(sprintf('Not allowed operator: %s', $operator));
        }

        if ($operator === 'in') {
            $query->whereIn($filter, explode('|', $value));
        } else {

            $relation = $this->getRelationFromField($field);
            $field = $this->parseField($field);

            if($relation) {

                $query->whereHas($relation, function( $q ) use ($field, $method, $clauseOperator, $value) {
                    $q->{$method}($field, $clauseOperator, $value);
                });

            } else {

                call_user_func_array([$query, $method], [
                    $field, $clauseOperator, $value
                ]);

            }

        }
    }

    protected function applySort(Builder $query, Sort $sort)
    {
        $query->orderBy($sort->getField(), $sort->getDirection());
    }

    protected function parseField($field)
    {
        if(strpos($field, '.') !== false) {
            $temp = (explode('.', $field) ?: []);
            return array_pop($temp);
        }
        return $field;
    }

    protected function getRelationFromField($field)
    {
        if(strpos($field, '.') !== false) {
            $temp = (explode('.', $field) ?: []);
            array_pop($temp);
            $temp = array_map(function($v) {
                return lcfirst(str_replace('_', '', ucwords($v, '_')));
            }, $temp);
            $relation = implode('.', $temp);
            return $relation;
        }
        return null;
    }
}
