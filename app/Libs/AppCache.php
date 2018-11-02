<?php
/**
 * @datetime  2018/8/13 14:04
 * @author    fangjianwei
 * @copyright www.weicut.com
 */
declare(strict_types = 1);//默认严格模式
namespace App\Libs;

use Illuminate\Support\Facades\Cache;

class AppCache {

    /**
     * 是否开启缓存
     * @var bool
     */
    public static $cache = true;

    /**
     * 获取缓存
     * @author  fangjianwei
     * @param $key
     * @return mixed|null
     */
    public static function get($key) {
        if (false == self::$cache) return null;
        return Cache::get($key);
    }

    /**
     * 永久保存缓存
     * @author  fangjianwei
     * @param $key
     * @param $value
     */
    public static function forever($key, $value): void {
        Cache::forever($key, $value);
    }

    /**
     * 清除缓存
     * @author  fangjianwei
     * @param $key
     */
    public static function forget($key): void {
        Cache::forget($key);
    }

}