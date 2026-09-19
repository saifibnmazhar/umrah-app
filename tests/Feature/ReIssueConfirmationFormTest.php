<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReIssueConfirmationFormTest extends TestCase
{
    private function confirmationSource(): string
    {
        return (string) file_get_contents(resource_path('views/re-issues/confirmation.blade.php'));
    }

    public function test_total_payment_handlers_are_defined_outside_sync_readonly_mirrors(): void
    {
        $src = $this->confirmationSource();

        // syncReadonlyMirrors body = from its declaration to the first
        // closing brace at column 0. The total-payment handlers must NOT
        // live inside it, otherwise the inline oninput hooks throw
        // ReferenceError and service_charge never auto-calculates.
        $matched = preg_match('/function syncReadonlyMirrors\(\) \{(.*?)\n\}/s', $src, $m);
        $this->assertSame(1, $matched, 'syncReadonlyMirrors function not found');
        $this->assertStringNotContainsString(
            'handleTotalPayment',
            $m[1],
            'total-payment handlers must not be nested inside syncReadonlyMirrors'
        );

        $this->assertStringContainsString(
            'function handleTotalPaymentSarInput()',
            $src,
            'SAR total-payment handler must exist'
        );
        $this->assertStringContainsString(
            'function handleTotalPaymentBdtInput()',
            $src,
            'BDT total-payment handler must exist'
        );
    }

    public function test_total_payment_inputs_wire_auto_calculation(): void
    {
        $src = $this->confirmationSource();

        $this->assertStringContainsString(
            'oninput="handleTotalPaymentSarInput(); updateTotals()"',
            $src,
            'SAR total-payment input must trigger auto-calculation'
        );
        $this->assertStringContainsString(
            'oninput="handleTotalPaymentBdtInput(); updateTotals()"',
            $src,
            'BDT total-payment input must trigger auto-calculation'
        );
        // service_charge is derived: readonly in both currency modes.
        $this->assertStringContainsString(
            '<input type="number" id="inputServiceCharge" readonly',
            $src,
            'service charge must be readonly (auto-calculated)'
        );
    }
}
