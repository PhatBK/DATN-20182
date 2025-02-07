<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Phpml\Association\Apriori;

use App\Models\UserSearchKey;
use App\Models\DanhGiaMonAn;
use App\Models\UserServey;
use App\Models\LikePost;
use App\Models\LikeMonAn;
use App\Models\User;
use App\Models\MonAn;
use App\Models\UserImplictsData;
use App\Models\RecommendPredict;
use App\Models\LoaiMon;
use App\Models\UserPost;
use App\Models\RankMonAnDate;

// use package of third
use GuzzleHttp\Client as GuzzleClient;
use Carbon\Carbon;




class RecommenderCoreController extends Controller
{
    protected $recommend_controller = null;
    
    function __construct()
    {
        $this->user_all = User::orderBy('id', 'ASC')->get();
        $this->monan_all = MonAn::orderBy('id', 'ASC')->get();
    }
    //TODO get data and send to flask
    public function getAllDataUserArray() {
        $all_datas = [];
        /**
         * Lấy dữ liệu thành dạng json item->user
         * Dữu liệu từ bảng: danhgiamonan
         */
        $json_danhgia_news = [];
        $monan_unique_ratings = DB::table('danhgiamonan')
            ->select(DB::raw('count(id_monan) as monan_count, id_monan'))
            ->groupBy('id_monan')
            ->get();
        foreach ($monan_unique_ratings as $monan_unique_rating) {
            $tmp = [];
            $monan_un_rates = DanhGiaMonAn::where('id_monan', $monan_unique_rating->id_monan)->get();
            foreach ($monan_un_rates as $monan_rate) {
                $tmp[strval($monan_rate->id_user)] = $monan_rate->danhgia;
            }
            $json_danhgia_news[strval($monan_unique_rating->id_monan)] = $tmp;
        }
        // dd($json_danhgia_news, json_encode($json_danhgia_news));
        /**
         * Lấy dữ liệu thành dạng json user->item
         * Dữu liệu từ bảng: danhgiamonan
        */
        $json_danhgias = [];
        $user_unique_ratings = DB::table('danhgiamonan')
            ->select(DB::raw('count(id_user) as user_count, id_user'))
            ->groupBy('id_user')
            ->get();
        foreach ($user_unique_ratings as $unr) {
            $score_danhgias = [];
            $user_unique_rated = DanhGiaMonAn::where('id_user', $unr->id_user)->get();
            foreach ($user_unique_rated as $rated) {
                $score_danhgias[strval($rated->id_monan)] = $rated->danhgia;
            }
            $json_danhgias[strval($unr->id_user)] = $score_danhgias;
        }

        $all_datas['rated'] = $json_danhgias;
        /**
         * Lấy dữ liệu chuyển thành dạng json
         * Dữ liệu được lấy từ bảng: survey_user
         */
        $json_user_surveys = [];
        $user_surveys = UserServey::all();
        foreach ($user_surveys as $user_survey) {
            $json_user_surveys[strval($user_survey->user_id)] = explode("|", $user_survey->loaimon_lists);
        }
        $all_datas['user_survey'] = $json_user_surveys;

        /**
         * Lấy dữ liệu chuyển thành dạng json
         * Dữ liệu được lấy từ bảng: user_search_key
        */
        $json_user_search_keys = [];
        $user_unique_search_keys = DB::table('user_search_key')
            ->select(DB::raw('count(user_id) as user_count, user_id'))
            ->groupBy('user_id')
            ->get();
        foreach ($user_unique_search_keys as $user_unique_search_key) {
            $key_searchs = UserSearchKey::where('user_id', $user_unique_search_key->user_id)->get();
            $tmp = [];
            foreach ($key_searchs as $key_search) {
                $tmp[] = $key_search->mon_an_id;
            }
            array_count_values($tmp);
            $json_user_search_keys[strval($user_unique_search_key->user_id)] = array_count_values($tmp);;
        }
        $all_datas['user_search_key'] = $json_user_search_keys;

        /**
         * Lấy dữ liệu chuyển thành dạng json
         * Dữ liệu được lấy từ bảng: user_implicts_data
         */
        $json_user_implicts_datas = [];
        $query =
            " 
            SELECT id, user_id, mon_an_id, COUNT(user_id) as count_user, COUNT(mon_an_id) as count_mon, SUM(visited_time) as total_visit 
            FROM `user_implicts_data` 
            WHERE 1 
            GROUP BY user_id, mon_an_id
            ";
        $implict_datas = DB::select(DB::raw($query));

        foreach ($implict_datas as $implict_data) {

        }
        /**
         * Lấy dữ liệu chuyển thành dạng json
         * Dữ liệu được lấy từ bảng: likepost
         */
        $json_user_likeposts = [];
        $user_unique_likeposts = DB::table('likepost')
            ->select(DB::raw('count(id_user) as user_count, id_user'))
            ->groupBy('id_user')
            ->get();
        foreach ($user_unique_likeposts as $user_unique_likepost) {
            $user_unique_likeposts = LikePost::where('id_user', $user_unique_likepost->id_user)->get();
            $tmp = [];
            foreach ($user_unique_likeposts as $user_unique_likepost) {
                $tmp[strval($user_unique_likepost->userpost->loaimon->id)] = 1;
            }
            $json_user_likeposts[strval($user_unique_likepost->id_user)] = $tmp;
        }
        $all_datas['user_likeposts'] = $json_user_likeposts;
        /**
         * Lấy dữ liệu thành dạng json
         * Dữu liệu từ bảng: likemonan
         */
        $json_likes = [];
        $user_unique_likes = DB::table('likemonan')
            ->select(DB::raw('count(id_user) as user_count, id_user'))
            ->groupBy('id_user')
            ->get();
        foreach ($user_unique_likes as $uniliked) {
            $tmp = [];
            $likeOfusers = LikeMonAn::where('id_user', $uniliked->id_user)->get();
            foreach ($likeOfusers as $like) {
                $tmp[] = $like->id_monan;
            }
            $json_likes[strval($uniliked->id_user)] = $tmp;
        }

        $all_datas['likes'] = $json_danhgias;

        return response()->json($all_datas);
    }
    public function postFlaskResultRecommender(Request $req) {
        return response()->json(json_decode($req->data, true));
    }
    public function getFlaskAPI(){
        $client = new GuzzleClient(['base_uri' => 'http://127.0.0.1:5000/']);
        $res = $client->request('GET', 'api/data/get/v1');
        $code = $res->getStatusCode(); // 200
        $reason = $res->getReasonPhrase(); // OK
        dd($res);
    }

