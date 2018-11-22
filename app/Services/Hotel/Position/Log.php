<?php
/**
 * @datetime  2018/11/22 10:00
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Services\Hotel\Position;


use App\Services\BaseService;

class Log extends BaseService {

    /**
     * 错误信息
     * @var array
     */
    protected static $errorData = [];

    /**
     * 正常信息
     * @var array
     */
    protected static $infoData = [];


    public function saveErrorLog(): void {
    }

    public function saveInfoLog(): void {
    }

    /**
     * 设置正常信息
     * @author  fangjianwei
     * @param array $data
     * @param string $message
     */
    public static function setInfo(array $data, string $message): void {
        self::$infoData[] = [
            'message' => $message,
            'id'      => $data['id'],
        ];
    }

    /**
     * 设置错误
     * @author  fangjianwei
     * @param array $data
     * @param string $message
     */
    public static function setError(array $data, string $message): void {
//        var_dump($message);
        self::$errorData[] = [
            'message' => $message,
            'id'      => $data['id'],
        ];
    }
}