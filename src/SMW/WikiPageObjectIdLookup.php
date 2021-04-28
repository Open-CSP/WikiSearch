<?php


namespace WSSearch\SMW;

use SMW\ApplicationFactory;
use SMW\DIWikiPage;
use SMW\SQLStore\SQLStore;
use Title;
use Wikimedia\Rdbms\IResultWrapper;

class WikiPageObjectIdLookup {
	/**
	 * Returns the object ID for the given Title.
	 *
	 * @param Title $title
	 * @return string
	 */
	public static function getObjectIdForTitle( Title $title ): string {
		$store = ApplicationFactory::getInstance()->getStore();
		$connection = $store->getConnection( "mw.db" );

		$condition = sprintf( "smw_title=%s", $connection->addQuotes( str_replace( " ", "_", $title->getFullText() ) ) );

		/** @var IResultWrapper $rows */
		$rows = $connection->select(
			SQLStore::ID_TABLE,
			[
				'smw_id'
			],
			$condition,
			__METHOD__
		);

		return $rows->current()->smw_id;
	}
}