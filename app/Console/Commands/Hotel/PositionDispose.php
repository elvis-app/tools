<?php

namespace App\Console\Commands\Hotel;

use Illuminate\Console\Command;
use App\Services\Hotel\Position\Redis\Data;
use App\Services\Hotel\Position\Log;
use App\Models\MysqlDb\PositionDisposeDb\ean_city_tbl;
use CurlHelper;

class PositionDispose extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Hotel:PositionDispose';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '大都市数据处理';

    protected $db;

    protected $apiData = [];


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * @author  fangjianwei
     * @throws \Exception
     */
    protected function init(){
        //加载周边数据
        $this->info('加载ean周边数据...');
        if (!Data::loadPeripheral()) {
            $this->error('加载ean周边数据失败！');
            return;
        }

        //加载周边下级数据
        $this->info('加载ean周边下级数据...');
        if (!Data::loadPeripheralSubMap()) {
            $this->error('加载ean周边下级数据失败！');
            return;
        }

        //加载ean省数据
        $this->info('加载ean省数据...');
        if (!Data::loadProvince()) {
            $this->error('加载ean省数据失败！');
            return;
        }

        //对ean省数据建立索引
        $this->info('对ean省数据建立索引...');
        if (!Data::buildProvinceIndex()) {
            $this->error('对ean省数据建立索引失败！');
            exit;
        }

        //加载ean对应租租车国家表数据
        $this->info('加载ean对应租租车国家表数据...');
        if (!Data::loadEanZzcRegionMap()) {
            $this->error('加载ean对应租租车国家表数据失败！');
            exit;
        }

        //加载租租车城市表数据
        $this->info('加载租租车城市表数据...');
        if (!Data::loadZzcCity()) {
            $this->error('加载租租车城市表数据失败！');
            exit;
        }

        //对城市表建立索引
        $this->info('对城市表数据建立索引...');
        if (!Data::buildZzcCityIndex()) {
            $this->error('对城市表数据建立索引失败！');
            exit;
        }
    }

    /**
     * @author  fangjianwei
     * @throws \Exception
     */
    public function handle() {

        $this->init();
        $this->info('开始处理数据...');

        $insertData = [];
        foreach (Data::getPeripheral() as $data) {
            if (empty($data['r_city_list'])) {
                Log::setError($data, '下属城市列表为空');
                continue;
            }
            //获取下属城市
            $subIds = explode(',', $data['r_city_list']);
            $subLists = Data::getPeripheralSubByIds((int)$data['region_id'], $subIds);
            if (empty($subLists)) {
                Log::setError($data, '下属城市列表不为空，但找不到数据');
                continue;
            }
            //检查缺失的下属城市id
            $lostIds = array_diff($subIds, array_column($subLists, 'r_id'));
            if (!empty($lostIds)) {
                Log::setError($data, "下属城市ID找不到数据：" . implode(',', $lostIds));
            }
            $result = $this->disposeCity($data, $subLists);
            if (!empty($result)) {
                $insertData = array_merge($insertData, $result);
            }

            //处理周边本身城市的匹配
            $data['temp_name_cn'] = $data['r_name_cn'];
            $newName = str_replace(mb_substr($data['temp_name_cn'], mb_strpos($data['temp_name_cn'], '('), mb_strrpos($data['temp_name_cn'], ')')), '', $data['temp_name_cn']);
            $newName = trim($newName);
            $data['r_name_cn'] = $newName;
            $data['r_name_en'] =  trim($data['r_name_en']);
            $result = $this->searchCity($data);
            if (!empty($result)) {
                $insertData[] = $result;
            }else{
                $this->apiData[] = $data;
            }
        }
//        //通过接口补数据
//        $result = $this->getPoiCityByApi();
//        if(!empty($result)){
//            $insertData = array_merge($insertData, $result);
//        }
        if (empty($insertData)) {
            $this->error('没有匹配到数据');
            return;
        }
        $unique = [];
        foreach ($insertData as $key => $val) {
            $uni = $val['region_id'] . '_' . $val['r_id'] . '_' . $val['r_type'];
            if (array_key_exists($uni, $unique)) {

                if (!empty($val['vicinity_id']) && !empty($insertData[$unique[$uni]]['vicinity_id'])) {
                    $a = explode(',', $insertData[$unique[$uni]]['vicinity_id']);
                    if (array_search($val['vicinity_id'], $a) === false) {
                        $a[] = $val['vicinity_id'];
                        $insertData[$unique[$uni]]['vicinity_id'] = implode(',', $a);
                    }
                }
                unset($insertData[$key]);
                continue;
            }
            $unique[$uni] = $key;
        }
        $insertData = array_values($insertData);

        $total = count($insertData);
        $limit = 100;
        $maxPage = ceil($total / $limit);
        $bar = $this->output->createProgressBar($maxPage);

        for ($i = 0; $i < $maxPage; $i++) {
            $data = array_slice($insertData, $i * $limit, $limit);
            if (empty($data)) continue;
            $this->insertData($data);
            $bar->advance(1);
        }
        $this->info('正在写日志...');
        Log::saveErrorLog();
        Log::saveInfoLog();
        $this->info('done...');
    }


    /**
     * @author  fangjianwei
     * @param array $data
     * @param array $subLists
     * @return array
     */
    protected function disposeCity(array $data, array $subLists) {
        if (empty($subLists)) return [];
        $data = collect($subLists)->transform(function($item) use ($data) {
            $item['r_name_cn'] = trim($item['r_name_cn']);
            $Item['r_name_en'] = trim($item['r_name_en']);
            $item['vicinity_id'] = $data['r_id'];
            return $this->searchCity($item);
        })->filter()->values()->toArray();
        return $data;
    }

    /**
     * 查询城市
     * @author  fangjianwei
     * @param array $item
     * @return array|null
     */
    protected function searchCity(array $item) {
        //查找与租租车关联的国家数据

        $eanZzcRegion = Data::getEanZzcRegionByEanRegion((int)$item['region_id']);
        if (empty($eanZzcRegion)) {
            Log::setError($item, '找不到ean与租租车关联国家信息，不执行此条数据');
            return null;
        }
        $item['zzc_region_id'] = $eanZzcRegion['region_id'];
        $item['province_name'] = [];
        //查找省名称
        $eanProvinceLists = Data::getEanProvince($item);
        if (empty($eanProvinceLists)) {
            Log::setInfo($item, '找不到省数据，执行第二种方案');
        } else {
            //如果多个省，则忽略
            if (count($eanProvinceLists) > 1) {
                Log::setError($item, '存在多条省数据，不执行此条数据');
                return null;
            }
            $eanProvince = current($eanProvinceLists);

            $item['province_info'] = [
                'r_id'    => $eanProvince['r_id'],
                'name_cn' => $eanProvince['r_name_cn'],
                'name_en' => $eanProvince['r_name_en'],
            ];
        }

        if (!empty($item['province_info'])) {
            //执行第一种方案：带省信息
            $poiCity = Data::getZzcCityByNameState($item);
            if (empty($poiCity)) {
                //执行第二种方案：不带省信息
                $poiCity = Data::getZzcCityByName($item);
            }
        } else {
            //执行第二种方案：不带省信息
            $poiCity = Data::getZzcCityByName($item);
        }
        if (empty($poiCity) || empty($poiCity['city_id'])) {
            $this->apiData[] = $item;
            return null;
        }
        if ($poiCity['scheme'] != 1) {
            $item['province_info'] = [];
        }
        Log::setInfo($item, sprintf('通过第%s种方案获得数据', $poiCity['scheme']));
        $item['zzc_city_id'] = $poiCity['city_id'];
        return $item;
    }

    /**
     * @author  fangjianwei
     * @return array
     */
    public function getPoiCityByApi() {
        if (empty($this->apiData)) return [];
        //通过接口补充数据
        $this->info('通过接口补充数据...');
        $limit = 10;
        $maxPage = count($this->apiData) / $limit;
        $bar = $this->output->createProgressBar($maxPage);
        $returnData = [];
        for ($i = 0; $i < $maxPage; $i++) {
            sleep(1);
            $data = array_slice($this->apiData, $i * $limit, $limit);
            $result = $this->_getMulPoiCityByApi($data);
            $data = collect($data)->transform(function($item) use ($result) {
                $poiCity = $result[md5($item['center_latitude'] . '_' . $item['center_longitude'])] ?? null;
                if (empty($poiCity)) {
                    Log::setError($item, '通过接口经纬度都获取不到数据');
                    return null;
                }
                if (empty($poiCity['city_id'])) {
                    Log::setError($item, '通过接口获取到数据，但城市为空');
                    return null;
                }
                $item['zzc_city_id'] = $poiCity['city_id'];
                $item['province_info'] = [];
                Log::setInfo($item, sprintf('通过第%s种方案获得数据', 3));
                return $item;
            })->filter()->values()->toArray();

            if(!empty($data)){
                $returnData  = array_merge($returnData, $data);
            }
            $bar->advance(1);
        }
        $bar->finish();
        $this->apiData = [];
        return $returnData;
    }

    /**
     * 通过接口经纬度：第三种方案
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public function _getMulPoiCityByApi(array $eanCitys): array {
        $latLngJson = collect($eanCitys)->transform(function($item) {
            return [
                'lat' => (float)$item['center_latitude'],
                'lng' => (float)$item['center_longitude'],
            ];
        })->values()->mapToGroups(function($item) {
            return ['locations' => $item];
        })->toJson();
        $i = 0;

        while (true) {
            $result = CurlHelper::factory('https://map-api.tantu.com/city/get_citys')->setPostRaw(
                $latLngJson
            )->setTimeout(5)->setHeaders([
                'Content-Type' => 'application/json',
            ])->exec();

            if (empty($result) || $result['status'] != 200) {
                echo $latLngJson.PHP_EOL;
                var_dump($result);
                echo sprintf('调用经纬度接口出错，进行第%d次重试', $i + 1) . PHP_EOL;
                $i++;
                continue;
            }

            $content = json_decode($result['content'], true);
            if (empty($content) || !is_array($content)) {
                echo sprintf('调用返回格式有误，进行第%d次重试', $i + 1) . PHP_EOL;
                $i++;
                continue;
            }
//            if (empty($content['data']) || !is_array($content['data'])) {
//                echo sprintf('调用返回数据为空：%s，进行第%d次重试', $content['message'], $i + 1) . PHP_EOL;
//                $i++;
//                continue;
//            }
            return collect($content['data'])->keyBy(function($item) {
                return md5($item['lat'] . '_' . $item['lng']);
            })->toArray();
        }
        return [];
    }

    protected function insertData($data) {
        if (empty($data)) return;
        /**
         * @var ean_city_tbl $eanCityTbl
         */
        $eanCityTbl = app()->make(ean_city_tbl::class);
        $insert = collect($data)->transform(function($item) {
            $format = [
                'ean_region_id'                => $item['region_id'],
                'ean_city_id'                  => $item['r_id'],
                'ean_name_cn'                  => $item['temp_name_cn'] ?? $item['r_name_cn'],
                'ean_name_en'                  => $item['r_name_en'],
                'lng'                          => $item['center_longitude'],
                'lat'                          => $item['center_latitude'],
                'region_id'                    => $item['zzc_region_id'],
                'city_id'                      => $item['zzc_city_id'],
                'ean_province_state_cn'        => '',
                'ean_province_state_en'        => '',
                'ean_province_state_region_id' => '',
                'vicinity_id'                  => $item['vicinity_id'] ?? '',
            ];
            if (!empty($item['province_name'])) {
                $format = array_merge($format, [
                    'ean_province_state_cn'        => $item['province_name']['name_cn'],
                    'ean_province_state_en'        => $item['province_name']['name_en'],
                    'ean_province_state_region_id' => $item['province_name']['r_id'],
                ]);
            }
            return $format;
        })->toArray();
        $eanCityTbl->insert($insert);
    }


}
