<?php


class Instapress_Core_GAnalytics
{
	private $_objAnalytics = null;

	public function __construct( $clientEmail, $clientPassword )
	{
//		define('ga_email',$clientEmail );
//		define('ga_password',$clientPassword );
		require_once(LIB_PATH. 'Instapress/Core/Google/gapi.php');
//		$this->_objAnalytics = new gapi(ga_email, ga_password);
		$this->_objAnalytics = new gapi($clientEmail, $clientPassword);
	}

	/*
	 *  Returns an associative array of requested data from google analytics  
	 */
	public function getSiteData( $profileId,$startDate,$endDate )
	{
		$this->_objAnalytics->requestReportData($profileId,array('visitCount'),
		array('visits','pageviews','uniquePageviews','bounces','timeOnSite','exits','entrances','newVisits'),
							'-uniquePageviews',null,$startDate,$endDate);
		// visits
		$visits = $this->_objAnalytics->getvisits();
		//Pageviews
		$pageviews = $this->_objAnalytics->getPageviews();
		// uniquePageviews
		$uniquePageviews = $this->_objAnalytics->getuniquePageviews();
		// Bounces
		$bounces = $this->_objAnalytics->getbounces();
		// timeOnSite
		$time = $this->_objAnalytics->gettimeOnSite();
		// exits
		$exits = $this->_objAnalytics->getexits();
		// entrances
		$entrances = $this->_objAnalytics->getentrances();
		// newVisits
		$newVisits = $this->_objAnalytics->getnewVisits();
		/*
		 *  Calculations for "Bounce Rate","Exit Rate","Avg time On Site","Pages/Visits","%newVisits".
		 */
		// BounceRate
		if( $entrances > 0 )
		{
			$br=round( ( ( $bounces/$entrances )*100 ), 2 );
		}
		else
		{
			$br = 0;
		}

		// Avg time On Site
		if( $exits > 0 )
		{
			$secs = round( $time/$exits );
		}
		else
		{
			$secs = 0;
		}

		// Pages/Visits
		if( $visits > 0 )
		{
			$ppv = round( ( $pageviews/$visits ), 2 );
		}
		else
		{
			$ppv = 0;
		}
			
		// % New Visits
		if( $visits > 0 )
		{
			$nv=round( ( ( $newVisits/$visits )*100 ), 2 );
		}
		else
		{
			$nv = 0;
		}

		/*
		 *  array of site data
		 */
		$siteInfo = array();
		$siteInfo['visits'] = $visits;
		$siteInfo['pageViews'] = $pageviews;
		$siteInfo['uniquePageviews'] = $uniquePageviews;
		$siteInfo['bounceRate'] = $br;
		$siteInfo['avgTime'] = $secs;
		$siteInfo['pagesPerVisit'] = $ppv;
		$siteInfo['newVisitsPercentage'] = $nv;

		return $siteInfo;
	}

	
	/*
	 *  Returns an associative array of requested data for permalink( top content )
	 */
	public function getPermalinkData( $profileId, $startDate, $endDate, $start, $quantity )
	{
		$this->_objAnalytics->requestReportData( $profileId, array('hostname','pagePath'),
												 array('visits','pageviews','uniquePageviews','bounces','timeOnPage','exits','entrances'),
												 '-pageviews', null, $startDate,$endDate, $start, $quantity );
		$arr = array();
		foreach($this->_objAnalytics->getResults() as $result){
			$hostName=$result->getHostName();
			$pagePath = $result->getPagePath();
            $visits=$result->getvisits();
			$pv=$result->getpageviews();
			$uv=$result->getuniquePageviews();
			$bounce=$result->getbounces();
			$time=$result->gettimeOnPage();
			$exits=$result->getexits();
			$entrance=$result->getentrances();

			// Avg Time on site in seconds
			if( $exits > 0 ){
				$avg=$time/$exits;
				$avgT=round($avg);
				$secs = $avgT;
			}else{
				$sec = 0;
			}

			// Bounce Rate
			if($entrance>0){
				$br=round((($bounce/$entrance)*100),2);
			}else{
				$br = 0;
			}
			$pagesPerVisit = 0;
			if( $visits > 0 ){
				$pagesPerVisit = round( $pv/$visits, 2 );
			}
			/*$arr[$pagePath] = array("pageViews"=>$pv,
									"pagesPerVisit"=>$pagesPerVisit,
  									"uniquePageViews"=>$uv,
  									"avgTime"=>$secs,
  									"bounceRate"=>$br);	*/
			$arr[] = array( "hostName"=>$hostName,
							"slug"=>$pagePath,
							"pageViews"=>$pv,
							"uniquePageViews"=>$uv,
  							"avgTime"=>$secs,
  							"bounceRate"=>$br);	
		}
		return $arr;
	}

