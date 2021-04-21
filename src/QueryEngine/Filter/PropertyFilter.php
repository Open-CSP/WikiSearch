<?php


namespace WSSearch\QueryEngine\Filter;


use WSSearch\SMW\PropertyFieldMapper;

abstract class PropertyFilter extends AbstractFilter
{
	abstract public function getProperty(): PropertyFieldMapper;
}