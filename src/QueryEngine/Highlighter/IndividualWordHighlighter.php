<?php


namespace WSSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WSSearch\SearchEngineConfig;
use WSSearch\SMW\PropertyFieldMapper;

/**
 * Class FieldHighlighter
 *
 * Simple highlighter that returns individual words in the given field(s).
 *
 * @package WSSearch\QueryEngine\Highlighter
 */
class FieldHighlighter implements Highlighter {
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
	 * @param array $fields
	 * @param int $limit
	 */
	public function __construct( array $fields, int $limit = 128 ) {
		$this->fields = $fields;
		$this->limit = $limit;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
		$highlight = new Highlight();
		$highlight->setTags( [""], [""] );

		foreach ( $this->fields as $field ) {
			$highlight->addField( $field, [
				"fragment_size" => 0,
				"number_of_fragments" => $this->limit
			] );
		}

		return $highlight;
	}
}