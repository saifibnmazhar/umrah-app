<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Traits\ConvertsDocumentsToPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    use ConvertsDocumentsToPdf;

    public function upload(Request $request)
    {
        $request->validate([
            'documents.*' => 'required|file|max:10240',
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = \App\Models\Booking::with('customer')->findOrFail($request->booking_id);
        $invoiceId = $booking->invoice_id ?? 'INV';
        $customerName = $booking->customer->name ?? 'Customer';

        $customerDocCount = $booking->customer ? $booking->customer->documents->count() : 0;
        $bookingDocCount = $booking->documents()->count();
        $uploadedDocs = [];

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $index => $file) {
                $path = $file->store('documents', 'public');

                $doc = Document::create([
                    'owner_type' => 'App\Models\Booking',
                    'owner_id' => $request->booking_id,
                    'file_path' => $path,
                    'display_name' => "{$invoiceId} {$customerName} " . ($customerDocCount + $bookingDocCount + $index + 1),
                ]);

                $uploadedDocs[] = $doc;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Documents uploaded successfully',
            'documents' => $uploadedDocs,
        ]);
    }

    public function download(Document $document)
    {
        $fullPath = $this->resolveDocumentPath($document);
        if (!$fullPath || !file_exists($fullPath)) {
            abort(404, 'File not found');
        }

        $tmpFile = storage_path('app/tmp/doc_' . uniqid() . '.pdf');
        $fileName = $document->display_name . '.pdf';

        try {
            $this->convertToPdf($fullPath, $tmpFile);
            $content = file_get_contents($tmpFile);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $fileName);
    }

    public function uploadPassenger(Request $request)
    {
        $request->validate([
            'documents.*' => 'required|file|max:10240',
            'passenger_id' => 'required|exists:passengers,id',
        ]);

        $uploadedDocs = [];

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $file) {
                $path = $file->store('documents', 'public');
                
                $doc = Document::create([
                    'owner_type' => 'App\Models\Passenger',
                    'owner_id' => $request->passenger_id,
                    'file_path' => $path,
                    'display_name' => $file->getClientOriginalName(),
                ]);

                $uploadedDocs[] = $doc;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Documents uploaded successfully',
            'documents' => $uploadedDocs,
        ]);
    }

    public function destroy(Document $document)
    {
        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully',
        ]);
    }
}