<?php


namespace WSSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\SearchEngineConfig;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class FragmentHighlighter
 *
 * Simple highlighter that takes a fragment size and some properties and constructs a highlighter.
 *
 * @package WSSearch\QueryEngine\Highlighter
 */
class FragmentHighlighter implements Highlighter {
	/**
	 * @var array The fields to apply the highlight to
	 */
	private $fields;

	/**
	 * @var int The maximum number of words to return
	 */
	private $limit;

	/**
	 * @var int The fragment size
	 */
	private $size;

	/**
	 * FieldHighlighter constructor.
	 *
	 * @param PropertyFieldMapper[] $properties
	 * @param int $size
	 * @param int $limit
	 */
	public function __construct( array $properties, int $size = 1, int $limit = 128 ) {
		$this->size = $size;
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
				"fragment_size" => $this->size,
				"number_of_fragments" => $this->limit
			] );
		}

		return $highlight;
	}
}