<?php

namespace App\Models\Events;

use App\Match;

use Carbon\Carbon;
use App\Association;
use App\Distribution;
use App\SubscriptionTipHistory;
use Illuminate\Database\Eloquent\Model;
use App\Console\Commands\AutoUnitAddEvents;

class EventModel extends Model
{
    protected $table = "event";

    public static function bulkInsert($data)
    {
        $addedEvents = [];
        $errors = [];
        $isErrored = false;

        foreach ($data as $event) {
            $validMessage = EventModel::validate($event);
            $errors[] = $validMessage;
            if ($validMessage["type"] == "error") {
                $isErrored = true;
            }
        }
        if ($isErrored) {
            return [
                'type' => 'error',
                'message' => "Failed to insert",
                'data' => $errors
            ];
        }

        foreach ($data as $event) {
            $country = $event['country'];
            $league = $event['league'];
            $homeTeam = $event['homeTeam'];
            $awayTeam = $event['awayTeam'];
            $homeTeamId = $event['homeTeamId'];
            $awayTeamId = $event['awayTeamId'];
            $leagueId = $event['leagueId'];
            $countryCode = $event['countryCode'];
            $eventDate = $event['eventDate'];
            $predictionId = $event['predictionId'];
            $odd = $event['odd'];
            
            // get the aliases - added by GDM
            $homeTeamAlias = \App\Models\Team\Alias::where('teamId', $homeTeamId)->first();
            if( $homeTeamAlias && $homeTeamAlias->alias && $homeTeamAlias->alias != '' ) {
                $homeTeam = $homeTeamAlias->alias;
            }
            $awayTeamAlias = \App\Models\Team\Alias::where('teamId', $awayTeamId)->first();
            if( $awayTeamAlias && $awayTeamAlias->alias && $awayTeamAlias->alias != '' ) {
                $awayTeam = $awayTeamAlias->alias;
            }
            $leagueAlias = \App\Models\League\Alias::where('leagueId', $leagueId)->first();
            if( $leagueAlias && $leagueAlias->alias && $leagueAlias->alias != '' ) {
                $league = $leagueAlias->alias;
            }
            $countryAlias = \App\Models\Country\Alias::where('countryCode', $countryCode )->first();
            if( $countryAlias && $countryAlias->alias && $countryAlias->alias != '' ) {
                $country = $countryAlias->alias;
            }
            
            // prepare the event data
            $eventData = [];
            $eventData['country'] = $country;
            $eventData['league'] = $league;
            $eventData['homeTeam'] = $homeTeam;
            $eventData['awayTeam'] = $awayTeam;
            
            $eventData['result'] = '';
            $eventData['status'] = '';
            
            $eventData['homeTeamId'] = $homeTeamId;
            $eventData['awayTeamId'] = $awayTeamId;
            $eventData['leagueId'] = $leagueId;
            $eventData['countryCode'] = $countryCode;
            $eventData['predictionId'] = $predictionId;
            $eventData['eventDate'] = $eventDate;
            $eventData['odd'] = number_format((float) $odd, 2, '.', '');
            $eventData['source'] = 'manual';
            $eventData['provider'] = 'manual';
            $eventData['matchId'] = 0;
            
            $addedEvents[] = \App\Event::create($eventData);
        }
        return $addedEvents;
    }

