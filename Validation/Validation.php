<?php

declare(strict_types=1);

namespace System\Validation;

use System\Language\Language;

class Validation {
    private $errors = [];
    private $labels = [];
    private $rules = [];
    private $data = [];
    private $language;

    public function __construct(Language $language) {
        $this->language = $language;
    }

    /**
     * rules
     *
     * @param mixed $param1
     * @param string|null $param2
     * @param string|null $param3
     *
     * @return void
     */
    public function rules(mixed $param1, ?string $param2 = null, ?string $param3 = null): void {
        if (is_array($param1)) {
            if (isset($param1[0]) && is_array($param1[0])) {
                foreach ($param1 as $rule) {
                    list($key, $label, $rules) = $rule;
                    $this->labels[$key] = $label;
                    $this->rules[$key] = $rules;
                }
            } else {
                foreach ($param1 as $key => $value) {
                    $this->labels[$key] = $value['label'];
                    $this->rules[$key] = $value['rules'];
                }
            }
        } elseif (is_string($param1) && isset($param2) && isset($param3)) {
            $this->labels[$param1] = $param2;
            $this->rules[$param1] = $param3;
        }
    }

    /**
     * data
     *
     * @param array $data
     *
     * @return void
     */
    public function data(array $data): void {
        foreach ($data as $key => $value) {
            $this->data[$key] = $value;
        }
    }

    /**
     * isValid
     *
     * @return bool
     */
    public function validate(): bool {
        foreach ($this->rules as $key => $value) {
            $rules = explode('|', $value);

            if (in_array('nullable', $rules)) {
                $nullableFieldKey = array_search('nullable', $rules);
                unset($rules[$nullableFieldKey]);

                $nullable = true;
            } else {
                $nullable = false;
            }

            foreach ($rules as $rule) {
                if (strpos($rule, ',')) {
                    $group = explode(',', $rule);
                    $filter = $group[0];
                    $params = $group[1];

                    if ($filter === 'matches') {
                        if ($this->matches($this->data[$key], $this->data[$params]) === false) {
                            $this->errors[$key] = $this->language->get('validation', $filter . '_error', [$this->labels[$key], $this->labels[$params]]);
                        }
                    } else {
                        if ($nullable === true) {
                            if (is_array($this->data[$key])) {
                                foreach ($this->data[$key] as $k => $v) {
                                    if ($this->nullable($v) === false && $this->$filter($v, $params) === false) {
                                        $this->errors[$key][$k] = $this->language->get('validation', $filter . '_error', [$k, $params]);
                                    }
                                }
                            } else {
                                if ($this->nullable($this->data[$key]) === false && $this->$filter($this->data[$key], $params) === false) {
                                    $this->errors[$key] = $this->language->get('validation', $filter . '_error', [$this->labels[$key], $params]);
                                }
                            }
                        } else {
                            if (is_array($this->data[$key])) {
                                foreach ($this->data[$key] as $k => $v) {
                                    if ($this->$filter($v, $params) === false) {
                                        $this->errors[$key][$k] = $this->language->get('validation', $filter . '_error', [$k, $params]);
                                    }
                                }
                            } else {
                                if ($this->$filter($this->data[$key], $params) === false) {
                                    $this->errors[$key] = $this->language->get('validation', $filter . '_error', [$this->labels[$key], $params]);
                                }
                            }
                        }
                    }
                } else {
                    if ($nullable === true) {
                        if (is_array($this->data[$key])) {
                            foreach ($this->data[$key] as $k => $v) {
                                if ($this->nullable($v) === false && $this->$rule($v) === false) {
                                    $this->errors[$key][$k] = $this->language->get('validation', $rule . '_error', $k);
                                }
                            }
                        } else {
                            if ($this->nullable($this->data[$key]) === false && $this->$rule($this->data[$key]) === false) {
                                $this->errors[$key] = $this->language->get('validation', $rule . '_error', $this->labels[$key]);
                            }
                        }
                    } else {
                        if (is_array($this->data[$key])) {
                            foreach ($this->data[$key] as $k => $v) {
                                if ($this->$rule($v) === false) {
                                    $this->errors[$key][$k] = $this->language->get('validation', $rule . '_error', $k);
                                }
                            }
                        } else {
                            if ($this->$rule($this->data[$key]) === false) {
                                $this->errors[$key] = $this->language->get('validation', $rule . '_error', $this->labels[$key]);
                            }
                        }
                    }
                }
            }
        }

        return count($this->errors) === 0;
    }

