<?php namespace App;

use Illuminate\Database\Eloquent\Model;

class PackagePredictionGroup extends Model {

    public $timestamps = false;
    protected $table = 'package_prediction_group';

    protected $fillable = [
        'packageId',
        'predictionGroup',
    ];

//    protected $hidden = [ ‘password’ ];
}
