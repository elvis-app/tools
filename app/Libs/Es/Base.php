<?php
/**
 * @datetime  2018/6/6 22:12
 * @author    fangjainwei
 * @copyright www.zuzuche.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Libs\Es;


/**
 * Class Base
 * @package TantuTravelApi\Services\Search\DataModel\Es
 * @author  fangjainwei
 */
class Base {
    /**
     * @var string IK最大化分词搜索分词器
     */
    public static $ik_max_word_search_analyzer = "ik_max_word";

    public static function textStruct(string $analyzer, $search_analyzer, bool $index): \stdClass {

        $structure = [
            "type"            => "text",
            "analyzer"        => $analyzer,
            "search_analyzer" => $search_analyzer,
        ];
        if ($index === false) {
            $structure['index'] = false;
        }
        return (object)$structure;
    }

    /**
     * @author fangjainwei
     * @param string $type
     * @param bool $index
     * @return \stdClass
     * @throws \Exception
     */
    public static function baseTypeStruct(string $type, bool $index = true): \stdClass {
        $map = [
            "int"  => "integer",
            "bool" => "boolean",
        ];
        if (isset($map[$type])) {
            $type = $map[$type];
        }
        if (!in_array($type, [
            //number
            "long",
            "integer",
            "short",
            "byte",
            "double",
            "float",
            "half_float",
            "scaled_float",
            //ip
            "ip",
            //date
            "date",
            "boolean",
            "geo_point",
            "integer_range",
            "binary",
            "keyword",
        ])) {
            throw new \Exception("{$type} Not allow type.");
        }
        $obj = ["type" => $type,];
        if ($index === false) {
            $obj['index'] = false;
        }
        return (object)$obj;
    }

    public static function dateStruct($fmt): \stdClass {
        return (object)[
            "type"   => "date",
            "format" => $fmt,
        ];
    }

    public static function epochMillisDate(): \stdClass {
        return self::dateStruct("epoch_millis");
    }

    public static function ikMaxWordStruct(bool $index, array $cfg = []): \stdClass {
        $search_analyzer = isset($cfg['search_analyzer']) ? $cfg['search_analyzer'] : self::$ik_max_word_search_analyzer;
        $analyzer = isset($cfg['analyzer']) ? $cfg['analyzer'] : "ik_max_word";

        //IK自定义别名分词器
        $rest = (array)self::textStruct($analyzer, $search_analyzer, $index);

        if (isset($cfg['ext_merge'])) {
            $rest = array_merge($rest, $cfg['ext_merge']);
        }

        return (object)$rest;
    }

    /**
     * @author fangjainwei
     * @param bool $index
     * @param array $cfg
     * @return object
     * @throws \Exception
     */
    public static function languageFieldStruct(bool $index, array $cfg) {
        $res = [
            "properties" => (object)[
                "language" => self::baseTypeStruct("keyword", $index),
                "value"    => self::ikMaxWordStruct($index, $cfg),
            ],
        ];

        return (object)$res;
    }

    /**
     * @author fangjainwei
     * @param array $lang_list
     * @param bool $index
     * @param array $cfg 额外配置
     * @param array $langCfg
     * @return \stdClass
     * @throws \Exception
     */
    public static function multiLanguageStruct(array $lang_list, bool $index, array $cfg = [], array $langCfg = []): \stdClass {
        $properties = [];
        foreach ($lang_list as $lang) {
            $properties[$lang] = self::languageFieldStruct($index, isset($langCfg[$lang]) ? $langCfg[$lang] : $cfg);
        }
        return (object)['properties' => $properties];
    }
}