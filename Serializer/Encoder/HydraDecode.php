<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Emhar\HydraClientBundle\Serializer\Encoder;

use GuzzleHttp\Client;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Decodes JSON data.
 *
 * @author Sander Coolen <sander@jibber.nl>
 */
class HydraDecode implements DecoderInterface
{
    const FORMAT = 'hydra';

    /**
     * Specifies the recursion depth.
     *
     * @var int
     */
    private $recursionDepth;

    private $lastError = JSON_ERROR_NONE;

    protected $serializer;

    /**
     * @var Client
     */
    protected $client;

    /**
     * Constructs a new JsonDecode instance.
     *
     * @param Client $client
     * @param int $depth Specifies the recursion depth
     */
    public function __construct(Client $client, $depth = 512)
    {
        $this->recursionDepth = (int)$depth;
    }

    /**
     * Decodes data.
     *
     * @param string $data The encoded JSON string to decode
     * @param string $format Must be set to JsonEncoder::FORMAT
     * @param array $context An optional set of options for the JSON decoder; see below
     *
     * The $context array is a simple key=>value array, with the following supported keys:
     *
     * json_decode_associative: boolean
     *      If true, returns the object as associative array.
     *      If false, returns the object as nested stdClass
     *      If not specified, this method will use the default set in JsonDecode::__construct
     *
     * json_decode_recursion_depth: integer
     *      Specifies the maximum recursion depth
     *      If not specified, this method will use the default set in JsonDecode::__construct
     *
     * json_decode_options: integer
     *      Specifies additional options as per documentation for json_decode. Only supported with PHP 5.4.0 and higher
     *
     * @return mixed
     *
     * @throws UnexpectedValueException
     *
     * @see http://php.net/json_decode json_decode
     */
    public function decode($data, $format, array $context = array())
    {
        $context = $this->resolveContext($context);

        $recursionDepth = $context['json_decode_recursion_depth'];
        $options = $context['json_decode_options'];

        $decodedData = json_decode($data, false, $recursionDepth, $options);
        if (isset($decodedData->{'hydra:member'}) || isset($decodedData->{'hydra:members'})) {
            $members = isset($decodedData->{'hydra:member'}) ? $decodedData->{'hydra:member'} : $decodedData->{'hydra:members'};
            /* @var $members array */
            if (isset($decodedData->{'hydra:view'}->{'hydra:next'}, $decodedData->{'hydra:view'}->{'@id'})
                && $decodedData->{'hydra:view'}->{'hydra:next'} !== $decodedData->{'hydra:view'}->{'@id'}) {
                $response = $this->client->get($decodedData->{'hydra:view'}->{'hydra:next'});
                $nextResponsesData = $this->decode((string)$response->getBody(), $format, $context);
                /* @var $nextResponsesData array */
                array_push($members, ...$nextResponsesData);
            }
            $decodedData = $members;
        }
        if (is_array($decodedData)) {
            foreach ($decodedData as $key => $object) {
                $object->{'@hydra:partial'} = true;
            }
        } else {
            $decodedData->{'@hydra:partial'} = false;
        }

        if (!isset($decodedData->id) && isset($decodedData->{'@id'}) && $decodedData->{'@id'}) {
            $exploded = explode('//', $decodedData->{'@id'});
            $decodedData->id = array_pop($exploded);
        }

        if (JSON_ERROR_NONE !== $this->lastError = json_last_error()) {
            throw new UnexpectedValueException(json_last_error_msg());
        }

        return $decodedData;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDecoding($format)
    {
        return self::FORMAT === $format;
    }

    /**
     * Merges the default options of the Json Decoder with the passed context.
     *
     * @param array $context
     *
     * @return array
     */
    private function resolveContext(array $context)
    {
        $defaultOptions = array(
            'json_decode_recursion_depth' => $this->recursionDepth,
            'json_decode_options' => 0,
        );

        return array_merge($defaultOptions, $context);
    }
}
