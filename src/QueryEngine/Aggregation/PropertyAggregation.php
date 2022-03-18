<?php

namespace WikiSearch\QueryEngine\Aggregation;

use WikiSearch\SMW\PropertyFieldMapper;

interface PropertyAggregation extends Aggregation {
	/**
	 * Returns the property field mapper associated with this aggregation.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getProperty(): PropertyFieldMapper;
}
