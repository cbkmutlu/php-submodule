<?php

declare(strict_types=1);

namespace System\Validation;

class Validation {
   private array $errors = [];
   private array $labels = [];
   private array $rules = [];
   private array $data = [];
   private array $messages = [];
   private array $customRules = [];
   private bool $stopOnFirstFail = false;

   public function __construct() {
      $this->setDefaultMessages();
   }

   /**
    * Set validation data
    */
   public function data(array $data): self {
      $this->data = $data;
      return $this;
   }

   /**
    * Set validation rules
    */
   public function rules(array $rules): self {
      $this->rules = $rules;
      return $this;
   }

   /**
    * Set custom labels for fields
    */
   public function labels(array $labels): self {
      $this->labels = $labels;
      return $this;
   }

   /**
    * Set custom error messages
    */
   public function messages(array $messages): self {
      $this->messages = array_merge($this->messages, $messages);
      return $this;
   }

   /**
    * Get all errors
    */
   public function errors(): array {
      return $this->errors;
   }

   /**
    * Stop validation on first failure
    *
    * @param bool $stop
    * @return self
    */
   public function stopOnFirstFail(bool $stop = true): self {
      $this->stopOnFirstFail = $stop;
      return $this;
   }

   /**
    * Validate the data
    */
   public function validate(): bool {
      $this->errors = [];

      foreach ($this->rules as $field => $rules) {
         $this->checkField($field, $rules);

         if ($this->stopOnFirstFail && !empty($this->errors)) {
            break;
         }
      }

      return empty($this->errors);
   }

   /**
    * Check a single field
    */
   private function checkField(string $field, array $rules): void {
      if (strpos($field, '*') !== false) {
         $this->checkWildcardField($field, $rules);
         return;
      }

      $value = $this->getFieldValue($field);
      $label = $this->labels[$field] ?? $field;

      foreach ($rules as $rule) {
         $this->applyRule($field, $value, $rule, $label);

         if ($this->stopOnFirstFail && !empty($this->errors)) {
            break;
         }
      }
   }

   /**
    * Check wildcard fields
    */
   private function checkWildcardField(string $field, array $rules): void {
      $parts = explode('.', $field);
      $data = $this->data;
      $paths = $this->expandWildcard($parts, $data);

      foreach ($paths as $path) {
         $value = $this->getFieldValue($path);
         $label = $this->labels[$field] ?? $path;

         foreach ($rules as $rule) {
            $this->applyRule($path, $value, $rule, $label);

            if ($this->stopOnFirstFail && !empty($this->errors)) {
               break;
            }
         }
      }
   }

   /**
    * Expand wildcard paths
    */
   private function expandWildcard(array $parts, $data, string $prefix = ''): array {
      $paths = [];
      $current = array_shift($parts);

      if ($current === '*') {
         if (is_array($data)) {
            foreach (array_keys($data) as $key) {
               $newPrefix = $prefix ? $prefix . '.' . $key : $key;
               if (empty($parts)) {
                  $paths[] = $newPrefix;
               } else {
                  $paths = array_merge($paths, $this->expandWildcard($parts, $data[$key], $newPrefix));
               }
            }
         }
      } else {
         $newPrefix = $prefix ? $prefix . '.' . $current : $current;
         if (empty($parts)) {
            $paths[] = $newPrefix;
         } else {
            $nextData = is_array($data) && isset($data[$current]) ? $data[$current] : null;
            $paths = array_merge($paths, $this->expandWildcard($parts, $nextData, $newPrefix));
         }
      }

      return $paths;
   }

   /**
    * Get field value using dot notation
    */
   private function getFieldValue(string $field) {
      $keys = explode('.', $field);
      $value = $this->data;

      foreach ($keys as $key) {
         if (is_array($value) && array_key_exists($key, $value)) {
            $value = $value[$key];
         } else {
            return null;
         }
      }

      return $value;
   }

   /**
    * Apply a validation rule
    */
   private function applyRule(string $field, $value, string $rule, string $label): void {
      $params = [];
      if (strpos($rule, ':') !== false) {
         [$rule, $paramString] = explode(':', $rule, 2);
         $params = explode(',', $paramString);
      }

      $method = 'validate' . ucfirst($rule);

      if (method_exists($this, $method)) {
         $result = $this->$method($value, $params);
         if (!$result) {
            $this->addError($field, $rule, $label, $params);
         }
      } elseif (isset($this->customRules[$rule])) {
         $result = call_user_func($this->customRules[$rule], $value, $params, $field);
         if (!$result) {
            $this->addError($field, $rule, $label, $params);
         }
      }
   }

