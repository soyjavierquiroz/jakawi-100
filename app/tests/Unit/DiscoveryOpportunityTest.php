<?php

namespace Tests\Unit;

use App\Discovery\DiscoveryAvailability;
use App\Discovery\DiscoveryCity;
use App\Discovery\DiscoveryLocation;
use App\Discovery\DiscoveryOpportunity;
use App\Discovery\DiscoveryPartner;
use App\Discovery\DiscoveryUrgency;
use App\Discovery\OpportunityType;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Tests\TestCase;

class DiscoveryOpportunityTest extends TestCase
{
    public function test_benefit_shaped_opportunity_serializes_without_a_domain_model(): void
    {
        $opportunity = new DiscoveryOpportunity(
            type: OpportunityType::BENEFIT,
            sourceId: 41,
            title: 'Café de especialidad',
            destinationUrl: '/beneficios/cafe',
            city: new DiscoveryCity(1, 'Cochabamba', 'cochabamba'),
            subtitle: '20% de descuento',
            image: 'https://cdn.example.test/cafe.jpg',
            partner: new DiscoveryPartner(9, 'Casa Café', 'casa-cafe'),
            location: new DiscoveryLocation(12, 'Casa Café Cala Cala', 'Cala Cala'),
            categories: ['food'],
            availability: new DiscoveryAvailability(true, 'AVAILABLE'),
            startsAt: new DateTimeImmutable('2026-09-30T10:00:00+00:00'),
            endsAt: new DateTimeImmutable('2026-10-30T10:00:00+00:00'),
            primaryValue: '20% de descuento',
            secondaryValue: 'Para miembros JAKAWI',
            urgency: new DiscoveryUrgency('ENDING_SOON', 'Termina pronto', new DateTimeImmutable('2026-10-30T10:00:00+00:00')),
            editorialPriority: 0.9,
            metadata: ['benefit_badge' => 'Exclusivo'],
        );

        $serialized = $opportunity->toArray();

        $this->assertSame('BENEFIT', $serialized['type']);
        $this->assertSame(41, $serialized['source_id']);
        $this->assertSame(['id' => 1, 'name' => 'Cochabamba', 'slug' => 'cochabamba'], $serialized['city']);
        $this->assertSame('2026-09-30T10:00:00+00:00', $serialized['starts_at']);
        $this->assertSame($serialized, $opportunity->jsonSerialize());
    }

    public function test_experience_shaped_opportunity_serializes(): void
    {
        $serialized = $this->opportunity(OpportunityType::EXPERIENCE, 'experience-44', ['culture'])->toArray();

        $this->assertSame('EXPERIENCE', $serialized['type']);
        $this->assertSame('experience-44', $serialized['source_id']);
        $this->assertSame(['culture'], $serialized['categories']);
    }

    public function test_unlock_shaped_opportunity_serializes_with_empty_categories(): void
    {
        $serialized = $this->opportunity(OpportunityType::UNLOCK, 77)->toArray();

        $this->assertSame('UNLOCK', $serialized['type']);
        $this->assertSame([], $serialized['categories']);
    }

    public function test_optional_fields_are_null_or_empty_without_persistence(): void
    {
        $serialized = $this->opportunity(OpportunityType::BENEFIT, 1)->toArray();

        $this->assertNull($serialized['subtitle']);
        $this->assertNull($serialized['partner']);
        $this->assertNull($serialized['availability']);
        $this->assertSame([], $serialized['categories']);
        $this->assertSame([], $serialized['metadata']);
    }

    public function test_type_and_source_id_are_a_stable_identity_and_metadata_is_adapter_owned(): void
    {
        $first = $this->opportunity(OpportunityType::BENEFIT, '42', metadata: ['campaign_label' => 'Semana JAKAWI']);
        $second = $this->opportunity(OpportunityType::EXPERIENCE, '42', metadata: ['campaign_label' => 'Semana JAKAWI']);

        $this->assertNotSame([$first->type->value, $first->sourceId], [$second->type->value, $second->sourceId]);
        $this->assertSame(['campaign_label' => 'Semana JAKAWI'], $first->toArray()['metadata']);
    }

    public function test_contract_is_non_persisted_so_a_future_adapter_needs_no_schema_change(): void
    {
        $contract = new ReflectionClass(DiscoveryOpportunity::class);

        $this->assertFalse($contract->isSubclassOf(Model::class));
        $this->assertFalse($contract->hasMethod('save'));
        $this->assertSame(
            ['type', 'source_id', 'title', 'destination_url', 'city', 'subtitle', 'image', 'partner', 'location', 'categories', 'availability', 'starts_at', 'ends_at', 'primary_value', 'secondary_value', 'urgency', 'editorial_priority', 'metadata'],
            array_keys($this->opportunity(OpportunityType::BENEFIT, 1)->toArray()),
        );
    }

    /**
     * A future fourth adapter can provide this same constructor data without a
     * schema or table because this DTO has no persistence or domain-model dependency.
     *
     * @param list<string> $categories
     * @param array<string, bool|float|int|string|null> $metadata
     */
    private function opportunity(OpportunityType $type, int|string $sourceId, array $categories = [], array $metadata = []): DiscoveryOpportunity
    {
        return new DiscoveryOpportunity(
            type: $type,
            sourceId: $sourceId,
            title: 'Oportunidad',
            destinationUrl: '/destino',
            city: new DiscoveryCity(1, 'Cochabamba', 'cochabamba'),
            categories: $categories,
            metadata: $metadata,
        );
    }
}
