<?php

namespace LumenApiQueryParser;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use LumenApiQueryParser\Params\Filter;
use LumenApiQueryParser\Params\RequestParamsInterface;
use LumenApiQueryParser\Params\Sort;
use LumenApiQueryParser\Provider\ConnectionParser;
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
                    $connectionName = $fieldProvider->getConnectionString();
                    if($filter->getOperator() === 'has') {
                        $filter->setField($connectionName);
                        $this->applyFilter($query, $filter);
                    } else {
                        $filter->setField($fieldProvider->getField());
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

        $connection_sorts = [];
        if ($params->hasSort()) {
            foreach($params->getSorts() as $sort) {
                $parser = new ConnectionParser($query, $sort->getField());
                if($parser->hasConnections()) {
                    $connectionName = $parser->getConnectionString();
                    if(!isset($connection_sorts[$connectionName])) {
                        $connection_sorts[$connectionName] = [];
                    }
                    $pieces = explode('.', $sort->getField());
                    $field = array_pop($pieces);
                    $connection_sorts[$connectionName][] = [$field, $sort->getDirection()];
                } else {
                    $this->applySort($query, $sort);
                }
            }
        }

        $with = [];
        if ($params->hasConnection()) {
            foreach ($params->getConnections() as $connection) {
                $connectionName = $connection->getName();
                $parser = new ConnectionParser($query, $connectionName);
                $connectionName = $parser->getConnectionString();
                if(isset($connection_filters[$connectionName])) {
                    continue;
                } else if(isset($connection_sorts[$connectionName])) {
                    continue;
                } else {
                    $with[] = $connectionName;
                }
            }
        }
        $where_has_connections = array_merge(
            ($connection_filters ? array_keys($connection_filters) : []),
            ($connection_sorts ? array_keys($connection_sorts) : [])
        );
        foreach($where_has_connections as $connectionName) {
            $filters = isset($connection_filters[$connectionName]) ? $connection_filters[$connectionName] : [];
            $sorts = isset($connection_sorts[$connectionName]) ? $connection_sorts[$connectionName] : [];
            if(count($filters) || count($sorts)) {
                $with[$connectionName] = function($q) use($filters, $sorts) {
                    foreach($filters as $filter) {
                        $this->applyFilter($q, $filter);
                    }
                    foreach($sorts as $sort) {
                        if(count($sort) == 2) {
                            if($sort[1] === 'DESC') {
                                $q->orderByDesc($sort[0]);
                            } else {
                                $q->orderBy($sort[0]);
                            }
                        }
                    }
                };
            }
        }
        if(count($with)) {
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
