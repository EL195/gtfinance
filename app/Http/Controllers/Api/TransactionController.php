<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Users\EmailController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\{Preference,
    Transaction,
    User
};
use App\Libraries\Playlist;


use Maviance\S3PApiClient\Service\HealthcheckApi;
use Maviance\S3PApiClient\Service\AccountApi;
use Maviance\S3PApiClient\Service\InitiateApi;
use Maviance\S3PApiClient\Service\ConfirmApi;
use Maviance\S3PApiClient\Service\VerifyApi;
use Maviance\S3PApiClient\Service\MasterdataApi;
use Maviance\S3PApiClient\Configuration;
use Maviance\S3PApiClient\ApiClient;
use GuzzleHttp\Client;


class TransactionController extends Controller
{
    public $successStatus      = 200;
    public $unauthorisedStatus = 401;
    public $email;

    public function __construct()
    {
        $this->email = new EmailController();
    }

    public function getCheckPayStatusApi(Request $request){
        $paiements = Transaction::where('uuid', $request->ptn)->get()[0];
        //$paiements = Transaction::where('status', "Pending")->get();

        //return $paiements;
        if($request->status=="SUCCESS"){
            //return $paiements->id;
            $paiement = Transaction::query()->find($paiements->id);
            $paiement->status = 'Success';
            $paiement->update();
        }
        else{
            //return $paiements->id;
            $paiement = Transaction::query()->find($paiements->id);
            $paiement->status = "Pending";
            $paiement->update();
        }
        return response()->json(['success' => "Success", "result"=>$paiement]);

    }


    public function getTestApi(){
        $url ="https://s3p.smobilpay.staging.maviance.info/v2";
        $secret = "529093B1-68C1-D8E6-98AB-4711196E0AA0";
        $token = "EAEE1044-DA6B-0914-C4F3-3C705391A425";
        $xApiVersion = "3.0.0";
        $config = new Configuration();
        $config->setHost($url);
        $client = new ApiClient($token, $secret, ['verify' => false]);
        //return response()->json(['success' => "Success", 'Results' => $result]);
/*
        $apiInstance = new HealthcheckApi(
            $client, $config
        );
        

        $apiInstance = new AccountApi(
            $client, $config
        );
*/

        $apiInstance = new MasterdataApi(
            $client, $config
        );
        
        $serviceid = "20052";
        try {
            //$result = $apiInstance->pingGet($xApiVersion);
            //$result = $apiInstance->accountGet($xApiVersion);
            //$result = $apiInstance->serviceGet($xApiVersion);
            //$result = $apiInstance->cashinGet($xApiVersion, $serviceid);
            $result = $apiInstance->cashinGet($xApiVersion, $serviceid);
            return response()->json(['success' => "Success", 'Results' => $result]);
            //print_r($result);
            //return $result;
        } catch (Exception $e) {
            return $e;
            echo 'Exception when calling AccountApi->accountGet: ', $e->getMessage(), PHP_EOL;
        }


    }



    public function getTransactionApi()
    {
        if (request('type') && request('user_id'))
        {
            $type    = request('type');
            $user_id = request('user_id');

            $transaction  = new Transaction();
            $transactions = $transaction->getTransactionLists($type, $user_id);

            $success['status'] = $this->successStatus;
            return response()->json(['success' => $success, 'transactions' => $transactions], $this->successStatus);
        }
        else
        {
            echo "In else block";exit();return false;
        }
    }

    public function getTransactionDetailsApi()
    {
        if (request('user_id'))
        {
            $user_id           = request('user_id');
            $tr_id             = request('tr_id');
            $transaction       = new Transaction();
            $transaction       = $transaction->getTransactionDetails($tr_id, $user_id);
            $success['status'] = $this->successStatus;
            return response()->json(['success' => $success, 'transaction' => $transaction], $this->successStatus);
        }
        else
        {
            echo "In else block";exit();return false;
        }
    }
}
