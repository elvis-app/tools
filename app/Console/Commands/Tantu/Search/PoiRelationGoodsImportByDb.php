<?php

namespace App\Console\Commands\Tantu\Search;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use CurlHelper;

class PoiRelationGoodsImportByDb extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:Search:PoiRelationGoodsImportByDb {poi_type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'POI商品关联通过数据库导入初始化数据';

    /**
     * es地址
     * @var
     */
    protected $dst;

    /**
     * 每次处理数量
     * @var int
     */
    protected $processTotal = 100;

    protected $now;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        $this->dst = config('elasticsearch')['connections']['es_1']['hosts'][0];
        $this->now = time();
        parent::__construct();
    }

    /**
     * @author  fangjianwei
     * @throws \Exception
     */
    public function handle(): void {
        $poiType = $this->argument('poi_type');
        $length = $this->getCount($poiType);
        if ($length == 0) {
            $this->info('暂时没有数据处理');
            return;
        }

        $pageCount = (int)ceil($length / $this->processTotal);
        $bar = $this->output->createProgressBar(ceil($length / $this->processTotal));

        $rows = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $details = $this->getDetails($poiType, $i * $this->processTotal, $this->processTotal);
            foreach ($details as $detail) {
                $detail = (array)$detail;
                $rows[] = [
                    'poi_id'      => (int)$detail['poi_id'],
                    'poi_type'    => trim($detail['poi_type'], '_tbl'),
                    'is_accurate' => (int)$detail['is_accurate'],
                    'goods_id'    => (string)$detail['goods_id'],
//                    'add_time'    => $detail['add_time'],
//                    'update_time' => $detail['update_time'],
                ];
            }
            $bar->advance();

        }
        $this->insertDataToEs($rows);
//        $this->insertDataToDb($rows);
        $bar->finish();
    }


    /**
     * 获取关联详情
     * @author  fangjianwei
     * @param array $ids
     * @return array
     */
    public function getDetails(string $poiType, int $offset, int $limit): array {
        $data = DB::connection('local_mysql')
            ->table('poi_goods_rel')
            ->where(['poi_type' => $poiType])
            ->offset($offset)
            ->limit($limit)
            ->get(['poi_id', 'poi_type', 'is_accurate', 'goods_id', 'add_time', 'update_time']);
        return (array)$data->toArray();
    }


    /**
     * 获取记录数
     * @author  fangjianwei
     * @param string $poiType
     * @return int
     */
    public function getCount(string $poiType): int {
        return DB::connection('local_mysql')
            ->table('poi_goods_rel')
            ->where(['poi_type' => $poiType])->count();
    }


    /**
     * 插入到es
     * @author  fangjianwei
     * @param array $rows
     */
    public function insertDataToEs(array $rows) {
        if (empty($rows)) return;
        $hits = [];
        foreach ($rows as $val) {
            if (empty($hits[$val['poi_id']])) {
                $hits[$val['poi_id']] = [];
            }
            array_push($hits[$val['poi_id']], $val);
        }


        $list = "";
        foreach ($hits as $poiId => $hit) {
            $info = [];
            $info['poi_info']['poi_id'] = $hit[0]['poi_id'];
            $info['poi_info']['poi_type'] = $hit[0]['poi_type'];
            $info['goods_info'] = [];
            $info['es_update_time'] = $this->now * 1000;
            foreach ($hit as $h) {
                array_push($info['goods_info'], [
                    'goods_id'    => $h['goods_id'],
                    'is_accurate' => $h['is_accurate'],
                    'add_type'    => 1,
                ]);
            }
            $list .= json_encode(["index" => ['_id' => $poiId.'_'.$hit[0]['poi_type']]], JSON_UNESCAPED_UNICODE) . "\n";
            $list .= json_encode($info, JSON_UNESCAPED_UNICODE) . "\n";
        }
        $success = CurlHelper::factory("http://{$this->dst}/poi_goods_rel/relations/_bulk")
            ->setPostRaw($list)->setHeaders(['Content-Type' => 'application/x-ndjson'])->exec();
        $success = CurlHelper::factory("http://{$this->dst}/poi_goods_rel_test/relations/_bulk")
            ->setPostRaw($list)->setHeaders(['Content-Type' => 'application/x-ndjson'])->exec();
        if (!empty($success['data']) && $success['status'] == 200) {
//            $success = json_decode(json_encode($success['data']));
//            $this->info(sprintf("Success: %d", count($success->items)));
        } else {
            $this->error('更新失败');
        }
    }

    /**
     * 插入到数据库
     * @author  fangjianwei
     * @param array $rows
     * @throws \Exception
     */
    public function insertDataToDb(array $rows) {
        if (empty($rows)) return;
        $bulk = [];
        $length = count($rows);
        foreach ($rows as $key => $val) {
            $val['_db_update_time'] = $this->now;
            $val['add_type'] = 1;
            $bulk[] = $val;
            if (count($bulk) != $this->processTotal && $key != $length - 1) continue;
            $result = DB::connection('30002_mysql')
                ->table('poi_goods_relations_tbl')->insert($bulk);
            if (true !== $result) {
                throw new \Exception('db插入失败');
            }
        }
    }

}
