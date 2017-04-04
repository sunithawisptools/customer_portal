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
        $trans_data=$this->complexSearch("payments","2017-03-14 00:00:00","2017-03-16 00:00:00");
         foreach($trans_data as $temp)
             {
                 if($temp)
                 {
                     echo "</br>";
                     echo "Amount :".$temp->amount;
                   $sum=$sum+$temp->amount;
                 }
             }
        echo "</br>";
        echo "SUM: ".$sum;
        $this->createQuickbookEntry($sum);
    
    }
    public function createQuickbookEntry($sum)
    {
//        $homepage=  file_get_contents("http://192.168.0.104/test/sunitha/oauth-php-master/v3-php-sdk-2.2.0-RC/_Samples/createJournal.php?amt=".$sum);
//        echo $homepage;
        $ch=  curl_init();
        $url="http://192.168.0.104/test/sunitha/oauth-php-master/v3-php-sdk-2.2.0-RC/_Samples/createJournal.php";
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "amt=".$sum);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $output = curl_exec ($ch); 
        curl_close ($ch); 
        var_dump($output); 

    }
}

