<?php

namespace WikiSearch\Exception;

use MWException;

class ParsingException extends MWException {
	/**
	 * @param string $message An information message to tell the user why parsing failed.
	 * @param array $path The path to the value that could not be parsed.
	 */
	public function __construct( string $message, array $path ) {
		$message = 'Failed to parse [' . implode( '.', $path ) . ']: ' . $message;

		parent::__construct( $message );
	}
}
