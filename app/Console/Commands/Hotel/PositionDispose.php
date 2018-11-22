<?php

namespace App\Console\Commands\Hotel;

use Illuminate\Console\Command;
use App\Services\Hotel\Position\Redis\Data;
use App\Services\Hotel\Position\Log;

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

    protected $errorData;
    protected $infoData;

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
    public function handle() {

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
            }
            //检查缺失的下属城市id
            $lostIds = array_diff($subIds, array_column($subLists, 'r_id'));
            if (!empty($lostIds)) {
                Log::setError($data, "下属城市ID找不到数据：" . implode(',', $lostIds));
            }
            $this->disposeCity($subLists);
        }
    }


    protected function disposeCity(array $subLists) {
        if (empty($subLists)) return;

        $data = collect($subLists)->transform(function($item) {
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
            if (empty($poiCity)) {
                Log::setError($item, '找不到租租车城市数据');
                return null;
            }

            if (empty($poiCity['city_id'])) {
                Log::setError($item, '找到租租车城市数据，但城市ID为空');
                return null;
            }
            dd($poiCity);

//            if (empty($poiCity)) {
//                //执行第三种方案：通过接口经纬度获取
//                $poiCity = $dataDbService->getPoiCityByApi($item);
//                if (empty($poiCity)) {
//                    $this->setError($item, '通过接口经纬度都获取不到数据');
//                    return null;
//                }
//                if (empty($poiCity['city_id'])) {
//                    $this->setError($item, '通过接口获取到数据，但城市为空');
//                    return null;
//                }
//            }
            Log::setError($item, sprintf('通过第%s种方案获得数据', $poiCity['scheme']));

            $item['zzc_city_id'] = $poiCity['city_id'];
            return $item;
        })->filter()->values()->toArray();
    }

    /**
     * @author  fangjianwei
     * @throws \Exception
     */
    public function handle_temp() {
        //加载ean城市最小单位数据
        $this->info('加载ean城市最小单位数据...');
        $dataService = new Data();
        $dataDbService = new DataByDb();


        //加载ean对应租租车国家表数据
        $this->info('加载ean对应租租车国家表数据...');
        $eanZzcRegionLists = $dataService->getEanZzcRegionLists();
        if (empty($eanZzcRegionLists)) {
            $this->error('ean对应租租车国家表数据为空！');
            exit;
        }

        $eanCityTotal = $dataDbService->getEanCityTotal();

        $limit = 100;
        $maxPage = ceil($eanCityTotal / $limit);
        $bar = $this->output->createProgressBar($maxPage);

        for ($i = 0; $i < $maxPage; $i++) {
            //获取此时总数
            $eanCityLists = $dataDbService->getEanCityLists($i * $limit, $limit);
            if (empty($eanCityLists)) {
                $this->error('ean没有城市信息！');
                exit;
            }

            $data = collect($eanCityLists)->transform(function($item) use ($eanZzcRegionLists, $dataDbService) {
                //查到与租租车关联的国家ID
                $eanZzcRegion = $eanZzcRegionLists[$item['region_id']] ?? null;
                if (empty($eanZzcRegion)) {
                    $this->setError($item, '找不到ean与租租车关联国家信息，不执行此条数据');
                    return null;
                }
                $item['zzc_region_id'] = $eanZzcRegion['region_id'];
                $item['province_name'] = [];

                //查找省名称
                $eanProvinceLists = $dataDbService->getEanProvince($item);
                if (empty($eanProvinceLists)) {
                    $this->setInfo($item, '找不到省数据，执行第二种方案');
                } else {
                    //如果多个省，则忽略
                    if (count($eanProvinceLists) > 1) {
                        $this->setError($item, '存在多条省数据，不执行此条数据');
                        return null;
                    }
                    $eanProvince = current($eanProvinceLists);
                    $item['province_name'] = [
                        'r_id'    => $eanProvince['r_id'],
                        'name_cn' => $eanProvince['r_name_cn'],
                        'name_en' => $eanProvince['r_name_en'],
                    ];
                }
                if (!empty($item['province_name'])) {
                    //执行第一种方案：带省信息
                    $poiCity = $dataDbService->getPoiCityByNameState($item);
                    if (empty($poiCity)) {
                        //执行第二种方案：不带省信息
                        $poiCity = $dataDbService->getPoiCityByName($item);
                    }
                } else {
                    //执行第二种方案：不带省信息
                    $poiCity = $dataDbService->getPoiCityByName($item);
                }

                if (empty($poiCity)) {
                    $this->setError($item, '找不到租租车城市数据');
                    return null;
                }

                if (empty($poiCity['city_id'])) {
                    $this->setError($item, '找到租租车城市数据，但城市ID为空');
                    return null;
                }

//            if (empty($poiCity)) {
//                //执行第三种方案：通过接口经纬度获取
//                $poiCity = $dataDbService->getPoiCityByApi($item);
//                if (empty($poiCity)) {
//                    $this->setError($item, '通过接口经纬度都获取不到数据');
//                    return null;
//                }
//                if (empty($poiCity['city_id'])) {
//                    $this->setError($item, '通过接口获取到数据，但城市为空');
//                    return null;
//                }
//            }
                $this->setInfo($item, sprintf('通过第%s种方案获得数据', $poiCity['scheme']));

                $item['zzc_city_id'] = $poiCity['city_id'];
                return $item;
            })->filter()->values()->toArray();
//            $this->insertData($data);
            $this->log();
            $bar->advance(1);
        }
        return;
    }

    protected function insertData($data) {
        if (empty($data)) return;

        $insert = collect($data)->transform(function($item) {
            $format = [
                'ean_region_id'                => $item['region_id'],
                'ean_city_id'                  => $item['r_id'],
                'ean_name_cn'                  => $item['r_name_cn'],
                'ean_name_en'                  => $item['r_name_en'],
                'lng'                          => $item['center_longitude'],
                'lat'                          => $item['center_latitude'],
                'region_id'                    => $item['zzc_region_id'],
                'city_id'                      => $item['zzc_city_id'],
                'ean_province_state_cn'        => '',
                'ean_province_state_en'        => '',
                'ean_province_state_region_id' => '',
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
        $this->db->table('ean_city_tbl')->insert($insert);
    }


    public function log() {
        dd($this->infoData);
    }


}
