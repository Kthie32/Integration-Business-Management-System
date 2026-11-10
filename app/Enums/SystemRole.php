<?php

namespace App\Enums;

use App\Support\PermissionCatalog;

/**
 * Roles seeded on installation. Their permissions are a starting point and can be edited
 * (except Super Administrator, which always holds every permission).
 */
enum SystemRole: string
{
    case SuperAdministrator = 'Super Administrator';
    case SystemAdministrator = 'System Administrator';
    case Manager = 'Manager';
    case FinanceOfficer = 'Finance Officer';
    case Accountant = 'Accountant';
    case HumanResourceOfficer = 'Human Resource Officer';
    case ProcurementOfficer = 'Procurement Officer';
    case InventoryOfficer = 'Inventory Officer';
    case DepartmentHead = 'Department Head';
    case GeneralEmployee = 'General Employee';

    public function description(): string
    {
        return match ($this) {
            self::SuperAdministrator => 'Unrestricted access to every module and setting.',
            self::SystemAdministrator => 'Manages user accounts, roles, system settings and the audit log.',
            self::Manager => 'Oversees operations; approves quotations, requisitions, payroll and stock adjustments.',
            self::FinanceOfficer => 'Manages invoicing, payments, expenses and finance settings; approves payroll.',
            self::Accountant => 'Records invoices, payments and expenses; prepares payroll.',
            self::HumanResourceOfficer => 'Manages employees, departments and payroll preparation.',
            self::ProcurementOfficer => 'Manages suppliers, receives goods and fulfils requisitions.',
            self::InventoryOfficer => 'Manages items, warehouses and stock movements.',
            self::DepartmentHead => 'Approves requisitions for the departments they head.',
            self::GeneralEmployee => 'Raises requisitions and views own payslips.',
        };
    }

    /**
     * Default permissions granted when the role is first created.
     *
     * @return list<string>
     */
    public function defaultPermissions(): array
    {
        $common = ['dashboard.view', 'requisitions.create', 'requisitions.view_own', 'payslips.view_own'];

        $specific = match ($this) {
            self::SuperAdministrator => PermissionCatalog::all(),
            self::SystemAdministrator => [
                'users.view', 'users.create', 'users.update', 'users.deactivate', 'sessions.revoke', 'roles.manage',
                'settings.system', 'audit.view',
            ],
            self::Manager => [
                'dashboard.view_financials', 'dashboard.view_payroll',
                'customers.view', 'suppliers.view',
                'quotations.view', 'quotations.create', 'quotations.update', 'quotations.delete', 'quotations.approve',
                'quotations.send', 'quotations.convert', 'quotations.cancel',
                'invoices.view', 'payments.view', 'expenses.view',
                'items.view', 'stock.view', 'stock.adjust_approve',
                'employees.view', 'payroll.view', 'payroll.approve',
                'requisitions.view_all', 'requisitions.approve',
                'reports.financial', 'reports.sales', 'reports.inventory', 'reports.payroll', 'reports.requisitions',
                'reports.export', 'audit.view',
            ],
            self::FinanceOfficer => [
                'dashboard.view_financials', 'dashboard.view_payroll',
                'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'suppliers.view',
                'quotations.view', 'quotations.create', 'quotations.update', 'quotations.delete', 'quotations.send',
                'quotations.convert', 'quotations.cancel',
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.issue', 'invoices.void',
                'payments.view', 'payments.record', 'payments.reverse',
                'expenses.view', 'expenses.create', 'expenses.void',
                'items.view', 'stock.view',
                'payroll.view', 'payroll.approve', 'payroll.mark_paid',
                'requisitions.view_all', 'requisitions.approve',
                'reports.financial', 'reports.sales', 'reports.payroll', 'reports.export',
                'settings.finance',
            ],
            self::Accountant => [
                'dashboard.view_financials',
                'customers.view', 'customers.create', 'customers.update', 'suppliers.view',
                'quotations.view',
                'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.issue',
                'payments.view', 'payments.record',
                'expenses.view', 'expenses.create', 'expenses.void',
                'items.view', 'stock.view',
                'payroll.view', 'payroll.prepare',
                'requisitions.view_all',
                'reports.financial', 'reports.sales', 'reports.export',
            ],
            self::HumanResourceOfficer => [
                'dashboard.view_payroll',
                'employees.view', 'employees.create', 'employees.update', 'employees.view_salary',
                'employees.view_documents', 'employees.manage_documents', 'departments.manage',
                'payroll.view', 'payroll.prepare', 'payroll.configure',
                'reports.payroll', 'reports.export',
            ],
            self::ProcurementOfficer => [
                'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete',
                'expenses.view', 'expenses.create',
                'items.view', 'stock.view', 'stock.receive',
                'requisitions.view_all', 'requisitions.fulfil',
                'reports.inventory', 'reports.requisitions', 'reports.export',
            ],
            self::InventoryOfficer => [
                'suppliers.view',
                'items.view', 'items.manage', 'warehouses.manage',
                'stock.view', 'stock.receive', 'stock.issue', 'stock.transfer', 'stock.adjust',
                'requisitions.view_all', 'requisitions.fulfil',
                'reports.inventory', 'reports.export',
            ],
            self::DepartmentHead => [
                'items.view', 'stock.view', 'employees.view',
                'requisitions.view_department', 'requisitions.approve',
                'reports.requisitions',
            ],
            self::GeneralEmployee => [],
        };

        return array_values(array_unique([...$common, ...$specific]));
    }
}