   /**
    * Add a custom validation rule
    */
   public function addRule(string $name, callable $callback, string $message = ':attribute geçersiz.'): self {
      $this->customRules[$name] = $callback;
      $this->messages[$name] = $message;
      return $this;
   }

   /**
    * Add an error message
    */
   private function addError(string $field, string $rule, string $label, array $params = []): void {
      $message = $this->messages[$rule] ?? $this->messages['default'];
      $message = str_replace(':attribute', $label, $message);
      $message = str_replace(':field', $label, $message);

      if (!empty($params)) {
         $labeledParams = array_map(function ($param) {
            return $this->labels[$param] ?? $param;
         }, $params);
         $message = str_replace(':values', implode(', ', $labeledParams), $message);

         foreach ($params as $index => $param) {
            $message = str_replace(':' . $index, $param, $message);
            $message = str_replace(':value', $param, $message);
         }
      }

      if (!isset($this->errors[$field])) {
         $this->errors[$field] = [];
      }

      $this->errors[$field][] = $message;
   }

   /**
    * Set default error messages
    */
   private function setDefaultMessages(): void {
      $this->messages = [
         // Basic
         'required'     => ':attribute alanı zorunludur.',
         'nullable'     => ':attribute boş bırakılabilir.',
         'requiredWith' => ':attribute alanı, :values alanlarından biri mevcut olduğunda zorunludur.',
         'requiredWithout' => ':attribute alanı, :values alanlarından biri mevcut olmadığında zorunludur.',
         'requiredWithAll' => ':attribute alanı, :values alanlarının tümü mevcut olduğunda zorunludur.',
         'requiredWithoutAll' => ':attribute alanı, :values alanlarının tümü mevcut olmadığında zorunludur.',

         // Data Type
         'email'        => ':attribute geçerli bir e-posta adresi olmalıdır.',
         'numeric'      => ':attribute sayısal bir değer olmalıdır.',
         'integer'      => ':attribute tam sayı olmalıdır.',
         'float'        => ':attribute ondalıklı sayı olmalıdır.',
         'string'       => ':attribute metin olmalıdır.',
         'array'        => ':attribute bir dizi olmalıdır.',
         'json'         => ':attribute geçerli bir JSON formatında olmalıdır.',
         'boolean'      => ':attribute true veya false olmalıdır.',

         // Length/Value
         'min'          => ':attribute en az :value olmalıdır.',
         'max'          => ':attribute en fazla :value olmalıdır.',
         'between'      => ':attribute :0 ile :1 arasında olmalıdır.',
         'exact'        => ':attribute tam olarak :value olmalıdır.',

         // Array Rules
         'in'           => ':attribute geçerli bir değer olmalıdır.',
         'notIn'        => ':attribute geçersiz bir değer içeriyor.',

         // String Rules
         'contains'     => ':attribute :value içermelidir.',
         'notContains'  => ':attribute :value içermemelidir.',
         'startWith'    => ':attribute :value ile başlamalıdır.',
         'endWith'      => ':attribute :value ile bitmelidir.',
         'alpha'        => ':attribute sadece harflerden oluşmalıdır.',
         'alphanum'     => ':attribute sadece harf ve rakamlardan oluşmalıdır.',
         'alphaDash'    => ':attribute sadece harf, rakam, tire ve alt çizgi içerebilir.',

         // URL and Network
         'url'          => ':attribute geçerli bir URL olmalıdır.',
         'ip'           => ':attribute geçerli bir IP adresi olmalıdır.',
         'ipv4'         => ':attribute geçerli bir IPv4 adresi olmalıdır.',
         'ipv6'         => ':attribute geçerli bir IPv6 adresi olmalıdır.',

         // Format
         'date'         => ':attribute geçerli bir tarih olmalıdır.',
         'creditCard'   => ':attribute geçerli bir kredi kartı numarası olmalıdır.',

         // File
         'mimes'        => ':attribute geçerli bir dosya türü olmalıdır.',
         'minSize'      => ':attribute en az :value KB boyutunda olmalıdır.',
         'maxSize'      => ':attribute en fazla :value KB boyutunda olmalıdır.',

         // Comparison
         'confirmed'    => ':attribute onayı eşleşmiyor.',
         'same'         => ':attribute ile :value eşleşmelidir.',
         'different'    => ':attribute ile :value farklı olmalıdır.',

         // Other
         'regex'        => ':attribute formatı geçersiz.',
         'default'      => ':attribute geçersiz.',
      ];
   }

   /**
    * Required validation
    */
   private function validateRequired($value): bool {
      if (is_null($value)) {
         return false;
      }
      if (is_string($value) && trim($value) === '') {
         return false;
      }
      if (is_array($value) && empty($value)) {
         return false;
      }
      return true;
   }

