<?php
require_once( getabspath('connections/rest.php') );

class RestManager
{

	public function getConnection( $id ) {
		if( $id == spidGOOGLEDRIVE ) {
			return getGoogleDriveConnection();
		}
		if( $id == spidONEDRIVE ) {
			return getOneDriveConnection();
		}
		if( $id == spidDROPBOX ) {
			return getDropboxConnection();
		}
		if( $id == empidGOOGLE ) {
			return getGoogleEmailConnection();
		}
		if( $id == empidAZURE ) {
			return getAzureEmailConnection();
		}
		$connInfo = runnerGetRestConnectionInfo( $id );
		if( !$connInfo ) {
			return null;
		}
		return new RestConnection( $connInfo );
	}

	public function idByName( $name ) {
		return runnerRestConnectionIdByName( $name );
	}
}


?>