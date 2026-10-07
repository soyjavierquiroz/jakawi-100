<?php
namespace App\Integrations\ShareContest;
use RuntimeException;
final class InspectionException extends RuntimeException {
    public function __construct(public readonly string $errorCode, public readonly bool $retryable) { parent::__construct($errorCode); }
}
