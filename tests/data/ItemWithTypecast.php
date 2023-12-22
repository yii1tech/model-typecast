<?php

namespace yii1tech\model\typecast\test\data;

use yii1tech\model\typecast\AttributeTypecastBehavior;

/**
 * @mixin \yii1tech\model\typecast\AttributeTypecastBehavior
 *
 * @property-read \yii1tech\model\typecast\AttributeTypecastBehavior $typecastBehavior
 */
class ItemWithTypecast extends Item
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
    public function behaviors(): array
    {
        return [
            'typecastBehavior' => [
                'class' => AttributeTypecastBehavior::class,
                'typecastBeforeSave' => true,
                'typecastAfterFind' => true,
                'attributeTypes' => [
                    'name' => AttributeTypecastBehavior::TYPE_STRING,
                    'category_id' => AttributeTypecastBehavior::TYPE_INTEGER,
                    'price' => AttributeTypecastBehavior::TYPE_FLOAT,
                    'is_active' => AttributeTypecastBehavior::TYPE_BOOLEAN,
                    'callback' => function ($value) {
                        return 'callback: ' . $value;
                    },
                    'created_date' => AttributeTypecastBehavior::TYPE_DATETIME,
                    'created_timestamp' => AttributeTypecastBehavior::TYPE_TIMESTAMP,
                    'data_array' => AttributeTypecastBehavior::TYPE_ARRAY,
                    'data_array_object' => AttributeTypecastBehavior::TYPE_ARRAY_OBJECT,
                ],
            ],
        ];
    }
}