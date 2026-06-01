<?php
require_once( getabspath( 'classes/email/email_provider.php') );

class AzureMailProvider extends EmailProvider {

	function __construct() {
		parent::__construct( getAzureEmailConnection() );
	}
	
	public function send( $params ) {
		$token = $this->rest->getOauthAccessToken();
		if( !$token ) {
			return array( 
				'mailed' => false,
				'message' => $this->rest->lastError()
			);
		}
		
		$message = $this->createEmailMessage( $params );
		$emailSettings = ProjectSettings::getSecurityValue( 'emailSettings' );
		
		$request = new HttpRequest( 'https://graph.microsoft.com/v1.0/users/' . urlencode( $emailSettings['oauthUserEmail'] ) . '/sendMail', 'POST' );
		$request->postPayload = array(
			'message' => $message,
			'saveToSentItems' => true
		);
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
	 * replyTo - reply email address (optional) - supports single email, comma-separated string, or array
	 * priority - message priority: 1=urgent, 2=high, 3=normal (optional)
	 * subject - email subject (optional)
	 * body - plain text message body (optional)
	 * htmlbody - HTML message body (optional, don't use 'body' if this is used)
	 * charset - HTML message charset (optional, defaults to UTF-8) - Note: Microsoft Graph always uses UTF-8
	 * attachments - array of attachments with 'path', optional 'name', 'encoding', 'type' (optional)
	 */
	protected function createEmailMessage( $params ) {
		$emailSettings = ProjectSettings::getSecurityValue('emailSettings');
		
		if( !isset($params['to']) || !$params['to'] ) //?
			throw new Exception('Email "to" parameter is required');
		$to = $this->emailListToArray( $params['to'] );
		
		$cc = isset($params['cc']) ? $this->emailListToArray( $params['cc'] ) : array();
		$bcc = isset($params['bcc']) ? $this->emailListToArray( $params['bcc'] ) : array();
		$replyTo = isset($params['replyTo']) ? $this->emailListToArray( $params['replyTo'] ) : array();
		
		$subject = isset($params['subject']) ? $this->sanitizeText( $params['subject'] ) : '';
		$priority = isset($params['priority']) ? $params['priority'] : '3';
		$attachments = isset($params['attachments']) ? $params['attachments'] : array();
		
		$fromAddress = isset($params['from']) ? $this->sanitizeEmail( $params['from'] ) : $emailSettings['oauthUserEmail'];
		$fromName = isset($params['fromName']) ? $this->sanitizeText( $params['fromName'] ) : '';
		
		$isHtml = isset($params['htmlbody']);
		$messageBody = $isHtml ? $params['htmlbody'] : (isset($params['body']) ? $params['body'] : '');
		$contentType = $isHtml ? 'HTML' : 'Text';
		
		// build message structure for Microsoft Graph API
		$message = array(
			'subject' => $subject,
			'body' => array(
				'contentType' => $contentType,
				'content' => $messageBody
			),
			'from' => array(
				'emailAddress' => array(
					'address' => $fromAddress,
					'name' => $fromName
				)
			),
			'toRecipients' => $this->buildRecipientArray( $to )
		);
		
		if( !empty($cc) )
			$message['ccRecipients'] = $this->buildRecipientArray( $cc );
		
		if( !empty($bcc) ) {
			$message['bccRecipients'] = $this->buildRecipientArray( $bcc );
		}

		if (!empty($replyTo))
			$message['replyTo'] = $this->buildRecipientArray( $replyTo );
		
		$message['importance'] = $this->convertPriority( $priority );
		
		if( !empty($attachments) )
			$message['attachments'] = $this->buildAttachments( $attachments );
		
		return $message;
	}

	/**
	 * Build recipient array in Microsoft Graph API format from array of email addresses
	 */
	protected function buildRecipientArray( $emailAddresses ) {
		$recipients = array();
		
		if( !$emailAddresses ) {
			return $recipients;
		}
		
		if( is_string($emailAddresses) ) {
			$emailAddresses = array( $emailAddresses );
		}
		
		foreach( $emailAddresses as $email ) {
			$_email = trim( $email );
			if( $_email ) {
				$recipients[] = array(
					'emailAddress' => array(
						'address' => $_email
					)
				);
			}
		}
		
		return $recipients;
	}

	/**
	 * Convert PHPRunner priority (1=urgent, 2=high, 3=normal) to Microsoft Graph importance
	 */
	protected function convertPriority( $priority ) {
		switch( $priority ) {
			case '1':
			case '2':
				return 'high';
			case '3':
			default:
				return 'normal';
		}
	}

	/**
	 * Build attachments array for Microsoft Graph API
	 */
	protected function buildAttachments( $attachments ) {
		$graphAttachments = array();
		
		foreach( $attachments as $attachment ) {
			$attachmentData = $this->buildAttachment( $attachment );
			if( $attachmentData )
				$graphAttachments[] = $attachmentData;
		}
		
		return $graphAttachments;
	}

	/**
	 * Build single attachment for Microsoft Graph API
	 */
	protected function buildAttachment( $attachment ) {
		$filePath = $attachment['path'];
		$fileContent = runner_file_get_contents( $filePath );
		
		$fileName = isset( $attachment['name'] ) ? $attachment['name'] : basename( $filePath );
		$mimeType = isset( $attachment['type'] ) ? $attachment['type'] : mimeTypeByExt( $filePath );

		return array(
			'@odata.type' => '#microsoft.graph.fileAttachment',
			'name' => $fileName,
			'contentType' => $mimeType,
			'contentBytes' => base64_encode( $fileContent )
		);
	}
}
?>
