<?php

namespace LumenApiQueryParser\Params;

class Filter implements FilterInterface
{
    protected $field;
    protected $operator;
    protected $value;
    protected $method;

    public function __construct(string $field, string $operator, string $value, string $method)
    {
        $this->setField($field);
        $this->setOperator($operator);
        $this->setValue($value);
        $this->setMethod($value);
    }

    public function setField(string $field): void
    {
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setOperator(string $operator): void
    {
        $this->operator = $operator;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
