<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class DefaultSetting extends Model {

    protected $table = 'auto_unit_default_setting';

    protected $fillable = [
        'siteId',
        'tipIdentifier',
        'tableIdentifier',
        'minOdd',
        'maxOdd',
        'win',
        'loss',
        'draw',
        'prediction1x2',
        'predictionOU',
        'predictionAH',
        'predictionGG',
        'winrate',
        'configType',
        'minTips',
        'maxTips',
        'tipsPerDay',
    ];

//    protected $hidden = [ ‘password’ ];
}

