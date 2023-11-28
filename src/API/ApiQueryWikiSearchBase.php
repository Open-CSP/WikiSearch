<?php

/**
 * WikiSearch MediaWiki extension
 * Copyright (C) 2021  Wikibase Solutions
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

namespace WikiSearch\API;

use ApiQueryBase;
use ApiUsageException;
use MediaWiki\MediaWikiServices;
use WikiSearch\Logger;

/**
 * Class ApiQueryWikiSearchBase
 *
 * @package WikiSearch
 */
abstract class ApiQueryWikiSearchBase extends ApiQueryBase {
	/**
	 * @inheritDoc
	 */
	public function isReadMode() {
		return in_array( 'read', $this->getConfig()->get( "WikiSearchAPIRequiredRights" ), true );
	}

	/**
	 * Checks applicable user rights.
	 *
	 * @throws ApiUsageException
	 */
	protected function checkUserRights(): void {
		try {
			$required_rights = $this->getConfig()->get( "WikiSearchAPIRequiredRights" );
			$this->checkUserRightsAny( $required_rights );
		} catch ( \ConfigException $e ) {
			Logger::getLogger()->critical(
				'Caught exception while trying to get required rights for WikiSearch API: {e}',
				[
					'e' => $e
				]
			);

			// Something went wrong; to be safe we block the access
			$this->dieWithError( [ 'apierror-permissiondenied', $this->msg( "action-read" ) ] );
		}
	}
}
