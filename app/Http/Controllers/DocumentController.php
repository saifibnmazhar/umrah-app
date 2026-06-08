<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'documents.*' => 'required|file|max:10240',
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = \App\Models\Booking::with('customer')->findOrFail($request->booking_id);
        $invoiceId = $booking->invoice_id ?? 'INV';
        $customerName = $booking->customer->name ?? 'Customer';

        $existingCount = $booking->documents()->count();
        $uploadedDocs = [];

        if ($request->hasFile('documents')) {
            foreach ($request->file('documents') as $index => $file) {
                $path = $file->store('documents', 'public');

                $doc = Document::create([
                    'owner_type' => 'App\Models\Booking',
                    'owner_id' => $request->booking_id,
                    'file_path' => $path,
                    'display_name' => "{$invoiceId} {$customerName} " . ($existingCount + $index + 1),
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
        if (!Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->display_name
        );
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
}