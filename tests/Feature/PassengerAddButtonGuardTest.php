<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the double-submit protection on the passenger add/edit buttons.
 *
 * The Add Passenger modal is shared by the create-booking and invoice-details
 * pages (the latter POSTs to the server), and the passenger edit page PUTs.
 * Each now exposes a `savingPassenger` flag that disables the button and swaps
 * its label while the request is in flight.
 */
class PassengerAddButtonGuardTest extends TestCase
{
    private function bookingJs(): string
    {
        $js = file_get_contents(base_path('resources/js/booking.js'));
        $this->assertNotFalse($js);

        return $js;
    }

    private function componentBlock(string $js, string $name, ?string $nextName = null): string
    {
        $start = strpos($js, "Alpine.data('{$name}'");
        $this->assertNotFalse($start, "{$name} component not found.");

        if ($nextName === null) {
            return substr($js, $start);
        }

        $end = strpos($js, "Alpine.data('{$nextName}'", $start);
        $this->assertNotFalse($end, "{$nextName} component not found.");

        return substr($js, $start, $end - $start);
    }

    public function test_add_passenger_modal_button_binds_disabled_and_adding_label(): void
    {
        $html = file_get_contents(resource_path('views/partials/passenger-form-modal.blade.php'));
        $this->assertNotFalse($html);

        $this->assertStringContainsString(':disabled="savingPassenger"', $html);
        $this->assertStringContainsString("savingPassenger ? 'Adding...' : 'Add'", $html);
    }

    public function test_create_and_show_declare_saving_passenger_flag(): void
    {
        $js = $this->bookingJs();

        foreach ([
            'createBookingApp' => 'editBookingApp',
            'showBookingApp' => null,
        ] as $name => $nextName) {
            $block = $this->componentBlock($js, $name, $nextName);

            $this->assertStringContainsString(
                'savingPassenger: false,',
                $block,
                "{$name} must declare the savingPassenger flag."
            );
            $this->assertStringContainsString(
                'this.savingPassenger = true;',
                $block,
                "{$name}.savePassenger() must mark savingPassenger."
            );
            $this->assertMatchesRegularExpression(
                '/closePassengerModal\(\)\s*\{\s*this\.passengerModalVisible = false;\s*this\.savingPassenger = false;/',
                $block,
                "{$name}.closePassengerModal() must clear savingPassenger."
            );
        }
    }

    public function test_show_save_passenger_is_in_flight_guarded(): void
    {
        $block = $this->componentBlock($this->bookingJs(), 'showBookingApp');

        $this->assertStringContainsString(
            'if (this.savingPassenger) {',
            $block,
            'showBookingApp.savePassenger() must bail out while a save is in flight.'
        );
        $this->assertStringContainsString(
            'this.savingPassenger = false;',
            $block,
            'showBookingApp.savePassenger() must clear savingPassenger when the request settles.'
        );
    }

    public function test_passenger_edit_page_guards_save_button(): void
    {
        $html = file_get_contents(resource_path('views/passengers/edit.blade.php'));
        $this->assertNotFalse($html);

        $this->assertStringContainsString('savingPassenger: false', $html);
        $this->assertStringContainsString('if (this.savingPassenger) {', $html);
        $this->assertStringContainsString('this.savingPassenger = true;', $html);
        $this->assertStringContainsString(':disabled="savingPassenger"', $html);
        $this->assertStringContainsString("savingPassenger ? 'Saving...' : 'Save Changes'", $html);
    }
}
