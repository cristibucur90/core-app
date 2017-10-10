<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\PackagePrediction;

class AppResultStatusTableSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        AppResultStatus::firstOrCreate([
            'statusName' => 'Win',
        ]);
        AppResultStatus::firstOrCreate([
            'statusName' => 'Loss',
        ]);
        AppResultStatus::firstOrCreate([
            'statusName' => 'Draw',
        ]);
        AppResultStatus::firstOrCreate([
            'statusName' => 'PostP',
        ]);
    }
}