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
    public const HIGHLIGHT_TAG_LEFT = '{@@_HIGHLIGHT_@@';
    public const HIGHLIGHT_TAG_RIGHT = '@@_HIGHLIGHT_@@}';

    /**
     * @param PropertyFieldMapper[] $fields The fields to apply the highlight to
     * @param string|null $type The type of the highlighter, should be one of the Highlighter::TYPE_* constants
     * @param int $size The fragment size
     * @param int $limit The maximum number of words to return
     */
	public function __construct(
		private array $fields,
        private ?string $type = self::TYPE_UNIFIED,
        private int $size = 1,
        private int $limit = 128
	) {
        if ($type === null) {
            $this->type = self::TYPE_UNIFIED;
        }
    }

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
		$highlight = new Highlight();
		$highlight->setTags( [ self::HIGHLIGHT_TAG_LEFT ], [ self::HIGHLIGHT_TAG_RIGHT ] );

		$commonFieldSettings = [
			"fragment_size" => $this->size,
			"number_of_fragments" => $this->limit,
            "type" => $this->type
		];

		foreach ( $this->fields as $field ) {
			$fieldSettings = $commonFieldSettings;

			if ( $this->type === self::TYPE_FVH && !$field->supportsFVH() ) {
                // Fast vector highlighting is not always supported. If FVH is enabled, but it is not supported,
                // revert back to "unified".
                $fieldSettings["type"] = self::TYPE_UNIFIED;
			}

			if ( $field->hasSearchSubfield() ) {
				$fieldSettings['matched_fields'] = [ $field->getPropertyField(), $field->getSearchField() ];
			}

			$highlight->addField( $field->getPropertyField(), $fieldSettings );
		}

		return $highlight;
	}
}