    public static function validate($event) {
        // Input validations
        if (!$event["predictionId"] || trim($event["predictionId"]) == '-') {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "Prediction can not be empty!",
            ];
        }
        if (!$event["odd"] || trim($event["odd"]) == '-') {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "Odd can not be empty!",
            ];
        }
        if (!$event["homeTeamId"] || trim($event["homeTeamId"]) == '-') {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "Home Team can not be empty!",
            ];
        }
        if (!$event["awayTeamId"] || trim($event["awayTeamId"]) == '-') {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "Away Team can not be empty!",
            ];
        }
        if (!$event["leagueId"] || trim($event["leagueId"]) == '-') {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "League can not be empty!",
            ];
        }
        if (!$event["countryCode"] || trim($event["countryCode"]) == '-') {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "Country can not be empty!",
            ];
        }
        // check if the same team was selected for both away and home team inputs
        if ( $event["homeTeamId"] == $event["awayTeamId"] ) {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "You can not select the same team as both away and home teams",
            ];
        }
        
        // check if event already exists with same prediciton
        // we check only the date - not the time of the event
        $checkDate = strtok($event["eventDate"],  ' ');

        if (\App\Event::where('homeTeamId', $event["homeTeamId"])
            ->where('awayTeamId', $event["awayTeamId"])
            ->where('eventDate', 'like' , $checkDate . '%')
            ->where('predictionId', $event["predictionId"])
            ->count())
        {
            return [
                'data' => $event,
                'type' => 'error',
                'message' => "This event already exists with same prediction",
            ];
        }
        return [
            'data' => $event,
            'type' => 'success',
            'message' => "This event is correct",
        ];
    }
    
    public static function validateAddFromMatch($event) {
        if (!$event["predictionId"] || trim($event["predictionId"]) == '-') {
            return [
                'type' => 'error',
                'message' => "Prediction can not be empty!",
            ];
        }

        if (!$event["odd"] || trim($event["odd"]) == '-') {
            return [
                'type' => 'error',
                'message' => "Odd can not be empty!",
            ];
        }

        $match = Match::find($event["matchId"]);
        if (!$match) {
            return [
                'type' => 'error',
                'message' => "Match with id: " . $event["matchId"]  . " not found!",
            ];
        }
        
        // check if event already exists with same prediciton
        // we check only the date - not the time of the event
        $checkDate = strtok($match['eventDate'],  ' ');
        if (\App\Event::where('homeTeamId', $match['homeTeamId'])
            ->where('awayTeamId', $match['awayTeamId'])
            ->where('eventDate', 'like' , $checkDate . '%')
            ->where('predictionId', $event["predictionId"])
            ->count())
        {
            return [
                'type' => 'error',
                'message' => "This event already exists with same prediction",
            ];
        }
        return [
            'data' => $event,
            'type' => 'success',
            'message' => "This event is correct",
        ];
    }

    public static function postpone($eventId)
    {
        $event = \App\Event::find($eventId);
        $systemDate = date("Y-m-d", strtotime($event->eventDate));
    
        \App\Event::where("homeTeamId", "=", $event->homeTeamId)
            ->where("awayTeamId", "=", $event->awayTeamId)
            ->where("leagueId", "=", $event->leagueId)
            ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $systemDate . "'")
            ->update([
                "statusId" => 4
            ]);

        Distribution::where("homeTeamId", "=", $event->homeTeamId)
            ->where("awayTeamId", "=", $event->awayTeamId)
            ->where("leagueId", "=", $event->leagueId)
            ->where("systemDate", "=", $systemDate)
            ->update([
                "statusId" => 4
            ]);

        Association::where("homeTeamId", "=", $event->homeTeamId)
            ->where("awayTeamId", "=", $event->awayTeamId)
            ->where("leagueId", "=", $event->leagueId)
            ->where("systemDate", "=", $systemDate)
            ->update([
                "statusId" => 4
            ]);

        $subscriptionHistories = SubscriptionTipHistory::with("subscription")->where("eventId", "=", $eventId)->get();

        foreach ($subscriptionHistories as $subscriptionHistory) {
            if ($subscriptionHistory->subscription->type == "days") {
                $endDate = new Carbon($subscriptionHistory->subscription->dateEnd);
                $subscriptionHistory->subscription->dateEnd = $endDate->addDay()->format("Y-m-d");
                $subscriptionHistory->subscription->status = "active";
                $subscriptionHistory->subscription->update();
            } else {
                $subscriptionHistory->subscription->tipsLeft += 1;
                $subscriptionHistory->subscription->tipsBlocked -= 1;
                $subscriptionHistory->subscription->status = "active";
                $subscriptionHistory->subscription->update();
            }
        }

        $match = Match::find($event->matchId);

        if ($match) {
            $match->is_postponed = 1;
            $match->update();
            $match->statusId = 4;

            $autoUnitCron = new AutoUnitAddEvents();
            $autoUnitCron->fire($match, false, null, false);  
        }

        return [
            'type' => 'success',
            'message' => 'Match was postponed'
        ];
    }

    public static function getNoUserEventsWithResults($date)
    {
        $data = self::where("eventDate", "LIKE", "%" .  $date . "%")
            ->where("result", "!=", "")
            ->get();
        return $data;
    }
}