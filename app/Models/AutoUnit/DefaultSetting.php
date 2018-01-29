<?php namespace App\Models\AutoUnit;

use Illuminate\Database\Eloquent\Model;

class DefaultSetting extends Model {

    protected $table = 'auto_unit_default_setting';

    protected $fillable = [
        'siteId',
        'tipIdentifier',
        'minOdd',
        'maxOdd',
        'win',
        'loss',
        'draw',
        'winrate',
    ];

//    protected $hidden = [ ‘password’ ];
}

