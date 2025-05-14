<?php

namespace Slack\Models\Components\Collection;

use Countable;
use Iterator;

/**
 * @author Jakub Petržílka <petrzilka@czweb.net>
 *
 */
class Collection implements Iterator, Countable
{

    private $items = array();

    public function __construct($items = null)
    {
        if (is_array($items))
        {
            $this->items = $items;
        }
    }

    public function current()
    {
        return current($this->items);
    }

    public function key()
    {
        return key($this->items);
    }

    public function next()
    {
        return next($this->items);
    }

    public function rewind()
    {
        reset($this->items);
    }

    public function getFirst()
    {
        $this->rewind();
        return $this->current();
    }

    public function getFirstKey()
    {
        $this->rewind();
        return $this->key();
    }

    public function getLastKey()
    {
        end($this->items);
        return $this->key();
    }

    public function valid()
    {
        $key = key($this->items);
        return ($key !== null && $key !== false);
    }

    public function contains($value)
    {
        return ($this->getKey($value) != null);
    }

    public function containsKey($key)
    {
        return array_key_exists($key, $this->items);
    }

    public function containsAnyKeyOf(array $keys)
    {
        foreach ($keys as $key)
        {
            if (array_key_exists($key, $this->items))
                return true;
        }
        return false;
    }

    public function put($key, $value)
    {
        if (!($this->containsKey($key)))
        {
            $this->items[$key] = $value;
        }
    }

    public function add($value)
    {
        $this->items[] = $value;
    }

    public function set($key, $value)
    {
        $this->items[$key] = $value;
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->items))
            return $this->items[$key];
        else
            return null;
    }

    public function getKey($value)
    {
        foreach ($this->items as $key => $currentValue)
        {
            if ($currentValue == $value)
                return $key;
        }
        return null;
    }

    public function count()
    {
        return count($this->items);
    }

    public function uniqueValues()
    {
        return array_unique($this->items);
    }

    public function toArray()
    {
        return $this->items;
    }

    public function isEmpty()
    {
        return (count($this->items) == 0);
    }

    public function removeKey($key)
    {
        if (array_key_exists($key, $this->items))
        {
            $this->items[$key] = null;
            unset($this->items[$key]);
        }
    }

    public function join(Collection $collection)
    {
        $this->items = array_merge($collection->toArray(), $this->items);
    }

    public function jsonSerialize()
    {
        return json_encode($this->toArray());
    }

    public function getSubCollection($path)
    {
        return ToolSuite::getSubCollection($this->items, $path);
    }

}
