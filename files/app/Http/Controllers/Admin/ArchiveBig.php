<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ArchiveBig extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /*
     * @return array()
     */
    public function index()
    {
    }

    public function get() {}


    // @param integer $siteId
    // @param string $table
    // @param string $date
    // @return array()
    public function getMonthEvents(Request $r)
    {
        $siteId = $r->input('siteId');
        $tableIdentifier = $r->input('tableIdentifier');
        $date = $r->input('date');

        return \App\ArchiveBig::where('siteId', $siteId)
            ->where('tableIdentifier', $tableIdentifier)
            ->where('systemDate', '>=', $date . '-01')
            ->where('systemDate', '<=', $date . '-31')->get()->toArray();
    }

    // get array with available years and month based on archived events.
    // @return array()
    public function getAvailableMounths()
    {
        $dates = [];

        $first = \App\ArchiveBig::select(\DB::raw('min(systemDate) as systemDate'))->get()[0]->systemDate;
        $last = \App\ArchiveBig::select(\DB::raw('max(systemDate) as systemDate'))->get()[0]->systemDate;

        $start    = (new \DateTime($first))->modify('first day of this month');
        $end      = (new \DateTime($last))->modify('first day of next month');
        $interval = \DateInterval::createFromDateString('1 month');
        $period   = new \DatePeriod($start, $interval, $end);

        foreach ($period as $dt) {
            $dates[] = $dt->format("Y-m");
        }

        return array_reverse($dates);
    }

    public function store() {}

    public function update() {}

    public function destroy() {}

}