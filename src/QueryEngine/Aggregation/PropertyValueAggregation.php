<?php

namespace WikiSearch\QueryEngine\Aggregation;

use ONGR\ElasticsearchDSL\Aggregation\AbstractAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\TermsAggregation;
use WikiSearch\Logger;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Multi-bucket value source based aggregation with buckets of property values.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/5.6/search-aggregations-bucket-terms-aggregation.html
 */
class PropertyValueAggregation extends PropertyAggregation {
    /**
     * @inheritDoc
     * @param int|null $size The maximum number of term buckets to be returned
     */
	public function __construct( string|PropertyFieldMapper $field, private ?int $size = null, string $name = null ) {
		parent::__construct( $field, $name );
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): AbstractAggregation {
		$field = $this->field->hasKeywordSubfield() ?
			$this->field->getKeywordField() :
			$this->field->getPropertyField();

		$termsAggregation = new TermsAggregation(
			$this->name,
			$field
		);

		if ( $this->size !== null ) {
			$termsAggregation->addParameter( "size", $this->size );
		}

		return $termsAggregation;
	}
}
