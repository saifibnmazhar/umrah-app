<?php

use App\Models\Passenger;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('passengers', 'booking_service_charge')) {
            Schema::table('passengers', function (Blueprint $table) {
                $table->decimal('booking_service_charge', 14, 6)->default(0)->after('service_charge');
            });
        }

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('passengers', function (Blueprint $table) {
            $table->dropColumn('booking_service_charge');
        });
    }

    private function backfill(): void
    {
        DB::transaction(function () {
            DB::table('passengers')
                ->join('bookings', 'passengers.booking_id', '=', 'bookings.id')
                ->join('packages', 'bookings.package_id', '=', 'packages.id')
                ->where('passengers.is_cancelled', false)
                ->whereRaw("LOWER(passengers.service_required) != 'visa_only'")
                ->update(['passengers.booking_service_charge' => DB::raw('packages.service_charge')]);

            $userId = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
                ->orWhereHas('roles', fn ($q) => $q->where('name', 'Ticket Admin'))
                ->value('id') ?? 1;

            Model::withoutEvents(function () use ($userId) {
                $this->backfillSingleTickets();
                $this->backfillDoubleTickets($userId);
            });
        });
    }

    private function backfillSingleTickets(): void
    {
        Passenger::whereNotNull('ticket_fare_id')
            ->whereNull('ticket_fare_inbound_id')
            ->whereNull('ticket_fare_outbound_id')
            ->whereRaw("LOWER(service_required) != 'visa_only'")
            ->with('ticketFare', 'issuedTickets')
            ->cursor()
            ->each(function (Passenger $p) {
                $fare = $p->ticketFare;
                if (! $fare) {
                    return;
                }
                $type = $fare->ticket_type instanceof BackedEnum ? $fare->ticket_type->value : $fare->ticket_type;
                $ptype = strtolower($p->passenger_type instanceof BackedEnum ? $p->passenger_type->value : (string) $p->passenger_type);
                [$s, $o] = $this->adjustFaresForType(
                    (float) ($fare->selling_fare ?? 0),
                    $type === 'offer' ? (float) ($fare->offer_price ?? 0) : 0,
                    $ptype,
                    $fare
                );
                $p->issuedTickets()
                    ->where(fn ($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
                    ->update(['selling_fare' => $s, 'offer_price' => $o]);

                $p->issuedTickets()->where('issue_type', 'pending_outbound')
                    ->update(['selling_fare' => 0, 'offer_price' => 0]);
            });
    }

    private function backfillDoubleTickets(int $userId): void
    {
        Passenger::whereNull('ticket_fare_id')
            ->whereNotNull('ticket_fare_inbound_id')
            ->whereNotNull('ticket_fare_outbound_id')
            ->whereRaw("LOWER(service_required) != 'visa_only'")
            ->with('ticketFareInbound', 'ticketFareOutbound', 'issuedTickets')
            ->cursor()
            ->each(function (Passenger $p) use ($userId) {
                $ptype = strtolower($p->passenger_type instanceof BackedEnum ? $p->passenger_type->value : (string) $p->passenger_type);

                $in = $p->ticketFareInbound;
                $inType = $in?->ticket_type instanceof BackedEnum ? $in->ticket_type->value : $in?->ticket_type;
                [$inS, $inO] = $this->adjustFaresForType(
                    (float) ($in?->selling_fare ?? 0),
                    $inType === 'offer' ? (float) ($in?->offer_price ?? 0) : 0,
                    $ptype,
                    $in
                );
                $p->issuedTickets()
                    ->where(fn ($q) => $q->whereNull('issue_type')->orWhere('issue_type', 'regular'))
                    ->update(['selling_fare' => $inS, 'offer_price' => $inO]);

                $out = $p->ticketFareOutbound;
                $outType = $out?->ticket_type instanceof BackedEnum ? $out->ticket_type->value : $out?->ticket_type;
                [$outS, $outO] = $this->adjustFaresForType(
                    (float) ($out?->selling_fare ?? 0),
                    $outType === 'offer' ? (float) ($out?->offer_price ?? 0) : 0,
                    $ptype,
                    $out
                );
                $exists = $p->issuedTickets()->where('issue_type', 'pending_outbound')->exists();
                if ($exists) {
                    $p->issuedTickets()->where('issue_type', 'pending_outbound')
                        ->update(['selling_fare' => $outS, 'offer_price' => $outO]);
                } else {
                    DB::table('issued_tickets')->insert([
                        'passenger_id' => $p->id,
                        'booking_id' => $p->booking_id,
                        'user_id' => $userId,
                        'status' => 'pending',
                        'issue_type' => 'pending_outbound',
                        'selling_fare' => $outS,
                        'offer_price' => $outO,
                        'net_fare' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    private function adjustFaresForType(float $sellingFare, float $offerPrice, string $passengerType, $fare): array
    {
        if (! $fare) {
            return [$sellingFare, $offerPrice];
        }
        $pct = match ($passengerType) {
            'child' => (float) $fare->child_fare_percentage,
            'infant' => (float) $fare->infant_fare_percentage,
            default => 100,
        };
        if ($pct != 100) {
            $sellingFare = round($sellingFare * $pct / 100, 6);
            $offerPrice = round($offerPrice * $pct / 100, 6);
        }

        return [$sellingFare, $offerPrice];
    }
};