   /**
    * Required with validation
    */
   private function validateRequiredWith($value, array $params): bool {
      $hasAnyField = false;

      foreach ($params as $fieldName) {
         $otherValue = $this->getFieldValue($fieldName);

         if (!is_null($otherValue) && $otherValue !== '') {
            $hasAnyField = true;
            break;
         }
      }

      if (!$hasAnyField) {
         return true;
      }

      return $this->validateRequired($value);
   }

   /**
    * Required without validation
    */
   private function validateRequiredWithout($value, array $params): bool {
      foreach ($params as $fieldName) {
         $otherValue = $this->getFieldValue($fieldName);

         if (is_null($otherValue) || $otherValue === '') {
            return $this->validateRequired($value);
         }
      }

      return true;
   }

   /**
    * Required with all validation
    */
   private function validateRequiredWithAll($value, array $params): bool {
      foreach ($params as $fieldName) {
         $otherValue = $this->getFieldValue($fieldName);

         if (is_null($otherValue) || $otherValue === '') {
            return true;
         }
      }

      return $this->validateRequired($value);
   }

   /**
    * Required without all validation
    */
   private function validateRequiredWithoutAll($value, array $params): bool {
      foreach ($params as $fieldName) {
         $otherValue = $this->getFieldValue($fieldName);

         if (!is_null($otherValue) && $otherValue !== '') {
            return true;
         }
      }

      return $this->validateRequired($value);
   }

   /**
    * Nullable validation (allows null values)
    */
   private function validateNullable($value): bool {
      return true;
   }

   /**
    * Email validation
    */
   private function validateEmail($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
   }

   /**
    * Numeric validation
    */
   private function validateNumeric($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return is_numeric($value);
   }

   /**
    * Integer validation
    */
   private function validateInteger($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return filter_var($value, FILTER_VALIDATE_INT) !== false;
   }

   /**
    * Float validation
    */
   private function validateFloat($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
   }

   /**
    * String validation
    */
   private function validateString($value): bool {
      if (is_null($value)) {
         return true;
      }
      return is_string($value);
   }

   /**
    * Array validation
    */
   private function validateArray($value): bool {
      if (is_null($value)) {
         return true;
      }
      return is_array($value);
   }

   /**
    * Boolean validation
    */
   private function validateBoolean($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return in_array($value, [true, false, 0, 1, '0', '1'], true);
   }

   /**
    * Minimum value/length validation
    */
   private function validateMin($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      $min = $params[0] ?? 0;

      if (is_numeric($value)) {
         return $value >= $min;
      }

      if (is_string($value)) {
         return mb_strlen($value) >= $min;
      }

      if (is_array($value)) {
         return count($value) >= $min;
      }

      return false;
   }

   /**
    * Maximum value/length validation
    */
   private function validateMax($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      $max = $params[0] ?? 0;

      if (is_numeric($value)) {
         return $value <= $max;
      }

      if (is_string($value)) {
         return mb_strlen($value) <= $max;
      }

      if (is_array($value)) {
         return count($value) <= $max;
      }

      return false;
   }

   /**
    * Between validation
    */
   private function validateBetween($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      $min = $params[0] ?? 0;
      $max = $params[1] ?? 0;

      if (is_numeric($value)) {
         return $value >= $min && $value <= $max;
      }

      if (is_string($value)) {
         $length = mb_strlen($value);
         return $length >= $min && $length <= $max;
      }

      if (is_array($value)) {
         $count = count($value);
         return $count >= $min && $count <= $max;
      }

      return false;
   }

   /**
    * Exact validation
    */
   private function validateExact($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      $exact = $params[0] ?? 0;

      if (is_numeric($value)) {
         return $value == $exact;
      }

      if (is_string($value)) {
         return mb_strlen($value) == $exact;
      }

      if (is_array($value)) {
         return count($value) == $exact;
      }

      return false;
   }

   /**
    * Contains validation
    */
   private function validateContains($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      if (!is_string($value)) {
         return false;
      }

      $needle = $params[0] ?? '';

      if (empty($needle)) {
         return false;
      }

      if (function_exists('mb_strpos')) {
         return mb_strpos($value, $needle) !== false;
      }

      return strpos($value, $needle) !== false;
   }

   /**
    * Not contains validation
    */
   private function validateNotContains($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      if (!is_string($value)) {
         return false;
      }

      $needle = $params[0] ?? '';

      if (empty($needle)) {
         return false;
      }

      if (function_exists('mb_strpos')) {
         return mb_strpos($value, $needle) === false;
      }

      return strpos($value, $needle) === false;
   }

