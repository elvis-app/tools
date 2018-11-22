<?php
/**
 * @datetime  2018/11/22 10:21
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Models\MysqlDb;

use Illuminate\Support\Facades\DB;

class DbInstance {


    /**
     * db实例
     * @var array
     */
    protected static $dbInstance = [];

    /**
     * @author  fangjianwei
     * @param string $configName
     * @return \Illuminate\Database\ConnectionInterface|mixed
     * @throws \Exception
     */
    public static function getDbInstance(string $configName) {
        if (empty($configName)) {
            throw new \Exception('db配置名为空');
        };

        if (!empty(self::$dbInstance[$configName])) return self::$dbInstance[$configName];

        return self::$dbInstance[$configName] = Db::connection($configName);
    }
}