<?php
require_once( getabspath( 'classes/email/email_provider.php') );

class GmailProvider extends EmailProvider {

	function __construct() {
		parent::__construct( getGoogleEmailConnection() );
	}
	
	public function send( $params ) {
		$token = $this->rest->getOauthAccessToken();
		if( !$token ) {
			return array( 
				'mailed' => false,
				'message' => $this->rest->lastError()
			);
		}
		
		$rawMessage = $this->createRawEmail( $params );
		
		$encodedMessage = str_replace( array('+','/','='), array('-','_',''), base64_encode($rawMessage) );

		$request = new HttpRequest( 'https://www.googleapis.com/gmail/v1/users/me/messages/send', 'POST' );
		$request->postPayload['raw'] = $encodedMessage;
		$request->headers['content-type'] = 'application/json';

		$response = $this->rest->requestWithAuth( $request );
		if( $response["error"] ) {
			return array( 
				'mailed' => false,
				'message' => $response["errorMessage"]
			);
		}
		return array( 'mailed' => true );
		
	}

	/**
	 * See https://xlinesoft.com/phprunner/docs/runner_mail_function.htm
	 * Supported parameters:
	 * from - sender's email address (optional, uses email settings if not specified)
	 * fromName - sender's name (optional)
	 * to - receiver's email address (required) - supports single email, comma-separated string, or array
	 * cc - secondary recipients (optional) - supports single email, comma-separated string, or array
	 * bcc - blind carbon copy recipients (optional) - supports single email, comma-separated string, or array
	 * replyTo - reply email address (optional)
	 * priority - message priority: 1=urgent, 2=high, 3=normal (optional)
	 * subject - email subject (optional)
	 * body - plain text message body (optional)
	 * htmlbody - HTML message body (optional, don't use 'body' if this is used)
	 * charset - HTML message charset (optional, defaults to UTF-8)
	 * attachments - array of attachments with 'path', optional 'name', 'encoding', 'type' (optional)
	 */
	protected function createRawEmail( $params ) {	
		if( !isset($params['to']) || !$params['to'] )
			throw new Exception('Email "to" parameter is required');
		
		$charset = isset($params['charset']) ? $params['charset'] : 'UTF-8';
		
		$hasAttachments = isset($params['attachments']);
		$attachments = $hasAttachments ? $params['attachments'] : array();
		
		$isHtml = isset($params['htmlbody']);
		$messageBody = $isHtml ? $params['htmlbody'] : (isset($params['body']) ? $params['body'] : '');
		
		// boundary for multipart messages
		$boundary = '----=_Part_'.generatePassword(24);
		
		$rawMessage = $this->buildEmailHeaders( $params, $charset, $boundary, $hasAttachments, $isHtml );
		$rawMessage.= $this->buildEmailBody( $messageBody, $charset, $boundary, $attachments, $isHtml );
	
		return $rawMessage;
	}
	
