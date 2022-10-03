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
	 * @param int|null $size The fragment size
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
		$this->fields = $properties;
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
		$highlight = new Highlight();
		$highlight->setTags( [ '{@@_HIGHLIGHT_@@' ], [ "@@_HIGHLIGHT_@@}" ] );

		$common_field_settings = [
			"fragment_size" => $this->size,
			"number_of_fragments" => $this->limit
		];

		foreach ( $this->fields as $field ) {
			$field_settings = $common_field_settings;

			if ( $this->highlighter_type === "fvh" && $field->supportsFVH() ) {
				// TODO: Support different highlighter types
				$field_settings['type'] = $this->highlighter_type;
			}

			if ( $field->hasSearchSubfield() ) {
				$field_settings['matched_fields'] = [ $field->getPropertyField(), $field->getSearchField() ];
			}

			$highlight->addField( $field->getPropertyField(), $field_settings );
		}

		return $highlight;
	}
}
