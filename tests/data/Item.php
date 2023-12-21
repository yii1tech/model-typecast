<?php

namespace yii1tech\ar\softdelete\test\data;

use CActiveRecord;
use CDbException;
use yii1tech\model\typecast\AttributeTypecastBehavior;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property bool $is_deleted
 * @property int $created_at
 * @property int $created_date
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
    public function tableName()
    {
        return 'item';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'softDeleteBehavior' => [
                'class' => AttributeTypecastBehavior::class,
            ],
        ];
    }
}