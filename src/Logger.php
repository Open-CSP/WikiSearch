<?php

/**
 * WikiSearch MediaWiki extension
 * Copyright (C) 2022  Wikibase Solutions
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

namespace WikiSearch;

use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * Class Logger
 *
 * @package WikiSearch
 */
class Logger {
	// The logging channel for this extension
	public const LOGGING_CHANNEL = 'wikisearch';

	/**
	 * @var LoggerInterface An instance of a logger
	 */
	private static LoggerInterface $loggerInstance;

	/**
	 * Returns the logger instance.
	 *
	 * @return LoggerInterface
	 */
	public static function getLogger(): LoggerInterface {
		if ( !isset( self::$loggerInstance ) ) {
			self::$loggerInstance = LoggerFactory::getInstance( self::LOGGING_CHANNEL );
		}

		return self::$loggerInstance;
	}
}
