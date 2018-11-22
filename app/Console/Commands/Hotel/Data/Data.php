<?php
/**
 * @datetime  2018/11/21 15:30
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式
namespace App\Console\Commands\Hotel\Data;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class Data {


    public $db;
    public $redisDb;


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        $this->db = Db::connection('local_position_dispose_mysql');
        $this->redisDb = Redis::connection('default');
    }

    /**
     * 通过中文名获取poi城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public function getPoiCityByNameCn(array $eanCity): array {
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
    public function getPoiCityByNameEn(array $eanCity): array {
        return $this->getPoiCityByWhere([
            ['region', $eanCity['region_id']],
            ['city_en', $eanCity['r_name_en']],
        ]);
    }


    /**
     * 获取poi城市信息
     * @author  fangjianwei
     * @param array $where
     * @return array
     */
    public function getPoiCityByWhere(array $where): array {
        dd($where);
        if (empty($where)) return [];
        return $this->db->table('city_tbl')
            ->where($where)
            ->get()
            ->transform(function($item) {
                return (array)$item;
            })
            ->toArray();
    }


}