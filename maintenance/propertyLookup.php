<?php

namespace WikiSearch\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use SMW\DIProperty;
use WikiSearch\SMW\PropertyAliasMapper;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class propertyLookup extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription(
			'Look up the SMW property ID (smw_id) for a given property name.'
		);

		$this->addOption(
			'property',
			'The human-readable name of the property (e.g. "Parsed text")',
			true,
			true
		);

		$this->requireExtension( 'WikiSearch' );
	}

	public function execute() {
		$propertyName = $this->getOption( 'property' );

		if ( class_exists( '\SMW\StoreFactory' ) ) {
			$store = \SMW\StoreFactory::getStore();
		} else {
			$store = \SMW\ApplicationFactory::getInstance()->getStore();
		}

		$propertyKey = PropertyAliasMapper::findPropertyKey( $propertyName );

		try {
			$property = new DIProperty( $propertyKey );
		} catch ( \SMW\Exception\PropertyLabelNotResolvedException $e ) {
			$this->fatalError( "ERROR: Could not resolve property '$propertyName'.\n" );
		}

		$id = $store->getObjectIds()->getSMWPropertyId( $property );

		if ( $id === 0 ) {
			$this->fatalError(
				"ERROR: Property '$propertyName' (key: $propertyKey) was not found in the SMW ID table.\n"
				. "Make sure the property exists and SMW has been rebuilt.\n"
			);
		}

		$this->output( "$id\n" );
	}
}

$maintClass = propertyLookup::class;
require_once RUN_MAINTENANCE_IF_MAIN;
