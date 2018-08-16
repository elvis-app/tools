<?php

namespace App\Console\Commands\Tantu;

use Illuminate\Console\Command;
use Elasticsearch\ClientBuilder;
use App;
use Illuminate\Support\Facades\DB;
use App\Libs\AppCache;

class GoodsBatchRel extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:GoodsBatchRel {table_name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '商品与POI批量关联';

    /**
     * 每次处理的条数
     * @var int
     */
    protected $pageSize = 100;

    /**
     * 上次处理的最后poi_id，用于断点记忆
     * @var
     */
    protected $maxPoiId = 0;

    /**
     * es句柄
     * @var
     */
    protected $esHandle;

    /**
     * 是否开启缓存
     * @var bool
     */
    protected $cache = false;

    /**
     * 处理信息
     * @var array
     */
    protected $processInfo = [];


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        $this->esHandle = ClientBuilder::fromConfig(config('elasticsearch')['connections']['es_1']);
        $this->processInfo = array_fill_keys([
            'poi_valid_count',  //poi有效数量，也就是匹配上商品的数量
            'poi_invalid_count', //poi无效数量，也就是匹配不上商品的数量
            'goods_count',      //poi匹配商品的总数量
        ], 0);
        AppCache::$cache = $this->cache;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle() {
        //

        $tableName = $this->argument('table_name');
        $this->info("正在获取要需要处理的城市集...");
        $cityIds = $this->getGoodsCityIds();
        $this->info(sprintf("需要处理%d个城市ID", count($cityIds)));
        $this->distribution($tableName, $cityIds);
        return;
    }

    /**
     * @author  fangjianwei
     * @param string $tableName
     * @param array $cityIds
     */
    protected function distribution(string $tableName, array $cityIds) {
        $tables = [
            'attraction_tbl',
            'shopping_tbl',
            'entertainment_tbl',
            'hotels_tbl',
            'food_tbl',
        ];
        if (!in_array($tableName, $tables)) {
            $this->error(sprintf("没有[%s]此表名", $tableName));
            exit;
        }
        $this->info(sprintf("正在处理%s表", $tableName));

        $maxPoiIdCacheKey = sprintf('%s_max_poi_id', $tableName);
        //获取上一次处理的ID
        $maxPoiId = (int)AppCache::get($maxPoiIdCacheKey);
        //获取最大页码
        $maxPage = $this->getPoiMaxPage($tableName, $cityIds, $maxPoiId);
        //设置进度条
        $bar = $this->output->createProgressBar($maxPage);
        for ($i = 0; $i < $maxPage; $i++) {
            $startTime = microtime(true);
            $this->disposeData($tableName, $i, $cityIds, $maxPoiId);
            //保存最大ID，用于奔溃启动恢复
            if ($this->maxPoiId > 0) {
                AppCache::forever($maxPoiIdCacheKey, $this->maxPoiId);
            }
            $endTime = microtime(true);
            $this->info(sprintf("已处理完%s表第%d页，耗时%s毫秒", $tableName, $i + 1, ($endTime - $startTime) * 1000));
            $bar->advance();
        }
        $bar->finish();
        $this->info(sprintf('已处理完%s表，匹配上的poi数量为：%d，匹配不上的poi数量为：%d，共匹配%d个商品',
                $tableName,
                (int)$this->processInfo['poi_valid_count'],
                (int)$this->processInfo['poi_invalid_count'],
                (int)$this->processInfo['goods_count'])
        );
    }

