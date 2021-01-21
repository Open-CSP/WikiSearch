<?php

namespace WSSearch;

use BadMethodCallException;
use SMW\ApplicationFactory;
use SMW\DIProperty;
use SMW\Elastic\ElasticStore;

/**
 * Class PropertyInfo
 *
 * Queries the SemanticMediaWiki property store and stores information about the given property
 * name.
 *
 * @package WSSearch
 */
class PropertyInfo {
    /**
     * @var int The unique ID of the property
     */
    private $id;

    /**
     * @var string The kind/datatype of the property
     */
    private $type;

    /**
     * @var string The name of the property as a string
     */
    private $name;

    /**
     * PropertyInfo constructor.
     *
     * @param string $property_name The name of the property
     */
    public function __construct( string $property_name ) {
        $store = ApplicationFactory::getInstance()->getStore();

        if ( !$store instanceof ElasticStore ) {
            throw new BadMethodCallException( "WSSearch requires ElasticSearch to be installed");
        }

        $property = new DIProperty( $property_name );

        $this->id   = $store->getObjectIds()->getSMWPropertyID( $property );
        $this->type = $property->findPropertyValueType() === "_txt" ? "txtField" : "wpgField";
        $this->name = $property_name;
    }

    /**
     * Returns the ID of the property.
     *
     * @return int
     */
    public function getPropertyID(): int {
        return $this->id;
    }

    /**
     * Returns the type of this property.
     *
     * @return string
     */
    public function getPropertyType(): string {
        return $this->type;
    }

    /**
     * Returns the name of this property.
     *
     * @return string
     */
    public function getPropertyName(): string {
        return $this->name;
    }
}