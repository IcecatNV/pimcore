<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Model\DataObject;

use Pimcore\Model;
use Pimcore\Model\Paginator\PaginateListingInterface;

/**
 * @method Model\DataObject[] load()
 * @method Model\DataObject current()
 * @method int getTotalCount()
 * @method int getCount()
 * @method int[] loadIdList()
 * @method \Pimcore\Model\DataObject\Listing\Dao getDao()
 * @method onCreateQueryBuilder(?callable $callback)
 */
class Listing extends Model\Listing\AbstractListing implements PaginateListingInterface
{
    /**
     * @var bool
     */
    protected $unpublished = false;

    /**
     * @var array
     */
    protected $objectTypes = [Model\DataObject::OBJECT_TYPE_OBJECT, Model\DataObject::OBJECT_TYPE_FOLDER];

    /**
     * @return array
     */
    public function getObjects()
    {
        return $this->getData();
    }

    /**
     * @param array $objects
     *
     * @return static
     */
    public function setObjects($objects)
    {
        return $this->setData($objects);
    }

    /**
     * @return bool
     */
    public function getUnpublished()
    {
        return $this->unpublished;
    }

    /**
     * @param bool $unpublished
     *
     * @return $this
     */
    public function setUnpublished($unpublished)
    {
        $this->setData(null);

        $this->unpublished = (bool) $unpublished;

        return $this;
    }

    /**
     * @param array $objectTypes
     *
     * @return $this
     */
    public function setObjectTypes($objectTypes)
    {
        $this->setData(null);

        $this->objectTypes = $objectTypes;

        return $this;
    }

    /**
     * @return array
     */
    public function getObjectTypes()
    {
        return $this->objectTypes;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @param string $concatenator
     *
     * @return $this
     */
    public function addConditionParam($key, $value = null, $concatenator = 'AND')
    {
        return parent::addConditionParam($key, $value, $concatenator); // TODO: Change the autogenerated stub
    }

    /**
     * @return $this
     */
    public function resetConditionParams()
    {
        return parent::resetConditionParams(); // TODO: Change the autogenerated stub
    }

    /**
     * @param string $condition
     * @param array|scalar $conditionVariables
     *
     * @return $this
     */
    public function setCondition($condition, $conditionVariables = null)
    {
        return parent::setCondition($condition, $conditionVariables);
    }

    /**
     *
     * Methods for AdapterInterface
     */

    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->getDao()->getTotalCount();
    }

    /**
     * {@inheritdoc}
     */
    public function getItems($offset, $itemCountPerPage)
    {
        $this->setOffset($offset);
        $this->setLimit($itemCountPerPage);

        return $this->load();
    }

    /**
     * @internal
     *
     * @return bool
     */
    public function addDistinct()
    {
        return false;
    }

    /**
     * @internal
     *
     * @param string $field database column to use for WHERE condition
     * @param string $operator SQL comparison operator, e.g. =, <, >= etc. You can use "?" as placeholder, e.g. "IN (?)"
     * @param string|int|float|array $data comparison data, can be scalar or array (if operator is e.g. "IN (?)")
     *
     * @return static
     */
    public function addFilterByField($field, $operator, $data)
    {
        if (strpos($operator, '?') === false) {
            $operator .= ' ?';
        }

        return $this->addConditionParam('`'.$field.'` '.$operator, $data);
    }
}
