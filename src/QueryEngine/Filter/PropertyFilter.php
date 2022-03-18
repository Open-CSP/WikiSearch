<?php

namespace WikiSearch\QueryEngine\Filter;

use WikiSearch\SMW\PropertyFieldMapper;

abstract class PropertyFilter extends AbstractFilter {
	/**
	 * Returns the property field mapper associated with this property filter.
	 *
	 * @return PropertyFieldMapper
	 */
	abstract public function getProperty(): PropertyFieldMapper;
}
