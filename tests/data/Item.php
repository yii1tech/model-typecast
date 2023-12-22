<?php

namespace yii1tech\model\typecast\test\data;

use CActiveRecord;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property float $price
 * @property bool $is_active
 * @property int $created_timestamp
 * @property string $created_date
 * @property string $callback
 * @property array|string $data_array
 * @property \ArrayObject|string $data_array_object
 */
class Item extends CActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function model($className = __CLASS__)
    {
        return parent::model($className);
    }

    /**
     * {@inheritdoc}
     */
    public function tableName(): string
    {
        return 'item';
    }
}