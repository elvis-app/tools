<?php
/**
 * @datetime  2018/11/22 14:19
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Models\RedisDb\Hotel;


use App\Models\RedisDb\BaseCommonModel;
use App\Models\MysqlDb\PositionDisposeDb\region_descendant_tbl_cn;
use App\Models\MysqlDb\PositionDisposeDb\ean_region_tbl;
use App\Models\MysqlDb\PositionDisposeDb\city_tbl;

class Position extends BaseCommonModel {

    /**
     * @author  fangjianwei
     * @return array|string
     * @throws \Exception
     */
    public function getPeripheralLists(): array {
        $key = md5(__METHOD__);
        $data = $this->redisGet($key);
        if (!empty($data) && is_array($data)) return $data;

        /** @var region_descendant_tbl_cn $regionDescendantTblCn */
        $regionDescendantTblCn = app()->make(region_descendant_tbl_cn::class);

        $data = (array)$regionDescendantTblCn->select([
            ['r_type', 'multi_city_vicinity'],
        ]);
        $this->redisSet($key, $data);
        return $data;
    }

    /**
     * @author  fangjianwei
     * @param array $peripheral
     * @return array
     * @throws \Exception
     */
    public function getPeripheralSubMap(array $peripheral): array {
        $key = md5(__METHOD__);
        $data = $this->redisGet($key);
        if (!empty($data) && is_array($data)) return $data;

        /** @var region_descendant_tbl_cn $regionDescendantTblCn */
        $regionDescendantTblCn = app()->make(region_descendant_tbl_cn::class);

        $rows = collect($peripheral)->transform(function($item) {
            if (empty($item['r_city_list'])) return null;
            $rCityIds = array_filter(array_map('trim', explode(',', $item['r_city_list'])));
            return [
                'region_id' => $item['region_id'],
                'sub_id'    => $rCityIds,
            ];
        })->filter()->transform(function($item) use ($regionDescendantTblCn) {
            $subIdStr = implode(',', $item['sub_id']);
            $where = "region_id={$item['region_id']} AND r_type='city' AND r_id IN({$subIdStr})";
            return $regionDescendantTblCn->select($where);
        })->filter()->toArray();
        if(empty($rows)) return [];
        $data = [];
        foreach($rows as $row){
            foreach($row as $val){
                $data[$val['region_id'].'_'.$val['r_id']] = $val;
            }
        }
        $this->redisSet($key, $data);

        return $data;
    }

    /**
     * @author  fangjianwei
     * @return array
     * @throws \Exception
     */
    public function getEanZzcRegionMap():array{
        $key = md5(__METHOD__);
        $data = $this->redisGet($key);
        if (!empty($data) && is_array($data)) return $data;

        /** @var ean_region_tbl $eanRegionTbl */
        $eanRegionTbl = app()->make(ean_region_tbl::class);

        $data = collect($eanRegionTbl->select())->keyBy('ean_region_id')->toArray();
        $this->redisSet($key, $data);
        return $data;
    }

    /**
     * @author  fangjianwei
     * @return array
     * @throws \Exception
     */
    public function getZzcCity():array{
        $key = md5(__METHOD__);
        $data = $this->redisGet($key);
        if (!empty($data) && is_array($data)) return $data;

        /** @var city_tbl $cityTbl */
        $cityTbl = app()->make(city_tbl::class);

        $data = $cityTbl->select(['is_del' => 0]);
        $this->redisSet($key, $data);
        return $data;
    }

    /**
     * @author  fangjianwei
     * @return array|string
     * @throws \Exception
     */
    public function getProvince(): array {
        $key = md5(__METHOD__);
        $data = $this->redisGet($key);
        if (!empty($data) && is_array($data)) return $data;

        /** @var region_descendant_tbl_cn $regionDescendantTblCn */
        $regionDescendantTblCn = app()->make(region_descendant_tbl_cn::class);

        $data = (array)$regionDescendantTblCn->select([
            ['r_type', 'province_state'],
        ]);
        $this->redisSet($key, $data);
        return $data;
    }

}