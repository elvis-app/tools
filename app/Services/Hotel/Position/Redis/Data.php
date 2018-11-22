<?php
/**
 * @datetime  2018/11/22 10:01
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Services\Hotel\Position\Redis;

use App\Services\BaseService;
use App\Models\RedisDb\Hotel\Position;
use App\Libs\GeoHelper;

class Data extends BaseService {

    /**
     * 周边数据
     * @var array
     */
    protected static $peripheral = [];

    /**
     * 周边下级数据
     * @var array
     */
    protected static $peripheralSubMap = [];

    /**
     * ean对应租租车国家数据
     * @var array
     */
    protected static $eanZzcRegionMap = [];

    /**
     * 租租车城市表
     * @var array
     */
    protected static $zzcCity = [];

    /**
     * 租租车城市索引
     * @var array
     */
    protected static $zzcCityIndex = [];

    /**
     * ean省数据
     * @var array
     */
    protected static $eanProvince = [];

    /**
     * ean省数据索引
     * @var array
     */
    protected static $eanProvinceIndex = [];

    /**
     * 加载周边数据
     * @author  fangjianwei
     * @return bool
     * @throws \Exception
     */
    public static function loadPeripheral(): bool {
        /** @var Position $positionDb */
        $positionDb = app()->make(Position::class);
        self::$peripheral = $positionDb->getPeripheralLists();
        if (empty(self::$peripheral)) return false;
        return true;
    }

    /**
     * 加载周边下级数据
     * @author  fangjianwei
     * @return bool
     * @throws \Exception
     */
    public static function loadPeripheralSubMap(): bool {
        if (empty(self::$peripheral)) return false;

        /** @var Position $positionDb */
        $positionDb = app()->make(Position::class);
        self::$peripheralSubMap = $positionDb->getPeripheralSubMap(self::$peripheral);

        if (empty(self::$peripheralSubMap)) return false;
        return true;
    }

    /**
     * 加载ean对应租租车国家表数据
     * @author  fangjianwei
     * @return bool
     * @throws \Exception
     */
    public static function loadEanZzcRegionMap(): bool {
        /** @var Position $positionDb */
        $positionDb = app()->make(Position::class);
        self::$eanZzcRegionMap = $positionDb->getEanZzcRegionMap();
        if (empty(self::$eanZzcRegionMap)) return false;
        return true;
    }

    /**
     * 加载租租车城市表数据
     * @author  fangjianwei
     * @return bool
     * @throws \Exception
     */
    public static function loadZzcCity():bool{
        /** @var Position $positionDb */
        $positionDb = app()->make(Position::class);
        self::$zzcCity = $positionDb->getZzcCity();
        if (empty(self::$zzcCity)) return false;
        return true;
    }

    /**
     * 建立租租车城市表索引
     * @author  fangjianwei
     * @return bool
     */
    public static function buildZzcCityIndex():bool{
        if(empty(self::$zzcCity)) return false;
        foreach(self::$zzcCity as $index => $city){

            //建立国家ID、城市中文名、省中文名联合索引
            self::$zzcCityIndex[
                self::buildIndex((int)$city['region'], $city['city_cn'], $city['state_cn'])
            ][] = $index;

            //建立国家ID、城市英文名、省英文名联合索引
            self::$zzcCityIndex[
            self::buildIndex((int)$city['region'], $city['city_en'], $city['state_en'])
            ][] = $index;

            //建立国家ID。城市中文名联合索引
            self::$zzcCityIndex[
            self::buildIndex((int)$city['region'], $city['city_cn'])
            ][] = $index;

            //建立国家ID。城市英文名联合索引
            self::$zzcCityIndex[
            self::buildIndex((int)$city['region'], $city['city_en'])
            ][] = $index;

        }
        return true;
    }

    /**
     * 加载ean省数据
     * @author  fangjianwei
     * @return bool
     * @throws \Exception
     */
    public static function loadProvince(): bool {
        /** @var Position $positionDb */
        $positionDb = app()->make(Position::class);
        self::$eanProvince = $positionDb->getProvince();
        if (empty(self::$eanProvince)) return false;
        return true;
    }

    /**
     * 建立ean省数据索引
     * @author  fangjianwei
     * @return bool
     */
    public static function buildProvinceIndex():bool{
        if(empty(self::$eanProvince)) return false;
        foreach(self::$eanProvince as $index => $province){
            if(empty($province['r_city_list'])) continue;
            $cityIds = explode(',', $province['r_city_list']);

            foreach($cityIds as $cityId){
                //建立国家ID。城市ID联合索引
                self::$eanProvinceIndex[
                self::buildIndex((int)$province['region_id'], (int)$cityId)
                ][] = $index;
            }
        }
        return true;
    }

    /**
     * 建立索引值
     * @author  fangjianwei
     * @param mixed ...$indexName
     * @return string
     */
    private static function buildIndex(...$indexName):string{
        return md5(json_encode($indexName));
    }


    /**
     * 获取周边数据
     * @author  fangjianwei
     * @return bool
     * @throws \Exception
     */
    public static function getPeripheral(): array {
        return self::$peripheral;
    }


    /**
     * 通过周边下属城市ID列表获取数据
     * @author  fangjianwei
     * @param array $ids
     * @return array
     */
    public static function getPeripheralSubByIds(int $regionId, array $ids){
        $data = [];

        foreach($ids as $id){
            $key = $regionId.'_'.$id;
            if(!empty(self::$peripheralSubMap[$key])){
                $data[] = self::$peripheralSubMap[$key];
            }
        }

        return $data;
    }


    /**
     * 通过ean国家ID获取ean对应租租车国家表数据
     * @author  fangjianwei
     * @param int $eanRegionId
     * @return array
     */
    public static function getEanZzcRegionByEanRegion(int $eanRegionId): array {
        return self::$eanZzcRegionMap[$eanRegionId] ?? [];
    }


    /**
     * 获取ean省数据
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public static function getEanProvince(array $eanCity):array{
        //根据国家ID与ean城市ID获取索引值
        $key = self::buildIndex((int)$eanCity['region_id'], (int)$eanCity['r_id']);
        $index = self::$eanProvinceIndex[$key] ?? null;
        if(is_null($index)) return [];
        $index = array_unique($index);

        $data = [];
        foreach($index as $i){
            $data[] = self::$eanProvince[$i];
        }
        return $data;
    }



    /**
     * 通过城市名称获取租租车城市信息：第一种方案
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    public static function getZzcCityByName(array $eanCity): array {
        $lists = self::getZzcCityByNameCn($eanCity);
        if (empty($lists)) {
            $lists = self::getZzcCityByNameEn($eanCity);
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
    public static function getZzcCityByNameState(array $eanCity): array {
        $lists = static::getZzcCityByNameStateCn($eanCity);
        if (empty($lists)) {
            $lists = self::getZzcCityByNameStateEn($eanCity);
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
     * 通过中文名获取Zzc城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected static function getZzcCityByNameCn(array $eanCity): array {
        return self::getZzcCityByValues([
            (int)$eanCity['zzc_region_id'],
            $eanCity['r_name_cn'],
        ]);
    }

    /**
     * 通过英文名获取Zzc城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected static function getZzcCityByNameEn(array $eanCity): array {
        return self::getZzcCityByValues([
            (int)$eanCity['zzc_region_id'],
            !empty($eanCity['r_name_en']) ? $eanCity['r_name_en'] : $eanCity['r_name_cn']
        ]);
    }

    /**
     * 通过中文名获取Zzc城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected static function getZzcCityByNameStateCn(array $eanCity): array {
        return self::getZzcCityByValues([
            (int)$eanCity['zzc_region_id'],
            $eanCity['r_name_cn'],
            $eanCity['province_info']['name_cn']
        ]);
    }

    /**
     * 获取Zzc城市
     * @author  fangjianwei
     * @param array $values
     * @return array
     */
    protected static function getZzcCityByValues($values = []){
        if(empty($values)) return [];
        dd(count(self::$peripheralSubMap));
        $key = self::buildIndex($values);
        $index = self::$zzcCityIndex[$key] ?? null;
        if(is_null($index)) return [];
        $index = array_unique($index);

        $data = [];
        foreach($index as $i){
            $data[] = self::$zzcCity[$i];
        }
        return $data;
    }

    /**
     * 通过英文名获取Zzc城市信息
     * @author  fangjianwei
     * @param array $eanCity
     * @return array
     */
    protected static function getZzcCityByNameStateEn(array $eanCity): array {
        return self::getZzcCityByValues([
            (int)$eanCity['zzc_region_id'],
            !empty($eanCity['r_name_en']) ? $eanCity['r_name_en'] : $eanCity['r_name_cn'],
            $eanCity['province_info']['name_en']
        ]);
    }
}