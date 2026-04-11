<?php

namespace Http\Forms;

use Core\ValidationException;
use Core\Validator;

class LoginForm {

    protected $errors = [];

    public function __construct(public array $attributes) {
        if (! Validator::email($attributes['email'])) {
            $this->errors['email'] = 'Provide a valid email';
        }

        if (! Validator::string($attributes['password'])) {
            $this->errors['password'] = 'Provide a valid password';
        }
    }

    public function throw() {
        ValidationException::throw($this->errors(), $this->attributes);
    }

    public function failed() {
        return count($this->errors);
    }
    
    public static function validate($attributes) {
        $instance = new static($attributes);

        return $instance->failed() ? $instance->throw() : $instance;
    }

    public function errors() {
        return $this->errors;
    }

    public function error($field, $msg) {
        $this->errors[$field] = $msg;
        
        return $this;
    }
}