<?php

namespace WSSearch\QueryEngine\Aggregation;

use WSSearch\SMW\PropertyFieldMapper;

interface PropertyAggregation extends Aggregation {
	public function getProperty(): PropertyFieldMapper;
}