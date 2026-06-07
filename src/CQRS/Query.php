<?php
namespace Phoenix\CQRS;

abstract class Query
{
    abstract public function getQueryName(): string;
}
