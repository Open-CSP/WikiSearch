<?php

namespace WikiSearch\SMW;

use MediaWiki\MediaWikiServices;
use SMW\PropertyRegistry;
use WikiSearch\SMW\Annotators\AltTextAnnotator;
use WikiSearch\SMW\Annotators\Annotator;
use WikiSearch\SMW\Annotators\ExternalLinksAnnotator;
use WikiSearch\SMW\Annotators\ImagesAnnotator;
use WikiSearch\SMW\Annotators\InternalLinksAnnotator;
use WikiSearch\SMW\Annotators\ParsedTextAnnotator;

/**
 * Initializes the predefined properties that may be used for search.
 */
class PropertyInitializer {
    public const ANNOTATORS = [
        AltTextAnnotator::class,
        ImagesAnnotator::class,
        InternalLinksAnnotator::class,
        ExternalLinksAnnotator::class,
        ParsedTextAnnotator::class
    ];

	/**
	 * @var PropertyRegistry The PropertyRegistry in which to initialise the predefined properties
	 */
	private PropertyRegistry $registry;

	/**
	 * @param PropertyRegistry $registry The PropertyRegistry in which to initialise the predefined properties
	 */
	public function __construct( PropertyRegistry $registry ) {
		$this->registry = $registry;
	}

    /**
     * Returns the array of enabled annotators. Annotators can be disabled by adding their ID to the
     * $wgWikiSearchDisabledAnnotators array.
     *
     * @return array
     */
    public static function getAnnotators(): array {
        $disabledAnnotators = MediaWikiServices::getInstance()->getMainConfig()->get( "WikiSearchDisabledAnnotators" );

        return array_filter( self::ANNOTATORS, function ( $class ) use ( $disabledAnnotators ): bool {
            return !in_array( $class::getId(), $disabledAnnotators, true );
        } );
    }

	/**
	 * Initialize the predefined properties.
	 *
	 * @link https://github.com/SemanticMediaWiki/SemanticMediaWiki/blob/master/docs/examples/hook.property.initproperties.md
	 */
	public function initProperties(): void {
		$definitions = $this->getPropertyDefinitions();

		foreach ( $definitions as $propertyId => $definition ) {
			$this->registry->registerProperty(
				$propertyId,
				$definition['type'],
				$definition['label'] ?? false,
				$definition['viewable'] ?? false,
				$definition['annotable'] ?? true,
				$definition['declarative'] ?? false
			);

			if ( isset( $definition['alias'] ) ) {
				$this->registry->registerPropertyAlias(
					$propertyId,
					wfMessage( $definition['alias'] )->text()
				);

				$this->registry->registerPropertyAliasByMsgKey(
					$propertyId,
					$definition['alias']
				);
			}

			if ( isset( $definition['description'] ) ) {
				$this->registry->registerPropertyDescriptionByMsgKey(
					$propertyId,
					$definition['description']
				);
			}
		}
	}

	/**
	 * Returns the property definitions of the predefined properties.
	 *
	 * @return array
	 */
	public function getPropertyDefinitions(): array {
		$definitions = [];

		foreach ( self::getAnnotators() as $annotation ) {
			$definitions[$annotation::getId()] = $annotation::getDefinition();
		}

		return $definitions;
	}
}
