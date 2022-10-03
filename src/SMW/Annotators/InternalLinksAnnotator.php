<?php

namespace WikiSearch\SMW\Annotators;

use Content;
use ParserOutput;
use SMW\DIProperty;
use SMW\SemanticData;
use SMWDIBlob;
use Title;

/**
 * This annotator contains information about internal links that are used on a page.
 */
class InternalLinksAnnotator implements Annotator {
	/**
	 * @inheritDoc
	 */
	public static function addAnnotation( Content $content, ParserOutput $parserOutput, SemanticData $semanticData ): void {
		$links = self::formatLinks( $parserOutput->getLinks() );

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
		return '__wikisearch_internal_links';
	}

	/**
	 * @inheritDoc
	 */
	public static function getLabel(): string {
		return 'Internal links';
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

	/**
	 * Formats the result of the parser output getLinks function.
	 *
	 * @param $links
	 * @return array
	 */
	private static function formatLinks( $links ): array {
		$result = [];
		foreach ( $links as $ns => $nsLinks ) {
			foreach ( $nsLinks as $title => $id ) {
				$result[] = Title::makeTitle( $ns, $title )->getFullText();
			}
		}

		return $result;
	}
}
