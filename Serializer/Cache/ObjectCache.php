<?php

namespace Emhar\HydraClientBundle\Serializer\Cache;

class ObjectCache
{
    /**
     * @var array
     */
    protected $cache = array();

    /**
     * @param $iri
     * @return mixed|null
     */
    public function get($iri)
    {
        return isset($this->cache[$iri]) ? $this->cache[$iri] : null;
    }

    /**
     * @param $iri
     * @param $object
     */
    public function set($iri, $object)
    {
        $this->cache[$iri] = $object;
    }
}