    /**
     * 处理数据
     * @author  fangjianwei
     * @param string $tableName
     * @param int $page
     * @param array $cityIds
     */
    protected function disposeData(string $tableName, int $page, array $cityIds, int $maxPoiId): void {

        $rows = DB::table("{$tableName}")
            ->where([
                ["{$tableName}.id", '>', $maxPoiId],
                ["{$tableName}.is_del", '=', 0],
            ])
            ->leftJoin('city_tbl', "{$tableName}.city_id", '=', 'city_tbl.city')
            ->whereIn("{$tableName}.city_id", $cityIds)
            ->offset($page * $this->pageSize)
            ->limit($this->pageSize)
            ->orderBy("{$tableName}.id")
            ->select('city_tbl.city_cn', 'city_tbl.region_cn', "{$tableName}.region_id", "{$tableName}.city_id", "{$tableName}.cn_name", "{$tableName}.id")->get();
        $dataInfo = [];
        foreach ($rows as $row) {
            //获取精准数据
            $row = (array)$row;
            $accurateGoodsResult = $this->getEsGoodsResult($row, '100%');
            //获取模糊数据
            $fuzzyGoodsResult = $this->getEsGoodsResult($row, '70%');
            //合并数据
            $mergeData = $this->mergeData([
                'accurate' => $accurateGoodsResult,
                'fuzzy'    => $fuzzyGoodsResult,
            ]);
            //保存最大ID，用于奔溃启动恢复
            if ($row['id'] > $this->maxPoiId) {
                $this->maxPoiId = $row['id'];
            }

            if (empty($mergeData)) {
                $this->processInfo['poi_invalid_count']++;
//                $this->info(sprintf("%s表id为%d的数据没查到对应的poi信息", $tableName, $row['id']));
            } else {
                $this->processInfo['poi_valid_count']++;
                $this->processInfo['goods_count'] += count($mergeData);
                $row['poi_info'] = $mergeData;
                $dataInfo[] = $row;
            }
        }
        if (!empty($dataInfo)) {
            $save = [];
            foreach ($dataInfo as $info) {
                if (!empty($info['poi_info'])) {
                    foreach ($info['poi_info'] as $poiInfo) {
                        if ($poiInfo['goods_id'] <= 0) continue;
                        $save[] = [
                            'region_id'   => $info['region_id'],
                            'region_cn'   => $info['region_cn'],
                            'city_id'     => $info['city_id'],
                            'city_cn'     => $info['city_cn'],
                            'poi_id'      => $info['id'],
                            'poi_name'    => $info['cn_name'],
                            'goods_id'    => $poiInfo['goods_id'],
                            'goods_name'  => $poiInfo['goods_name'],
                            'poi_type'    => $tableName,
                            'score'       => $poiInfo['score'],
                            'status'      => $poiInfo['status'],
                            'is_accurate' => $poiInfo['is_accurate'],
                        ];
                    }
                }
            }
            if (!empty($save)) {
                $result = DB::table('poi_goods_rel')->insert($save);
                if (false === $result) {
                    $this->info(sprintf("%s表跑到第%d页发生错误：%s", $tableName, $page));
                    exit;
                }
            }
        }

        return;
    }

    /**
     * 精准与模糊数据匹配
     * @author  fangjianwei
     * @param array $data
     * @return array
     */
    protected function mergeData(array $data): array {
        if (empty($data['accurate']) || empty($data['fuzzy'])) return [];
        array_walk($data['accurate'], function(&$val) {
            $val['is_accurate'] = 1;
        });
        array_walk($data['fuzzy'], function(&$val) {
            $val['is_accurate'] = 0;
        });
        return (array)$data['accurate'] + (array)$data['fuzzy'];
    }

    /**
     * 获取es商品信息
     * @author  fangjianwei
     * @param array $params
     * @return array
     */
    protected function getEsGoodsResult(array $row, string $should): array {
        $params = [
            'index' => 'poi_goods_index',
            'type'  => 'goods',
            'body'  => [
                'query'   => [
                    'bool' => [
                        'must'   => [
                            [
                                'multi_match' => [
                                    'query'                => $row['cn_name'],
                                    'fields'               => ['goods_info.goods_name.zh.value'],
                                    'minimum_should_match' => $should,
                                ],
                            ],
                        ],
                        'filter' => [
                            'term' => [
                                'goods_info.city_id' => $row['city_id'],
                            ],
                        ],
                    ],
                ],
                'from'    => 0,
                'size'    => 3000,
                '_source' => ['goods_id', 'goods_info.status', 'goods_info.goods_name.zh.value'],
            ],
        ];
        $poiData = $this->esHandle->search($params);
        $data = [];
        if ($poiData['hits']['hits']) {
            foreach ($poiData['hits']['hits'] as $poi) {
                if (empty($poi['_source']['goods_id'])) continue;
                if ($poi['_source']) {
                    $data[$poi['_source']['goods_id']] = [
                        'goods_id'   => $poi['_source']['goods_id'],
                        'goods_name' => $poi['_source']['goods_info']['goods_name']['zh']['value'][0],
                        'status'     => $poi['_source']['goods_info']['status'],
                        'score'      => $poi['_score'],
                    ];
                }
            }
        }
        return $data;
    }

