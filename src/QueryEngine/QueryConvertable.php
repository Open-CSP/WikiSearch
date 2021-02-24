<?php


namespace WSSearch\QueryEngine;

use ONGR\ElasticsearchDSL\BuilderInterface;

/**
 * Interface QueryObject
 *
 * Represents an object that can be converted to a BuilderInterface.
 *
 * @package WSSearch\QueryEngine
 */
interface BuilderInterfaceConvertable {
    /**
     * Converts the object to a BuilderInterface for use in the QueryEngine.
     *
     * @return BuilderInterface
     */
    public function toBuilderInterface(): BuilderInterface;
}