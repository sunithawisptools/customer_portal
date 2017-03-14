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

 
  
    public function complexSearch($entity)
    {
        $result = $this->httpHelper->post("/search/" . $entity,['size'=>100,'page'=>1]);
        return $result;
    }
}
