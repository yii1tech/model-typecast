<?php

namespace yii1tech\model\typecast\test\data;

use CFormModel;
use yii1tech\model\typecast\TypecastBehavior;

class FormWithTypecast extends CFormModel
{
    public $name;

    public $amount;

    public $price;

    public $isAccepted;

    /**
     * {@inheritdoc}
     */
    public function rules(): array
    {
        return [
            ['name', 'length', 'min' => 2],
            ['amount', 'numerical', 'integerOnly' => true],
            ['price', 'numerical', 'integerOnly' => false],
            ['isAccepted', 'boolean'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'typecastBehavior' => [
                'class' => TypecastBehavior::class,
                'attributeTypes' => null,
            ],
        ];
    }
}