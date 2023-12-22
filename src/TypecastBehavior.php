<?php

namespace yii1tech\model\typecast;

use CActiveRecord;
use Carbon\Carbon;
use CBehavior;
use CBooleanValidator;
use CDbColumnSchema;
use CDbException;
use CEvent;
use CModelEvent;
use CNumberValidator;
use CStringValidator;
use InvalidArgumentException;
use Yii;

/**
 * TypecastBehavior provides an ability of automatic model attribute typecasting.
 *
 * This behavior should be attached to {@see \CModel} or {@see \CActiveRecord} descendant.
 *
 * For example:
 *
 * ```php
 * use yii1tech\model\typecast\TypecastBehavior;
 *
 * class Item extends CActiveRecord
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'typecastBehavior' => [
 *                 'class' => TypecastBehavior::class,
 *                 'attributeTypes' => [
 *                     'id' => TypecastBehavior::TYPE_INTEGER,
 *                     'amount' => TypecastBehavior::TYPE_INTEGER,
 *                     'price' => TypecastBehavior::TYPE_FLOAT,
 *                     'is_active' => TypecastBehavior::TYPE_BOOLEAN,
 *                     'created_at' => TypecastBehavior::TYPE_DATETIME,
 *                     'json_data' => TypecastBehavior::TYPE_ARRAY_OBJECT,
 *                 ],
 *                 'typecastAfterValidate' => true,
 *                 'typecastBeforeSave' => false,
 *                 'typecastAfterSave' => true,
 *                 'typecastAfterFind' => true,
 *             ],
 *         ];
 *     }
 *
 *     // ...
 * }
 * ```
 *
 * Tip: you may leave {@see $attributeTypes} blank - in this case its value will be detected
 * automatically: for ActiveRecord - based on owner DB table schema, for regular model - based validation rules.
 *
 * Note: you can manually trigger attribute typecasting anytime invoking {@see typecastAttributes()} method:
 *
 * ```php
 * $model = new Item();
 * $model->price = '38.5';
 * $model->is_active = 1;
 * $model->typecastAttributes();
 * ```
 *
 * This behavior allows automatic conversion of {@see \DateTime} instances into ISO datetime string, and array into JSON string
 * on model saving. For example:
 *
 * ```php
 * $model = new Item();
 * $model->created_at = new DateTime('now'); // will be saved in DB as '2023-12-22 10:14:17'
 * $model->json_data = [ // will be saved in DB as '{foo: "bar"}'
 *     'foo' => 'bar',
 * ];
 * $model->save();
 * ```
 *
 * @property \CModel|\CActiveRecord $owner The owner component that this behavior is attached to.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class TypecastBehavior extends CBehavior
{
    /**
     * Converts attribute to `int`.
     */
    const TYPE_INTEGER = 'integer';
    /**
     * Converts attribute to `float`.
     */
    const TYPE_FLOAT = 'float';
    /**
     * Converts attribute to `bool`.
     */
    const TYPE_BOOLEAN = 'boolean';
    /**
     * Converts attribute to `string`.
     */
    const TYPE_STRING = 'string';
    /**
     * Converts JSON to array and vice versa.
     */
    const TYPE_ARRAY = 'array';
    /**
     * Converts JSON to {@see \ArrayObject} and vice versa.
     */
    const TYPE_ARRAY_OBJECT = 'array-object';
    /**
     * Converts ISO datetime string into {@see \DateTime} and vice versa.
     */
    const TYPE_DATETIME = 'datetime';
    /**
     * Converts integer Unix timestamp into {@see \DateTime} and vice versa.
     */
    const TYPE_TIMESTAMP = 'timestamp';
    /**
     * Converts ISO datetime string into {@see \Carbon\Carbon} and vice versa.
     */
    const TYPE_DATETIME_CARBON = 'datetime-carbon';
    /**
     * Converts integer Unix timestamp into {@see \Carbon\Carbon} and vice versa.
     */
    const TYPE_TIMESTAMP_CARBON = 'timestamp-carbon';

    /**
     * @var array<string, string|callable>|null attribute typecast map in format: attributeName => type.
     * Type can be set via PHP callable, which accept raw value as an argument and should return
     * typecast result.
     * For example:
     *
     * ```php
     * [
     *     'amount' => 'integer',
     *     'price' => 'float',
     *     'is_active' => 'boolean',
     *     'date' => function ($value) {
     *         return ($value instanceof \DateTime) ? $value->getTimestamp(): (int) $value;
     *     },
     * ]
     * ```
     *
     * If not set, attribute type map will be composed automatically from the owner validation rules.
     */
    public $attributeTypes;
    /**
     * @var bool whether to skip typecasting of `null` values.
     * If enabled attribute value which equals to `null` will not be type-casted (e.g. `null` remains `null`),
     * otherwise it will be converted according to the type configured at {@see attributeTypes}.
     */
    public $skipOnNull = true;
    /**
     * @var bool whether to perform typecasting after owner model validation.
     * Note that typecasting will be performed only if validation was successful, e.g.
     * owner model has no errors.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     */
    public $typecastAfterValidate = true;
    /**
     * @var bool whether to perform typecasting before saving owner model (insert or update).
     * This option may be disabled in order to achieve better performance.
     * For example, in case of {@see \CActiveRecord} usage, typecasting before save
     * will grant no benefit and thus can be disabled.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     */
    public $typecastBeforeSave = false;
    /**
     * @var bool whether to perform typecasting after saving owner model (insert or update).
     * This option may be disabled in order to achieve better performance.
     * For example, in case of {@see \CActiveRecord} usage, typecasting after save
     * will grant no benefit and thus can be disabled.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     * @since 2.0.14
     */
    public $typecastAfterSave = true;
    /**
     * @var bool whether to perform typecasting after retrieving owner model data from
     * the database (after find or refresh).
     * This option may be disabled in order to achieve better performance.
     * For example, in case of {@see \CActiveRecord} usage, typecasting after find
     * will grant no benefit in most cases and thus can be disabled.
     * Note that changing this option value will have no effect after this behavior has been attached to the model.
     */
    public $typecastAfterFind = true;

    /**
     * @var array<string, mixed> stashed raw attributes, used to transfer raw non-scalar values from {@see beforeSave()} to {@see afterSave()}.
     */
    private $_stashedAttributes = [];

    /**
     * @var array<string, array> internal static cache for auto detected {@see $attributeTypes} values
     * in format: `ownerClassName => attributeTypes`.
     */
    private static $autoDetectedAttributeTypes = [];

    /**
     * {@inheritdoc}
     */
    public function attach($owner): void
    {
        parent::attach($owner);

        if ($this->attributeTypes === null) {
            $this->attributeTypes = $this->detectAttributeTypes();
        }
    }

    /**
     * Detects (guesses) the attribute types analysing owner class.
     *
     * @return array<string, string> detected attribute types.
     */
    protected function detectAttributeTypes(): array
    {
        $ownerClass = get_class($this->owner);
        if (!isset(self::$autoDetectedAttributeTypes[$ownerClass])) {
            if ($this->owner instanceof CActiveRecord) {
                self::$autoDetectedAttributeTypes[$ownerClass] = $this->detectAttributeTypesFromSchema();
            } else {
                self::$autoDetectedAttributeTypes[$ownerClass] = $this->detectAttributeTypesFromRules();
            }
        }

        return self::$autoDetectedAttributeTypes[$ownerClass];
    }

    /**
     * Clears internal static cache of auto-detected {@see $attributeTypes} values
     * over all affected owner classes.
     */
    public static function clearAutoDetectedAttributeTypes(): void
    {
        self::$autoDetectedAttributeTypes = [];
    }

    /**
     * Typecast owner attributes according to {@see $attributeTypes}.
     *
     * @param array|null $attributeNames list of attribute names that should be type-casted.
     * If this parameter is empty, it means any attribute listed in the {@see $attributeTypes}
     * should be type-casted.
     * @return \CModel|\CActiveRecord owner instance.
     */
    public function typecastAttributes($attributeNames = null)
    {
        $attributeTypes = [];

        if ($attributeNames === null) {
            $attributeTypes = $this->attributeTypes;
        } else {
            foreach ($attributeNames as $attribute) {
                if (!isset($this->attributeTypes[$attribute])) {
                    throw new InvalidArgumentException("There is no type mapping for '{$attribute}'.");
                }
                $attributeTypes[$attribute] = $this->attributeTypes[$attribute];
            }
        }

        foreach ($attributeTypes as $attribute => $type) {
            $value = $this->owner->{$attribute};
            if ($this->skipOnNull && $value === null) {
                continue;
            }
            $this->owner->{$attribute} = $this->typecastValue($value, $type);
        }

        return $this->owner;
    }

    /**
     * Casts the given value to the specified type.
     *
     * @param mixed $value value to be type-casted.
     * @param string|callable $type type name or typecast callable.
     * @return mixed typecast result.
     */
    protected function typecastValue($value, $type)
    {
        if (!is_scalar($type)) {
            return call_user_func($type, $value);
        }

        switch ($type) {
            case self::TYPE_INTEGER:
            case 'int':
                return (int) $value;
            case self::TYPE_FLOAT:
                return (float) $value;
            case self::TYPE_BOOLEAN:
            case 'bool':
                return (bool) $value;
            case self::TYPE_STRING:
                return (string) $value;
            case self::TYPE_ARRAY:
                if (empty($value) || is_iterable($value)) {
                    return $value;
                }

                return json_decode($value, true);
            case self::TYPE_ARRAY_OBJECT:
                if (empty($value) || is_iterable($value)) {
                    return $value;
                }

                return new \ArrayObject(json_decode($value, true));
            case self::TYPE_DATETIME:
                if ($value === null || $value instanceof \DateTime) {
                    return $value;
                }

                return \DateTime::createFromFormat('Y-m-d H:i:s', (string) $value);
            case self::TYPE_TIMESTAMP:
                if ($value === null || $value instanceof \DateTime) {
                    return $value;
                }

                return (new \DateTime())->setTimestamp((int) $value);
            case self::TYPE_DATETIME_CARBON:
                if ($value === null || $value instanceof \DateTime) {
                    return $value;
                }

                if (!class_exists(Carbon::class)) {
                    throw new \LogicException('Extension "nesbot/carbon" has not been installed');
                }

                return Carbon::createFromFormat('Y-m-d H:i:s', (string) $value);
            case self::TYPE_TIMESTAMP_CARBON:
                if ($value === null || $value instanceof \DateTime) {
                    return $value;
                }

                if (!class_exists(Carbon::class)) {
                    throw new \LogicException('Extension "nesbot/carbon" has not been installed');
                }

                return (new Carbon())->setTimestamp((int) $value);
            default:
                throw new InvalidArgumentException("Unsupported attribute type '{$type}'");
        }
    }

    /**
     * Composes default value for {@see $attributeTypes} from the owner validation rules.
     *
     * @return array attribute type map.
     */
    protected function detectAttributeTypesFromRules(): array
    {
        $attributeTypes = [];
        foreach ($this->owner->getValidators() as $validator) {
            $type = null;
            if ($validator instanceof CBooleanValidator) {
                $type = self::TYPE_BOOLEAN;
            } elseif ($validator instanceof CNumberValidator) {
                $type = $validator->integerOnly ? self::TYPE_INTEGER : self::TYPE_FLOAT;
            } elseif ($validator instanceof CStringValidator) {
                $type = self::TYPE_STRING;
            }

            if ($type !== null) {
                $attributeTypes += array_fill_keys($validator->attributes, $type);
            }
        }

        return $attributeTypes;
    }

    /**
     * Detects attribute types from the owner's DB table schema.
     *
     * @return array<string, string> detected attribute types.
     */
    protected function detectAttributeTypesFromSchema(): array
    {
        $tableName = $this->owner->tableName();

        if (($table = $this->owner->getDbConnection()->getSchema()->getTable($tableName)) === null) {
            throw new CDbException(
                Yii::t('yii', 'The table "{table}" for active record class "{class}" cannot be found in the database.', [
                    '{class}' => get_class($this->owner),
                    '{table}' => $tableName,
                ])
            );
        }

        $attributeTypes = [];
        foreach($table->columns as $column) {
            $attributeTypes[$column->name] = $this->detectTypeFromDbColumnSchema($column);
        }

        return $attributeTypes;
    }

    /**
     * Detects the attribute type from DB column schema.
     *
     * @param \CDbColumnSchema $column DB column schema.
     * @return string type name.
     */
    protected function detectTypeFromDbColumnSchema(CDbColumnSchema $column): string
    {
        switch ($column->type) {
            case 'integer':
                return self::TYPE_INTEGER;
            case 'boolean':
                return self::TYPE_BOOLEAN;
            case 'double':
                return self::TYPE_FLOAT;
        }

        if (stripos($column->dbType, 'json') !== false) {
            return self::TYPE_ARRAY_OBJECT;
        }

        if (stripos($column->dbType, 'date') !== false) {
            return self::TYPE_DATETIME;
        }

        if (stripos($column->dbType, 'timestamp') !== false) {
            return self::TYPE_DATETIME;
        }

        return self::TYPE_STRING;
    }

    /**
     * Stashes original raw value of attribute for the future restoration.
     *
     * @param string $name attribute name.
     * @param mixed $value attribute raw value.
     * @return void
     */
    private function stashAttribute(string $name, $value): void
    {
        $this->_stashedAttributes[$name] = $value;
    }

    /**
     * Applies all stashed attribute values to the owner.
     *
     * @return void
     */
    private function applyStashedAttributes(): void
    {
        foreach ($this->_stashedAttributes as $name => $value) {
            $this->owner->setAttribute($name, $value);
            unset($this->_stashedAttributes[$name]);
        }
    }

    /**
     * Clears all stashed attributes.
     *
     * @return void
     */
    private function clearStashedAttributes(): void
    {
        $this->_stashedAttributes = [];
    }

    /**
     * Performs typecast for attributes values in the way they are suitable for the saving in database.
     * E.g. convert objects and arrays to scalars.
     *
     * @return void
     */
    protected function typecastAttributesForSaving(): void
    {
        $this->clearStashedAttributes();

        foreach ($this->owner->getAttributes() as $name => $value) {
            if ($value === null || is_scalar($value)) {
                continue;
            }

            if ($value instanceof \CDbExpression) {
                continue;
            }

            $this->stashAttribute($name, $value);

            if (is_array($value) || $value instanceof \JsonSerializable) {
                $this->owner->setAttribute($name, json_encode($value));

                continue;
            }

            if ($value instanceof \DateTime) {
                if (isset($this->attributeTypes[$name]) && $this->attributeTypes[$name] === self::TYPE_TIMESTAMP) {
                    $this->owner->setAttribute($name, $value->getTimestamp());
                } else {
                    $this->owner->setAttribute($name, $value->format('Y-m-d H:i:s'));
                }

                continue;
            }

            if ($value instanceof \Traversable) {
                $this->owner->setAttribute($name, json_encode(iterator_to_array($value)));

                continue;
            }

            $this->owner->setAttribute($name, (string) $value);
        }
    }

    // Event Handlers:

    /**
     * {@inheritdoc}
     */
    public function events(): array
    {
        $events = [];

        if ($this->typecastAfterValidate) {
            $events['onAfterValidate'] = 'afterValidate';
        }

        if ($this->getOwner() instanceof CActiveRecord) {
            $events['onBeforeSave'] = 'beforeSave';
            $events['onAfterSave'] = 'afterSave';

            if ($this->typecastAfterFind) {
                $events['onAfterFind'] = 'afterFind';
            }
        }

        return $events;
    }

    /**
     * Handles owner 'afterValidate' event, ensuring attribute typecasting.
     *
     * @param \CEvent $event event instance.
     */
    public function afterValidate(CEvent $event): void
    {
        if (!$this->owner->hasErrors()) {
            $this->typecastAttributes();
        }
    }

    /**
     * Handles owner 'beforeSave' owner event, ensuring attribute typecasting.
     *
     * @param \CModelEvent $event event instance.
     */
    public function beforeSave(CModelEvent $event): void
    {
        if ($this->typecastBeforeSave) {
            $this->typecastAttributes();
        }

        $this->typecastAttributesForSaving();
    }

    /**
     * Handles owner 'afterSave' event, ensuring attribute typecasting.
     *
     * @param \CEvent $event event instance.
     */
    public function afterSave(CEvent $event): void
    {
        $this->applyStashedAttributes();

        if ($this->typecastAfterSave) {
            $this->typecastAttributes();
        }
    }

    /**
     * Handles owner 'afterFind' event, ensuring attribute typecasting.
     *
     * @param \CEvent $event event instance.
     */
    public function afterFind(CEvent $event): void
    {
        $this->typecastAttributes();
    }
}