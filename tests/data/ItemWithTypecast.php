<?php

namespace yii1tech\model\typecast\test\data;

use yii1tech\model\typecast\TypecastBehavior;

/**
 * @mixin \yii1tech\model\typecast\TypecastBehavior
 *
 * @property-read \yii1tech\model\typecast\TypecastBehavior $typecastBehavior
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
                'class' => TypecastBehavior::class,
                'typecastBeforeSave' => true,
                'typecastAfterFind' => true,
                'attributeTypes' => [
                    'name' => TypecastBehavior::TYPE_STRING,
                    'category_id' => TypecastBehavior::TYPE_INTEGER,
                    'price' => TypecastBehavior::TYPE_FLOAT,
                    'is_active' => TypecastBehavior::TYPE_BOOLEAN,
                    'callback' => function ($value) {
                        return 'callback: ' . $value;
                    },
                    'created_date' => TypecastBehavior::TYPE_DATETIME,
                    'created_timestamp' => TypecastBehavior::TYPE_TIMESTAMP,
                    'data_array' => TypecastBehavior::TYPE_ARRAY,
                    'data_array_object' => TypecastBehavior::TYPE_ARRAY_OBJECT,
                ],
            ],
        ];
    }
}