<?php

namespace App\Console\Commands\Tantu\Search;

use Illuminate\Console\Command;
use App\Libs\Es\Base;

class MappingCreate extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tantu:Search:MappingCreate {type}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '生成es mapping';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void {
        //
        $type = $this->argument('type');
        if (!method_exists($this, $type)) {
            $this->error(sprintf('没有%s的生成方法', $type));
            return;
        }
        call_user_func([$this, $type]);
        $this->info('生成成功');
    }

    /**
     * @author  fangjianwei
     * @return void
     * @throws \Exception
     */
    protected function relations(): void {
        $data = (object)[
//            'dynamic'    => false,
            'properties' => (object)[
                'poi_info'       => [
                    'properties' => [
                        'poi_id'   => Base::baseTypeStruct('integer'),
                        'poi_type' => Base::baseTypeStruct('keyword'),
                    ],
                ],
                'goods_info'     => [
                    'properties' => [
                        'goods_id'    => Base::baseTypeStruct('keyword'),
                        'is_accurate' => Base::baseTypeStruct('integer'),
                        'add_type'    => Base::baseTypeStruct('integer'),
//                        'update_time' => Base::epochMillisDate(),
//                        'add_time'    => Base::epochMillisDate(),
                    ],
                ],
//                'region_id'      => Base::baseTypeStruct('integer'),
//                'city_id'        => Base::baseTypeStruct('integer'),
                'es_update_time' => Base::epochMillisDate(),
            ],
        ];
        file_put_contents(__FUNCTION__ . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * 生成默认settings
     * @author  fangjianwei
     */
    protected function getDefaultSettings(): void {
        $data = (object)[
            "analysis" => [
                "filter"   => [
                    "unique_stem_filter" => [
                        "type"                  => "unique",
                        "only_on_same_position" => "true",
                    ],

                    "my_ik_synonym_filter" => [
                        "type"          => "synonym",
                        "synonyms_path" => "analysis-ik/config/synonyms.txt",
                    ],
                ],
                "analyzer" => [
                    "ik_syno_max"   => [
                        "filter"    => [
                            "my_ik_synonym_filter",
                            "unique",
                        ],
                        "type"      => "custom",
                        "tokenizer" => "ik_max_word",
                    ],
                    "ik_syno_smart" => [
                        "filter"    => [
                            "my_ik_synonym_filter",
                            "unique",
                        ],
                        "type"      => "custom",
                        "tokenizer" => "ik_smart",
                    ],
                ],
            ],
        ];
        file_put_contents(__FUNCTION__ . '.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}
