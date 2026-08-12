<?php

namespace Pop\Pdf\Test\Extract\Filter;

use Pop\Pdf\Extract\Exception;
use Pop\Pdf\Extract\Filter\Budget;
use PHPUnit\Framework\TestCase;

class BudgetTest extends TestCase
{

    public function testChargeUnderTotalSucceeds()
    {
        $budget = new Budget(1000);

        $budget->charge(400);
        $budget->charge(400);

        $this->assertTrue(true);
    }

    public function testChargePastTotalThrows()
    {
        $this->expectException(Exception::class);

        $budget = new Budget(1000);
        $budget->charge(600);
        $budget->charge(500);
    }

    public function testExhaustedBudgetChargeIsFast()
    {
        $budget = new Budget(1000);

        try {
            $budget->charge(2000);
        } catch (Exception $e) {
            // expected - budget is now exhausted
        }

        $start = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            try {
                $budget->charge(1);
            } catch (Exception $e) {
                // expected every time
            }
        }
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(1.0, $elapsed);
    }

}
