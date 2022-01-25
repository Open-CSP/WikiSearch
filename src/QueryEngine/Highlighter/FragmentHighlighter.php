<?php

namespace WSSearch\QueryEngine\Highlighter;

use ONGR\ElasticsearchDSL\Highlight\Highlight;
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
	private array $fields;

	/**
	 * @var int The maximum number of words to return
	 */
	private int $limit;

	/**
	 * @var int The fragment size
	 */
	private int $size;

    /**
     * @var string
     */
    private string $tag_left;

    /**
     * @var string
     */
    private string $tag_right;

    /**
     * FieldHighlighter constructor.
     *
     * @param PropertyFieldMapper[] $properties The fields to apply the highlight to
     * @param int $size The fragment size
     * @param int $limit The maximum number of words to return
     * @param string $tag_left The left highlight tag
     * @param string $tag_right The right highlight tag
     */
	public function __construct(
	    array $properties,
        int $size = 1,
        int $limit = 128,
        string $tag_left = "HIGHLIGHT_@@",
        string $tag_right = "@@_HIGHLIGHT"
    ) {
		$this->size = $size;
		$this->limit = $limit;
		$this->fields = array_map( function ( PropertyFieldMapper $property ): string {
			return $property->getPropertyField();
		}, $properties );
		$this->tag_left = $tag_left;
		$this->tag_right = $tag_right;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
	    $highlight = new Highlight();
		$highlight->setTags( [ $this->tag_left ], [ $this->tag_right ] );

		foreach ( $this->fields as $field ) {
			$highlight->addField( $field, [
				"fragment_size" => $this->size,
				"number_of_fragments" => $this->limit
			] );
		}

		return $highlight;
	}
}
