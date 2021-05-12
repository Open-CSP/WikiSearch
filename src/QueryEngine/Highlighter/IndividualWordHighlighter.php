<?php


namespace WSSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\SearchEngineConfig;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class IndividualWordHighlighter
 *
 * Simple highlighter that returns individual words in the given field(s).
 *
 * @package WSSearch\QueryEngine\Highlighter
 */
class IndividualWordHighlighter implements Highlighter {
	/**
	 * @var array The fields to apply the highlight to
	 */
	private $fields;

	/**
	 * @var int The maximum number of words to return.
	 */
	private $limit;

	/**
	 * FieldHighlighter constructor.
	 *
	 * @param PropertyFieldMapper[] $properties
	 * @param int $limit
	 */
	public function __construct( array $properties, int $limit = 128 ) {
		$this->limit = $limit;
		$this->fields = array_map(function( PropertyFieldMapper $property ): string {
			return $property->getPropertyField();
		}, $properties );
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
		$highlight = new Highlight();
		$highlight->setTags( [""], [""] );

		foreach ( $this->fields as $field ) {
			$highlight->addField( $field, [
				"fragment_size" => 1,
				"number_of_fragments" => $this->limit
			] );
		}

		return $highlight;
	}
}