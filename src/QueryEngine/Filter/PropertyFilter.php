<?php

namespace WikiSearch\QueryEngine\Filter;

use WikiSearch\SMW\PropertyFieldMapper;

abstract class PropertyFilter extends Filter {
    protected PropertyFieldMapper $field;

    /**
     * @param string|PropertyFieldMapper $field The field associated with this filter
     */
    public function __construct( string|PropertyFieldMapper $field ) {
        $this->field = is_string( $field ) ?
            new PropertyFieldMapper( $field ) :
            $field;
    }

	/**
	 * Returns the property field mapper associated with this property filter.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getField(): PropertyFieldMapper {
        return $this->field;
    }
}
