<?php

namespace App\Console\Commands\Hotel;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

ini_set('memory_limit', '-1');

class ProductRank extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hotel:ProductRank';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '酒店产品排序';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //
        /**
         *  评分：25%
         *  评论数：45%
         *  距离：15%
         *  星级：15%
         */

//        //获取所有城市的经纬度
//        $this->info('开始加载des城市信息...');
//        $desCityMap = $this->getDesDbCityAllMap();
//        $this->info(sprintf('加载des城市信息成功：%d条', count($desCityMap)));
//        if (empty($desCityMap)) {
//            $this->error('des城市信息为空，停止执行...');
//            return;
//        }

        $this->info('开始加载poi_db城市信息...');
        $poiDbCityMap = $this->getPoiDbCityAllMap();
        $this->info(sprintf('加载poi_db城市信息成功：%d条', count($poiDbCityMap)));
        if (empty($poiDbCityMap)) {
            $this->error('poi_db城市信息为空，停止执行...');
            return;
        }

        $this->info('开始加载酒店产品信息...');
        //加载所有酒店产品信息
        $lists = $this->getProductAllLists();
        $this->info(sprintf('加载酒店产品信息成功：%d条', count($lists)));
        if (empty($lists)) {
            $this->error('酒店产品信息为空，停止执行...');
            return;
        }
        $rankList = collect($lists)->transform(function($item) use ($poiDbCityMap) {
            $item = (array)$item;

            //当前酒店经纬度
            $lat1 = (float)$item['latitude'];
            $lon1 = (float)$item['longitude'];

            //市中心经纬度
            $lat2 = '0';
            $lon2 = '0';
//            $cityMap = $item['is_new'] == 1 ? $desCityMap : $poiDbCityMap;
            $cityMap = $poiDbCityMap;

            if (empty($cityMap[$item['city']])) {
                $log = sprintf('酒店ID：%d没找到对应城市信息：%d，is_new：%d', $item['product_id'], $item['city'], $item['is_new']);
                $this->error($log);
                file_put_contents('product_error.log', $log . "\r\n", FILE_APPEND);
            } else {
                $lat2 = (float)$cityMap[$item['city']]['lat'];
                $lon2 = (float)$cityMap[$item['city']]['lng'];

                if($lat2 == 0){
                    $log = sprintf('酒店ID：%d没找到对应lat：%d，is_new：%d', $item['product_id'], $item['city'], $item['is_new']);
                    $this->error($log);
                    file_put_contents('product_error.log', $log . "\r\n", FILE_APPEND);
                }
                if($lon2 == 0){
                    $log = sprintf('酒店ID：%d没找到对应lon：%d，is_new：%d', $item['product_id'], $item['city'], $item['is_new']);
                    $this->error($log);
                    file_put_contents('product_error.log', $log . "\r\n", FILE_APPEND);
                }
            }

            //计算分数
            $data = [
                'product_id'            => $item['product_id'],
                'star_score'            => $this->starScore($item['star']),
                'geo_score'             => $this->geoScore($lat1, $lon1, $lat2, $lon2),
                'supplier_rating_score' => $this->supplierRatingScore($item['supplier_rating']),
                'comment_count_score'   => $this->commentCountScore($item['comment_count']),

            ];
            $data['total_score'] = $data['star_score'] * 0.15 + $data['geo_score'] * 0.15 + $data['supplier_rating_score'] * 0.25 + $data['comment_count_score'] * 0.45;
            $item['score_list'] = $data;
            return $item;

        })->sort(function($item1, $item2) {
            return $item2['score_list']['total_score'] <=> $item1['score_list']['total_score'];
        })->values()->transform(function($item, $key) {
            $item['score_list']['rank'] = $key + 1;
            return $item;
        })->toArray();

        unset($lists);//回收源数据内存

        $this->insert($rankList);
    }

    /**
     * @author  fangjianwei
     * @param $data
     */
    public function insert($data) {
        //分批按一百条进行插入
        $total = count($data);
        $limit = 100;
        $maxPage = ceil($total / $limit);
        $bar = $this->output->createProgressBar($maxPage);
        $db = DB::connection('local_des_mysql');

        for ($i = 0; $i < $maxPage; $i++) {
            $rows = array_slice($data, $i * $limit, $limit);
            $insert = collect($rows)->transform(function($item) {
                $item = $item['score_list'];
                return [
                    'product_id'            => $item['product_id'],
                    'star_score'            => $item['star_score'],
                    'geo_score'             => $item['geo_score'],
                    'supplier_rating_score' => $item['supplier_rating_score'],
                    'comment_count_score'   => $item['comment_count_score'],
                    'rank'                  => $item['rank'],
                ];
            })->toArray();
            if(!empty($insert)){
                $result = $db->table('htl_product_score')->insert($insert);
                if (false == $result) {
                    $this->error('插入错误');
                    exit;
                }
            }

            $bar->advance(1);
        }
        $this->info('执行完成...');
        $bar->finish();
    }


    /**
     * 计算酒店离市中心分数
     * @author  fangjianwei
     * @param $latitude1
     * @param $longitude1
     * @param $latitude2
     * @param $longitude2
     * @return float|int
     */
    protected function geoScore($latitude1, $longitude1, $latitude2, $longitude2) {
        //计算两个经纬度直线距离
        $distance = $this->getDistance($latitude1, $longitude1, $latitude2, $longitude2);
        if ($distance > 25) {
            $score = 0;
        } elseif ($distance > 10) {
            $score = 0.1;
        } elseif ($distance > 5) {
            $score = 0.3;
        } elseif ($distance > 3) {
            $score = 0.6;
        } elseif ($distance > 1) {
            $score = 0.8;
        } elseif ($distance > 0.5) {
            $score = 0.9;
        } else {
            $score = 1;
        }
        return $score;
    }

    /**
     * 计算供应商评分分数
     * @author  fangjianwei
     * @param $supplierRating
     * @return float
     */
    protected function supplierRatingScore($supplierRating) {
        if ($supplierRating < 0 || $supplierRating > 5) {
            $this->error(sprintf('供应商分数异常：%s', $supplierRating));
            return 0;
        }
        return $supplierRating * 0.2;
    }

    /**
     * 计算评论分数
     * @author  fangjianwei
     * @param $commentCount
     * @return float|int
     */
    protected function commentCountScore($commentCount) {
        $score = 0;
        if ($commentCount >= 1000) {
            $score = 1;
        } elseif ($commentCount >= 500) {
            $score = 0.8;
        } elseif ($commentCount >= 100) {
            $score = 0.5;
        } elseif ($commentCount >= 50) {
            $score = 0.3;
        } elseif ($commentCount >= 10) {
            $score = 0.2;
        }
        return $score;
    }

    /**
     * 计算星级分数
     * @author  fangjianwei
     * @param $star
     * @return float|int
     */
    protected function starScore($star) {
        return [
                0 => 0,
                1 => 0.2,
                2 => 0.4,
                3 => 0.6,
                4 => 0.8,
                5 => 1,
            ][$star] ?? 0;
    }


    /**
     * 获取酒店产品所有记录
     * @author  fangjianwei
     * @return array
     */
    protected function getProductAllLists() {

        $rows = DB::connection('des_mysql')->table('htl_product')
            ->where([
                ['city', '<>', 0],
            ])->whereIn('is_new', [1,2])
            ->get(['product_id', 'is_new', 'star', 'latitude', 'longitude', 'city', 'supplier_rating', 'comment_count'])->toArray();
        return $rows;
    }

    /**
     * @author  fangjianwei
     * @return array
     */
    protected function getPoiDbCityAllMap() {
//        $redis = Redis::connection('default');
//        $key = 'hotel_product_cache';
//        dd($redis->get($key));

        $rows = DB::connection('poi_mysql')->table('city_tbl')
            ->where('is_del', 0)
            ->get(['lat', 'lng', 'city'])->toArray();

        $new = [];
        foreach ($rows as $val) {
            $val = (array)$val;
            $new[$val['city']] = $val;
        }
        return $new;
    }

    /**
     * @author  fangjianwei
     * @return array
     */
    protected function getDesDbCityAllMap() {
        $rows = DB::connection('des_mysql')->table('city_tbl')
            ->get(['lat', 'lng', 'city'])->toArray();

        $new = [];
        foreach ($rows as $val) {
            $val = (array)$val;
            $new[$val['city']] = $val;
        }
        return $new;
    }

    /**
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @return int
     */
    function getDistance($lat1, $lng1, $lat2, $lng2) {

        //将角度转为狐度

        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度

        $radLat2 = deg2rad($lat2);

        $radLng1 = deg2rad($lng1);

        $radLng2 = deg2rad($lng2);

        $a = $radLat1 - $radLat2;

        $b = $radLng1 - $radLng2;

        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137;

        return $s;

    }


}
