<?php

namespace GoalioForgotPassword\Form;

use Zend\InputFilter\InputFilter;
use GoalioForgotPassword\Options\ForgotOptionsInterface;

class ForgotFilter extends InputFilter
{
    protected $options;

    /**
     * @param ForgotOptionsInterface $options
     */
    public function __construct(ForgotOptionsInterface $options)
    {
        $this->setOptions($options);

        $this->add(array
        (
            'name'       => 'email',
            'required'   => true,
            'validators' => array
            (
                array
                (
                    'name' => 'EmailAddress'
                ),
            ),
        ));
    }

    /**
     * set options
     *
     * @param ForgotOptionsInterface $options
     */
    public function setOptions(ForgotOptionsInterface $options)
    {
        $this->options = $options;
    }

    /**
     * get options
     *
     * @return mixed
     */
    public function getOptions()
    {
        return $this->options;
    }
}
