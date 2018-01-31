<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Emhar\HydraClientBundle\Serializer\Normalizer;

use Emhar\HydraClientBundle\Serializer\Cache\ObjectCache;
use Emhar\HydraClientBundle\Serializer\Encoder\HydraDecode;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Decodes JSON data.
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class HydraObjectDenormalizer implements DenormalizerInterface, SerializerAwareInterface
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var Client
     */
    private $guzzleClient;

    /**
     * @var ObjectCache
     */
    protected $cache;

    /**
     * @var ObjectNormalizer
     */
    protected $objectDenormalizer;

    /**
     * HydraObjectDenormalizer constructor.
     * @param ObjectNormalizer $objectDenormalizer
     * @param Client $guzzleClient
     * @param ObjectCache $cache
     */
    public function __construct(ObjectNormalizer $objectDenormalizer, Client $guzzleClient, ObjectCache $cache)
    {
        $this->objectDenormalizer = $objectDenormalizer;
        $this->guzzleClient = $guzzleClient;
        $this->cache = $cache;
    }

    /**
     * Denormalizes data back into an object of the given class.
     *
     * @param mixed $data data to restore
     * @param string $class the expected class to instantiate
     * @param string $format format the given data was extracted from
     * @param array $context options available to the denormalizer
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $data = (array)$data;
        $normalizedData = $this->defineIdFromIri($data);
        if (isset($normalizedData['@id']) && $this->cache->get($normalizedData['@id'])) {
            return $this->cache->get($normalizedData['@id']);
        }
        $object = $this->objectDenormalizer->denormalize($normalizedData, $class, $format, $context);
        if (isset($normalizedData['@id'])) {
            if (isset($normalizedData['@hydra:partial']) && $normalizedData['@hydra:partial']) {
                $this->initializeLazyLoadProperties($object, $normalizedData, $class, $context);
            } else {
                $this->cache->set($normalizedData['@id'], $object);
            }
        }
        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return $this->objectDenormalizer->supportsDenormalization($data, $type, $format);
    }


    /**
     * {@inheritDoc}
     */
    public function setSerializer(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @param array $decodedData
     * @return array
     */
    public function defineIdFromIri($decodedData)
    {
        if (isset($decodedData['@id']) && $decodedData['@id']) {
            $exploded = explode('/', $decodedData['@id']);
            $decodedData['id'] = array_pop($exploded);
        }
        return $decodedData;
    }

    /**
     * @param $object
     * @param $normalizedData
     * @param $class
     * @param $context
     * @throws \ReflectionException
     */
    protected function initializeLazyLoadProperties($object, $normalizedData, $class, $context)
    {
        $reflexionClass = new \ReflectionClass($object);
        $customProperties = isset($context['hydra_custom_properties']) ? $context['hydra_custom_properties'] : array();
        if ($reflexionClass->hasProperty('lazyLoadPropertiesAndClosureList')) {
            $lazyLoadedProperties = array();
            foreach ($reflexionClass->getProperties() as $property) {
                if ($property->getName() !== 'lazyLoadPropertiesAndClosureList'
                    && !isset($normalizedData[$property->getName()])
                    && !in_array($property->getName(), $customProperties, true)
                ) {
                    $lazyLoadedProperties[] = $property->getName();
                }
            }
            $object->iri = $normalizedData['@id'];
            $closure = \Closure::bind(
                $this->lazyLoadClosure($class, $lazyLoadedProperties, $context),
                $object,
                $object
            );
            $object->initializeLazyProperties(array(
                'properties' => $lazyLoadedProperties,
                'closure' => $closure
            ));
        }
    }

    /**
     * @param $class
     * @param array $lazyLoadedProperties
     * @param $context
     * @return \Closure
     */
    public function lazyLoadClosure($class, array $lazyLoadedProperties, $context)
    {
        $guzzleClient = $this->guzzleClient;
        $cache = $this->cache;
        $serializer = $this->serializer;
        return function () use ($class, $lazyLoadedProperties, $context, $guzzleClient, $serializer, $cache) {
            if (!$completeObject = $cache->get($this->iri)) {
                try {
                    $response = $guzzleClient->get($this->iri);
                    $completeObject = $serializer->deserialize(
                        $response->getBody(), $class, HydraDecode::FORMAT, $context
                    );
                } catch (RequestException $e) {
                    $completeObject = null;
                }
            }
            foreach ($lazyLoadedProperties as $property) {
                if (!isset($this->{$property})) {
                    $this->{$property} = isset($completeObject->{$property}) ? $completeObject->{$property} : null;
                }
            }
            $cache->set($this->iri, $this);
        };
    }

}