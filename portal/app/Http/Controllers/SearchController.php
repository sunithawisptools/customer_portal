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
        $details= $this->apiController->complexSearch("accounts");
       print_r($details);
    
      
    }
    public function searchPayment()
    {
        $details= $this->apiController->complexSearch("invoices");
       print_r($details);
    
    }
    public function searchTransaction()
    {
        $details=$this->apiController->getAccountPayment(1);
        print_r($details);
    }
     public function paymentCalculation()
    {
        $account_details= $this->apiController->complexSearch("accounts");
        $account=json_encode($account_details->results);
        echo "</br>";
        $account_json = json_decode($account, true);
        $i=0;
        foreach($account_json as $values)
        {
            if($values)
            {
            echo "Name : ".$account_json[$i]["name"];
            echo "<br/>";
            echo "Account Id : ".$account_json[$i]["id"];
            echo "<br/>";
            $id=$account_json[$i]["id"];
            $trans_details=$this->apiController->getAccountPayment($id);
            if($trans_details)
                {
                   $trans=json_encode($trans_details);
                   $trans_json = json_decode($trans, true);
                   $sum=0;
                   echo "<h3>Transactions</h3>";
                   $num=1;
                   foreach($trans_json as $data)
                   {
                       echo $num.".  $".$data["amount"];
                       echo "  (".$data["date"].")";
                       $sum=$sum +$data["amount"];
                       $num++;
                       echo "<br/>";
                   }
                   echo "TOTAL PAYMENT :$".$sum;
                   echo "</br>";
                }
            else 
                {
                   echo "<h3>No Transactions</h3>";
                }
                echo "</br>";
                echo "---------------------------------";
                echo "</br>";
            $i++;
            
            
            }
        }
        return;
      
    }
}
