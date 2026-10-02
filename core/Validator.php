<?php
declare(strict_types=1);

namespace Core;

final class Validator
{
    /**
     * Rules per field, e.g. ['email' => 'required|email|max:190'].
     * @return array<string,string> field => first error message
     */
    public static function check(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $ruleStr) {
            $value = trim((string)($data[$field] ?? ''));
            $label = ucfirst(str_replace('_', ' ', $field));
            foreach (explode('|', $ruleStr) as $rule) {
                [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
                $msg = match ($name) {
                    'required' => $value === '' ? "$label is required." : null,
                    'email'    => $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL) ? "$label must be a valid email." : null,
                    'min'      => $value !== '' && mb_strlen($value) < (int)$arg ? "$label must be at least $arg characters." : null,
                    'max'      => mb_strlen($value) > (int)$arg ? "$label must be at most $arg characters." : null,
                    'int'      => $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false ? "$label must be a whole number." : null,
                    'numeric'  => $value !== '' && !is_numeric($value) ? "$label must be a number." : null,
                    'date'     => $value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? "$label must be a date." : null,
                    default    => null,
                };
                if ($msg) { $errors[$field] = $msg; break; }
            }
        }
        return $errors;
    }
}
