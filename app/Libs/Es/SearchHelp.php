<?php
/**
 * @datetime  2018/9/25 16:41
 * @author    fangjianwei
 * @copyright www.weicut.com
 */

declare(strict_types = 1);//默认严格模式

namespace App\Libs\Es;

class SearchHelp {
    /**
     * @var array 多语言的支持
     */
    public static $multiLanguageSupport = [
        "en",
        "zh",
        "zh-tw",
        "zh-hk",
    ];

    /**
     * 格式化数组
     * @author  fangjianwei
     * @param $value
     * @param string $type
     * @throws \Exception
     */
    public static function fmt_value(&$value, string $type): void {
        switch ($type) {
            case "text":
                $value = trim($value);
                break;
            case "text_array":
                if (is_string($value)) {
                    $value = [$value];
                }
                $value = array_map('trim', $value);
                break;
            case "multiLanguage":
                $value = self::fmt_language($value);
                break;
            case "int":
                $value = intval($value);
                break;
            case "int_array":
                if (!is_array($value)) {
                    $value = [$value];
                }
                $value = array_map('intval', $value);
                break;
            case "float":
                $value = floatval($value);
                break;
            case "double":
                $value = doubleval($value);
                break;
            case "millisecond":
                //64位本身支持毫秒
                $value = intval($value);
                break;
            case "geo_point":
                if ($value instanceof GeoPoint) {
                    /** @var GeoPoint $value */
                    $value = $value->toArray();
                } else {
                    throw new \Exception("geo_point type must instance of GeoPoint Object");
                }
                break;
            default:
                throw new \Exception("Unknown ES fmt type: {$type}");
        }
    }

    /**
     * 格式化多语言
     * @author  fangjianwei
     * @param $value
     * @return object
     */
    public static function fmt_language($value) :object{
        $result = [];
        if (is_array($value)) {
            foreach (self::$multiLanguageSupport as $language) {
                if (!array_key_exists($language, $value)) {
                    continue;
                }
                $obj = $value[$language];
                $obj['language'] = $language;
                if (is_array($obj['value'])) {
                    $v2 = [];
                    foreach ($obj['value'] as $item_l2) {
                        if (is_string($item_l2)) {
                            $v2[] = trim($item_l2);
                        }
                    }
                    $obj['value'] = $v2;
                } elseif (is_string($obj['value'])) {
                    $obj['value'] = [trim($obj['value'])];
                } else {
                    $obj['value'] = [];
                }
                $result[$language] = $obj;
            }
        }
        return (object)$result;
    }
}