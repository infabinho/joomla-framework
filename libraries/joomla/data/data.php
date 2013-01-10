<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Data
 *
 * @copyright   Copyright (C) 2005 - 2012 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\Data;

defined('JPATH_PLATFORM') or die;

use Joomla\Date\Date;
use Joomla\Registry\Registry;

/**
 * JData is a class that is used to store data but allowing you to access the data
 * by mimicking the way PHP handles class properties.
 *
 * @package     Joomla.Platform
 * @subpackage  Data
 * @since       12.3
 */
class Data implements Dumpable, \IteratorAggregate, \JsonSerializable, \Countable
{
	/**
	 * The data properties.
	 *
	 * @var    array
	 * @since  12.3
	 */
	private $_properties = array();

	/**
	 * The class constructor.
	 *
	 * @param   mixed  $properties  Either an associative array or another object
	 *                              by which to set the initial properties of the new object.
	 *
	 * @since   11.1
	 * @throws  InvalidArgumentException
	 */
	public function __construct($properties = array())
	{
		// Check the properties input.
		if (!empty($properties))
		{
			// Bind the properties.
			$this->bind($properties);
		}
	}

	/**
	 * The magic get method is used to get a data property.
	 *
	 * This method is a public proxy for the protected getProperty method.
	 *
	 * Note: Magic __get does not allow recursive calls. This can be tricky because the error generated by recursing into
	 * __get is "Undefined property:  {CLASS}::{PROPERTY}" which is misleading. This is relevant for this class because
	 * requesting a non-visible property can trigger a call to a sub-function. If that references the property directly in
	 * the object, it will cause a recursion into __get.
	 *
	 * @param   string  $property  The name of the data property.
	 *
	 * @return  mixed  The value of the data property, or null if the data property does not exist.
	 *
	 * @see     JData::getProperty()
	 * @since   12.3
	 */
	public function __get($property)
	{
		return $this->getProperty($property);
	}

	/**
	 * The magic isset method is used to check the state of an object property.
	 *
	 * @param   string  $property  The name of the data property.
	 *
	 * @return  boolean  True if set, otherwise false is returned.
	 *
	 * @since   12.3
	 */
	public function __isset($property)
	{
		return isset($this->_properties[$property]);
	}

	/**
	 * The magic set method is used to set a data property.
	 *
	 * This is a public proxy for the protected setProperty method.
	 *
	 * @param   string  $property  The name of the data property.
	 * @param   mixed   $value     The value to give the data property.
	 *
	 * @return  void
	 *
	 * @see     JData::setProperty()
	 * @since   12.3
	 */
	public function __set($property, $value)
	{
		$this->setProperty($property, $value);
	}

	/**
	 * The magic unset method is used to unset a data property.
	 *
	 * @param   string  $property  The name of the data property.
	 *
	 * @return  void
	 *
	 * @since   12.3
	 */
	public function __unset($property)
	{
		unset($this->_properties[$property]);
	}

	/**
	 * Binds an array or object to this object.
	 *
	 * @param   mixed    $properties   An associative array of properties or an object.
	 * @param   boolean  $updateNulls  True to bind null values, false to ignore null values.
	 *
	 * @return  JData  Returns itself to allow chaining.
	 *
	 * @since   12.3
	 * @throws  InvalidArgumentException
	 */
	public function bind($properties, $updateNulls = true)
	{
		// Check the properties data type.
		if (!is_array($properties) && !is_object($properties))
		{
			throw new \InvalidArgumentException(sprintf('%s(%s)', __METHOD__, gettype($properties)));
		}

		// Check if the object is traversable.
		if ($properties instanceof \Traversable)
		{
			// Convert iterator to array.
			$properties = iterator_to_array($properties);
		}
		// Check if the object needs to be converted to an array.
		elseif (is_object($properties))
		{
			// Convert properties to an array.
			$properties = (array) $properties;
		}

		// Bind the properties.
		foreach ($properties as $property => $value)
		{
			// Check if the value is null and should be bound.
			if ($value === null && !$updateNulls)
			{
				continue;
			}

			// Set the property.
			$this->setProperty($property, $value);
		}

		return $this;
	}

