<?php

namespace WikiSearch\Exception;

use MWException;
use Throwable;

class ParsingException extends MWException {
	public function __construct( string $message, array $path, int $code = 0, ?Throwable $previous = null ) {
		$message = 'Failed to parse [' . implode( '.', $path ) . ']: ' . $message;

		parent::__construct( $message, $code, $previous );
	}
}
