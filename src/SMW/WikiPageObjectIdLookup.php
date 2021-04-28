<?php


namespace WSSearch\SMW;

use SMW\DIWikiPage;
use Title;

class WikiPageObjectIdLookup {
	/**
	 * Returns the object ID for the given Title.
	 *
	 * @param Title $title
	 * @return string
	 */
	public static function getObjectIdForTitle( Title $title ) {
		$wikipage = DIWikiPage::newFromTitle( $title );
		return $wikipage->getId();
	}
}