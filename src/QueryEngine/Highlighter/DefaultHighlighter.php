<?php

namespace WikiSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WikiSearch\SearchEngineConfig;

/**
 * Class DefaultHighlighter
 *
 * The default highlighter applied to all WikiSearch searches.
 *
 * @package WikiSearch\QueryEngine\Highlighter
 */
class DefaultHighlighter implements Highlighter {
	private const FALLBACK_HIGHLIGHT_FIELDS = [
		"text_raw",
		"text_raw.search",
		"text_copy",
		"text_copy.search",
		"attachment.content"
	];

	/**
	 * @var SearchEngineConfig
	 */
	private SearchEngineConfig $config;

	/**
	 * @var array The fields to apply the highlight to
	 */
	private array $fields;

	/**
	 * @var array The settings applied to each field of the highlight. This specifies for instance the fragment
	 * size or the number of fragments per field.
	 *
     * phpcs:ignore
	 * @see https://www.elastic.co/guide/en/elasticsearch/reference/6.7/search-request-highlighting.html#highlighting-settings
	 */
	private array $field_settings;

	/**
	 * DefaultHighlighter constructor.
	 *
	 * @param SearchEngineConfig $config
	 * @param string[]|null $fields The fields to apply the highlight to, or null to highlight the default fields
	 * @param array|null $field_settings
	 */
	public function __construct( SearchEngineConfig $config, array $fields = null, array $field_settings = null ) {
		$this->config = $config;

		if ( $fields !== null ) {
			$this->fields = $fields;
		} else {
			$this->fields = $this->getDefaultFields();
		}

		if ( $field_settings !== null ) {
			$this->field_settings = $field_settings;
		} else {
			$config = MediaWikiServices::getInstance()->getMainConfig();

			$this->field_settings = [
				"fragment_size" => $config->get( "WikiSearchHighlightFragmentSize" ),
				"number_of_fragments" => $config->get( "WikiSearchHighlightNumberOfFragments" ),
				"type" => "fvh" // Use the fast vector highlighter for combining snippets
			];
		}
	}

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
		$highlight = new Highlight();
		$highlight->setTags( [ '{@@_HIGHLIGHT_@@' ], [ "@@_HIGHLIGHT_@@}" ] );

		foreach ( $this->fields as $field ) {
			if ( is_string( $field ) ) {
				$highlight->addField( $field, $this->field_settings );
			} else {
				$field_settings = $this->field_settings;
				$field_settings['matched_fields'] = $field;

				$highlight->addField( $field[0], $field_settings );
			}
		}

		return $highlight;
	}

	/**
	 * Returns an array of fields to highlight if no specific fields are given in the constructor.
	 *
	 * @return array
	 */
	private function getDefaultFields(): array {
		$properties =
			$this->config->getSearchParameter( "highlighted properties" ) ?:
			$this->config->getSearchParameter( "search term properties" );

		if ( $properties !== false ) {
			$res = [];

			foreach ( $properties as $property ) {
				if ( !$property->hasSearchSubfield() ) {
					$res[] = $property->getPropertyField();
				} else {
					$res[] = [
						$property->getPropertyField(),
						$property->getSearchField()
					];
				}
			}

			return $res;
		}

		// Fallback fields if no field is specified in the highlighted properties or search term properties
		return self::FALLBACK_HIGHLIGHT_FIELDS;
	}
}
