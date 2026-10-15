<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Edit booking page: toggling the package away and back to the originally
 * loaded package (A -> B -> A, no save) must show HISTORIC stored passenger
 * values, not live-recomputed ones.
 *
 * Regression test for the reported bug: returning to the previous package
 * without saving displayed live package prices in the passenger cards, while
 * the saved invoice (and a reloaded edit page) correctly showed historic
 * values. The edit flow must restore the stored snapshot instead of calling
 * the live client recompute on the return-to-original path.
 */
class BookingEditReturnToOriginalHistoricTest extends TestCase
{
    private function editBookingJsBlock(): string
    {
        $js = file_get_contents(base_path('resources/js/booking.js'));
        $this->assertNotFalse($js);

        $editStart = strpos($js, "Alpine.data('editBookingApp'");
        $showStart = strpos($js, "Alpine.data('showBookingApp'");
        $this->assertNotFalse($editStart);
        $this->assertNotFalse($showStart);
        $this->assertGreaterThan($editStart, $showStart);

        return substr($js, $editStart, $showStart - $editStart);
    }

    public function test_return_to_original_package_restores_historic_snapshot(): void
    {
        $editBlock = $this->editBookingJsBlock();

        $onChangePos = strpos($editBlock, 'onPackageChange()');
        $this->assertNotFalse($onChangePos, 'editBookingApp must define onPackageChange().');
        $onChangeBlock = substr($editBlock, $onChangePos, 4000);

        $this->assertMatchesRegularExpression(
            '/String\(targetId\)\s*===\s*String\(this\.originalPackageId\)/',
            $onChangeBlock,
            'onPackageChange() must branch when the target equals the originally loaded package.'
        );

        $this->assertStringContainsString(
            'restoreHistoricPassengerValues',
            $onChangeBlock,
            'return-to-original path must restore the historic snapshot, never live-recompute.'
        );

        $this->assertStringContainsString(
            'reseedStoredPackageValues',
            $editBlock,
            'historic restore must reseed from STORED package_value, never live-recompute.'
        );
    }

    public function test_historic_snapshot_is_captured_at_load(): void
    {
        $editBlock = $this->editBookingJsBlock();

        $this->assertStringContainsString(
            'initialPassengerFareSnapshot',
            $editBlock,
            'editBookingApp must snapshot passenger fare/display fields in loadExistingBooking().'
        );

        $this->assertStringContainsString(
            'initialBookingTotals',
            $editBlock,
            'editBookingApp must snapshot booking totals so a preview does not leak into the restored view.'
        );
    }
}
