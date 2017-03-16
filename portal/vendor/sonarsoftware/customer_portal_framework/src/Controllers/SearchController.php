<?php

namespace SonarSoftware\CustomerPortalFramework\Controllers;

use SonarSoftware\CustomerPortalFramework\Helpers\HttpHelper;
use SonarSoftware\CustomerPortalFramework\Models\Contract;

class SearchController
{
    private $httpHelper;
    /**
     * AccountAuthenticationController constructor.
     */
    public function __construct()
    {
        $this->httpHelper = new HttpHelper();
    }

 
  
    public function complexSearch($entity,$size,$page,$from_date,$to_date)
    {  
        $post_data = array(
	  'size' => $size,
	  'page' => $page,
	  'search'=> array(
	    "date_limits" => array(
	      array(
	        "field" => "datetime",
		"from" => $from_date,
		"to" => $to_date
	      )
	    )
	  )
	);
        $result = $this->httpHelper->post("/search/". $entity, $post_data);
        return $result;
    }

}
