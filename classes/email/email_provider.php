<?php
class EmailProvider {
	protected $rest;
	
	function __construct( $rest ) {
		$this->rest = $rest;
	}

	/**
	 * returns array(
	 *  mailed: boolean
	 *  message?: string
	 * )
	 */
	public function send( $params ) {
		return array( 'mailed' => false, 'message' => 'unknown email provider' );
	}

	/**
	 * handles single email, comma-separated string, or array
	 * Returns array of email addresses
	 */
	protected function emailListToArray( $emails ) {
		if( !$emails )
			return array();
		
		//parse_addr_list?
		$emailArray = array();
		
		if( is_string($emails) ) {
			$emails = explode(',', $emails);
		}
		
		if( is_array( $emails ) ) {
			foreach( $emails as $email ) {
				$_email = $this->sanitizeEmail( $email );
				if( $_email )
					$emailArray[] = $_email;
			}
		}

		return $emailArray;
	}

	/**
	 * handles single email, comma-separated string, or array
	 * returns comma-separated string 
	 */
	protected function emailListToString( $emails ) {
		$emailArray = $this->emailListToArray( $emails );
		return implode(', ', $emailArray);
	}

	/**
	 * Removes injection characters to prevent header injection
	 * returns string
	 */
	protected function sanitizeEmail( $email ) {
		$email = str_replace( array("\r", "\n", chr(0), "%0a", "%0d"), '', $email );
		return trim( $email );
	}

	/**
	 * Sanitize text content (subject, fromName, etc.) to prevent injection
	 * returns string
	 */
	protected function sanitizeText( $text ) {
		return str_replace( array(chr(0), "%0a", "%0d"), '', $text );
	}

	/**
	 * Removes injection characters for header
	 * returns string
	 */
	protected function sanitizeHeader( $headerValue ) {
		return str_replace( array("\r", "\n", chr(0), "%0a", "%0d"), '', $headerValue );
	}
}
?>