    //TODO api send data for recommender engine to Json
    public function getDataLike() {
        /**
         * Lấy dữ liệu thành dạng json
         * Dữu liệu từ bảng: likemonan
         */
        $json_likes = [];
        $user_unique_likes = DB::table('likemonan')
            ->select(DB::raw('count(id_user) as user_count, id_user'))
            ->groupBy('id_user')
            ->get();
        foreach ($user_unique_likes as $uniliked) {
            $tmp = [];
            $likeOfusers = LikeMonAn::where('id_user', $uniliked->id_user)->get();
            foreach ($likeOfusers as $like) {
                $tmp[] = $like->id_monan;
            }
            $json_likes[strval($uniliked->id_user)] = $tmp;
        }
        return response()->json($json_likes);
    }
    public function getDataSurvey() {
        $json_user_surveys = [];
        $user_surveys = UserServey::all();
        foreach ($user_surveys as $user_survey) {
            $json_user_surveys[strval($user_survey->user_id)] = explode("|", $user_survey->loaimon_lists);
        }
        return response()->json($json_user_surveys);
    }
    public function getDataImplict() {
        /**
         * Lấy dữ liệu chuyển thành dạng json
         * Dữ liệu được lấy từ bảng: user_implicts_data
         */
        $json_user_implicts_datas = [];
        $query =
            " 
            SELECT id, user_id, mon_an_id, COUNT(user_id) as count_user, COUNT(mon_an_id) as count_mon, SUM(visited_time) as total_visit 
            FROM `user_implicts_data` 
            WHERE 1 
            GROUP BY user_id, mon_an_id
            ";
        $implict_datas = DB::select(DB::raw($query));

        $monan2users = array();
        $user2monans = array();
        foreach ($implict_datas as $element) {
            $monan2users[strval($element->mon_an_id)][$element->user_id] = $element->total_visit;
            $user2monans[strval($element->user_id)][$element->mon_an_id] = $element->total_visit;
        }
        return response()->json($monan2users);
    }
    public function getSearchKey() {
        /**
         * Lấy dữ liệu chuyển thành dạng json
         * Dữ liệu được lấy từ bảng: user_search_key
         */
        $json_user_search_keys = [];
        $user_unique_search_keys = DB::table('user_search_key')
            ->select(DB::raw('count(user_id) as user_count, user_id'))
            ->groupBy('user_id')
            ->get();
        foreach ($user_unique_search_keys as $user_unique_search_key) {
            $key_searchs = UserSearchKey::where('user_id', $user_unique_search_key->user_id)->get();
            $tmp = [];
            foreach ($key_searchs as $key_search) {
                $tmp[] = $key_search->mon_an_id;
            }
            array_count_values($tmp);
            $json_user_search_keys[strval($user_unique_search_key->user_id)] = array_count_values($tmp);;
        }
        return response()->json($json_user_search_keys);
    }
    // Done to get and send data



