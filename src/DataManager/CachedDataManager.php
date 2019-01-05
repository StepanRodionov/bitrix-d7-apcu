<?php

use Psr\SimpleCache\CacheInterface;
use SR\Cache\ApcuStorage;
use SR\D7Cache\Utils\Serializer;
use Bitrix\Main\ORM\Query\Result as QueryResult;

/**
 * Created by PhpStorm.
 * User: Админ
 * Date: 05.01.2019
 * Time: 16:04
 */

abstract class CachedDataManager extends DataManager
{
    private static $defaultCacheInterface = ApcuStorage::class;

    /** @var CacheInterface */
    private static $cacheInterface;

    /** @var Serializer */
    private static $serializer;

    /** @var string */
    private static $entityPrefix;

    private static $inited = 0;

    public static function init()
    {
        self::$cacheInterface = self::$cacheInterface ?? new (self::$defaultCacheInterface)();
        self::$serializer = new Serializer();
        self::$inited = 1;
    }

    public static function setCacheInterface(CacheInterface $cacheInterface)
    {
        self::$cacheInterface = $cacheInterface;
    }

    /**
     * @param $primary
     * @return QueryResult
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function getByPrimary($primary){
        static::normalizePrimary($primary);
        static::validatePrimary($primary);

        //  Хз сколько я тут сэкономил.
        self::$inited && self::init();

        $key = static::$entityPrefix . $primary;
        $cacheInterface = self::$cacheInterface;
        if($elem = $cacheInterface->get($key)){
            return (self::$serializer)->unserialize($elem);
        }

        $elem = parent::getByPrimary($primary);
        $cacheInterface->set($key, $elem);
        return $elem;
    }

    public static function getReference($xmlId)
    {
        $q = static::$query;


    }
}