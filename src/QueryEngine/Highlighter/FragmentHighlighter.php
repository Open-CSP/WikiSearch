<?php

namespace WikiSearch\QueryEngine\Highlighter;

use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * Class FragmentHighlighter
 *
 * Simple highlighter that takes a fragment size and some properties and constructs a highlighter.
 *
 * @package WikiSearch\QueryEngine\Highlighter
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
     * @var string|null
     */
    private ?string $highlighter_type;

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
        ?string $highlighter_type = null,
		int $size = 1,
		int $limit = 128,
		string $tag_left = "HIGHLIGHT_@@",
		string $tag_right = "@@_HIGHLIGHT"
	) {
        $this->highlighter_type = $highlighter_type;
		$this->size = $size;
		$this->limit = $limit;
		$this->tag_left = $tag_left;
		$this->tag_right = $tag_right;

		foreach ( $properties as $property ) {
			if ( !$property->hasSearchSubfield() ) {
				$this->fields[] = $property->getPropertyField();
			} else {
				$this->fields[] = [
					$property->getPropertyField(),
					$property->getSearchField()
				];
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
		$highlight = new Highlight();
		$highlight->setTags( [ $this->tag_left ], [ $this->tag_right ] );

		foreach ( $this->fields as $field ) {
			$field_settings = [
				"fragment_size" => $this->size,
				"number_of_fragments" => $this->limit
			];

            if ( $this->highlighter_type !== null ) {
                $field_settings["type"] = $this->highlighter_type;
            }

			if ( is_string( $field ) ) {
				$highlight->addField( $field, $field_settings );
			} else {
				$field_settings['matched_fields'] = $field;
				$highlight->addField( $field[0], $field_settings );
			}
		}

		return $highlight;
	}
}
