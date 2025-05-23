<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfValidatorSchema represents an array of fields.
 *
 * A field is a named validator.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class sfValidatorSchema extends sfValidatorBase implements ArrayAccess
{
    protected $fields = [];
    protected $preValidator;
    protected $postValidator;

    /**
     * Constructor.
     *
     * The first argument can be:
     *
     *  * null
     *  * an array of named sfValidatorBase instances
     *
     * @param mixed $fields   Initial fields
     * @param array $options  An array of options
     * @param array $messages An array of error messages
     *
     * @see sfValidatorBase
     */
    public function __construct($fields = null, $options = [], $messages = [])
    {
        if (is_array($fields)) {
            foreach ($fields as $name => $validator) {
                $this[$name] = $validator;
            }
        } elseif (null !== $fields) {
            throw new InvalidArgumentException('sfValidatorSchema constructor takes an array of sfValidatorBase objects.');
        }

        parent::__construct($options, $messages);
    }

    public function __clone()
    {
        foreach ($this->fields as $name => $field) {
            $this->fields[$name] = clone $field;
        }

        if (null !== $this->preValidator) {
            $this->preValidator = clone $this->preValidator;
        }

        if (null !== $this->postValidator) {
            $this->postValidator = clone $this->postValidator;
        }
    }

    /**
     * @see sfValidatorBase
     */
    public function clean($values)
    {
        return $this->doClean($values);
    }

    /**
     * Cleans the input values.
     *
     * This method is the first validator executed by doClean().
     *
     * It executes the validator returned by getPreValidator()
     * on the global array of values.
     *
     * @param array $values The input values
     *
     * @return array The cleaned values
     *
     * @throws sfValidatorError
     */
    public function preClean($values)
    {
        if (null === $validator = $this->getPreValidator()) {
            return $values;
        }

        return $validator->clean($values);
    }

    /**
     * Cleans the input values.
     *
     * This method is the last validator executed by doClean().
     *
     * It executes the validator returned by getPostValidator()
     * on the global array of cleaned values.
     *
     * @param array $values The input values
     *
     * @throws sfValidatorError
     */
    public function postClean($values)
    {
        if (null === $validator = $this->getPostValidator()) {
            return $values;
        }

        return $validator->clean($values);
    }

    /**
     * Sets the pre validator.
     *
     * @param sfValidatorBase $validator An sfValidatorBase instance
     *
     * @return sfValidatorBase The current validator instance
     */
    public function setPreValidator(sfValidatorBase $validator)
    {
        $this->preValidator = clone $validator;

        return $this;
    }

    /**
     * Returns the pre validator.
     *
     * @return sfValidatorBase A sfValidatorBase instance
     */
    public function getPreValidator()
    {
        return $this->preValidator;
    }

    /**
     * Sets the post validator.
     *
     * @param sfValidatorBase $validator An sfValidatorBase instance
     *
     * @return sfValidatorBase The current validator instance
     */
    public function setPostValidator(sfValidatorBase $validator)
    {
        $this->postValidator = clone $validator;

        return $this;
    }

    /**
     * Returns the post validator.
     *
     * @return sfValidatorBase An sfValidatorBase instance
     */
    public function getPostValidator()
    {
        return $this->postValidator;
    }

    /**
     * Returns true if the schema has a field with the given name (implements the ArrayAccess interface).
     *
     * @param string $name The field name
     *
     * @return bool true if the schema has a field with the given name, false otherwise
     */
    #[ReturnTypeWillChange]
    public function offsetExists($name)
    {
        return isset($this->fields[$name]);
    }

    /**
     * Gets the field associated with the given name (implements the ArrayAccess interface).
     *
     * @param string $name The field name
     *
     * @return sfValidatorBase The sfValidatorBase instance associated with the given name, null if it does not exist
     */
    #[ReturnTypeWillChange]
    public function offsetGet($name)
    {
        return isset($this->fields[$name]) ? $this->fields[$name] : null;
    }

    /**
     * Sets a field (implements the ArrayAccess interface).
     *
     * @param string          $name      The field name
     * @param sfValidatorBase $validator An sfValidatorBase instance
     */
    #[ReturnTypeWillChange]
    public function offsetSet($name, $validator)
    {
        if (!$validator instanceof sfValidatorBase) {
            throw new InvalidArgumentException('A validator must be an instance of sfValidatorBase.');
        }

        /** @var sfValidatorBase $origValidator */
        $origValidator = isset($this->fields[$name]) ? $this->fields[$name] : null;

        $this->fields[$name] = clone $validator;

        // on generated form from a model, a basic validator is already
        // supplied such as on string with max_length or min_length,
        // or number with max or min
        if ($origValidator) {
            $fn = function ($validator, $validatorClass) {
                if (is_a($validator, $validatorClass)) {
                    return $validator;
                }
                if ($validator instanceof sfValidatorAnd || $validator instanceof sfValidatorOr) {
                    foreach ($validator->getValidators() as $v) {
                        if (is_a($v, $validatorClass)) {
                            return $v;
                        }
                    }
                }
            };
            foreach ([
                sfValidatorString::class => ['max_length', 'min_length'],
                sfValidatorNumber::class => ['max', 'min'],
            ] as $validatorClass => $copiedOptions) {
                if (is_a($origValidator, $validatorClass) && $targetValidator = $fn($this->fields[$name], $validatorClass)) {
                    $origOptions = $origValidator->getOptions();
                    /** @var sfValidatorBase $targetValidator */
                    $targetOptions = $targetValidator->getOptions();
                    foreach ($copiedOptions as $opt) {
                        if (!array_key_exists($opt, $targetOptions)) {
                            continue;
                        }
                        if (null === $targetOptions[$opt] && null !== ($value = $origOptions[$opt])) {
                            $targetValidator->setOption($opt, $value);
                        }
                    }
                    break;
                }
            }
        }
    }

    /**
     * Removes a field by name (implements the ArrayAccess interface).
     *
     * @param string $name
     */
    #[ReturnTypeWillChange]
    public function offsetUnset($name)
    {
        unset($this->fields[$name]);
    }

    /**
     * Returns an array of fields.
     *
     * @return sfValidatorBase[] An array of sfValidatorBase instances
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @see sfValidatorBase
     */
    public function asString($indent = 0)
    {
        throw new Exception('Unable to convert a sfValidatorSchema to string.');
    }

    /**
     * Configures the validator.
     *
     * Available options:
     *
     *  * allow_extra_fields:  if false, the validator adds an error if extra fields are given in the input array of values (default to false)
     *  * filter_extra_fields: if true, the validator filters extra fields from the returned array of cleaned values (default to true)
     *
     * Available error codes:
     *
     *  * extra_fields
     *
     * @param array $options  An array of options
     * @param array $messages An array of error messages
     *
     * @see sfValidatorBase
     */
    protected function configure($options = [], $messages = [])
    {
        $this->addOption('allow_extra_fields', false);
        $this->addOption('filter_extra_fields', true);

        $this->addMessage('extra_fields', 'Unexpected extra form field named "%field%".');
        $this->addMessage('post_max_size', 'The form submission cannot be processed. It probably means that you have uploaded a file that is too big.');
    }

    /**
     * @see sfValidatorBase
     */
    protected function doClean($values)
    {
        if (null === $values) {
            $values = [];
        }

        if (!is_array($values)) {
            throw new InvalidArgumentException('You must pass an array parameter to the clean() method');
        }

        $clean = [];
        $unused = array_keys($this->fields);
        $errorSchema = new sfValidatorErrorSchema($this);

        // check that post_max_size has not been reached
        if (isset($_SERVER['CONTENT_LENGTH']) && (int) $_SERVER['CONTENT_LENGTH'] > $this->getBytes(ini_get('post_max_size')) && 0 != ini_get('post_max_size')) {
            $errorSchema->addError(new sfValidatorError($this, 'post_max_size'));

            throw $errorSchema;
        }

        // pre validator
        try {
            $values = $this->preClean($values);
        } catch (sfValidatorErrorSchema $e) {
            $errorSchema->addErrors($e);
        } catch (sfValidatorError $e) {
            $errorSchema->addError($e);
        }

        // validate given values
        foreach ($values as $name => $value) {
            // field exists in our schema?
            if (!array_key_exists($name, $this->fields)) {
                if (!$this->options['allow_extra_fields']) {
                    $errorSchema->addError(new sfValidatorError($this, 'extra_fields', ['field' => $name]));
                } elseif (!$this->options['filter_extra_fields']) {
                    $clean[$name] = $value;
                }

                continue;
            }

            unset($unused[array_search($name, $unused, true)]);

            // validate value
            try {
                $clean[$name] = $this->fields[$name]->clean($value);
            } catch (sfValidatorError $e) {
                $clean[$name] = null;

                $errorSchema->addError($e, (string) $name);
            } catch (Exception $e) {
                $class = get_class($e);

                throw new $class($e->getMessage().' of "'.$name.'" field');
            }
        }

        // are non given values required?
        foreach ($unused as $name) {
            // validate value
            try {
                $clean[$name] = $this->fields[$name]->clean(null);
            } catch (sfValidatorError $e) {
                $clean[$name] = null;

                $errorSchema->addError($e, (string) $name);
            }
        }

        // post validator
        try {
            $clean = $this->postClean($clean);
        } catch (sfValidatorErrorSchema $e) {
            $errorSchema->addErrors($e);
        } catch (sfValidatorError $e) {
            $errorSchema->addError($e);
        }

        if (count($errorSchema)) {
            throw $errorSchema;
        }

        return $clean;
    }

    protected function getBytes($value)
    {
        $value = trim((string) $value);
        $number = (float) $value;
        if (strlen($value)) {
            $modifier = strtolower($value[strlen($value) - 1]);

            $exp_by_modifier = [
                'k' => 1,
                'm' => 2,
                'g' => 3,
            ];

            if (array_key_exists($modifier, $exp_by_modifier)) {
                $exp = $exp_by_modifier[$modifier];
                $number = $number * pow(1024, $exp);
            }
        }

        return $number;
    }
}
