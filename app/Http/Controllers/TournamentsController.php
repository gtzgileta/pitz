<?php

namespace App\Http\Controllers;

use App\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;


class TournamentsController extends Controller {

    /*
      Estadisticas de los equipos en un torneo
      las cuales se obtienen solo de las jornadas
      regulares (omiten las jornadas extras), el
      arreglo tiene que estár ordenado del equipo
      que tiene mas puntos(points) al que menos
      [
        {
        {
          "id": 1,                     // id de equipo
          "name": "Leones",            // Nombre de equipo
          "goals": 4,                  // Goles totales del equipo en el torneo
          "received_goals": 5,         // Goles recibidos del equipo en el torneo
          "difference_of_goals": -1,   // Diferencia de goles (goals-received_goals)
          "matches_played": 6,         // El numero total de "matches" con resultado donde participa el equipo en el torneo
          "matches_won": 1,            // El numero total de "matches" con resultado donde participa el equipo en el torneo donde el equipo sea ganador
          "draw_matches": 2,           // El numero total de "matches" con resultado donde participa el equipo en el torneo donde el resultado sea empate
          "matches_lost": 3,           // El numero total de "matches" con resultado donde participa el equipo en el torneo donde el equipo sea el perdedor
          "points": 5,                 // Puntos obtenidos por el equipo en el torneo (3 puntos por partid ganado 1 punto por partido empatado y 0 puntos por partido perdido)
        }
        }
      ]
    */

    public function generalTable($id) {

      # Teams who played in tournament
      $teams = DB::table('matches')->join('sets', 'sets.id', '=', 'matches.set_id')->select('matches.team_a_id','matches.team_b_id')->where('sets.type','regular')->where('matches.tournament_id',$id)->get();

      $arr=[];
      foreach($teams as $k){
        $arr[$k->team_a_id]=$k->team_a_id;
        $arr[$k->team_b_id]=$k->team_b_id;
      }
      $goals_received=$matches_played=$matches_won=$draw_matches=$matches_lost=$points=$goals=array_fill_keys(array_keys(array_unique($arr)),0); # EQUIPOS QUE JUGARON EN UN TORNEO EN JORNADA NORMAL

      $results = DB::table('results')
      ->join('matches', 'matches.id','=','results.match_id')
      ->join('sets','sets.id','=','matches.set_id')
      ->where('sets.type','regular')
      ->where('matches.tournament_id',$id)
      ->get();

      $teams_results=[];
      foreach($results as $res){

        # GOALS MADE
        $goals[$res->team_a_id]+=$res->team_a_goals;
        $goals[$res->team_b_id]+=$res->team_b_goals;

        # GOALS RECEIVED
        $goals_received[$res->team_a_id]+=$res->team_b_goals;
        $goals_received[$res->team_b_id]+=$res->team_a_goals;

        # MATCHES PLAYED
        $matches_played[$res->team_a_id]+=1;
        $matches_played[$res->team_b_id]+=1;

        # DRAW MATCHES
        $points_a=$points_b=1;
        $is_draw=($res->team_a_goals == $res->team_b_goals)?true:false;
        $draw_matches[$res->team_a_id]+=($is_draw) ? 1 : 0;
        $draw_matches[$res->team_b_id]+=($is_draw) ? 1 : 0;

        if(!$is_draw){
          # MATCHES WON
          $a_won=($res->team_a_goals >= $res->team_b_goals)?true:false;
          $matches_won[$res->team_a_id]+=($a_won) ? 1 : 0;
          $matches_won[$res->team_b_id]+=(!$a_won) ? 1 : 0;
          # MATCHES LOST
          $matches_lost[$res->team_a_id]+=(!$a_won) ? 1 : 0;
          $matches_lost[$res->team_b_id]+=($a_won) ? 1 : 0;

          $points_a=($a_won)?3:0;
          $points_b=(!$a_won)?3:0;
        }

        # POINTS
        $points[$res->team_a_id]+=$points_a;
        $points[$res->team_b_id]+=$points_b;

      }
      $return=[];
      foreach($goals as $key => $value){
        $team = DB::table('teams')->where('id',$key)->first();

        $return[$key]['id']=$key;
        $return[$key]['name']=$team->name;
        $return[$key]['goals']=$value;
        $return[$key]['received_goals']=$goals_received[$key];
        $return[$key]['difference_of_goals']=abs($value-$goals_received[$key]);
        $return[$key]['matches_played']=$matches_played[$key];
        $return[$key]['matches_won']=$matches_won[$key];
        $return[$key]['draw_matches']=$draw_matches[$key];
        $return[$key]['matches_lost']=$matches_lost[$key];
        $return[$key]['points'] = $points[$key];
      }

      return json_encode($return);

    }

}
