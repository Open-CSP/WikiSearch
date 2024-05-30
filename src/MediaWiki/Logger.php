<?php

namespace WikiSearch\MediaWiki;

use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class Logger {
	// The logging channel for this extension
	public const LOGGING_CHANNEL = 'wikisearch';

	/**
	 * @var LoggerInterface An instance of a logger
	 */
	private LoggerInterface $loggerInstance;

	/**
	 * Returns the logger instance.
	 *
	 * @return LoggerInterface
	 */
	public function getLogger(): LoggerInterface {
		if ( !isset( $this->loggerInstance ) ) {
			$this->loggerInstance = LoggerFactory::getInstance( self::LOGGING_CHANNEL );
		}

		return $this->loggerInstance;
	}
}
