<?php

namespace App\Http\Controllers\Users;

use Illuminate\Support\Facades\{Validator,
    Auth
};
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Helpers\Common;
use App\Models\{Transaction,
    FeesLimit,
    Transfer,
    Setting,
    Wallet,
    User
};

use Maviance\S3PApiClient\Service\HealthcheckApi;
use Maviance\S3PApiClient\Service\AccountApi;
use Maviance\S3PApiClient\Service\InitiateApi;
use Maviance\S3PApiClient\Service\ConfirmApi;
use Maviance\S3PApiClient\Service\VerifyApi;
use Maviance\S3PApiClient\Service\MasterdataApi;
use Maviance\S3PApiClient\Configuration;
use Maviance\S3PApiClient\ApiClient;
use GuzzleHttp\Client;



class MoneyTransferController extends Controller
{
    protected $helper;
    protected $transfer;

    public function __construct()
    {
        $this->helper   = new Common();
        $this->transfer = new Transfer();
    }

    //Send Money - Email/Phone validation
    public function transferUserEmailPhoneReceiverStatusValidate(Request $request)
    {
        $phoneRegex = $this->helper->validatePhoneInput(trim($request->receiver));
        if ($phoneRegex)
        {
            //Check phone number exists or not
            $user = User::where(['id' => auth()->user()->id])->first(['formattedPhone']);
            if (empty($user->formattedPhone))
            {
                return response()->json([
                    'status'  => 404,
                    'message' => __("Please set your phone number first!"),
                ]);
            }

            //Check own phone number
            if ($request->receiver == auth()->user()->formattedPhone)
            {
                return response()->json([
                    'status'  => true,
                    'message' => __("You Cannot Send Money To Yourself!"),
                ]);
            }

            //Check Receiver/Recipient is suspended/inactive - if entered phone number
            $receiver = User::where(['formattedPhone' => $request->receiver])->first(['status']);
            if (!empty($receiver))
            {
                if ($receiver->status == 'Suspended')
                {
                    return response()->json([
                        'status'  => true,
                        'message' => __("The recipient is suspended!"),
                    ]);
                }
                elseif ($receiver->status == 'Inactive')
                {
                    return response()->json([
                        'status'  => true,
                        'message' => __("The recipient is inactive!"),
                    ]);
                }
            }
        }
        else
        {
            //Check own phone email
            if ($request->receiver == auth()->user()->email)
            {
                return response()->json([
                    'status'  => true,
                    'message' => __("You Cannot Send Money To Yourself!"),
                ]);
            }

            //Check Receiver/Recipient is suspended/inactive - if entered email
            $receiver = User::where(['email' => trim($request->receiver)])->first(['status']);
            if (!empty($receiver))
            {
                if ($receiver->status == 'Suspended')
                {
                    return response()->json([
                        'status'  => true,
                        'message' => __("The recipient is suspended!"),
                    ]);
                }
                elseif ($receiver->status == 'Inactive')
                {
                    return response()->json([
                        'status'  => true,
                        'message' => __("The recipient is inactive!"),
                    ]);
                }
            }
        }
    }