	/**
	 * Dumps the data properties into a stdClass object, recursively if appropriate.
	 *
	 * @param   integer           $depth   The maximum depth of recursion (default = 3).
	 *                                     For example, a depth of 0 will return a stdClass with all the properties in native
	 *                                     form. A depth of 1 will recurse into the first level of properties only.
	 * @param   SplObjectStorage  $dumped  An array of already serialized objects that is used to avoid infinite loops.
	 *
	 * @return  stdClass  The data properties as a simple PHP stdClass object.
	 *
	 * @since   12.3
	 */
	public function dump($depth = 3, \SplObjectStorage $dumped = null)
	{
		// Check if we should initialise the recursion tracker.
		if ($dumped === null)
		{
			$dumped = new \SplObjectStorage;
		}

		// Add this object to the dumped stack.
		$dumped->attach($this);

		// Setup a container.
		$dump = new \stdClass;

		// Dump all object properties.
		foreach (array_keys($this->_properties) as $property)
		{
			// Get the property.
			$dump->$property = $this->dumpProperty($property, $depth, $dumped);
		}

		return $dump;
	}

	/**
	 * Gets this object represented as an ArrayIterator.
	 *
	 * This allows the data properties to be access via a foreach statement.
	 *
	 * @return  ArrayIterator  This object represented as an ArrayIterator.
	 *
	 * @see     IteratorAggregate::getIterator()
	 * @since   12.3
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->dump(0));
	}

	/**
	 * Gets the data properties in a form that can be serialised to JSON format.
	 *
	 * @return  string  An object that can be serialised by json_encode().
	 *
	 * @since   12.3
	 */
	public function jsonSerialize()
	{
		return $this->dump();
	}

	/**
	 * Dumps a data property.
	 *
	 * If recursion is set, this method will dump any object implementing JDumpable (like JData and JDataSet); it will
	 * convert a JDate object to a string; and it will convert a JRegistry to an object.
	 *
	 * @param   string            $property  The name of the data property.
	 * @param   integer           $depth     The current depth of recursion (a value of 0 will ignore recursion).
	 * @param   SplObjectStorage  $dumped    An array of already serialized objects that is used to avoid infinite loops.
	 *
	 * @return  mixed  The value of the dumped property.
	 *
	 * @since   12.3
	 */
	protected function dumpProperty($property, $depth, \SplObjectStorage $dumped)
	{
		$value = $this->getProperty($property);

		if ($depth > 0)
		{
			// Check if the object is also an dumpable object.
			if ($value instanceof Dumpable)
			{
				// Do not dump the property if it has already been dumped.
				if (!$dumped->contains($value))
				{
					$value = $value->dump($depth - 1, $dumped);
				}
			}
			// Check if the object is a date.
			if ($value instanceof Date)
			{
				$value = (string) $value;
			}
			// Check if the object is a registry.
			elseif ($value instanceof Registry)
			{
				$value = $value->toObject();
			}
		}

		return $value;
	}

	/**
	 * Gets a data property.
	 *
	 * @param   string  $property  The name of the data property.
	 *
	 * @return  mixed  The value of the data property.
	 *
	 * @see     JData::__get()
	 * @since   12.3
	 */
	protected function getProperty($property)
	{
		// Get the raw value.
		$value = array_key_exists($property, $this->_properties) ? $this->_properties[$property] : null;

		return $value;
	}

	/**
	 * Sets a data property.
	 *
	 * If the name of the property starts with a null byte, this method will return null.
	 *
	 * @param   string  $property  The name of the data property.
	 * @param   mixed   $value     The value to give the data property.
	 *
	 * @return  mixed  The value of the data property.
	 *
	 * @see     JData::__set()
	 * @since   12.3
	 */
	protected function setProperty($property, $value)
	{
		/*
		 * Check if the property starts with a null byte. If so, discard it because a later attempt to try to access it
		 * can cause a fatal error. See http://us3.php.net/manual/en/language.types.array.php#language.types.array.casting
		 */
		if (strpos($property, "\0") === 0)
		{
			return null;
		}

		// Set the value.
		$this->_properties[$property] = $value;

		return $value;
	}

	/**
	 * Count the number of data properties.
	 *
	 * @return  integer  The number of data properties.
	 *
	 * @since   12.3
	 */
	public function count()
	{
		return count($this->_properties);
	}
}
