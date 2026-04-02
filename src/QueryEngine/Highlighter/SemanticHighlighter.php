<?php

namespace WikiSearch\QueryEngine\Highlighter;

use MediaWiki\MediaWikiServices;
use ONGR\ElasticsearchDSL\Highlight\Highlight;
use WikiSearch\SearchEngineConfig;
use WikiSearch\SMW\PropertyFieldMapper;

/**
 * The default highlighter applied to all WikiSearch searches.
 */
class SemanticHighlighter implements Highlighter {
    /**
     * @var string|null
     */
    private $highlightingModelId;

    public function __construct() {
        $this->highlightingModelId = MediaWikiServices::getInstance()
            ->getMainConfig()
            ->get( 'WikiSearchNeuralModels' )['highlighting'] ?? null;
    }

	/**
	 * @inheritDoc
	 */
	public function toQuery(): Highlight {
        $field = new PropertyFieldMapper( 'text_raw' );

		$highlight = new Highlight();
		$highlight->setTags( [ '{@@_HIGHLIGHT_@@' ], [ "@@_HIGHLIGHT_@@}" ] );
        $highlight->addField( $field->getPropertyField(), [
            'type' => 'semantic'
        ] );
        $highlight->addParameter( 'options', [
            'model_id' => $this->highlightingModelId
        ] );

		return $highlight;
	}
}
