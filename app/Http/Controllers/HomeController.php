<?php

namespace App\Http\Controllers;

use App\Providers\Services\Football\NoPredictionsWrongFileData;
use App\Providers\Services\Football\PoissonAlgorithm;
use App\Providers\Services\Football\TeamNotFound;
use duzun\hQuery;
use Goutte\Client;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $client = new Client();
        $crawler = $client->request('GET', 'https://int.soccerway.com/national/brazil/serie-b/2017/regular-season/r39675/');

        $options = $crawler->filter('#season_id_selector')->children();
        $getPastYear = $options->eq($options->count() + 1 - $options->count())->attr('value');
        $item = explode('/', $getPastYear);
        $season_id = (int) str_replace('s', '', $item[count($item) - 2]);
        $queryParams = [
            'block_id' => 'page_competition_1_block_competition_tables_7',
            'callback_params' => '{}',
            'action' => 'changeTable',
            'params' => '{"type":"competition_wide_table"}',

        ];

        $crawler->filter('#page_competition_1_block_competition_playerstats_8-wrapper + script')->each(function($node) use(&$queryParams, $season_id){
            $params = $this->extractJSON($node->text());

            if( !$params) {
                throw new \Exception('Try again');
            }

            $parsed = json_decode($params[0]);
            $parsed->season_id = $season_id;
            $parsed->outgroup = "";
            $parsed->view = "";
            $parsed->new_design_callback = "";

            $queryParams['callback_params'] = json_encode($parsed);

        });
        $queryParams = http_build_query($queryParams);

        $ch = curl_init();
        $timeout = 5;

        curl_setopt($ch, CURLOPT_URL, 'https://int.soccerway.com/a/block_competition_tables?' . $queryParams);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);

        $data = curl_exec($ch);
        curl_close($ch);

        $soccerwayData = json_decode($data, true);

        if( !$soccerwayData) {
            throw new \Exception('Invalid data');
        }

        $html = $soccerwayData['commands'][0]['parameters']['content'];
        $crawler->clear();
        $crawler->addHtmlContent($html);
        $table = $crawler->filter('#page_competition_1_block_competition_tables_7_block_competition_wide_table_1_table')->filter('tr')->each(function ($tr, $i) {
            return $tr->filter('td')->each(function ($td, $i) {
                return trim($td->text());
            });
        });

        return $table;

    }

    public function extractJSON($string) {
        $pattern = '
        /
        \{              # { character
            (?:         # non-capturing group
                [^{}]   # anything that is not a { or }
                |       # OR
                (?R)    # recurses the entire pattern
            )*          # previous group zero or more times
        \}              # } character
        /x
        ';

        preg_match_all($pattern, $string, $matches);

        if ($matches == false) {
            return [];
        }

        $result = json_decode($matches[0][0]);
        if (!$result) {
            return $this->extractJSON(substr($matches[0][0], 1, -1));
        }

        return $matches[0];
    }
//    /**
//     * Show the application dashboard.
//     *
//     * @return \Illuminate\Http\Response
//     */
//    public function index(Request $request)
//    {
//        $data = [];
//        $error = null;
//        if ($request->isMethod('post')) {
//            $this->validate($request, ['match.1' => 'required', 'sheet_url' => 'required', 'occurances' => 'required'], ['match.1.required' => 'Enter match game']);
//            $matches = $request->input('match');
//            $sheetUrl = $request->input('sheet_url');
//            $occurances = $request->input('occurances');
//            $games = [];
//            foreach($matches as $match) {
//                $game = explode('-', $match);
//                if (count($game) == 2) {
//                    $games[] = $game;
//                }
//            }
//
//            try {
//                $poisson = new PoissonAlgorithm($sheetUrl, $games, $occurances);
//                $data = $poisson->generatePredictions();
//            } catch (TeamNotFound $tex) {
//                $error = ValidationException::withMessages([
//                    'team_not_found' => [$tex->getMessage()],
//                ]);
//            } catch (NoPredictionsWrongFileData $ex) {
//                $error = ValidationException::withMessages([
//                    'team_not_found' => [$ex->getMessage()],
//                ]);
//            }
//
//            if($error) {
//                throw $error;
//            }
//        }
//
//        return view('home', ['data' => $data]);
//    }
}
