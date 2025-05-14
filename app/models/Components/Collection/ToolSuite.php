<?php

namespace Slack\Models\Components\Collection;


/**
 * General purpose tools
 *
 * @author Jakub Petržílka <petrzilka@czweb.net>
 */
class ToolSuite
{

    public static function genUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function changeDateFormat($date, $format)
    {
        $timestamp = strtotime($date);
        return date($format, $timestamp);
    }

    public static function roundToAny($n, $x = 5)
    {
        return round($n / $x) * $x;
    }

    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
        for ($i = 0; $i < $length; $i++)
        {
            $randomString .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $randomString;
    }

    public static function calculateAgeFromBirthDate($birthDate)
    {
        $birthday_timestamp = strtotime($birthDate);
        return date('md', $birthday_timestamp) > date('md') ? date('Y') - date('Y', $birthday_timestamp) - 1 : date('Y') - date('Y', $birthday_timestamp);
    }

    /** Zjištění data narození z rodného čísla
     * @param string $rodne_cislo rodné číslo ve formátu rrmmdd/xxxx
     * @return string datum ve formátu rrrr-mm-dd
     * @copyright Jakub Vrána, http://php.vrana.cz
     */
    public static function getBirthDateFromCZPersonalNumber($rodne_cislo)
    {
        if (preg_match('~^([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{3,4})$~', $rodne_cislo, $match))
        {
            return (strlen($match[4]) < 4 || $match[1] >= 54 ? "19" : "20") . "$match[1]-" . sprintf("%02d", $match[2] % 50) . "-$match[3]";
        }
        return false;
    }

    public static function getObjectValueFromPath($object, $path)
    {
        $attrNames = explode('/', $path);
        $lastRef = $object;
        foreach ($attrNames as $atrrName)
        {
            $getterName = 'get' . $atrrName;
            if (method_exists($lastRef, $getterName))
                $lastRef = $lastRef->$getterName();
            else
                return null;
        }
        return $lastRef;
    }

    public static function getSubCollection($iterable, $valuePath, $keyPath = null)
    {
        $collection = new Collection();

        foreach ($iterable as $item)
        {
            $value = ToolSuite::getObjectValueFromPath($item, $valuePath);
            if ($keyPath != null)
            {
                $key = ToolSuite::getObjectValueFromPath($item, $keyPath);
                $collection->put($key, $value);
            }
            else
            {
                $collection->add($value);
            }
        }

        return $collection;
    }

    public static function populateObjectFromAnother($source, $destination)
    {
        $setters = get_class_methods($destination);
        foreach ($setters as $setter)
        {
            if (ToolSuite::startsWith($setter, 'set') !== false)
            {
                $getter = str_replace('set', 'get', $setter);
                if (method_exists($source, $getter))
                {
                    $destination->$setter($source->$getter());
                }
            }
        }
    }

    public static function startsWith($haystack, $needle)
    {
        return !strncmp($haystack, $needle, strlen($needle));
    }

    public static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0)
        {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    public static function getStringBetween($string, $start, $end)
    {
        $string = " " . $string;
        $ini = strpos($string, $start);
        if ($ini == 0)
            return "";
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public static function getLastArrayItem($array)
    {
        end($array);
        $key = key($array);
        return $array[$key];
    }

}
