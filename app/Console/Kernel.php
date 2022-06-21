<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Transaction;
use Maviance\S3PApiClient\Service\HealthcheckApi;
use Maviance\S3PApiClient\Service\AccountApi;
use Maviance\S3PApiClient\Service\InitiateApi;
use Maviance\S3PApiClient\Service\ConfirmApi;
use Maviance\S3PApiClient\Service\VerifyApi;
use Maviance\S3PApiClient\Service\MasterdataApi;
use Maviance\S3PApiClient\Configuration;
use Maviance\S3PApiClient\ApiClient;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $url = env('MAVIANCE_URL');
        $secret = env('MAVIANCE_SECRET');
        $token = env('MAVIANCE_PUBLIC');
        $xApiVersion = env('MAVIANCE_VERSION');


        $config = new Configuration();
        $config->setHost($url);
        $client = new ApiClient($token, $secret, ['verify' => false]);

        $apiInstance_verifytxGet = new VerifyApi(
            $client, $config
        );

        $schedule->job(function(){
        print_r("verification");
        $paiements = Transaction::where('status', "Pending")->get();
        foreach ($paiements as $pay) {
            //print_r($pay);
            $ptn = $pay->uuid;
            print_r($ptn);
            $verifytxGet = $apiInstance_verifytxGet->verifytxGet($xApiVersion, $ptn);
            print_r($verifytxGet);
            if($verifytxGet['status']=="SUCCESS"){
                $paiement = Transaction::query()->find($pay->id);
                $paiement->status = "Success";
                $paiement->update();
            }
        }
        })->everyMinute()->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