    // TODO request from flask
    // normal data for item-based recommendation
    public function getAllRateToMatrix(){
        $data = [];
        $allMonAn = $this->monan_all;
        $allUser = $this->user_all;
        foreach ($allMonAn as $monan) {
            $tmp = [];
            foreach ($allUser as $user) {
                $query = "select * from danhgiamonan where id_user = " .$user->id. " and id_monan = ".$monan->id;
                $rate = DB::select(DB::raw($query));
                if($rate) {
                    $tmp[strval($user->id)] = $rate[0]->danhgia;
                } else {
                    // $tmp[strval($user->id)] = 0;
                    $tmp[strval($user->id)] = null;
                }
            }
            $data[strval($monan->id)] = $tmp;
        }
        return response()->json($data);
    }
    // matrix like
    public function getAllLikeToMatrix() {
        $data = [];
        $allMonAn = $this->monan_all;
        $allUser = $this->user_all;
        foreach ($allMonAn as $monan) {
            $tmp = [];
            foreach ($allUser as $user) {
                $query = "select * from likemonan where id_user = " .$user->id. " and id_monan = ".$monan->id;
                $like = DB::select(DB::raw($query));
                if($like) {
                    $tmp[strval($user->id)] = 1;
                } else {
                    // $tmp[strval($user->id)] = 0;
                    $tmp[strval($user->id)] = null;
                }
            }
            $data[strval($monan->id)] = $tmp;
        }
        return response()->json($data);
    }
    // matrix  search key
    public function getAllSearchKeyMatrix() {
        $data = [];
        $allMonAn = $this->monan_all;
        $allUser = $this->user_all;
        foreach ($allMonAn as $monan) {
            $tmp = [];
            foreach ($allUser as $user) {
                $query = "select * from user_search_key where user_id = " .$user->id. " and mon_an_id = ".$monan->id;
                $search = DB::select(DB::raw($query));
                if($search) {
                    $tmp[strval($user->id)] = count($search);
                } else {
                    // $tmp[strval($user->id)] = 0;
                    $tmp[strval($user->id)] = null;
                }
            }
            $data[strval($monan->id)] = $tmp;
        }

        return response()->json($data);
    }
    // maxtix implict data
    public function getAllImplictToMatrix() {
        $data = [];
        $allMonAn = $this->monan_all;
        $allUser = $this->user_all;

        foreach ($allMonAn as $monan) {
            $tmp = [];
            foreach ($allUser as $user) {
                $query = "select * from user_implicts_data where user_id = " .$user->id. " and mon_an_id = ".$monan->id;
                $implict = DB::select(DB::raw($query));
                if($implict) {
                    $total_time = 0;
                    foreach ($implict as $impl) {
                        $total_time += $impl->visited_time;
                    }
                    $tmp[strval($user->id)] = $total_time;
                } else {
                    // $tmp[strval($user->id)] = 0;
                    $tmp[strval($user->id)] = null;
                }
            }
            $data[strval($monan->id)] = $tmp;
        }
        return response()->json($data);
    }
    // normal data to matrix of user-itme for recommendation engine user-based
    public function getRateMatrixUser() {

    }
    public function getLikeMatrixUser() {

    }
    public function getSearchMatrixUser() {

    }
    public function getImplictMatrixUser() {
        
    }
    public function getItemRecommendedSaveDataBase() {
        // dd(json_decode($data, true));
        RecommendPredict::query()->delete();
        $client = new GuzzleClient(['base_uri' => 'http://127.0.0.1:5000/']);
        $res = $client->request('POST', '/api/response/data/recommended/post');
        $recommended_items = json_decode($res->getBody()->getContents(), true);

        foreach ($recommended_items as $id_monan => $list_recommended) {
            $item_pre = new RecommendPredict();
            $item_pre->id_monan = $id_monan;
            $item_pre->list_recommended = $list_recommended;
            $item_pre->save();
        }
        return response()->json("Success");
    }
    public function getRankMonAnDate() {
//        $date_now_ = new \DateTime();
//        $test = UserImplictsData::whereDate('created_at', Carbon::today())->get();
//        dd(strtotime($date_now->format('Y-m-d')) - strtotime($date_now_->format('Y-m-d')));
        /**
         * Score = (a*rate + b*like + c*search + d*watched ) / (age + 2)^g
        */
        $A = 1000;
        $B = 800;
        $C = 800;
        $D = 800;
        $E = 800;
        $G = 2;

        $date_now = new \DateTime();
        $all_mon_an = MonAn::all();
        $ranking = [];
        foreach ($all_mon_an as $monan) {
            $total_view_number = $monan->so_luot_xem;
            $total_watched_time = 0;
            $total_rating = 0;
            $all_rated = $monan->danhgiamonan;
            foreach ($all_rated as $rate) {
                if ($rate->danhgia < 4) continue;
                $total_rating += $rate->danhgia;
            }
            $all_liked_number = count($monan->likemon);
            $all_search_number = count(UserSearchKey::where('mon_an_id', $monan->id)->get());
            $all_watcched = UserImplictsData::where('mon_an_id', $monan->id)->get();
            foreach ($all_watcched as $watched) {
                if ($watched->visited_time < 10) continue;
                $total_watched_time += $watched->visited_time;
            }
            $age_sinece_create = (strtotime($date_now->format('Y-m-d')) - strtotime($monan->created_at->format('Y-m-d'))) / 3600;
            $score_ranking = ($A * $total_rating + $B * $all_liked_number + $C * $all_search_number + $D * $total_watched_time + $E * $total_view_number - 1) / pow(($age_sinece_create + 2), $G);
            $ranking[$monan->id] = $score_ranking;
        }
        arsort($ranking);
        $ranking_result = array_slice($ranking, 0, 5, true);
        RankMonAnDate::query()->delete();
        foreach ($ranking_result as $key=>$value) {
            $ranked = new RankMonAnDate();
            $ranked->id_monan = $key;
            $ranked->rank = $value;
            $ranked->save();
        }
        return response()->json("Successfully");
    }
}
