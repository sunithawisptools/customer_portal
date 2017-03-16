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

    /**
     * @param $id
     * @return \Illuminate\Http\Response
     */
    public function searchAccount()
    {
        $size=100;
        $page=1;
        $details= $this->apiController->complexSearch("accounts",$size,$page);
        print_r($details);
    
      
    }
    public function paymentCalculation()
    {
        $size=2;
        $page=1;
        $from_date="2017-03-14 00:00:00";
        $to_date="2017-03-16 00:00:00";
        $data= $this->apiController->complexSearch("payments",$size,$page,$from_date,$to_date);
        $data_values=$data->results;
        $page_values=$data->paginator;
        $total_page=$page_values->total_pages;
        $sum=0;
        $page=$page_values->current_page;
        for($i=1;$i<=$total_page;$i++)
        {
             $page=$i;
             $payment_data= $this->apiController->complexSearch("payments",$size,$page,$from_date,$to_date);
             $payment_values=$payment_data->results;
             echo "<h4>page=".$page."</h4>";
             foreach($payment_values as $temp)
             {
                 if($temp)
                 {
                     echo "</br>";
                     echo "Amount :".$temp->amount;
                   $sum=$sum+$temp->amount;
                 }
             }
        }
        echo "</br>";
        echo "</br>";
        echo "<h4>Sum= ".$sum."</h4>";
        
    }

}
