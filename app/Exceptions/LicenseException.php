<?php

namespace App\Exceptions;

use Exception;

class LicenseException extends Exception
{
    public function __construct(string $message = 'Error del sistema de licencias.', int $code = 503)
    {
        parent::__construct($message, $code);
    }

    public function render()
    {
        return response()->json([
            'message' => $this->getMessage(),
            'type' => 'license_error',
        ], $this->getCode());
    }
}
