<?php
/**
 * @datetime  2018/11/21 15:30
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式
namespace App\Console\Commands\Hotel\Data;

use Illuminate\Support\Facades\DB;
use App\Libs\GeoHelper;
use CurlHelper;

class DataByDb {


    public $db;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        $this->db = DB::connection('local_position_dispose_mysql');
        DB::listen(
            function($sql) {
                foreach ($sql->bindings as $i => $binding) {
                    if ($binding instanceof \DateTime) {
                        $sql->bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
                    } else {
                        if (is_string($binding)) {
                            $sql->bindings[$i] = "'$binding'";
                        }
                    }
                }

                // Insert bindings into query
                $query = str_replace(array('%', '?'), array('%%', '%s'), $sql->sql);

                $query = vsprintf($query, $sql->bindings);
//                echo $query . PHP_EOL;
            }
        );
    }

    /**
     * 通过接口经纬度：第三种方案
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public function getPoiCityByApi(array $eanCity): array {
        //只重试3次
        $i = 0;
        while ($i < 3) {
            $result = CurlHelper::factory('https://map-api.tantu.com/city/get_city')->setGetParams([
                'lat' => (float)$eanCity['center_latitude'],
                'lng' => (float)$eanCity['center_longitude'],
            ])->setTimeout(5)->exec();

            if (empty($result) || $result['status'] != 200) {
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
            if (empty($content['data']) || !is_array($content['data'])) {
                echo sprintf('调用返回数据为空：%s，进行第%d次重试', $content['message'], $i + 1) . PHP_EOL;
                $i++;
                continue;
            }
            $data = current($content['data']);
             return [
                 'region'  => $data['region'],
                 'city_id' => $data['city_id'],
                 'scheme'  => 3,
             ];
        }

        return [];
    }


    /**
     * 通过城市名称获取租租车城市信息：第一种方案
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public function getPoiCityByName(array $eanCity): array {
        $lists = $this->getPoiCityByNameCn($eanCity);
        if (empty($lists)) {
            $lists = $this->getPoiCityByNameEn($eanCity);
        }
        if (empty($lists)) return [];

        $data = collect($lists)->transform(function($item) use ($eanCity) {
            //计算ean与租租车城市经纬度
            $distance = GeoHelper::getDistance(
                (float)$eanCity['center_latitude'],
                (float)$eanCity['center_longitude'],
                (float)$item['lat'],
                (float)$item['lng']
            );
            if ($distance > 50000) { //大于50公里则放弃
                return null;
            }
            $item['distance'] = $distance;
            return $item;
        })->filter()->sortBy('distance')->first();

        return [
            'region'  => $data['region'],
            'city_id' => $data['city'],
            'scheme'  => 2,
        ];
    }

    /**
     * 通过省和城市名称获取租租车城市信息：第一种方案
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public function getPoiCityByNameState(array $eanCity): array {
        $lists = $this->getPoiCityByNameStateCn($eanCity);
        if (empty($lists)) {
            $lists = $this->getPoiCityByNameStateEn($eanCity);
        }
        if (empty($lists)) return [];

        //多条，只拿城市ID最小的
        $data = collect($lists)->sortBy('city')->first();
        return [
            'region'  => $data['region'],
            'city_id' => $data['city'],
            'scheme'  => 1,
        ];
    }


    /**
     * 通过中文名获取poi城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected function getPoiCityByNameCn(array $eanCity): array {
        return $this->getPoiCityByWhere([
            ['region', $eanCity['zzc_region_id']],
            ['city_cn', $eanCity['r_name_cn']],
        ]);
    }

    /**
     * 通过英文名获取poi城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected function getPoiCityByNameEn(array $eanCity): array {
        return $this->getPoiCityByWhere([
            ['region', $eanCity['zzc_region_id']],
            ['city_en', !empty($eanCity['r_name_en']) ? $eanCity['r_name_en'] : $eanCity['r_name_cn']],
        ]);
    }

    /**
     * 通过中文名获取poi城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected function getPoiCityByNameStateCn(array $eanCity): array {
        return $this->getPoiCityByWhere([
            ['region', $eanCity['zzc_region_id']],
            ['city_cn', $eanCity['r_name_cn']],
            ['state_cn', $eanCity['province_name']['name_cn']],
        ]);
    }

    /**
     * 通过英文名获取poi城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected function getPoiCityByNameStateEn(array $eanCity): array {
        return $this->getPoiCityByWhere([
            ['region', $eanCity['zzc_region_id']],
            ['city_en', !empty($eanCity['r_name_en']) ? $eanCity['r_name_en'] : $eanCity['r_name_cn']],
            ['state_en', $eanCity['province_name']['name_en']],
        ]);
    }


    /**
     * 获取poi城市信息
     * @author  fangjianwei
     * @param array $where
     * @return array
     */
    public function getPoiCityByWhere(array $where): array {
        if (empty($where)) return [];
        return $this->db->table('city_tbl')
            ->where($where)
            ->get(['city', 'lat', 'lng', 'region'])
            ->transform(function($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
 * 获取ean城市
 * @author  fangjianwei
 * @param int $offset
 * @param int $limit
 * @return array
 */
    public function getEanCityLists(int $offset, int $limit) {
        return $this->db->table('region_descendant_tbl_cn')
            ->where([
                ['r_type', 'city'],
//                ['region_id', 37],
            ])
//            ->orderBy('r_name_cn', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->transform(function($item) {
                return (array)$item;
            })
            ->toArray();
    }

    /**
     * 获取ean总数
     * @author  fangjianwei
     * @return int
     */
    public function getEanCityTotal() :int{
        return $this->db->table('region_descendant_tbl_cn')
            ->where([
                ['r_type', 'city'],
            ])->count();
    }

    /**
     * 获取ean省信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public function getEanProvince(array $eanCity): array {
        $data = $this->db->table('region_descendant_tbl_cn')
            ->where([
                ['r_type', 'province_state'],
                ['region_id', $eanCity['region_id']],
            ])
            ->WhereRaw("FIND_IN_SET(?,r_city_list)", [$eanCity['r_id']])
            ->get()
            ->transform(function($item) {
                return (array)$item;
            })
            ->toArray();

        return $data;
    }


}