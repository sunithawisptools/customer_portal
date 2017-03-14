<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;

class SearchController extends Controller
{
    private $apiController;
    public function __construct()
    {
        $this->apiController = new \SonarSoftware\CustomerPortalFramework\Controllers\SearchController();
    }

//    public function index()
//    {
//        /**
//         * This is not cached, as signing a contract outside the portal cannot be detected, and so would create invalid information display here.
//         */
//        $contracts = $this->apiController->getContracts(get_user()->account_id, 1);
//        return view("pages.contracts.index",compact('contracts'));
//    }

    /**
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function searchAccount()
    {
        $details= $this->apiController->complexSearch("accounts");
        $det=json_encode((array)$details->results);
        echo $det;
        return;
      
    }
    public function searchPayment()
    {
        $details= $this->apiController->complexSearch("invoices");
        $det=json_encode((array)$details->results);
        echo $det;
        return;
    
      
    }
}
