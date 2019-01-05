<?php

use Psr\SimpleCache\CacheInterface;
use SR\Cache\ApcuStorage;
use SR\D7Cache\Utils\Serializer;
use \Bitrix\Main\ORM\Query\Result;


abstract class CachedDataManager extends DataManager
{
    private static $defaultCacheInterface = ApcuStorage::class;

    /** @var CacheInterface */
    private static $cacheInterface;

    /** @var Serializer */
    private static $serializer;

    /** @var bool */
    private static $inited = false;

    public static function init()
    {
        self::$cacheInterface = self::$cacheInterface ?? new (self::$defaultCacheInterface)();
        self::$serializer = new Serializer();
        self::$inited = true;
    }

    /**
     * @param CacheInterface $cacheInterface
     */
    public static function setCacheInterface(CacheInterface $cacheInterface)
    {
        self::$cacheInterface = $cacheInterface;
    }

    /**
     * @return string
     */
    abstract static function getEntityPrefix();

    private static function makeKey($key, $isXml = false)
    {
        $xmlPart = $isXml ? 'XML_ID' : '';
        static::getEntityPrefix() . $xmlPart . $primary;
    }

    /**
     * @param $primary
     * @return Result
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * Не изменяем return type битриксового getByPrimary для совместимости
     */
    public static function getByPrimary($primary)
    {
        static::normalizePrimary($primary);
        static::validatePrimary($primary);

        //  Хз сколько я тут сэкономил.
        self::$inited && self::init();

        $key = self::makeKey($primary);
        $cacheInterface = self::$cacheInterface;
        if($elem = $cacheInterface->get($key)){
            return (self::$serializer)->unserialize($elem);
        }

        $elem = parent::getByPrimary($primary);
        $cacheInterface->set($key, $elem);
        return $elem;
    }

    /**
     * @param string $xmlId
     * @return array|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function getReference(string $xmlId)
    {
        self::$inited && self::init();

        $key = self::makeKey($xmlId, true);
        $cacheInterface = self::$cacheInterface;
        if($elem = $cacheInterface->get($key)){
            return (self::$serializer)->unserialize($elem);
        }

        $query = static::query();

        $elem = $query->setSelect(['*'])
            ->setFilter(['UF_XML_ID' => $xmlId])
            ->setLimit(1)
            ->exec()
            ->fetch();

        $cacheInterface->set($key, $elem);
        return $elem;
    }

    public static function getReferenceMulti(array $xmlIds)
    {
        self::$inited && self::init();

        $cacheInterface = self::$cacheInterface;
        $keys = array_map($xmlIds, function ($xmlId) {
            $xmlId = self::makeKey($xmlId, true);
        });

        $elems = $cacheInterface->getMultiple($keys);
    }
}