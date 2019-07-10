<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AssociationModel;
use Illuminate\Http\Request;
use App\Models\AutoUnit\MonthlySetting;
use App\Models\AutoUnit\DailySchedule;
use Illuminate\Support\Facades\DB;

class Association extends Controller
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

    /* @param string $tableIdentifier run|ruv|nun|nuv
     * @param string $date format: Y-m-d | 0 | null
     *     - $date = 0 | null => current date GMT
     * @return array()
     */
    public function index($tableIdentifier, $date)
    {
        if ($date === null || $date == 0)
            $date = gmdate('Y-m-d');

        $associations = \App\Association::where('type', $tableIdentifier)->where('systemDate', $date)->get();
        foreach ($associations as $association) {

            $unique = [];
            $count = 0;

            $association->status;
            $distributions = \App\Distribution::where('associationId', $association->id)->get();
            foreach ($distributions as $e) {
                if (array_key_exists($e->siteId, $unique))
                    if (array_key_exists($e->tipIdentifier, $unique[$e->siteId]))
                        continue;

                $unique[$e->siteId][$e->tipIdentifier] = true;
                $count++;
            }
            $association->distributedNumber = $count;
        }

        return $associations;
    }

    public function get() {}

    // get available packages according to table and event prediction
    // @param string  $table
    // @param integer $associateEventId
    // @param string | null $date
    // @return array();
    public function getAvailablePackages(Request $request, $table, $associateEventId, $type, $date = null, $previousSites = [])
    {
        $data = [];
        $ineligiblePackageIds = [];
        $date = ($date === null) ? gmdate('Y-m-d') : $date;

        $data['event'] = \App\Association::find($associateEventId);
        $isVip = 0;
        $sites = \App\Site::when($request->limit != null, function($query) use ($request) {
            return $query->limit($request->limit);
        })->when($request->offset, function($query) use ($request) {
            return $query->offset($request->offset);
        })->get()->toArray();

        $siteIds = array_column($sites, "id");

        if (!$data['event'])
            return response()->json([
                "type" => "error",
                "message" => "Event id: $associateEventId not exist anymore!"
            ]);

        // first get packagesIds acording to section
        $section = ($table === 'run' || $table === 'ruv') ? 'ru': 'nu';

        $packageSection = \App\PackageSection::select('packageId')
            ->join("package", "package.id", "package_section.packageId")
            ->where('section', $section)
            ->where('systemDate', $date)
            ->whereIn("package.siteId", $siteIds)
            ->get();
        
        $packagesIds = [];
        foreach ($packageSection as $p)
            $packagesIds[] = $p->packageId;

        // only vip or normal package according to table
        foreach ($packagesIds as $k => $id) {
            // table is vip exclude normal packages
            if ($table == "ruv" || $table == "nuv") {
                $isVip = 1;
                if (\App\Package::where('id', $id)->where('isVip', '0')->count())
                    unset($packagesIds[$k]);
                continue;
            }

            // table is normal exclude vip packages
            if (\App\Package::where('id', $id)->where('isVip', '1')->count())
                unset($packagesIds[$k]);
        }

        // sort by event type tip or noTip
        foreach ($packagesIds as $k => $id) {

            // event is no tip -> exclude packages who have tip events
            if ($data['event']->isNoTip) {
                $hasEvents = \App\Distribution::where('packageId', $id)
                    ->where('systemDate', $date)
                    ->where('isNoTip', '0')->count();

                if ($hasEvents)
                    unset($packagesIds[$k]);
                continue;
            }

            // there is event unset packages hwo has noTip
            $hasNoTip = \App\Distribution::where('packageId', $id)
                ->where('systemDate', $date)
                ->where('isNoTip', '1')->count();

            if ($hasNoTip) {
                unset($packagesIds[$k]);
                continue;
            }

            // there is event unset packages not according to betType
            $packageAcceptPrediction = \App\PackagePrediction::where('packageId', $id)
                ->where('predictionIdentifier', $data['event']->predictionId)
                ->count();

            if (! $packageAcceptPrediction) {
                $ineligiblePackageIds[] = $packagesIds[$k];
                unset($packagesIds[$k]);
            }
        }

        // Now $packagesIds contain only available pacakages

        $keys = [];
        $increments = 0;

        $packages = \App\Package::select(
                "package.*",
                DB::raw("
                    (
                        SELECT COUNT(distribution.id)
                        FROM distribution
                        WHERE distribution.packageId = package.id
                        AND distribution.systemDate = '" . $date . "'
                    ) - package.tipsPerDay AS tipsDifference
                "),
                DB::raw("EXISTS 
                    (
                        SELECT auto_unit_daily_schedule.id
                        FROM auto_unit_daily_schedule
                        WHERE auto_unit_daily_schedule.siteId = package.siteId
                        AND auto_unit_daily_schedule.systemDate = '" . $date . "'
                        AND package.paused_autounit = 0
                        GROUP BY auto_unit_daily_schedule.siteId
                    ) AS auConfigured
                ")
            )
            ->when($type == "auFilled" || $type == "filled", function($query) {
                return $query->having("tipsDifference", ">=", 0);
            })
            ->when($type == "auUnfilled" || $type == "unfilled", function($query) {
                return $query->having("tipsDifference", "<", 0);
            })
            ->when($type == "filled" || $type == "unfilled", function($query) {
                return $query->where("package.paused_autounit", "=", 1);
            })
            ->when($type == "auFilled" || $type == "auUnfilled", function($query) {
                return $query->where("package.paused_autounit", "=", 0);
            })
            ->whereIn('id', $packagesIds)
            ->orderBy('tipsDifference', 'ASC')
            ->get();
    
        $todayYM = gmdate("Y-m");
        $data["sites"] = $previousSites;

        if ($type == "inelegible") {
            $data['sites'] = array_merge($data['sites'], Association::getUnAvailablePackages($siteIds, $data, $date, $isVip, $section, $data['event']));
        } else {
            foreach ($packages as $p) {
                $site = \App\Site::find($p->siteId);
                // create array
                if (!array_key_exists($site->name, $keys)) {
                    $keys[$site->name] = $increments;
                    $increments++;
                }

                // check if event alredy exists in tips distribution
                $distributionExists = \App\Distribution::where('associationId', $data['event']->id)
                    ->where('packageId', $p->id)
                    ->count();

                // get number of associated events with package on event systemDate
                $eventsExistsOnSystemDate = \App\Distribution::where('packageId', $p->id)
                    ->where('systemDate', $date)
                    ->count();
                
                $autounit = MonthlySetting::where("siteId", "=", $p->siteId)
                    ->where("tipIdentifier", "=", $p->tipIdentifier)
                    ->where("tableIdentifier", "=", $p->tableIdentifier)
                    ->where("date", "=", $todayYM)
                    ->first();

                if (
                    $autounit &&
                    (float)$data['event']->odd >= (float)$autounit->minOdd && 
                    (float)$data['event']->odd <= (float)$autounit->maxOdd
                ) {
                    if ($type == "auFilled" || $type == "auUnfilled" || $type == "filled" || $type == "unfilled") {
                        $this->mapAssociationModalData($data, $site, $p, $p->tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                    }
                } else if (
                    ($type == "filled" || $type == "unfilled")
                ) {
                    $this->mapAssociationModalData($data, $site, $p, $p->tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true);
                } else ($type != "inelegible") {
                    $this->mapAssociationModalData($data, $site, $p, $p->tipsDifference, $distributionExists, $eventsExistsOnSystemDate, true)
                };
                
            }
        }

        if ($request->count) {
            $count = [];
            $count["count"] = count($data["sites"]);
            return $count;
        }

        if (count($data["sites"]) != 10 && count($sites)) {
            $request->merge(['offset' => $request->offset + $request->limit]);
            $request->merge(['limit' => $request->limit - count($data["sites"])]);
    
            return $this->getAvailablePackages($request, $table, $associateEventId, $type, $date, $data["sites"]);
        }
        $data["offset"] = $request->offset;
        return $data;
    }
    
    public static function getUnAvailablePackages($siteIds, $association, $date, $isVip, $section, $event) {
        $data = [];

        $ineligiblePackages = \App\Package::select(
                "package.id",
                "package.name",
                "package.tipsPerDay",
                "package.tipIdentifier",
                "distribution.id AS distributionId",
                "site.name AS siteName"
            )
            ->join("site", "site.id", "package.siteId")
            ->leftJoin("package_prediction", function($query) use ($association) {
                $query->on("package_prediction.packageId", "package.id");
                $query->where("package_prediction.predictionIdentifier", "=", $association['event']->predictionId);
            })
            ->join("package_section", "package_section.packageId", "package.id")
            ->leftJoin("distribution", "distribution.packageId", "package.id")
            ->where("package.isVip", "=", $isVip)
            ->where("package_section.section" , "=", $section)
            ->where("package_section.systemDate" , "=", $date)
            ->when($association['event']->isNoTip, function ($query, $date) {
                return $query->where("distribution.systemDate", $date)
                    ->where("distribution.isNoTip", "=", 1);
            })
            ->whereIn('site.id', $siteIds)
            ->whereNull("package_prediction.id")
            ->groupBy("package.id")
            ->get();

        foreach ($ineligiblePackages as $p) {            
            // check if event alredy exists in tips distribution
            $distributionExists = \App\Distribution::where('associationId', $association['event']->id)
                ->where('packageId', $p->id)
                ->count();

            // get number of associated events with package on event systemDate
            $eventsExistsOnSystemDate = \App\Distribution::where('packageId', $p->id)
                ->where('systemDate', $date)
                ->count();

            $tipsDifference = $eventsExistsOnSystemDate - $p->tipsPerDay;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]["siteName"] = $p->siteName;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]["toDistribute"] = $event->to_distribute;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]["eligible"] = false;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]["tipsDifference"] = $tipsDifference;
            $data[$p->siteName]['tipIdentifier'][$p->tipIdentifier]['packages'][] = [
                'id' => $p->id,
                'name' => $p->name,
                'tipsPerDay' => $p->tipsPerDay,
                'eventIsAssociated' => $distributionExists,
                'packageAssociatedEventsNumber' => $eventsExistsOnSystemDate
            ];
        }
        return $data;
    }

    // create new associations
    // @param array() $eventsIds
    // @param string  $table
    // @param string  $systemDate
    // @return array()
    public function store(Request $r)
    {
        $events = $r->input('events');
        $systemDate = $r->input('systemDate');

        if (empty($events))
            return response()->json([
                "type" => "error",
                "message" => "You must select at least one event"
            ]);

        // TODO check $systemDate is a vlid date

        $notFound = 0;
        $alreadyExists = 0;
        $success = 0;
        $returnMessage = '';

        foreach ($events as $item) {
            $vip = ($item["table"] === 'ruv' || $item["table"] === 'nuv') ? '1' : '';
        
            if (!\App\Event::find($item["id"])) {
                $notFound++;
                continue;
            }

            $event = \App\Event::find($item["id"])->toArray();

            // Check if already exists in association table
            if (\App\Association::where([
                ['eventId', '=', (int)$item["id"]],
                ['type', '=', $item["table"]],
                ['predictionId', '=', $event['predictionId']],
            ])->count()) {
                $alreadyExists++;
                continue;
            }

            $event['eventId'] = (int)$event['id'];
            unset($event['id']);
            unset($event['created_at']);
            unset($event['updated_at']);

            $event['isNoTip'] = '';
            $event['isVip'] = $vip;
            $event['type'] = $item["table"];
            $event['systemDate'] = $systemDate;
			
			// get the aliases - added by GDM
			$homeTeamAlias = \App\Models\Team\Alias::where('teamId', $event['homeTeamId'] )->first();
			if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
				$event['homeTeam'] = $homeTeamAlias->alias;
			}		
			$awayTeamAlias = \App\Models\Team\Alias::where('teamId', $event['awayTeamId'] )->first();
			if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
				$event['awayTeam'] = $awayTeamAlias->alias;
			}		
			$leagueAlias = \App\Models\League\Alias::where('leagueId', $event['leagueId'] )->first();
			if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
				$event['league'] = $leagueAlias->alias;
			}
			
			$countryAlias = \App\Models\Country\Alias::where('countryCode', $event['countryCode'] )->first();
			if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
				$event['country'] = $countryAlias->alias;
			}
			

            \App\Association::create($event);
            $success++;
        }

        if ($notFound)
            $returnMessage .= $notFound . " - events not found (maybe was deleted)\r\n";

        if ($alreadyExists)
            $returnMessage .= $alreadyExists . " - already associated with this table\r\n";

        if ($success)
            $returnMessage .= $success . " - events was added with success\r\n";

        return response()->json([
            "type" => "success",
            "message" => $returnMessage
        ]);
    }

    // add no tip to a table
    // @param string $table
    // @param string $systemDate
    // @return array()
    public function addNoTip(Request $r)
    {
        $table = $r->input('table');
        $systemDate = $r->input('systemDate');

        $errors = [];
        $isErrored = false;

        foreach ($table as $item) {
            $validMessage = AssociationModel::validate($item, $systemDate);
            $errors[] = $validMessage;
            if ($validMessage["type"] == "error") {
                $isErrored = true;
            }
            AssociationModel::validate($item, $systemDate);
        }
        
        if ($isErrored) {
            return [
                'type' => 'error',
                'message' => "Failed to insert",
                'data' => $errors,
            ];
        }
        
        foreach ($table as $item) {
            $a = new \App\Association();
            $a->type = $item["table"];
            $a->isNoTip = '1';

            if ($item["table"] === 'ruv' || $item["table"] === 'nuv')
                $a->isVip = '1';

            $a->systemDate = $systemDate;
            $a->save();

            return response()->json([
                "type" => "success",
                "message" => "No Tip was added with success!",
            ]);
        }
    }

    public function update() {}

    public function destroy($id) {

        $association = \App\Association::find($id);

        // assoociation not exists retur status not exists
        if ($association === null) {
            return response()->json([
                "type" => "error",
                "message" => "Event with id: $id not exists"
            ]);
        }

        // could not delete an already distributed association
        if (\App\Distribution::where('associationId', $id)->count())
        return response()->json([
            "type" => "error",
            "message" => "Before delete event: $id  you must delete all distribution of this!"
        ]);

        $association->delete();
        return response()->json([
            "type" => "success",
            "message" => "Site with id: $id was deleted with success!"
        ]);
    }
    
    private function mapAssociationModalData(&$data, $site, $package, $tipsDifference, $distributionExists, $eventsExistsOnSystemDate, $eligible)
    {
        $data['sites'][$site->name]['tipIdentifier'][$package->tipIdentifier]["siteName"] = $site->name;
        $data['sites'][$site->name]['tipIdentifier'][$package->tipIdentifier]["toDistribute"] = $data['event']->to_distribute;
        $data['sites'][$site->name]['tipIdentifier'][$package->tipIdentifier]["eligible"] = $eligible;
        $data['sites'][$site->name]['tipIdentifier'][$package->tipIdentifier]["tipsDifference"] = $tipsDifference;
        $data['sites'][$site->name]['tipIdentifier'][$package->tipIdentifier]['packages'][] = [
            'id' => $package->id,
            'name' => $package->name,
            'tipsPerDay' => $package->tipsPerDay,
            'eventIsAssociated' => $distributionExists,
            'packageAssociatedEventsNumber' => $eventsExistsOnSystemDate
        ];
    }
}
