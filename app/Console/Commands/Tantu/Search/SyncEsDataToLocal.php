<?php

namespace App\Console\Commands\Tantu\Search;

use Illuminate\Console\Command;
use CurlHelper;

class SyncEsDataToLocal extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:sync:es {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'es同步';

    protected $indexTypeName = '';

    protected $source      = '';
    protected $destination = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {

        $this->source = config('elasticsearch')['connections']['es_3']['hosts'][0];
        $this->description = config('elasticsearch')['connections']['es_1']['hosts'][0];
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        $name = $this->argument('name');

        if (empty($this->source)) {
            $this->error('来源es地址配置为空');
            exit;
        }
        if (empty($this->description)) {
            $this->error('目的es地址配置为空');
            exit;
        }

        $this->indexTypeName = $name;

        $hits = $this->get_first(1000, $next);
        if (empty($hits)) {
            return;
        }
        do {
            $this->insert_hits($hits);
            $hits = $this->get_next($next);
        } while (!empty($hits));
        return;
    }

    /**
     * @author  fangjianwei
     * @param $hits
     */
    private function insert_hits($hits) {
        static $all = 0;
        $count = count($hits);
        $all += $count;
        $this->info(sprintf("All: %d/%d.", $count, $all));

        $list = "";
        foreach ($hits as $hit) {
            $list .= json_encode(["index" => ["_id" => $hit->_id]], JSON_UNESCAPED_UNICODE) . "\n";
            $list .= json_encode($hit->_source, JSON_UNESCAPED_UNICODE) . "\n";
        }
        $success = CurlHelper::factory("http://{$this->description}/{$this->indexTypeName}/_bulk")
            ->setPostRaw($list)->setHeaders(['Content-Type' => 'application/x-ndjson'])->exec();
        if (!empty($success['data']) && $success['status'] == 200) {
            $success = json_decode(json_encode($success['data']));
            $this->info(sprintf("Success: %d", count($success->items)));
        } else {
            $this->error('更新失败');
        }

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
