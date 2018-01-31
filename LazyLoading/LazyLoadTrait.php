<?php

namespace Emhar\HydraClientBundle\LazyLoading;

trait LazyLoadTrait
{
    /**
     * @var array
     */
    protected $lazyLoadPropertiesAndClosureList = array();

    public function initializeLazyProperties(array $lazyLoadPropertiesAndClosure)
    {
        $this->lazyLoadPropertiesAndClosureList[] = $lazyLoadPropertiesAndClosure;
        $lazyLoadProperties = $lazyLoadPropertiesAndClosure['properties'];
        /* @var $lazyLoadProperties array */
        foreach ($lazyLoadProperties as $lazyLoadProperty) {
            unset($this->$lazyLoadProperty);
        }
    }

    /**
     * @param $name
     * @return mixed
     */
    public function &__get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }
        foreach ($this->lazyLoadPropertiesAndClosureList as $key => $lazyLoadPropertiesAndClosure) {
            if (in_array($name, $lazyLoadPropertiesAndClosure['properties'], true)) {
                unset($this->lazyLoadPropertiesAndClosureList[$key]);
                $lazyLoadPropertiesAndClosure['closure']($this);
            }
        }
        if (!property_exists($this, $name)) {
            trigger_error('Undefined property: ' . get_class($this) . ':$' . $name);
        }
        return $this->{$name};
    }
}
