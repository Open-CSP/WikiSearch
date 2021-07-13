<?php

namespace WSSearch\QueryEngine\Filter;

use WSSearch\SMW\PropertyFieldMapper;

abstract class PropertyFilter extends AbstractFilter {
	/**
	 * Returns the property field mapper associated with this property filter.
	 *
	 * @return PropertyFieldMapper
	 */
	abstract public function getProperty(): PropertyFieldMapper;
}