	public function getTrafficData( $profileId, $mediumType = '(none)', $startDate, $endDate )
	{
		$filter = 'medium == ' . $mediumType;
		$this->_objAnalytics->requestReportData( $profileId, array('source','medium'), array( 'visits', 'pageviews' ),
							'-Visits',$filter,$startDate,$endDate);
		// visits
		$visits = $this->_objAnalytics->getvisits();
		//Pageviews
		$pageviews = $this->_objAnalytics->getPageviews();
		
		$siteInfo = array();
		$siteInfo['visits'] = $visits;
		$siteInfo['pageViews'] = $pageviews;

		return $siteInfo;
	}
	
	/*
	 * Traffic Information
	 * Returns the requested data for differnt traffic sources from the google analytics
	 */
	public function getTrafficDetail( $profileId, $startDate, $endDate, $start, $quantity )
	{
		/*$this->_objAnalytics->requestReportData( $profileId,array('source','medium'),
														  array('visits','pageviews','bounces','timeOnSite','exits','entrances'),
														  '-Visits',null,$startDate,$endDate, $start, $quantity );*/
		$this->_objAnalytics->requestReportData( $profileId,array('source','medium'),
														  array( 'visits', 'pageviews' ),
														  '-Visits',null,$startDate,$endDate, $start, $quantity );
		$trafficDetails = array();
		foreach($this->_objAnalytics->getResults() as $result){
			$source_medium = $result->__tostring();
			$sm = explode(" ", $source_medium);
			$source = $sm[0];
			$medium = $sm[1];
			
			// page views
			$pageviews = $result->getpageviews();	
			
			// visits
			$visits = $result->getvisits();
						
			//bounces
			// $bounces = $result->getbounces();
			// time on page
			// $time = $result->gettimeOnSite();
			// exits
			// $exits = $result->getexits();
			// entrances
			// $entrances = $result->getentrances();

			// Average Time
			/*if($exits>0){
				$avg = $time/($exits);
				$avgT = round($avg);
				$secs = $avgT;
			}else{
				$secs = 0;
			}*/

			// Bounce Rate
			/*if($entrances>0){
				$bouncerate = round((($bounces/$entrances)*100),2);
			}else{
				$bouncerate = 0;
			}*/

			// Pages per Visits
			/*if($visits>0){
				$pagesPerVisit = round(($pageviews/$visits),2);
			}else{
				$pagesPerVisit = 0;
			}*/
			/*
			$trafficDetails[] = array( "source" => $source,
									   "medium" => $medium,
									   "visits" => $visits,
									   "pageviews"=>$pageviews,
	  								   "pagesPerVisits" => $pagesPerVisit,
	  								   "avgTime" => $secs,
									   "bounceRate" => $bouncerate );
			*/
			$trafficDetails[] = array("source" => $source,
									  "medium" => $medium,
									  "visits" => $visits,
									  "pageviews"=>$pageviews );
		}
		return $trafficDetails;
	}
	
	/*
	 * returns the total no. of records from analytics
	 */
	public function getTotalResults(){
		return $this->_objAnalytics->getTotalResults();
	}	
}
?>
