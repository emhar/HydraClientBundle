<?php

namespace Emhar\HydraClientBundle\Client;

use Emhar\HydraClientBundle\Serializer\Cache\ObjectCache;
use Emhar\HydraClientBundle\Serializer\Encoder\HydraDecode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Serializer\Serializer;

class HydraClient
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var Serializer
     */
    protected $serializer;

    /**
     * @var ObjectCache
     */
    protected $cache;

    /**
     * ResamaniaProvider constructor.
     * @param Client $client
     * @param Serializer $serializer
     */
    public function __construct(Client $client, Serializer $serializer, ObjectCache $cache)
    {
        $this->client = $client;
        $this->serializer = $serializer;
        $this->cache = $cache;
    }

    /**
     * @param $resource
     * @param $type
     * @param array $context
     * @return null|mixed
     * @throws \GuzzleHttp\Exception\RequestException
     */
    public function get($resource, $type, array $context = array())
    {
        if ($this->cache->get($resource)) {
            return $this->cache->get($resource);
        }
        try {
            $response = $this->client->get($resource);
//            echo($response->getBody());die;
//            var_dump($this->serializer->deserialize(
//                $response->getBody(),
//                $type,
//                HydraDecode::FORMAT
//            ));die;
            if($type) {
                $object = $this->serializer->deserialize(
                    $response->getBody(),
                    $type,
                    HydraDecode::FORMAT,
                    $context
                );
                $this->cache->set($resource, $object);
            } else{
                $object = (string)$response->getBody();
            }
            return $object;
        } catch (RequestException $e) {
            if (($response = $e->getResponse()) && $response->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}
