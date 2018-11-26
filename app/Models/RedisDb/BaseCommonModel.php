<?php
/**
 * @datetime  2018/11/22 14:17
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Models\RedisDb;


use Illuminate\Support\Facades\Redis;

class BaseCommonModel {

    /**
     * @var
     */
    protected $db;

    public function __construct() {
        $this->db = Redis::connection();
    }

    /**
     * @author  fangjianwei
     * @param string $key
     * @param $data
     * @param int $expire
     * @throws \Exception
     */
    public function redisSet(string $key, $data): void {
        if (is_object($data) || is_array($data)) {
            $data = json_encode($data);
            if (json_last_error() > 0) {
                throw new \Exception('Json压缩出错：' . json_last_error_msg());
            }
        }
        $this->db->set($key, $data);
        return;
    }


    /**
     * @author  fangjianwei
     * @param string $key
     * @return array|string
     */
    public function redisGet(string $key) {
//        echo sprintf("redis get：%s", $key).PHP_EOL;
        $result = $this->db->get($key);
        if (is_null($result)) return null;
        $data = json_decode($result, true);
        if (is_array($data)) return $data;
        return $result;
    }
}