    /**
     * 获取poi数据最大页码
     * @author  fangjianwei
     * @param string $tableName
     * @param array $cityIds
     * @return int
     */
    protected function getPoiMaxPage(string $tableName, array $cityIds, int $maxPoiId): int {
        $cacheKey = sprintf('%s_%d_total', $tableName, $maxPoiId);
        $total = (int)AppCache::get($cacheKey);

        if (false === $this->cache || $total == 0) {
            ///查询总条数
            $total = (int)DB::table("{$tableName}")->where([
                ['is_del', '=', 0],
                ['id', '>', $maxPoiId],
            ])->whereIn('city_id', $cityIds)->count();
            AppCache::forever($cacheKey, $total);
        }

        if ($total > 0) {
            $this->info(sprintf("%s表需要处理%d条记录", $tableName, $total));
        } else {
            $this->error(sprintf("%s表没有数据需要处理", $tableName));
            exit;
        }
        //计算分页
        $maxPage = ceil($total / $this->pageSize);
        $this->info(sprintf("%s表需要处理%d页，页数为%d条", $tableName, $maxPage, $this->pageSize));
        return $maxPage;
    }

    /**
     * @author  fangjianwei
     * @return array
     */
    protected function getGoodsCityIds(): array {
        $esCityIdsCacheKey = 'es_city_ids';
        $cityIds = AppCache::get($esCityIdsCacheKey);
        if (!empty($cityIds)) return $cityIds;

        $cityRowIds = $this->getGoodsEsCityIds();
        $cityRowIds = array_unique($cityRowIds);
        $cityIds = array_filter($cityRowIds);
        if (count($cityIds) != count($cityRowIds)) {
            $this->info(sprintf("剔除%d个城市ID为0的数据...", count($cityRowIds) - count($cityIds)));
        }
        if (empty($cityIds)) {
            $this->error("es没有需要跑的城市ID...");
            exit;
        }
        $cityIds = array_values($cityIds);
        AppCache::forever($esCityIdsCacheKey, $cityIds);
        return $cityIds;
    }

    /**
     * @author  fangjianwei
     * @param array $fields
     * @return array
     */
    protected function getGoodsEsCityIds(): array {
        $params = [
            "scroll" => "30s",          // how long between scroll requests. should be small!"scroll" => "30s",          // how long between scroll requests. should be small!
            "size"   => 100,               // how many results *per shard* you want back"size" => 50,               // how many results *per shard* you want back
            "index"  => "poi_goods_index",
            "type"   => "goods",
            'body'   => [
                'query'   => [
                    'bool' => [
                        'must'   => [
                            'match_all' => new \stdClass(),
                        ],
                        'filter' => [
                            'exists' => [
                                'field' => 'goods_info.city_id',
                            ],
                        ],

                    ],
                ],
                '_source' => ['goods_info.city_id'],
            ],
        ];
        $cityIds = [];
        $response = $this->esHandle->search($params);
        $cityIds = array_merge($cityIds, $this->parseCityIds($response));
        while (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
            $scroll_id = $response['_scroll_id'];
            $response = $this->esHandle->scroll([
                    "scroll_id" => $scroll_id,  //...using our previously obtained _scroll_id
                    "scroll"    => "30s"           // and the same timeout window
                ]
            );
            $cityIds = array_merge($cityIds, $this->parseCityIds($response));
        }
        return $cityIds;
    }

    /**
     * @author  fangjianwei
     * @param $response
     * @return array
     */
    protected function parseCityIds($response) {
        $cityIds = [];
        if (isset($response['hits']['hits']) && count($response['hits']['hits']) > 0) {
            foreach ($response['hits']['hits'] as $val) {
                $cityIds = array_merge($cityIds, $val['_source']['goods_info']['city_id']);
            }
        }
        return $cityIds;
    }
}
