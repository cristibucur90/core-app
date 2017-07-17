<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use App\Prediction;

class PredictionTableSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $predictions = [
            '1x2:team_1:Team 1',
            '1x2:team_2:Team 2',
            '1x2:equal:Equal',
            '1x2:team_1_ht:Team 1 HT',
            '1x2:team_2_ht:Team 2 HT',
            '1x2:equal_ht:Equal HT'
        ];

        foreach ($predictions as $prediction) {
            $group = explode(':', $prediction)[0];
            $identifier = explode(':', $prediction)[1];
            $name = explode(':', $prediction)[2];

            Prediction::firstOrCreate([
                'identifier' => $identifier,
                'name'       => $name,
                'group'      => $group
            ]);
        }
    }
}
