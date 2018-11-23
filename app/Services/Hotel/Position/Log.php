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


    public static function saveErrorLog(): void {
        if(empty(self::$errorData)) return;
        $fp = fopen('dispose_error.csv', 'w+');
        foreach(self::$errorData as $error){
            fputcsv($fp, array_values($error));
        }
        fclose($fp);
    }

    public static function saveInfoLog(): void {
        if(empty(self::$infoData)) return;
        $fp = fopen('dispose_info.csv', 'w+');
        foreach(self::$infoData as $info){
            fputcsv($fp, array_values($info));
        }
        fclose($fp);
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
        self::$errorData[] = [
            'message' => $message,
            'id'      => $data['id'],
        ];
    }
}