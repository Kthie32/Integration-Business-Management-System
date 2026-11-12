<?php

namespace App\Policies;

use App\Models\PayrollItem;
use App\Models\User;

/**
 * Payslips (FR-PRL-07): payroll staff see every payslip; an employee whose user account is linked to their
 * employee record sees their own once the run is approved.
 */
class PayrollItemPolicy
{
    public function view(User $actor, PayrollItem $item): bool
    {
        if ($actor->can('payroll.view')) {
            return true;
        }

        return $this->isOwnReleasedPayslip($actor, $item);
    }

    private function isOwnReleasedPayslip(User $actor, PayrollItem $item): bool
    {
        return $actor->can('payslips.view_own')
            && $actor->employee_id !== null
            && $actor->employee_id === $item->employee_id
            && $item->payrollRun !== null
            && $item->payrollRun->isLocked();
    }
}
