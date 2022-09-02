<?php

namespace WikiSearch\SMW;

use SMW\PropertyRegistry;

/**
 * Initializes the predefined properties that may be used for search.
 */
class PropertyInitializer {
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
        $annotators = AnnotatorStore::ANNOTATORS;

        foreach ( $annotators as $annotation ) {
            $definitions[$annotation::getId()] = $annotation::getDefinition();
        }

        return $definitions;
    }
}