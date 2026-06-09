<?php
/**
 * Validator Helper
 * Validasi input request (Aturan #6: Validasi wajib)
 * 
 * Mengikuti pola schemas/book.ts (Zod) di referensi:
 *   bookInputSchema.safeParse(req.body) → success/error
 * 
 * Penggunaan:
 *   $errors = Validator::validate($input, [
 *       'name'  => 'required|min:3',
 *       'price' => 'required|numeric|min:0',
 *       'email' => 'required|email',
 *   ]);
 *   if (!empty($errors)) { Response::error('Validasi gagal', 400, $errors); }
 */

class Validator {

    /**
     * Validate input data against rules
     * @return array Associative array of errors (empty if valid)
     */
    public static function validate($data, $rules) {
        $errors = [];

        foreach ($rules as $field => $ruleString) {
            $ruleList = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            $fieldErrors = [];

            foreach ($ruleList as $rule) {
                // Parse rule with parameter (e.g., min:3)
                $param = null;
                if (strpos($rule, ':') !== false) {
                    [$rule, $param] = explode(':', $rule, 2);
                }

                switch ($rule) {
                    case 'required':
                        if ($value === null || $value === '') {
                            $fieldErrors[] = ucfirst($field) . ' wajib diisi.';
                        }
                        break;

                    case 'string':
                        if ($value !== null && !is_string($value)) {
                            $fieldErrors[] = ucfirst($field) . ' harus berupa teks.';
                        }
                        break;

                    case 'numeric':
                        if ($value !== null && $value !== '' && !is_numeric($value)) {
                            $fieldErrors[] = ucfirst($field) . ' harus berupa angka.';
                        }
                        break;

                    case 'integer':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT) && $value !== 0 && $value !== '0') {
                            $fieldErrors[] = ucfirst($field) . ' harus berupa bilangan bulat.';
                        }
                        break;

                    case 'email':
                        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $fieldErrors[] = 'Format email tidak valid.';
                        }
                        break;

                    case 'min':
                        if ($value !== null && $value !== '') {
                            if (is_string($value) && strlen($value) < (int)$param) {
                                $fieldErrors[] = ucfirst($field) . " minimal $param karakter.";
                            } elseif (is_numeric($value) && $value < (float)$param) {
                                $fieldErrors[] = ucfirst($field) . " minimal $param.";
                            }
                        }
                        break;

                    case 'max':
                        if ($value !== null && $value !== '') {
                            if (is_string($value) && strlen($value) > (int)$param) {
                                $fieldErrors[] = ucfirst($field) . " maksimal $param karakter.";
                            } elseif (is_numeric($value) && $value > (float)$param) {
                                $fieldErrors[] = ucfirst($field) . " maksimal $param.";
                            }
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', $param);
                        if ($value !== null && $value !== '' && !in_array($value, $allowed)) {
                            $fieldErrors[] = ucfirst($field) . ' harus salah satu dari: ' . $param . '.';
                        }
                        break;

                    case 'array':
                        if ($value !== null && !is_array($value)) {
                            $fieldErrors[] = ucfirst($field) . ' harus berupa array.';
                        }
                        break;
                }
            }

            if (!empty($fieldErrors)) {
                $errors[$field] = $fieldErrors;
            }
        }

        return $errors;
    }

    /**
     * Get JSON body from request
     */
    public static function getJsonBody() {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : [];
    }
}
