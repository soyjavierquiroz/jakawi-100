<?php
namespace App\Integrations\ShareContest;
final readonly class InspectionResult {
    public function __construct(public array $data, public array $payload) {}
}