   /**
    * Start with validation
    */
   private function validateStartWith($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      if (!is_string($value)) {
         return false;
      }

      $needle = $params[0] ?? '';

      if (empty($needle)) {
         return false;
      }

      if (function_exists('mb_strpos')) {
         return mb_strpos($value, $needle) === 0;
      }

      return strpos($value, $needle) === 0;
   }

   /**
    * End with validation
    */
   private function validateEndWith($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      if (!is_string($value)) {
         return false;
      }

      $needle = $params[0] ?? '';

      if (empty($needle)) {
         return false;
      }

      if (function_exists('mb_substr')) {
         $needleLength = mb_strlen($needle);
         return mb_substr($value, -$needleLength) === $needle;
      }

      $needleLength = strlen($needle);
      return substr($value, -$needleLength) === $needle;
   }

   /**
    * In validation (value must be in the list)
    */
   private function validateIn($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return in_array($value, $params, true);
   }

   /**
    * Not in validation (value must not be in the list)
    */
   private function validateNotIn($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return !in_array($value, $params, true);
   }

   /**
    * URL validation
    */
   private function validateUrl($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return filter_var($value, FILTER_VALIDATE_URL) !== false;
   }

   /**
    * Date validation
    */
   private function validateDate($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return strtotime($value) !== false;
   }

   /**
    * Credit card validation
    */
   private function validateCreditCard($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      $value = preg_replace('/[^0-9]/', '', $value);
      $sum = 0;
      $length = strlen($value);

      for ($i = 0; $i < $length; $i++) {
         $digit = (int)$value[$length - $i - 1];
         if ($i % 2 == 1) {
            $digit *= 2;
            if ($digit > 9) {
               $digit -= 9;
            }
         }
         $sum += $digit;
      }

      return $sum % 10 === 0;
   }

   /**
    * Mimes validation
    */
   private function validateMimes($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      if (is_array($value) && isset($value['name'])) {
         $filename = $value['name'];
      } else {
         return false;
      }

      $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
      $allowedMimes = array_map('strtolower', $params);

      return in_array($extension, $allowedMimes);
   }

   /**
    * Min size validation
    */
   private function validateMinSize($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      $minSize = $params[0] ?? 0;

      if (is_array($value) && isset($value['size'])) {
         $fileSize = $value['size'];
      } else {
         return false;
      }

      $fileSizeKB = $fileSize / 1024;

      return $fileSizeKB >= $minSize;
   }

   /**
    * Max size validation
    */
   private function validateMaxSize($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      $maxSize = $params[0] ?? 0;

      if (is_array($value) && isset($value['size'])) {
         $fileSize = $value['size'];
      } else {
         return false;
      }

      $fileSizeKB = $fileSize / 1024;

      return $fileSizeKB <= $maxSize;
   }

   /**
    * Regex validation
    */
   private function validateRegex($value, array $params): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      $pattern = $params[0] ?? '';
      return preg_match($pattern, $value) === 1;
   }

   /**
    * Confirmed validation (field must match field_confirmation)
    */
   private function validateConfirmed($value, array $params, string $field = ''): bool {
      $confirmField = $field . '_confirmation';
      $confirmValue = $this->getFieldValue($confirmField);
      return $value === $confirmValue;
   }

   /**
    * Same validation (field must match another field)
    */
   private function validateSame($value, array $params): bool {
      $otherField = $params[0] ?? '';
      $otherValue = $this->getFieldValue($otherField);
      return $value === $otherValue;
   }

   /**
    * Different validation (field must be different from another field)
    */
   private function validateDifferent($value, array $params): bool {
      $otherField = $params[0] ?? '';
      $otherValue = $this->getFieldValue($otherField);
      return $value !== $otherValue;
   }

   /**
    * Alpha validation (only letters)
    */
   private function validateAlpha($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return preg_match('/^[\pL\pM]+$/u', $value) === 1;
   }

   /**
    * Alphanumeric validation (only letters and numbers)
    */
   private function validateAlphanum($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return preg_match('/^[\pL\pM\pN]+$/u', $value) === 1;
   }

   /**
    * Alpha dash validation (letters, numbers, dashes, underscores)
    */
   private function validateAlphaDash($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return preg_match('/^[\pL\pM\pN_-]+$/u', $value) === 1;
   }

   /**
    * JSON validation
    */
   private function validateJson($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }

      if (!is_string($value)) {
         return false;
      }

      json_decode($value);
      return json_last_error() === JSON_ERROR_NONE;
   }

   /**
    * IP validation
    */
   private function validateIp($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return filter_var($value, FILTER_VALIDATE_IP) !== false;
   }

   /**
    * IPv4 validation
    */
   private function validateIpv4($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
   }

   /**
    * IPv6 validation
    */
   private function validateIpv6($value): bool {
      if (is_null($value) || $value === '') {
         return true;
      }
      return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
   }
}
