<?php
/**
 * @datetime  2018/9/25 16:42
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Libs\Es;


class GeoPoint {
    /**
     * @var float
     */
    public $lat;
    /**
     * @var float
     */
    public $lon;

    public static function build(float $lat, float $lon): self {
        $obj = new GeoPoint();
        $obj->lat = $lat;
        $obj->lon = $lon;
        return $obj;
    }

    public function toArray(): array {
        return [
            'lat' => (double)$this->lat,
            'lon' => (double)$this->lon,
        ];
    }
}