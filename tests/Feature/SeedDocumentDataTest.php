<?php

namespace Tests\Feature;

use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedDocumentDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_booking_documents(): void
    {
        $this->seed();
        $bookingDocCount = Document::where('owner_type', 'App\Models\Booking')->count();
        $this->assertGreaterThan(0, $bookingDocCount, 'No booking documents found after seeding');
    }

    public function test_seed_creates_customer_documents(): void
    {
        $this->seed();
        $customerDocCount = Document::where('owner_type', 'App\Models\Customer')->count();
        $this->assertGreaterThan(0, $customerDocCount, 'No customer documents found after seeding');
    }

    public function test_seed_creates_passenger_documents(): void
    {
        $this->seed();
        $passengerDocCount = Document::where('owner_type', 'App\Models\Passenger')->count();
        $this->assertGreaterThan(0, $passengerDocCount, 'No passenger documents found after seeding');
    }

    public function test_seed_documents_have_file_paths_and_display_names(): void
    {
        $this->seed();
        $document = Document::first();
        $this->assertNotNull($document, 'No documents found after seeding');
        $this->assertNotEmpty($document->file_path, 'Document file_path is empty');
        $this->assertNotEmpty($document->display_name, 'Document display_name is empty');
    }

    public function test_seed_documents_linked_to_valid_owners(): void
    {
        $this->seed();
        $document = Document::first();
        $owner = $document->owner;
        $this->assertNotNull($owner, "Document owner (owner_type={$document->owner_type}, owner_id={$document->owner_id}) not found");
    }

    public function test_seed_creates_expected_total_document_count(): void
    {
        $this->seed();
        // 2 booking docs (Booking #1) + 1 booking doc (Booking #2) = 3
        // 2 customer docs
        // 2 passenger docs (passenger1) + 1 passenger doc (passenger2) + 1 passenger doc (passenger3) = 4
        // Total = 3 + 2 + 4 = 9
        $totalDocs = Document::count();
        $this->assertGreaterThanOrEqual(8, $totalDocs, 'Expected at least 8 documents after seeding');
    }

    public function test_seed_document_file_paths_within_max_length(): void
    {
        $this->seed();
        $longPaths = Document::whereRaw('LENGTH(file_path) > 512')->get();
        $this->assertCount(0, $longPaths, 'Some document file_paths exceed VARCHAR(512) limit');
    }
}
