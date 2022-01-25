<?php

namespace WikiSearch\SMW;

use SMW\ApplicationFactory;
use SMW\SQLStore\SQLStore;
use SMW\Store;
use Title;
use Wikimedia\Rdbms\IResultWrapper;
use WikiSearch\Logger;

class WikiPageObjectIdLookup {
	/**
	 * Returns the object ID for the given Title.
	 *
	 * @param Title $title
	 * @return string|null
	 */
	public static function getObjectIdForTitle( Title $title ): ?string {
		Logger::getLogger()->debug( 'Fetching object ID for Title {title}', [
			'title' => $title->getFullText()
		] );

		/** @var Store $store */
		$store = ApplicationFactory::getInstance()->getStore();
		$connection = $store->getConnection( "mw.db" );

		$smw_title = $connection->addQuotes( str_replace( " ", "_", $title->getText() ) );
		$smw_namespace = $title->getNamespace();

		$condition = sprintf( "smw_title=%s AND smw_namespace=%s", $smw_title, $smw_namespace );

		/** @var IResultWrapper $rows */
		$rows = $connection->select(
			SQLStore::ID_TABLE,
			[
				'smw_id'
			],
			$condition,
			__METHOD__
		);

		$object_id = $rows->current()->smw_id;

		Logger::getLogger()->debug( 'Finished fetching object ID for Title {title}: {objectId}', [
			'title' => $title->getFullText(),
			'objectId' => $object_id
		] );

		return $object_id;
	}
}
