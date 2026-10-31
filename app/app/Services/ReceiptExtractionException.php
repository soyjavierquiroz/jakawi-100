<?php

namespace App\Services;

class ReceiptExtractionException extends \RuntimeException
{
    public function __construct(public string $errorCode, public bool $retryable = false)
    {
        parent::__construct($errorCode);
    }
}
