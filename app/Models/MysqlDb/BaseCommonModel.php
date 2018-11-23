<?php
/**
 * @datetime  2018/11/22 10:13
 * @author    fangjianwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Models\MysqlDb;
use Illuminate\Support\Facades\DB;

abstract class BaseCommonModel {

    /**
     * @var
     */
    protected $db;

    /**
     * 表名
     * @var
     */
    protected $tableName;

    /**
     * 数据库名
     * @var
     */

    /**
     * 链接名
     * @var
     */
    protected $connectName;


    /**
     * BaseCommonModel constructor.
     * @throws \Exception
     */
    public function __construct() {
        $this->db = DbInstance::getDbInstance($this->connectName);

        DB::listen(
            function($sql) {
                foreach ($sql->bindings as $i => $binding) {
                    if ($binding instanceof \DateTime) {
                        $sql->bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
                    } else {
                        if (is_string($binding)) {
                            $sql->bindings[$i] = "'$binding'";
                        }
                    }
                }

                // Insert bindings into query
                $query = str_replace(array('%', '?'), array('%%', '%s'), $sql->sql);

                $query = vsprintf($query, $sql->bindings);
//                echo $query . PHP_EOL;
            }
        );
    }


    /**
     * 设置表名
     * @author  fangjianwei
     * @param string $tableName
     */
    protected function setTableName(string $tableName): void {
        $this->tableName = $tableName;
    }

    /**
     * @author  fangjianwei
     * @return \Illuminate\Database\ConnectionInterface|mixed
     */
    protected function db(){
        return $this->db;
    }

    /**
     * 获取总条数
     * @author  fangjianwei
     * @param array|string $where
     * @return int
     */
    public function count($where = null):int{
        $instance = $this->db->table($this->tableName);
        if(is_null($where)) return $instance->count();
        if(is_string($where)){
            return $instance->whereRaw($where)->count();
        }
        return $instance->where($where)->count();
    }

    /**
     * 查询数据
     * @author  fangjianwei
     * @param array|string $where
     * @param array $fields
     * @param array $orderBy
     * @param array $groupBy
     * @return array
     */
    public function select($where = null, array $fields = ['*'], array $orderBy = [], array $groupBy = []):array{
        $instance = $this->db->table($this->tableName);
        if(!empty($where)){
            $instance = is_string($where) ? $instance->whereRaw($where) : $instance->where($where);
        }
        if(!empty($orderBy)){
            foreach($orderBy as $order => $by){
                $instance = $instance->orderBy($order, $by);
            }
        }

        if(!empty($groupBy)){
            $instance = $instance->groupBy($groupBy);
        }

        return $instance->get($fields)->transform(function($item){return (array)$item;})->toArray();
    }

    /**
     * 修改数据
     * @author  fangjianwei
     * @param $where
     * @param $update
     * @return null
     */
    public function update($where, $update):?int{
        if(empty($where) || empty($update)) return null;
        $instance = $this->db->table($this->tableName);
        $instance = is_string($where) ? $instance->whereRaw($where) : $instance->where($where);
        return $instance->update($update);
    }

    /**
     * 删除数据
     * @author  fangjianwei
     * @param $where
     * @return int|null
     */
    public function delete($where):?int{
        if(empty($where)) return null;
        $instance = $this->db->table($this->tableName);
        $instance = is_string($where) ? $instance->whereRaw($where) : $instance->where($where);
        return $instance->delete();
    }

    /**
     * @author  fangjianwei
     * @param $data
     * @return bool
     */
    public function insert($data):bool{
        $instance = $this->db->table($this->tableName);
        return $instance->insert($data);
    }

    /**
     * @author  fangjianwei
     * @param $data
     * @return bool
     */
    public function replace($data):bool{
        $instance = $this->db->table($this->tableName);
        return $instance->updateOrInsert($data);
    }
}