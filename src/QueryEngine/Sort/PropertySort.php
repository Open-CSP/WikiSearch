<?php

namespace WikiSearch\QueryEngine\Sort;

use InvalidArgumentException;
use ONGR\ElasticsearchDSL\BuilderInterface;
use ONGR\ElasticsearchDSL\Sort\FieldSort;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Sorts based on the value of the given property.
 */
class PropertySort implements Sort {
    /**
     * @var PropertyFieldMapper The field to sort on
     */
    private PropertyFieldMapper $field;

	/**
	 * @var string|null The order of the sort
	 */
	private ?string $order = null;

    /**
     * @var string|null The mode of the sort (either min, max or null)
     */
    private ?string $mode = null;

	/**
	 * FieldSort constructor.
	 *
	 * @param string|PropertyFieldMapper $field The field to sort on
	 * @param string|null $order
	 */
	public function __construct( string|PropertyFieldMapper $field, string $order = null ) {
		if ( is_string( $field ) ) {
            $this->field = new PropertyFieldMapper( $field );
		} else {
            $this->field = $field;
        }

		switch ( $order ) {
			case "asc":
			case "ascending":
			case "up":
				$this->order = FieldSort::ASC;
				$this->mode = 'min';
				break;
			case "desc":
			case "dsc":
			case "descending":
			case "down":
				$this->order = FieldSort::DESC;
				$this->mode = 'max';
				break;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): BuilderInterface {
        $parameters = [];

        if ( $this->mode !== null ) {
            $parameters['mode'] = $this->mode;
        }

        $field = $this->field->hasKeywordSubfield() ?
            $this->field->getKeywordField() :
            $this->field->getPropertyField();

        return new FieldSort( $field, $this->order, $parameters );
	}
}
