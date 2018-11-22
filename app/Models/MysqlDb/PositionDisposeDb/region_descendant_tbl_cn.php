<?php
/**
 * @datetime  2018/11/22 10:12
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Models\MysqlDb\PositionDisposeDb;


use App\Models\MysqlDb\BaseCommonModel;

class region_descendant_tbl_cn extends BaseCommonModel {

    /**
     * region_descendant_tbl_cn constructor.
     * @throws \Exception
     */
    public function __construct() {
        $this->connectName = 'local_position_dispose_mysql';
        $this->setTableName('region_descendant_tbl_cn');
        parent::__construct();
    }


}