<?php namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

use App\Models\Odd;

class Match extends Model {

    protected $table = 'match';

    protected $fillable = [
        'primaryId',
        'id',
        'country',
        'countryCode',
        'league',
        'leagueId',
        'homeTeam',
        'homeTeamId',
        'awayTeam',
        'awayTeamId',
        'result',
        'eventDate',
        'estimated_finished_time'
    ];

    public function getLeagueMatches(array $leagueIds, string $date, int $limit, $offset, $search = NULL)
    {
        $matches = Match::select(
                        DB::raw('SQL_CALC_FOUND_ROWS *')
                    )
                    ->whereIn("leagueId", $leagueIds)
                    ->whereRaw("DATE_FORMAT(eventDate, '%Y-%m-%d') = '" . $date . "'")
                    ->where(function ($query) use ($date, $search) {
                        $query->when($search, function($innerQuery, $search) {
                            foreach ($this->fillable as $column) {
                                $innerQuery->orWhere($column, "LIKE", "%$search%");
                            }
                            return $innerQuery;
                        });
                    })
                    ->limit($limit)
                    ->offset($offset)
                    ->get();
        $total = DB::select(DB::raw('SELECT FOUND_ROWS() as total_count'));
        return [$matches, $total[0]->total_count];
    }
    
    public static function getMatchPredictionOdd($predictionIdentifier, $matchId)
    {
        $odd = Odd::select(
                    "odd"
                )
                ->where("predictionId", "=", $predictionIdentifier)
                ->where("matchId", "=", $matchId)
                ->first();
        return $odd;
    }
}
