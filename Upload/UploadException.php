<?php

declare(strict_types=1);

namespace System\Upload;

use System\Exception\SystemException;

class UploadException extends SystemException {
    public function __construct(string $message = 'Upload Error', int $code = 403) {
        parent::__construct($message, $code);
    }
}
