<?php
/**
 * @datetime  2018/11/15 17:08
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Libs;


class GeoHelper {



    /**
     * 计算两个经纬度距离
     * @param $lat1
     * @param $lng1
     * @param $lat2
     * @param $lng2
     * @return float 单位：m
     */
    public static function getDistance($lat1, $lng1, $lat2, $lng2) {
        //将角度转为狐度

        $radLat1 = deg2rad($lat1);//deg2rad()函数将角度转换为弧度

        $radLat2 = deg2rad($lat2);

        $radLng1 = deg2rad($lng1);

        $radLng2 = deg2rad($lng2);

        $a = $radLat1 - $radLat2;

        $b = $radLng1 - $radLng2;

        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6378.137;

        return $s * 1000;
    }

}