    public function create(Request $request)
    {
        //set the session for validating the action
        setActionSession();
        $url = env('MAVIANCE_URL');
        $secret = env('MAVIANCE_SECRET');
        $token = env('MAVIANCE_PUBLIC');
        $xApiVersion = env('MAVIANCE_VERSION');


        $config = new Configuration();
        $config->setHost($url);
        $client = new ApiClient($token, $secret, ['verify' => false]);


        $data['menu']    = 'send_receive';
        $data['submenu'] = 'send';

        if (!$request->isMethod('post'))
        {
            
            $apiInstance = new MasterdataApi(
                $client, $config
            );

            try {
                $results = $apiInstance->serviceGet($xApiVersion);
                //dd($results);
                $data['items_services'] = $results;
                //dd(json_encode($results));
                //return $result;
            } catch (Exception $e) {
                return $e;
                echo 'Exception when calling AccountApi->accountGet: ', $e->getMessage(), PHP_EOL;
            }

            /*Check Whether Currency is Activated in feesLimit*/
            $data['walletList'] = Wallet::where(['user_id' => auth()->user()->id])
                ->whereHas('active_currency', function ($q)
            {
                    $q->whereHas('fees_limit', function ($query)
                {
                        $query->where('transaction_type_id', Transferred)->where('has_transaction', 'Yes')->select('currency_id', 'has_transaction');
                    });
                })
                ->with(['active_currency:id,code', 'active_currency.fees_limit:id,currency_id']) //Optimized by parvez - for pm v2.3
                ->get(['id', 'currency_id', 'is_default']);

            //check Decimal Thousand Money Format Preference
            $data['preference'] = getDecimalThousandMoneyFormatPref(['decimal_format_amount']);

            return view('user_dashboard.moneytransfer.create', $data);
        }
        else if ($request->isMethod('post'))
        {
           //dd();
            $rules = array(
                'amount'   => 'required|numeric',
                'receiver' => 'required',
                'note'     => 'required',
            );

            $fieldNames = array(
                'amount'   => __("Amount"),
                'receiver' => __("Recipient"),
                'note'     => __("Note"),
            );

            $validator = Validator::make($request->all(), $rules);
            $validator->setAttributeNames($fieldNames);

            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }

            //instantiating message array
            $messages = [];

            // backend Validation - starts
            if ($request->sendMoneyProcessedBy == 'email')
            {
                //check if valid email
                $rules['receiver'] = 'required|email';
            }
            elseif ($request->sendMoneyProcessedBy == 'phone')
            {
                //check if valid phone
                $myStr = explode('+', $request->receiver);
                if ($request->receiver[0] != "+" || !is_numeric($myStr[1]))
                {
                    return back()->withErrors(__("Please enter valid phone (ex: +12015550123)"))->withInput();
                }
            }
            elseif ($request->sendMoneyProcessedBy == 'email_or_phone')
            {
                $myStr = explode('+', $request->receiver);
                //valid number is not entered
                if ($request->receiver[0] != "+" || !is_numeric($myStr[1]))
                {
                    //check if valid email or phone
                    $rules['receiver'] = 'required|email';
                    $messages          = [
                        'email' => __("Please enter valid email (ex: user@gmail.com) or phone (ex: +12015550123)"),
                    ];
                }
            }

            //Own Email or phone validation + Receiver/Recipient is suspended/Inactive validation
            $transferUserEmailPhoneReceiverStatusValidate = $this->transferUserEmailPhoneReceiverStatusValidate($request);
            if ($transferUserEmailPhoneReceiverStatusValidate)
            {
                if ($transferUserEmailPhoneReceiverStatusValidate->getData()->status == true || $transferUserEmailPhoneReceiverStatusValidate->getData()->status == 404)
                {
                    return back()->withErrors(__($transferUserEmailPhoneReceiverStatusValidate->getData()->message))->withInput();
                }
            }

            //Amount Limit Check validation
            $request['wallet_id']           = $request->wallet;
            $request['transaction_type_id'] = Transferred;
            $amountLimitCheck               = $this->amountLimitCheck($request);
            if ($amountLimitCheck->getData()->success->status == 200)
            {
                if ($amountLimitCheck->getData()->success->totalAmount > $amountLimitCheck->getData()->success->balance)
                {
                    return back()->withErrors(__("Not have enough balance."))->withInput();
                }
            }
            else
            {
                return back()->withErrors(__($amountLimitCheck->getData()->success->message))->withInput();
            }
            //backend validation ends
            
            $totalFee = $amountLimitCheck->getData()->success->totalFees;
         
            $wallet                          = Wallet::with(['currency:id,symbol'])->where(['id' => $request->wallet, 'user_id' => auth()->user()->id])->first(['currency_id', 'balance']);
            $request['currency_id']          = $wallet->currency->id;
            $request['currSymbol']           = $wallet->currency->symbol;
            $request['totalAmount']          = $request->amount + $totalFee;
            $request['fee']                  = $totalFee;
            $request['sendMoneyProcessedBy'] = $request->sendMoneyProcessedBy;
            
            session(['transInfo' => $request->all()]);
            $data['transInfo'] = $request->all();

            //dd($data['transInfo']);

            return view('user_dashboard.moneytransfer.confirmation', $data);
        }
    }

    //Send Money - Amount Limit Check
    public function amountLimitCheck(Request $request)
    {
        $amount      = $request->amount;
        $wallet_id   = $request->wallet_id;
        $user_id     = Auth::user()->id;
        $wallet      = Wallet::where(['id' => $wallet_id, 'user_id' => $user_id])->first(['currency_id', 'balance']);
        $currency_id = $wallet->currency_id;
        $feesDetails = FeesLimit::where(['transaction_type_id' => $request->transaction_type_id, 'currency_id' => $currency_id])->first(['max_limit', 'min_limit', 'charge_percentage', 'charge_fixed']);

        //Code for Amount Limit starts here
        if (@$feesDetails->max_limit == null)
        {
            if ((@$amount < @$feesDetails->min_limit))
            {
                $success['message'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit);
                $success['status']  = '401';
            }
            else
            {
                $success['status'] = 200;
            }
        }
        else
        {
            if ((@$amount < @$feesDetails->min_limit) || (@$amount > @$feesDetails->max_limit))
            {
                $success['message'] = __('Minimum amount ') . formatNumber($feesDetails->min_limit) . __(' and Maximum amount ') . formatNumber($feesDetails->max_limit);
                $success['status']  = '401';
            }
            else
            {
                $success['status'] = 200;
            }
        }
        //Code for Amount Limit ends here

        //Code for Fees Limit Starts here
        if (empty($feesDetails))
        {
            $feesPercentage            = 0;
            $feesFixed                 = 0;
            $totalFess                 = $feesPercentage + $feesFixed;
            $totalAmount               = $amount + $totalFess;
            $success['feesPercentage'] = $feesPercentage;
            $success['feesFixed']      = $feesFixed;
            $success['totalFees']      = $totalFess;
            $success['totalFeesHtml']  = formatNumber($totalFess);
            $success['totalAmount']    = $totalAmount;
            $success['pFees']          = $feesPercentage;
            $success['fFees']          = $feesFixed;
            $success['pFeesHtml']      = formatNumber($feesPercentage);
            $success['fFeesHtml']      = formatNumber($feesFixed);
            $success['min']            = 0;
            $success['max']            = 0;
            $success['balance']        = $wallet->balance;
        }
        else
        {
            $feesPercentage            = $amount * ($feesDetails->charge_percentage / 100);
            $feesFixed                 = $feesDetails->charge_fixed;
            $totalFess                 = $feesPercentage + $feesFixed;
            $totalAmount               = $amount + $totalFess;
            $success['feesPercentage'] = $feesPercentage;
            $success['feesFixed']      = $feesFixed;
            $success['totalFees']      = $totalFess;
            $success['totalFeesHtml']  = formatNumber($totalFess);
            $success['totalAmount']    = $totalAmount;
            $success['pFees']          = $feesDetails->charge_percentage;
            $success['fFees']          = $feesDetails->charge_fixed;
            $success['pFeesHtml']      = formatNumber($feesDetails->charge_percentage);
            $success['fFeesHtml']      = formatNumber($feesDetails->charge_fixed);
            $success['min']            = $feesDetails->min_limit;
            $success['max']            = $feesDetails->max_limit;
            $success['balance']        = $wallet->balance;
        }
        //Code for Fees Limit Ends here
        return response()->json(['success' => $success]);
    }

    public function getService($id){
        $url = env('MAVIANCE_URL');
        $secret = env('MAVIANCE_SECRET');
        $token = env('MAVIANCE_PUBLIC');
        $xApiVersion = env('MAVIANCE_VERSION');


        $config = new Configuration();
        $config->setHost($url);
        $client = new ApiClient($token, $secret, ['verify' => false]);


        $apiInstance = new MasterdataApi(
            $client, $config
        );

        try {
            return  $apiInstance->serviceIdGet($xApiVersion, $id);

        } catch (Exception $e) {
            return $e;
            echo 'Exception when calling AccountApi->accountGet: ', $e->getMessage(), PHP_EOL;
        }

    }

    //Send Money - Confirm
    public function sendMoneyConfirm(Request $request)
    {
        $url = env('MAVIANCE_URL');
        $secret = env('MAVIANCE_SECRET');
        $token = env('MAVIANCE_PUBLIC');
        $xApiVersion = env('MAVIANCE_VERSION');


        $config = new Configuration();
        $config->setHost($url);
        $client = new ApiClient($token, $secret, ['verify' => false]);

        $apiInstance = new MasterdataApi(
            $client, $config
        );
        $sessionValue1 = session('transInfo');
        $serviceid = $sessionValue1['pay'];
        //dd($request);
        try {
            $results = $apiInstance->cashinGet($xApiVersion, $serviceid);
            //dd($results[0]['payItemId']);
            if($results){
                $apiInstance_quotestd = new InitiateApi(
                    $client, $config
                );
                
                $bodyAPI = array(
                    'payItemId' => $results[0]['payItemId'],
                    'amount' => $sessionValue1['amount']
                );
                $bodyAPI = json_encode($bodyAPI);
                $quotestd = $apiInstance_quotestd->quotestdPost($xApiVersion, $bodyAPI);
                //dd($quotestd['quoteId']);
                
                //collect if statement
                if($quotestd){
                    $apiInstance_collectstd = new ConfirmApi(
                        $client, $config
                    );
                    //dd(auth()->user());
                   // dd($sessionValue1);
                    //dd($this->getService($sessionValue1['pay']));
                    $body_collectstd = array(
                        'quoteId' => $quotestd['quoteId'],
                        'customerPhonenumber' => substr($sessionValue1['receiver'],4),
                        'customerEmailaddress' => auth()->user()->email,
                        'customerName' => auth()->user()->first_name.' '.auth()->user()->last_name,
                        'customerAddress' => auth()->user()->formattedPhone,
                        'serviceNumber' => substr($sessionValue1['receiver'],4),
                        'trid' => rand(1,100000000)
                    );
                    $body_collectstd = json_encode($body_collectstd);
                    $collectstd = $apiInstance_collectstd->collectstdPost($xApiVersion, $body_collectstd);
                    $data['ptno']    = $collectstd['ptn'];
                    //dd($collectstd['ptn']);
                    //$collectstd['PENDING'];

/*                     if($collectstd){
                        $apiInstance_verifytxGet = new VerifyApi(
                            $client, $config
                        );
                        $ptn = $collectstd['ptn'];
                        $trid = $collectstd['trid'];
                        $verifytxGet = $apiInstance_verifytxGet->verifytxGet($xApiVersion, $ptn);
                        dd($verifytxGet);
                    } */
                }
            }

        } catch (Exception $e) {
            return $e;
            echo 'Exception when calling AccountApi->accountGet: ', $e->getMessage(), PHP_EOL;
        }


        $data['menu']    = 'send_receive';
        $data['submenu'] = 'send';

        $sessionValue = session('transInfo');
        if (empty($sessionValue))
        {
            return redirect('moneytransfer');
        }

        //initializing session
        actionSessionCheck();

        //Data - Wallet Balance Again Amount Check
        $total_with_fee          = $sessionValue['amount'] + $sessionValue['fee'];
        $currency_id             = session('transInfo')['currency_id'];
        $user_id                 = auth()->user()->id;
        $feesDetails             = $this->helper->getFeesLimitObject([], Transferred, $sessionValue['currency_id'], null, null, ['charge_percentage', 'charge_fixed']);
        $senderWallet            = $this->helper->getUserWallet([], ['user_id' => $user_id, 'currency_id' => $currency_id], ['id', 'balance']);
        $p_calc                  = $sessionValue['amount'] * (@$feesDetails->charge_percentage / 100);
        $processedBy             = $sessionValue['sendMoneyProcessedBy'];
        $request_wallet_currency = $sessionValue['currency_id'];
        $unique_code             = unique_code();
        $emailFilterValidate     = $this->helper->validateEmailInput(trim($sessionValue['receiver']));
        $phoneRegex              = $this->helper->validatePhoneInput(trim($sessionValue['receiver']));
        $userInfo                = $this->helper->getEmailPhoneValidatedUserInfo($emailFilterValidate, $phoneRegex, trim($sessionValue['receiver']));
        $arr                     = [
            'emailFilterValidate' => $emailFilterValidate,
            'phoneRegex'          => $phoneRegex,
            'processedBy'         => $processedBy,
            'user_id'             => $user_id,
            'userInfo'            => $userInfo,
            'currency_id'         => $request_wallet_currency,
            'uuid'                => $data['ptno'],
            'fee'                 => $sessionValue['fee'],
            'amount'              => $sessionValue['amount'],
            'note'                => trim($sessionValue['note']),
            'receiver'            => trim($sessionValue['receiver']),
            'charge_percentage'   => $feesDetails->charge_percentage,
            'charge_fixed'        => $feesDetails->charge_fixed,
            'p_calc'              => $p_calc,
            'total'               => $total_with_fee,
            'senderWallet'        => $senderWallet,
        ];
        $data['transInfo']['receiver']   = $sessionValue['receiver'];
        $data['transInfo']['currSymbol'] = $sessionValue['currSymbol'];
        $data['transInfo']['amount']     = $sessionValue['amount'];
        $data['transInfo']['pay']        = $sessionValue['pay'];
        $data['userPic']                 = isset($userInfo) ? $userInfo->picture : '';
        $data['receiverName']            = isset($userInfo) ? $userInfo->first_name . ' ' . $userInfo->last_name : '';

        //Get response
        $response = $this->transfer->processSendMoneyConfirmation($arr, 'web');
        if ($response['status'] != 200)
        {
            if (empty($response['transactionOrTransferId']))
            {
                session()->forget('transInfo');
                $this->helper->one_time_message('error', $response['ex']['message']);
                return redirect('moneytransfer');
            }
            $data['errorMessage'] = $response['ex']['message'];
        }
        $data['transInfo']['trans_id'] = $response['transactionOrTransferId'];

        //clearing session
        session()->forget('transInfo');
        clearActionSession();
        return view('user_dashboard.moneytransfer.success', $data);
    }

    //Send Money - Generate pdf for print
    public function transferPrintPdf($trans_id)
    {
        $data['companyInfo']        = Setting::where(['type' => 'general', 'name' => 'logo'])->first(['value']);
        $data['transactionDetails'] = Transaction::with(['end_user:id,first_name,last_name', 'currency:id,symbol,code'])
            ->where(['id' => $trans_id])
            ->first(['transaction_type_id', 'end_user_id', 'currency_id', 'uuid', 'created_at', 'status', 'subtotal', 'charge_percentage', 'charge_fixed', 'total', 'note']);

        $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/tmp']);
        $mpdf = new \Mpdf\Mpdf([
            'mode'                 => 'utf-8',
            'format'               => 'A3',
            'orientation'          => 'P',
            'shrink_tables_to_fit' => 0,
        ]);
        $mpdf->autoScriptToLang         = true;
        $mpdf->autoLangToFont           = true;
        $mpdf->allow_charset_conversion = false;
        $mpdf->SetJS('this.print();');
        $mpdf->WriteHTML(view('user_dashboard.moneytransfer.transferPaymentPdf', $data));
        $mpdf->Output('sendMoney_' . time() . '.pdf', 'I'); // this will output data
    }
}
