<?php


	class Instapress_Core_File {
	
		/**
		 * Location at where images will be store on Amazon S3.
		 * @var string
		 */
		private $_s3ImagePathFolder = "bucketpath";
	
		/**
		 * phpthumb class object.
		 * @var phpthumb
		 */
		private $_phpThumbObject = null;
	
		/**
		 * Id of the client for accessing images.
		 * @var integer
		 */
		private $_clientId = 0;
	
		/**
		 * Maximum width of the image.
		 * @var integer
		 */
		// private $_maxWidth = 650; // Testing
	
		/**
		 * Temporary storage location for storing images.
		 * @var string;
		 */
		private $_tempImageLocation = '';

		/**
		 * Create new instance of Image class.
		 * @param $clientId, Id of the client for accessing images.
		 * @param $s3Location, Location at where images will be store on Amazon S3.
		 * @return Instapress_Core_Image
		 */
		function __construct( $clientId = null, $userImage = false, $s3Location = null ) 
		{
			require_once( LIB_PATH . '/PhpThumb/phpthumb.class.php' );

			if( $clientId == null ) 
			{
				$clientId = Instapress_Core_Login::getUserInfo( 'clientId' );
			}

			$this->_tempImageLocation = ADMIN_PUBLIC . 'temp_files/';
			$this->_clientId = trim( $clientId );
			$currentYear = date( 'Y' );
			$currentMonth = date( 'm' );
			$currentDay = date( 'd' );
			if( $userImage )
			$s3Location = '';

			if( $s3Location != null ) {
				$this->_s3ImagePathFolder = $this->_s3ImagePathFolder . $s3Location;
			} else {
				$this->_s3ImagePathFolder = $this->_s3ImagePathFolder . "$this->_clientId/$currentYear/$currentMonth/$currentDay";
			}
		}
		

		/**
		 * This function copies an image file to S3 location.
		 * @param $sourceFilePath, String, Full path of the image file to be copied.
		 * @param $fileName, String, Name using which the file will be stored in s3 folder.
		 * @return boolean, True if operation was successful, False otherwise.
		 */
		public function saveIFileOnS3( $sourceFilePath, $fileName ) {
			$contents = file_get_contents( $sourceFilePath );

			$mimeType = mime_content_type( $sourceFilePath );
			$mimeType = $mimeType ? $mimeType : 'text/plain';

			$objS3 = new Amazon_S3();

			if( $objS3->putObject( $fileName, $contents, $this->_s3ImagePathFolder, 'public-read', $mimeType ) ) {
				return $this->_s3ImagePathFolder . '/' . $fileName;
			}
			return false;
		}

		/**
		 * Uploads an image from client file system and then stores that file in the s3 location.
		 * @param $fileInfo, Array, An array containing info of $_FILES element.
		 * @param $fileTitle, String, Optional, If not specified then a name is generated at run time for the image otherwise the specified string is used.
		 * @return String, S3 location of the stored file.
		 */
		public function uploadFile( $fileInfo, $fileTitle = '' ) 
		{
			if( !$this->_clientId ) {
				throw new Exception( 'Instpress_Core_File : Please set clientId first!' );
			}

			$validFileTypes = array( 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',	// xlsx
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',					// docx
				'application/vnd.oasis.opendocument.text', 'text/richtext', 'application/x-rtf',			// odt, rtf
				'application/rtf', "application/pdf", "application/msword", "text/plain",
				"application/octet-stream", "application/vnd.ms-excel",
				"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
				"text/x-troff-mm" );
			if( array_search( $fileInfo[ 'type' ], $validFileTypes ) === false ) {
				throw new Exception( 'Unsuported file type "' . $fileInfo['type'] . '"!' );
			}
	
			if( $fileInfo[ 'size' ] > ( 2097134 ) ) {
				throw new Exception( 'File size exceeds maximum size( 2 MB )!' );
			}

			$extension = explode( '.', $fileInfo[ 'name' ] );
			$extension = $extension[ count( $extension ) - 1 ];

			if( !$fileTitle ) {
				$fileTitle = explode( '.', $fileInfo[ 'name' ] );
				$fileTitle = ucfirst( $fileTitle[0] ) . ' ' . ucfirst( Helper::randomStringGenerator( 5 ) );
			}
			
			$fileSlug= Helper::sanitizeWithDashes( $fileTitle );
			$fileSlug = str_replace( '-', '_', $fileSlug );

			$fileSlug .= '_' . Helper::randomStringGenerator( 5 );

			//$fileTitle .= '_' . Helper::generateRandomKey( 5 );
				
			if( ( @filesize( $fileInfo['tmp_name'] ) ) ) {
				$fileFullPath = $this->_tempImageLocation . $fileSlug . "." . $extension;
	
				move_uploaded_file( $fileInfo['tmp_name'], $fileFullPath );
				chmod( $fileFullPath, 0777 );
	
				$fileInfo = filesize( $fileFullPath );
				
									
				if( $amazonFileUri = $this->saveIFileOnS3( $fileFullPath, $fileSlug . "." . $extension ) ) 
				{
					$file_uri = $amazonFileUri;
					@unlink( $fileFullPath );
					return $file_uri;
				} 
				else 
				{
					throw new Exception( "Could not store file on Amazon S3!" );
				}
			} 
			else 
			{
				throw new Exception( 'Could not access file "' . $fileInfo['tmp_name'] . '"!' );
			}
		}
			
		/**
		 * Returns the size in Bytes of a remote file(URL).
		 * @param $url string, URL of the file.
		 * @param $descriptive boolean, If true the size of URL is converted to descriptive form like 3.45KB, 1.2MB etc.
		 * @return integer/boolean, False if URL is not accessible, size of the file otherwise.
		 */
		private function getRemoteFileSize( $url, $descriptive = false ) 
		{
			$parsed = parse_url( $url );
			
			if( !isset( $parsed['scheme'] ) or !isset( $parsed['host'] ) or !isset( $parsed['path'] ) ) {
				throw new Exception( 'The URL "' . $url . '" seems to be invalid!');
			}
			$host = $parsed["host"];
			$portNumber = 80;
			if( $parsed['scheme'] == 'https' ) {
				$portNumber = 443;
			}
	
			$fp = @fsockopen( $host, $portNumber, $errno, $errstr, 20 );
	
			if( !$fp ) {
				return false;
			} else {
				@fputs( $fp, "HEAD $url HTTP/1.1\r\n" );
				@fputs( $fp, "HOST: $host\r\n" );
				@fputs( $fp, "Connection: close\r\n\r\n" );
				$headers = "";
				while( !@feof( $fp ) ) {
					$headers .= @fgets( $fp, 128 );
				}
			}
	
			@fclose( $fp );
	
			$return = false;
			$arr_headers = explode( "\n", $headers );
			foreach( $arr_headers as $header ) {
				// follow redirect
				$s = 'Location: ';
				if( substr( strtolower( $header ), 0, strlen( $s ) ) == strtolower( $s ) ) {
					$url = trim( substr( $header, strlen( $s ) ) );
					return $this->getRemoteFileSize( $url, $descriptive );
				}
				// parse for content length
				$s = "Content-Length: ";
				if( substr( strtolower( $header ), 0, strlen( $s ) ) == strtolower( $s ) ) {
					$return = trim( substr( $header, strlen( $s ) ) );
					break;
				}
			}
			if( $descriptive ) {
				$size = round( $return / 1024, 2 );
				$sz = "KB"; // Size In KB
				if( $size > 1024 ) {
					$size = round( $size / 1024, 2 );
					$sz = "MB"; // Size in MB
				}
				$return = "$size $sz";
			}
			return $return;
		}
		
		private function getFileExtension( $fileName ) 
		{
			$extension = explode( '.', $fileName );
			return $extension[ count( $extension ) - 1 ];
		}
		
	}
