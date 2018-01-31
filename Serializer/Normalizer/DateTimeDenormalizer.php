<?php

namespace Emhar\HydraClientBundle\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class DateTimeDenormalizer implements DenormalizerInterface
{
    const FORMAT_KEY = 'datetime_format';
    const TIMEZONE_KEY = 'datetime_timezone';

    private $timezone;

    private static $supportedTypes = array(
        \DateTimeInterface::class => true,
        \DateTimeImmutable::class => true,
        \DateTime::class => true,
    );

    public function __construct($format = \DateTime::RFC3339, \DateTimeZone $timezone = null)
    {
        $this->timezone = $timezone;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Symfony\Component\Serializer\Exception\InvalidArgumentException
     * @throws NotNormalizableValueException
     */
    public function denormalize($data, $class, $format = null, array $context = array())
    {
        $dateTimeFormat = isset($context[self::FORMAT_KEY]) ? $context[self::FORMAT_KEY] : null;
        $timezone = $this->getTimezone($context);

        if (null !== $dateTimeFormat) {
            $object = \DateTime::class === $class ? \DateTime::createFromFormat($dateTimeFormat, $data, $timezone)
                : \DateTimeImmutable::createFromFormat($dateTimeFormat, $data, $timezone);

            if (false !== $object) {
                return $object;
            }

            $dateTimeErrors = \DateTime::class === $class ? \DateTime::getLastErrors() : \DateTimeImmutable::getLastErrors();

            throw new InvalidArgumentException(sprintf(
                'Parsing datetime string "%s" using format "%s" resulted in %d errors:' . "\n" . '%s',
                $data,
                $dateTimeFormat,
                $dateTimeErrors['error_count'],
                implode("\n", $this->formatDateTimeErrors($dateTimeErrors['errors']))
            ));
        }

        try {
            return \DateTime::class === $class ? new \DateTime($data, $timezone) : new \DateTimeImmutable($data, $timezone);
        } catch (\Exception $e) {
            throw new InvalidArgumentException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, $type, $format = null)
    {
        return isset(self::$supportedTypes[$type]);
    }

    /**
     * Formats datetime errors.
     *
     * @param array $errors
     * @return string[]
     */
    private function formatDateTimeErrors(array $errors)
    {
        $formattedErrors = array();

        foreach ($errors as $pos => $message) {
            $formattedErrors[] = sprintf('at position %d: %s', $pos, $message);
        }

        return $formattedErrors;
    }

    /**
     * @param array $context
     * @return \DateTimeZone
     */
    private function getTimezone(array $context)
    {
        $dateTimeZone = array_key_exists(self::TIMEZONE_KEY, $context) ? $context[self::TIMEZONE_KEY] : $this->timezone;

        if (null === $dateTimeZone) {
            return null;
        }

        return $dateTimeZone instanceof \DateTimeZone ? $dateTimeZone : new \DateTimeZone($dateTimeZone);
    }
}