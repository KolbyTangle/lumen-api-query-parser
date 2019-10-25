<?php

namespace LumenApiQueryParser\Provider;

use Illuminate\Database\Eloquent\Builder;
use LumenApiQueryParser\Params\Filter;

class FieldComponentProvider
{

    protected $query;
    protected $filter;
    protected $field;
    protected $connections;
    protected $connection_field;

    protected $errors = [];

    public function __construct(Builder $query, Filter $filter)
    {
        $this->setQuery($query);
        $this->setFilter($filter);
    }

    /**
     * @return Builder
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * @param Builder $query
     */
    private function setQuery(Builder $query): void
    {
        $this->query = $query;
    }

    /**
     * @return Filter
     */
    public function getFilter(): Filter
    {
        return $this->filter;
    }

    /**
     * @param Filter $filter
     */
    private function setFilter(Filter $filter): void
    {
        $this->filter = $filter;
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        if($this->field === null) {
            $this->setConnections($this->parseConnections());
        }
        return $this->field;
    }

    private function parseField()
    {
        $field = $this->getFilter()->getField();
        if(!$this->hasConnections()) {
            return $field;
        }
        return $this->getConnectionField();
    }

    /**
     * @param mixed $field
     */
    private function setField($field): void
    {
        $this->field = $field;
    }

    /**
     * @return mixed
     */
    public function getConnections()
    {
        if($this->connections === null) {
            $this->setConnections($this->parseConnections());
        }
        return $this->connections;
    }

    private function snakeCaseToCamelCase(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    private function parseConnections()
    {
        $context = $this;
        $field = $this->getFilter()->getField();
        $model = $this->getQuery()->getModel();
        $connections = [];
        if(strpos($field, '.') !== false) {
            $temp = (explode('.', $field) ?: []);
            array_pop($temp);
            $temp = array_map(function($v) use ($context) {
                return $context->snakeCaseToCamelCase($v);
            }, $temp);
            $relation_model = $model;
            foreach($temp as $connection) {
                if(!method_exists($relation_model, $connection)) {
                    $this->errors[] = 'No Connection: ' . $relation_model->getTable();
                    return false;
                } else {
                    $relation = $relation_model->$connection();
                    if(!$relation) {
                        $this->errors[] = 'Connection was null';
                        return false;
                    }
                    $relation_model = $relation->getModel();
                    $connections[] = $connection;
                }
            }
        } else {
            $connection = self::snakeCaseToCamelCase($field);
            if(method_exists($model, $connection)) {
                $connections[] = $connection;
            } else {
                return false;
            }
        }
        return $connections;
    }

    /**
     * @param mixed $connections
     */
    private function setConnections($connections): void
    {
        $this->connections = $connections;
    }

    public function hasConnections()
    {
        $connections = $this->getConnections();
        if($connections && count($connections)) {
            return true;
        }
        return false;
    }

    /**
     * @return mixed
     */
    public function getConnectionField()
    {
        if($this->connection_field === null) {
            $this->setConnectionField($this->parseConnectionField());
        }
        return $this->connection_field;
    }

    private function parseConnectionField()
    {
        $field = $this->getFilter()->getField();
        if(!$this->hasConnections()) {
            return null;
        }
        if(strpos($field, '.') !== false) {
            $temp = (explode('.', $field) ?: []);
            if(count($temp) > 1) {
                return array_pop($temp);
            }
        }
        return null;
    }

    /**
     * @param mixed $connection_field
     */
    private function setConnectionField($connection_field): void
    {
        $this->connection_field = $connection_field;
    }

    public function toArray() {
        return [
            'filter' => $this->getFilter()->getField(),
            'field' => $this->getField(),
            'connections' => $this->getConnections(),
            'connection_field' => $this->getConnectionField(),
            'errors' => $this->errors,
        ];
    }

}
