<?php

namespace Database\Seeders;

use App\Models\AppStoreCredentials;
use Illuminate\Database\Seeder;

class AppStoreCredentialsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AppStoreCredentials::truncate();
        AppStoreCredentials::insert([
            ['id' => 1, 'has_app_credentials' => 'Yes', 'link' => 'https://play.google.com/store/apps/details?id=io.gtfinance.cm', 'logo' => 'playstore.png', 'company' => 'Google'],
            ['id' => 2, 'has_app_credentials' => 'Yes', 'link' => 'https://play.google.com/store/apps/details?id=io.gtfinance.cm', 'logo' => '1531134592.png', 'company' => 'Apple'],
        ]);
    }
}
