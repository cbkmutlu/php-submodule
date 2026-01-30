<?php

declare(strict_types=1);

namespace System\Exception;

class AuthorizationException extends SystemException
{
    protected $code = 403;
    protected $message = 'This action is unauthorized.';

    public function __construct(string $message = 'This action is unauthorized.', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
