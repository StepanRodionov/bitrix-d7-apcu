<?php

namespace SR\D7Cache\DataManager;

use Psr\SimpleCache\CacheInterface;
use SR\Cache\ApcuStorage;
use SR\D7Cache\Utils\Serializer;
use Bitrix\Main\ORM\Query\Result;
use Bitrix\Main\ORM\Data\DataManager;


abstract class CachedDataManager extends DataManager
{
    protected static $defaultCacheInterface = ApcuStorage::class;

    /** @var CacheInterface */
    protected static $cacheInterface;

    /** @var Serializer */
    protected static $serializer;

    /** @var bool */
    protected static $inited = false;

    public static function init()
    {
        $className = static::$defaultCacheInterface;
        static::$cacheInterface = static::$cacheInterface ?? new $className();
        static::$serializer = new Serializer();
        static::$inited = true;
    }

    /**
     * @param CacheInterface $cacheInterface
     */
    public static function setCacheInterface(CacheInterface $cacheInterface)
    {
        static::$cacheInterface = $cacheInterface;
    }

    /**
     * @return string
     */
    static function getEntityPrefix(){
        return get_called_class();
    }

    /**
     * @param $key
     * @param bool $isXml
     *
     * @return string
     */
    private static function makeKey($key, $isXml = false): string
    {
        $xmlPart = $isXml ? 'XML_ID' : '';
        return static::getEntityPrefix() . $xmlPart . $key;
    }

    /**
     * @param $primary
     * @return array|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * Не изменяем return type битриксового getByPrimary для совместимости
     */
    public static function getByPrimary($primary)
    {
        $originalPrimary = $primary;
        static::normalizePrimary($primary);
        static::validatePrimary($primary);

        //  Хз сколько я тут сэкономил.
        self::$inited || self::init();

        $key = self::makeKey($originalPrimary);
        $cacheInterface = static::$cacheInterface;
        if($elem = $cacheInterface->get($key)){
            static::cacheOk();
            return (static::$serializer)->unserialize($elem);
        }

        static::cacheMiss();
        $elem = parent::getByPrimary($primary)->fetch();
        $serializedElem = (static::$serializer)->serialize($elem);
        $succ = $cacheInterface->set($key, $serializedElem);
        return $elem;
    }

    /**
     * @param string $xmlId
     * @return array|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function getReference(string $xmlId)
    {
        self::$inited || self::init();

        $key = self::makeKey($xmlId, true);
        $cacheInterface = static::$cacheInterface;
        if($elem = $cacheInterface->get($key)){
            static::cacheOk();
            return (static::$serializer)->unserialize($elem);
        }

        static::cacheMiss();
        $query = static::query();

        $elem = $query->setSelect(['*'])
            ->setFilter(['UF_XML_ID' => $xmlId])
            ->setLimit(1)
            ->exec()
            ->fetch();

        $cacheInterface->set($key, $elem);
        return $elem;
    }

    /**
     * @param array $xmlIds
     * @return array|iterable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function getReferenceMulti(array $xmlIds)
    {
        self::$inited || self::init();

        $cacheInterface = static::$cacheInterface;
        $keys = array_map($xmlIds, function ($xmlId) {
            $xmlId = self::makeKey($xmlId, true);
        });

        $emptyKeys = [];
        $cachedElems = $cacheInterface->getMultiple($keys);
        foreach ($cachedElems as $key => $elem){
            if ($elem === null){
                static::cacheMiss();
                $emptyKeys[] = $key;
                unset($cachedElems[$key]);
                continue;
            }
            static::cacheOk();
        }
        if(\count($emptyKeys) == 0){
           return $cachedElems;
        }

        $elemsFromBase = [];
        $query = static::query();

        $dbElems = $elem = $query->setSelect(['*'])
            ->setFilter(['UF_XML_ID' => $emptyKeys])
            ->setLimit(1)
            ->exec()
            ->fetchAll();

        foreach ($dbElems as $elem) {
            $elemsFromBase[$elem['UF_XML_ID']] = $elem;
            $cacheInterface->set($elem['UF_XML_ID'], $elem);
        }

        return array_merge($cachedElems, $dbElems);
    }

    /**
     * Here you can realize statistics storing of cache usage
     */
    protected static function cacheOk()
    {}

    /**
     * Here you can realize statistics storing of cache usage
     */
    protected static function cacheMiss()
    {}
}