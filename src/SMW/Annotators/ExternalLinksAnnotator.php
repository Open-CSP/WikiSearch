<?php

namespace WikiSearch\SMW\Annotators;

use Content;
use ParserOutput;
use SMW\DIProperty;
use SMW\SemanticData;
use SMWDIBlob;

/**
 * This annotator contains information about external links that are used on a page.
 */
class ExternalLinksAnnotator implements Annotator {
	/**
	 * @inheritDoc
	 */
	public static function addAnnotation( Content $content, ParserOutput $parserOutput, SemanticData $semanticData ): void {
		$links = array_keys( $parserOutput->getExternalLinks() );

		foreach ( $links as $link ) {
			$semanticData->addPropertyObjectValue(
				new DIProperty( self::getId() ),
				new SMWDIBlob( $link )
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return '__wikisearch_external_links';
	}

	/**
	 * @inheritDoc
	 */
	public static function getLabel(): string {
		return 'External links';
	}

	/**
	 * @inheritDoc
	 */
	public static function getDefinition(): array {
		return [
			'label' => self::getLabel(),
			'type' => '_txt',
			'viewable' => true,
			'annotable' => false
		];
	}
}
