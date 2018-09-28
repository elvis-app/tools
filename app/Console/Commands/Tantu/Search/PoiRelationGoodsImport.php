<?php

namespace App\Console\Commands\Tantu\Search;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use CurlHelper;

class PoiRelationGoodsImport extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:search:poiRelationGoodsImport {file_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'POI商品关联导入初始化数据';

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
        $fileName = $this->argument('file_name');
        $data = file($fileName);
        $length = count($data);
        $bar = $this->output->createProgressBar(ceil($length / $this->processTotal));
        $ids = $rows = [];
        foreach ($data as $key => $val) {
            if ($key === 0 || trim($val) == "") continue;
            $val = array_map(function($data) {
                return trim($data, "\r\n\"");
            }, explode(',', $val));
            if (empty($val)) continue;
            array_push($ids, (int)$val[0]);
            if (count($ids) != $this->processTotal && $key != $length - 1) continue;
            $details = $this->getDetails($ids);
            if (empty($details) || count($details) != count($ids)) {
                throw new \Exception(sprintf('记录不完整'));
            }
            $ids = [];
            foreach ($details as $detail) {
                $detail = (array)$detail;
                $rows[] = [
                    'region_id'   => $detail['region_id'],
                    'city_id'     => $detail['city_id'],
                    'poi_id'      => $detail['poi_id'],
                    'poi_type'    => trim($detail['poi_type'], '_tbl'),
                    'is_accurate' => $detail['is_accurate'],
                    'score'       => $detail['score'],
                    'goods_id'    => $detail['goods_id'],
                ];
            }
            $bar->advance();
        }

        $this->insertDataToEs($rows);
        $this->insertDataToDb($rows);
        $bar->finish();
    }

    /**
     * 获取关联详情
     * @author  fangjianwei
     * @param array $ids
     * @return array
     */
    public function getDetails(array $ids): array {
        $data = DB::connection('local_mysql')
            ->table('poi_goods_rel')
            ->whereIn('id', $ids)
            ->get(['region_id', 'city_id', 'poi_id', 'poi_type', 'is_accurate', 'score', 'goods_id']);
        return (array)$data->toArray();
    }

    /**
     * 插入到es
     * @author  fangjianwei
     * @param array $rows
     */
    public function insertDataToEs(array $rows) {
        if(empty($rows)) return;
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
            $info['region_id'] = $hit[0]['region_id'];
            $info['city_id'] = $hit[0]['city_id'];
            $info['es_update_time'] = $this->now;
            foreach ($hit as $h) {
                array_push($info['goods_info'], [
                    'goods_id'          => $h['goods_id'],
                    'is_accurate'       => $h['is_accurate'],
                    'score'             => $h['score'],
                    'goods_update_time' => $this->now,
                ]);
            }
            $list .= json_encode(["index" => ['_id' => $poiId]], JSON_UNESCAPED_UNICODE) . "\n";
            $list .= json_encode($info, JSON_UNESCAPED_UNICODE) . "\n";
        }
        $success = CurlHelper::factory("http://{$this->dst}/poi_goods_rel/relations/_bulk")
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
        if(empty($rows)) return;
        $bulk = [];
        $length = count($rows);
        foreach($rows as $key =>  $val){
            $val['add_time'] = $this->now;
            $val['update_time'] = $this->now;
            $val['goods_update_time'] = $this->now;
            $bulk[] = $val;
            if(count($bulk) != $this->processTotal && $key != $length-1) continue;
            $result = DB::connection('30002_mysql')
                ->table('poi_goods_rel')->insert($bulk);
            if(true !== $result){
                throw new \Exception('db插入失败');
            }
        }
    }

}
