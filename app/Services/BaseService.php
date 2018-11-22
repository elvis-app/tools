<?php
/**
 * @datetime  2018/11/22 10:01
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Services;


abstract class BaseService {

    /**
     * 错误信息
     * @var
     */
    protected $error;

    /**
     * 设置错误信息
     * @author  fangjianwei
     * @param $error
     */
    public function error($error): void {
        $this->error = $error;
    }

    /**
     * @author  fangjianwei
     * @param $error
     * @return mixed
     */
    public function getError($error) {
        return $this->error;
    }
}