	/**
	 * Build email headers
	 */
	protected function buildEmailHeaders( $params, $charset, $boundary, $hasAttachments, $isHtml ) {
		$headers = '';
		
		$emailSettings = ProjectSettings::getSecurityValue('emailSettings');
		$fromName = isset($params['fromName']) ? $this->sanitizeHeader( $params['fromName'] ) : '';
		$fromEmail = isset($params['from']) ? $this->sanitizeEmail( $params['from'] ) : $emailSettings['oauthUserEmail'];
		
		if( $fromName ) {
			$encodedFromName = $this->encodeHeaderValue( $fromName, $charset );
			$headers.= "From: ". $encodedFromName ." <". $fromEmail .">\r\n";
		} else {
			$headers.= "From: ". $fromEmail ."\r\n";
		}
		
		$headers.= "To: ". $this->emailListToString( $params['to'] ) ."\r\n";
		
		$cc = isset($params['cc']) ? $this->emailListToString( $params['cc'] ) : '';	
		if( $cc )
			$headers.= "Cc: ". $cc ."\r\n";
				
		$bcc = isset($params['bcc']) ? $this->emailListToString( $params['bcc'] ) : '';
		if( $bcc )
			$headers.= "Bcc: ". $bcc ."\r\n";
		
		$replyTo = isset($params['replyTo']) ? $this->sanitizeEmail( $params['replyTo'] ) : '';
		if( $replyTo )
			$headers.= "Reply-To: ". $replyTo ."\r\n";
		
		
		$subject = isset($params['subject']) ? $this->sanitizeHeader( $params['subject'] ) : '';
		if( $subject ) {
			$encodedSubject = $this->encodeHeaderValue( $subject, $charset );
			$headers .= "Subject: ". $encodedSubject ."\r\n";
		}

		$priority = isset($params['priority']) ? $params['priority'] : '3';
		if( $priority !== '3' ) {
			$headers.= "X-Priority: ". $priority ."\r\n";
			$priorityText = $priority == '1' ? 'Urgent' : ($priority == '2' ? 'High' : 'Normal');
			$headers.= "X-MSMail-Priority: ". $priorityText ."\r\n";
		}
		
		$headers.= "MIME-Version: 1.0\r\n";
		
		if( $hasAttachments ) {
			$headers .= "Content-Type: multipart/mixed; boundary=\"". $boundary ."\"\r\n";
		} else {
			$contentType = $isHtml ? "text/html; charset=". $charset : "text/plain; charset=". $charset;
			$headers .= "Content-Type: ". $contentType ."\r\n";
			$headers .= "Content-Transfer-Encoding: quoted-printable\r\n";
		}
		
		return $headers."\r\n";
	}

	protected function buildEmailBody( $messageBody, $charset, $boundary, $attachments, $isHtml ) {
		//quoted_printable_encode( $messageBody )
		// Normalize line endings to CRLF before encoding
		$messageBody = str_replace(array("\r\n", "\r", "\n"), "\r\n", $messageBody);
		
		$encodedMessageBody = str_replace(
			array('%20', '%0D%0A.', '%0D%0A', '%'),
			array(' ', "\r\n=2E", "\r\n", '='),
			rawurlencode( $messageBody )
		);


		$encodedMessageBody = preg_replace( '/[^\r\n]{'. (76 - 3) .'}[^=\r\n]{2}/', "$0=\r\n", $encodedMessageBody );				
		
		if( !$attachments )
			return $encodedMessageBody;
		
		$body = "--".$boundary."\r\n";
		
		$contentType = $isHtml ? "text/html; charset=".$charset : "text/plain; charset=".$charset;
		$body.= "Content-Type: ".$contentType."\r\n";
		$body.= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
		$body.= $encodedMessageBody ."\r\n\r\n";

		foreach( $attachments as $attachment ) {
			$body .= $this->buildAttachmentPart( $attachment, $boundary );
		}

		$body .= "--".$boundary."--\r\n";
		return $body;
	}

	/**
	 * Build attachment part
	 */
	protected function buildAttachmentPart( $attachment, $boundary ) {
		$filePath = $attachment['path'];
		$fileContent = runner_file_get_contents( $filePath );
		
		$fileName = isset( $attachment['name'] ) ? $attachment['name'] : basename( $filePath );
		$encoding = isset( $attachment['encoding'] ) ? $attachment['encoding'] : 'base64';
		$mimeType = isset( $attachment['type'] ) ? $attachment['type'] : mimeTypeByExt( $filePath );
		
		$part = "--".$boundary."\r\n";
		$part.= "Content-Type: ". $mimeType ."; name=\"". $this->encodeHeaderValue( $fileName ) ."\"\r\n";
		$part.= "Content-Transfer-Encoding: ". $encoding ."\r\n";
		$part.= "Content-Disposition: attachment; filename=\"". $this->encodeHeaderValue( $fileName ) ."\"\r\n\r\n";
		
		if( $encoding === 'base64' ) {
			// chunk_split
			$encoded = base64_encode( $fileContent );
			$part.= implode( "\r\n", str_split($encoded, 76) )."\r\n";
		} else 
			$part.= $fileContent;
		
		return $part."\r\n";
	}

	protected function encodeHeaderValue( $value, $charset = 'UTF-8' ) {
		if( preg_match('/[^\x00-\x7F]/', $value) ) {
			// $value contains non-ASCII characters
			return '=?'. $charset .'?B?'. base64_encode( $value ) .'?=';
		}
		return $value;
	}
}
?>