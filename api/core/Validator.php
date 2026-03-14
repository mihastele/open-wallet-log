<?php
/**
 * Validator Class - Input validation
 */
class Validator {
    private $data;
    private $errors = [];
    
    public function __construct($data) {
        $this->data = $data;
    }
    
    public function required($field) {
        if (!isset($this->data[$field]) || empty($this->data[$field])) {
            $this->errors[$field] = "The {$field} field is required";
        }
        return $this;
    }
    
    public function optional($field) {
        // Just marks the field as optional, no validation
        return $this;
    }
    
    public function email($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
                $this->errors[$field] = "The {$field} must be a valid email address";
            }
        }
        return $this;
    }
    
    public function minLength($field, $length) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[$field] = "The {$field} must be at least {$length} characters";
        }
        return $this;
    }
    
    public function maxLength($field, $length) {
        if (isset($this->data[$field]) && strlen($this->data[$field]) > $length) {
            $this->errors[$field] = "The {$field} must not exceed {$length} characters";
        }
        return $this;
    }
    
    public function numeric($field) {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = "The {$field} must be a number";
        }
        return $this;
    }
    
    public function positive($field) {
        if (isset($this->data[$field]) && is_numeric($this->data[$field]) && $this->data[$field] <= 0) {
            $this->errors[$field] = "The {$field} must be a positive number";
        }
        return $this;
    }
    
    public function inArray($field, $allowed) {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed)) {
            $this->errors[$field] = "The {$field} must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }
    
    public function phone($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            // Basic phone validation - allows various formats
            $phone = preg_replace('/[^\d+]/', '', $this->data[$field]);
            if (strlen($phone) < 10) {
                $this->errors[$field] = "The {$field} must be a valid phone number";
            }
        }
        return $this;
    }
    
    public function date($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $date = DateTime::createFromFormat('Y-m-d', $this->data[$field]);
            if (!$date || $date->format('Y-m-d') !== $this->data[$field]) {
                $this->errors[$field] = "The {$field} must be a valid date (YYYY-MM-DD)";
            }
        }
        return $this;
    }
    
    public function match($field, $matchField) {
        if (isset($this->data[$field]) && isset($this->data[$matchField])) {
            if ($this->data[$field] !== $this->data[$matchField]) {
                $this->errors[$field] = "The {$field} does not match {$matchField}";
            }
        }
        return $this;
    }
    
    public function url($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            if (!filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
                $this->errors[$field] = "The {$field} must be a valid URL";
            }
        }
        return $this;
    }
    
    public function uuid($field) {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            if (!preg_match($pattern, $this->data[$field])) {
                $this->errors[$field] = "The {$field} must be a valid UUID";
            }
        }
        return $this;
    }
    
    public function isValid() {
        return empty($this->errors);
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getFirstError() {
        return reset($this->errors);
    }
}
