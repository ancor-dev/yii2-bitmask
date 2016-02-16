<?php
/**
 * Created by Anton Korniychuk <ancor.dev@gmail.com>.
 */
namespace ancor\bitmask;

use Yii;
use yii\base\Behavior;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;


/**
 * Class BitmaskBehavior
 * @property string[] bitmaskFields read only
 *
 * @package common\behaviors
 */
class BitmaskBehavior extends Behavior
{
    /**
     * @var mixed[] field names with correspond bits and default values
     * @example: [
     *     'banOption'            => [static::OPT_BAN, true],       // default true
     *     'adminOption'          => [static::OPT_ADMIN, false],    // default false
     *     'isConfidantOption'    => [static::OPT_IS_CONFIDANT],    // default false
     *     'emailNotVerifyOption' => static::OPT_EMAIL_NOT_VERIFY,  // default false
     *     ...
     * ]
     */
    public $fields;
    /**
     * @var string Bitmask attribute name in model
     */
    public $bitmaskAttribute = 'options';


    /**
     * @var boolean[] values of fields. This array will be filled with EVENT_AFTER_FIND
     * @example: [
     *               'banOptions' => true,
     *               'emailNotVerifyOptions' => false,
     *           ]
     */
    private $_values;
    /**
     * @var integer[] bits for bit mask, without default value
     */
    private $_fields;


    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_FIND => 'afterFind',
        ];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        if ($this->fields === null) {
            throw new InvalidConfigException('The "fields" property must be set.');
        }

        foreach ($this->fields as $name => $field) {
            if (is_array($field)) {
                if ( !isset($field[0])) {
                    throw new InvalidConfigException('The "' . $name . '" field MUST have bit mask.');
                }
                $this->_values[$name] = $field[0];
                $this->_fields[$name] = $field[1];
            } else {
                $this->_values[$name] = false;
                $this->_fields[$name] = $field;
            }
        }
    }

    /**
     * Parse bitmask and fill bitmask-values array. Based on bitmask-field names array
     *
     * @param int       $bitmask
     * @param integer[] $fields
     *
     * @return boolean[]
     */
    public static function parseBitmask($bitmask, array $fields)
    {
        $values = [];
        foreach ($fields as $name => $bit) {
            $values[$name] = (bool)($bitmask & $bit);
        }

        return $values;
    } // end parseBitmask()

    /**
     * Make bitmask from bitmask-values array and bitmask-field names array
     *
     * @param boolean[] $values
     * @param int[]     $fields
     *
     * @return int      bitmask
     */
    public static function makeBitmask(array $values, array $fields)
    {
        $bitmask = 0;
        foreach ($values as $field => $checked) {
            if ($checked && isset($fields[$field])) $bitmask |= $fields[$field];
        }

        return $bitmask;
    } // end makeBitmask()

    /**
     * Modify bit mask. Add or delete one bit
     *
     * @param integer $bitmask
     * @param integer $bit    modifying bit
     * @param boolean $exists bit must be set? or not?
     *
     * @return int    Result bit mask
     */
    public static function modifyBitmask($bitmask, $bit, $exists)
    {
        return $exists ? $bitmask | $bit : $bitmask & ~$bit;
    } // end modifyBitmask()

    /**
     * Get bitmask fields array
     * @return integer[]
     */
    public function getBitmaskFields()
    {
        return $this->_fields;
    } // end getBitmaskFields()

    /**
     * Get bitmask values array
     * @return boolean[]
     */
    public function getBitmaskValues()
    {
        return $this->_values;
    } // end getBitmaskValues()

    /**
     * @return void
     */
    public function afterFind()
    {
        $bitmask = (int)$this->owner->{$this->bitmaskAttribute};

        $this->_values = static::parseBitmask($bitmask, $this->_fields);
    } // end afterFind()

    /**
     * @inheritdoc
     */
    public function canGetProperty($name, $checkVars = true)
    {
        return isset($this->_fields[$name]) ?: parent::canGetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function canSetProperty($name, $checkVars = true)
    {
        return isset($this->_fields[$name]) ?: parent::canSetProperty($name, $checkVars);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        return isset($this->_values[$name]) ? $this->_values[$name] : parent::__get($name);
    }

    /**
     * @param string  $name  field name
     * @param boolean $value bit checked(exists) or not
     */
    public function __set($name, $value)
    {
        /* @var ActiveRecord $owner */
        $owner = $this->owner;;

        if (isset($this->_fields[$name])) {
            $this->_values[$name] = $value;

            $owner->{$this->bitmaskAttribute} = static::modifyBitmask($owner->{$this->bitmaskAttribute}, $this->_fields[$name], $value);
        } else {
            parent::__set($name, $value);
        }
    }
}