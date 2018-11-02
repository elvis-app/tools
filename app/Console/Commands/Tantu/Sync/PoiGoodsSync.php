<?php

namespace App\Console\Commands\Tantu\Sync;

use Illuminate\Console\Command;
use CurlHelper;

class PoiGoodsSync extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:Sync:PoiGoodsSync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步POI商品到es';

    protected $indexTypeName = '';
    protected $source      = '';
    protected $dst = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {

        $this->source = config('elasticsearch')['connections']['es_3']['hosts'][0];
        $this->indexTypeName = 'poi_goods_index_test/goods';
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //获取所有商品ID
        $hits = $this->get_first(100, $next);
        if (empty($hits)) {
            $this->info('没有数据需要处理');
            return;
        }
        $i = 1;
        do {
            $this->info(sprintf('处理第%d页,共%d条数据', $i, count($hits)));
            $goodsIds = [];
            foreach($hits as $val){
                $goodsIds[] = $val->_id;
            }
            $result = CurlHelper::factory(config('tantu')['poi_goods_sync_api'])->setGetParams([
                'goods_ids' => implode(',', $goodsIds)
            ])->exec();
            if($result['status'] != 200 || $result['data']['status'] != 200){
                $this->error($result['data']['msg']);
                return;
            }
            $hits = $this->get_next($next);
            $i ++;
        } while (!empty($hits));
        return ;
    }

    /**
     * @author  fangjianwei
     * @param null $next
     * @return array
     */
    private function get_next(&$next = null) {
        if (empty($next)) {
            return [];
        }
        $data = [
            'scroll_id' => $next,
            'scroll'    => "5m",
        ];
        $next = null;
        $list = CurlHelper::factory('http://' . $this->source . '/_search/scroll')
            ->setPostParams($data)->setHeaders([
                'Content-Type' => 'application/json',
            ])->exec();
        $data = json_decode(json_encode($list['data']));
        $next = $data->_scroll_id;
        if (isset($data->hits)) {
            return $data->hits->hits;
        }
        return [];
    }

    /**
     * @author  fangjianwei
     * @param $num
     * @param null $next
     * @return array
     */
    private function get_first($num, &$next = null) {
        $next = "";
        $data = [
            'query' => [
                'match_all' => (object)[],
            ],
            'sort'  => [
                '_doc',
            ],
            'size'  => $num,
            '_source' => ['goods_id'],
        ];
        $list = CurlHelper::factory('http://' . $this->source . '/' . $this->indexTypeName . '/_search?scroll=5m')->setPostFields($data)->setHeaders([
            'Content-Type' => 'application/json',
        ])->exec();
        $data = json_decode(json_encode($list['data']));
        if (isset($data->error)) {
            $this->error(print_r($data->error, true));
            exit;
        }
        $next = $data->_scroll_id;
        $this->info("next_scroll_id：{$next}");
        if (isset($data->hits)) {
            return $data->hits->hits;
        }
        return [];
    }
}
