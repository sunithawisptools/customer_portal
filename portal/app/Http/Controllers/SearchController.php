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
    public function complexSearch($entity,$from_date,$to_date)
    {
        $trans_array=array();
        $size=1;
        $page=1;
        $data= $this->apiController->complexSearch($entity,$size,$page,$from_date,$to_date);
        $data_values=$data->results;
        print_r($data_values);
        $page_values=$data->paginator;
        $total_page=$page_values->total_pages;
        foreach($data_values as $temp)
        {
            $trans_array[]=$temp;
           
        }
        if($total_page>1)
        {
            for($i=2;$i<=$total_page;$i++)
            {
                $data= $this->apiController->complexSearch($entity,$size,$i,$from_date,$to_date);
                $data_values=$data->results;
                foreach($data_values as $temp)
                 {
                   $trans_array[]=$temp;
                 }
            }
        }
        
        return $trans_array;
       
    }
    public function paymentCalculation()
    {
        
        $sum=0;
        $sum_array=array();
        $date=date('Y-m-d');
        $prev_date = date('Y-m-d', strtotime($date .' -1 day'));
        $trans_data=$this->complexSearch("payments",$prev_date." 00:00:00",$prev_date." 23:59:59");
        foreach($trans_data as $temp)
        {
            if($temp){
          $sum_array[$temp->type]=0;
            }
        }
        print_r($sum_array);
        foreach($trans_data as $temp)
             {
                 if($temp)
                 {
                         $sum_array[$temp->type]=$sum_array[$temp->type]+$temp->amount; 
                 }
             }
            echo "</br>";
            foreach ($sum_array as $key => $value) {
            echo $key."_SUM: ".$value;
            $this->createQuickbookEntry($value);
        }
        
       
    
    }
    public function createQuickbookEntry($sum)
    {
        $ch=  curl_init();
        $date=date('Y-m-d');
        $data_array=array('amt'=>$sum,'date'=>$date);
        $url="http://{Oauth_url}/oauth-php-master/createJournal.php";
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_array);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec ($ch); 
        curl_close ($ch); 
        echo "</br>";
        var_dump($output); 

    }
}