    /**
     * errors
     *
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * nullable
     *
     * @param mixed $data
     *
     * @return bool
     */
    private function nullable(mixed $data): bool {
        return is_array($data) ? (empty($data) === true) : (trim($data) === '');
    }

    /**
     * required
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function required(mixed $data): bool {
        return is_array($data) ? (empty($data) === false) : (trim($data) !== '');
    }

    /**
     * numeric
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function numeric(mixed $data): bool {
        return is_numeric($data);
    }

    /**
     * email
     *
     * @param mixed $email
     *
     * @return bool
     */
    protected function email(mixed $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * min_len
     *
     * @param mixed $data
     * @param mixed $length
     *
     * @return bool
     */
    protected function min_len(mixed $data, mixed $length): bool {
        return (strlen(trim($data)) < $length) === false;
    }

    /**
     * max_len
     *
     * @param mixed $data
     * @param mixed $length
     *
     * @return bool
     */
    protected function max_len(mixed $data, mixed $length): bool {
        return (strlen(trim($data)) > $length) === false;
    }

    /**
     * exact_len
     *
     * @param mixed $data
     * @param mixed $length
     *
     * @return bool
     */
    protected function exact_len(mixed $data, mixed $length): bool {
        return (strlen(trim($data)) === $length) !== false;
    }

    /**
     * alpha
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function alpha(mixed $data): bool {
        if (!is_string($data)) {
            return false;
        }

        return ctype_alpha($data);
    }

    /**
     * alpha_num
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function alpha_num(mixed $data): bool {
        return ctype_alnum($data);
    }

    /**
     * alpha_dash
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function alpha_dash(mixed $data): bool {
        return (!preg_match("/^([-a-z0-9_-])+$/i", $data)) ? false : true;
    }

    /**
     * alpha_space
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function alpha_space(mixed $data): bool {
        return (!preg_match("/^([A-Za-z0-9- ])+$/i", $data)) ? false : true;
    }

    /**
     * integer
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function integer(mixed $data): bool {
        return filter_var($data, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * boolean
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function boolean(mixed $data): bool {
        $acceptable = [true, false, 0, 1, '0', '1'];

        return in_array($data, $acceptable, true);
    }

    /**
     * float
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function float(mixed $data): bool {
        return filter_var($data, FILTER_VALIDATE_FLOAT) !== false;
    }

    /**
     * valid_url
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function valid_url(mixed $data): bool {
        return filter_var($data, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * valid_ip
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function valid_ip(mixed $data): bool {
        return filter_var($data, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * valid_ipv4
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function valid_ipv4(mixed $data): bool {
        return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    /**
     * valid_ipv6
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function valid_ipv6(mixed $data): bool {
        return filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    /**
     * valid_cc
     *
     * @param mixed $data
     *
     * @return bool
     */
    protected function valid_cc(mixed $data): bool {
        $number = preg_replace('/\D/', '', $data);

        if (function_exists('mb_strlen')) {
            $number_length = mb_strlen($number);
        } else {
            $number_length = strlen($number);
        }

        $parity = $number_length % 2;

        $total = 0;

        for ($i = 0; $i < $number_length; $i++) {
            $digit = $number[$i];

            if ($i % 2 === $parity) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $total += $digit;
        }

        return $total % 10 === 0;
    }

    /**
     * contains
     *
     * @param mixed $data
     * @param mixed $value
     *
     * @return bool
     */
    protected function contains(mixed $data, mixed $value): bool {
        if (is_array($data)) {
            return in_array($value, $data, true);
        }

        return strpos($data, $value) !== false;
    }

    /**
     * min_numeric
     *
     * @param mixed $data
     * @param mixed $value
     *
     * @return bool
     */
    protected function min_numeric(mixed $data, mixed $value): bool {
        return (is_numeric($data) && is_numeric($value) && $data >= $value) !== false;
    }

    /**
     * max_numeric
     *
     * @param mixed $data
     * @param mixed $value
     *
     * @return bool
     */
    protected function max_numeric(mixed $data, mixed $value): bool {
        return (is_numeric($data) && is_numeric($value) && $data <= $value) !== false;
    }

    /**
     * matches
     *
     * @param mixed $data
     * @param mixed $value
     *
     * @return bool
     */
    protected function matches(mixed $data, mixed $value): bool {
        return ($data === $value) !== false;
    }
}
