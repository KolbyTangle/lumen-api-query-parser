<?php

namespace LumenApiQueryParser;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use LumenApiQueryParser\Params\Filter;
use LumenApiQueryParser\Params\RequestParamsInterface;
use LumenApiQueryParser\Params\Sort;
use LumenApiQueryParser\Provider\FieldComponentProvider;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait BuilderParamsApplierTrait
{

    public function applyParams(Builder $query, RequestParamsInterface $params): LengthAwarePaginator
    {

        $connection_filters = [];
        if ($params->hasFilter()) {
            foreach ($params->getFilters() as $filter) {
                $fieldProvider = new FieldComponentProvider($query, $filter);
                if($fieldProvider->hasConnections()) {
                    $connections = $fieldProvider->getConnections();
                    $connectionName = implode('.', $connections);
                    if($filter->getOperator() === 'has') {
                        $filter->setField($connectionName);
                        $this->applyFilter($query, $filter);
                    } else {
                        $filter->setField($fieldProvider->getConnectionField());
                        if(!isset($connection_filters[$connectionName])) {
                            $connection_filters[$connectionName] = [];
                        }
                        $connection_filters[$connectionName][] = $filter;
                    }
                } else {
                    $this->applyFilter($query, $filter);
                }
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
                $connectionName = $connection->getName();
                if(isset($connection_filters[$connectionName]) && count($connection_filters[$connectionName])) {
                    $filters = $connection_filters[$connectionName];
                    $with[$connectionName] = function($q) use($filters) {
                        foreach($filters as $filter) {
                            $this->applyFilter($q->getQuery(), $filter);
                        }
                    };
                } else {
                    $with[] = $connectionName;
                }
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

        $field = $filter->getField();
        $operator = $filter->getOperator();
        $value = $filter->getValue();
        $method = ($filter->getMethod() ?: 'where');
        $clauseOperator = null;

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
            case 'in':
                break;
            case 'nin':
                break;
            case 'null':
                break;
            case 'nnull':
                break;
            case 'has':
                break;
            default:
                throw new BadRequestHttpException(sprintf('Not allowed operator: %s', $operator));
        }

        if ($operator === 'in') {
            $query->whereIn($field, explode('|', $value));
        } else if ($operator === 'nin') {
            $query->whereNotIn($field, explode('|', $value));
        } else if ($operator === 'null') {
            $query->whereNull($field);
        } else if ($operator === 'nnull') {
            $query->whereNotNull($field);
        } else if ($operator === 'has') {
            $query->has($field);
        } else {
            call_user_func_array([$query, $method], [
                $field, $clauseOperator, $value
            ]);
        }
    }

    protected function applySort(Builder $query, Sort $sort)
    {
        $query->orderBy($sort->getField(), $sort->getDirection());
    }

}
