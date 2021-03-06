<?php

class Instapress_Core_GBlogSearch
{
	private $searchUrl;
	private $totalResults = 0;
	
	public function __construct( )
	{}

	public function getBacklinks($url = '')
	{
		//$this->userip = $userip;
		$this->searchUrl = $url;
		if( $url )
		{
			$backlinks = $this->getInfo();
			return $backlinks;
		}
		return 0;
	}
	
	private function getInfo()
	{
		if( !$this->searchUrl )
		{
			throw new Exception( 'Empty search URL found!' );
		}
		//$resp = file_get_contents( "http://ajax.googleapis.com/ajax/services/search/blogs?q=".$this->searchUrl."&v=1.0&userip=".$this->userip );
		$resp = file_get_contents( "http://ajax.googleapis.com/ajax/services/search/blogs?q=".$this->searchUrl."&v=1.0" );
		if( $resp === false )
		{
			throw new Exception( 'Invalid URL or IP address!' );
		}
		$resp = json_decode($resp,true);
		$this->totalResults = $resp['responseData']['cursor']['estimatedResultCount'];
		return $this->totalResults;
	}
}
?>