<?php

namespace WikiSearch\QueryEngine\Aggregation;

use WikiSearch\SMW\PropertyFieldMapper;

abstract class PropertyAggregation extends Aggregation {
    /**
     * @var PropertyFieldMapper|string The property field mapper associated with this aggregation
     */
    protected PropertyFieldMapper|string $field;

    /**
     * @inheritDoc
     * @param string|PropertyFieldMapper $field The field associated with this aggregation
     */
    public function __construct( string|PropertyFieldMapper $field, ?string $name = null ) {
        if ( is_string( $field ) ) {
            $this->field = new PropertyFieldMapper( $field );
        } else {
            $this->field = $field;
        }

        parent::__construct( $name ?? $field->getPropertyName() );
    }

    /**
	 * Returns the property field mapper associated with this aggregation.
	 *
	 * @return PropertyFieldMapper
	 */
	public function getProperty(): PropertyFieldMapper {
        return $this->field;
    }
}
