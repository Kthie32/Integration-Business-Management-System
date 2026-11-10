<?php

namespace App\Support;

/**
 * The single source of truth for every permission in the system.
 *
 * Permissions are defined in code (not editable in the UI) and synced to the database by the PermissionSeeder.
 * Roles are editable bundles of these permissions.
 */
class PermissionCatalog
{
    /**
     * Permissions grouped by module: module label => [permission name => description].
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Dashboard' => [
                'dashboard.view' => 'View the dashboard',
                'dashboard.view_financials' => 'See financial figures on the dashboard',
                'dashboard.view_payroll' => 'See payroll figures on the dashboard',
            ],
            'Users & Roles' => [
                'users.view' => 'View user accounts',
                'users.create' => 'Create user accounts',
                'users.update' => 'Edit user accounts and their roles',
                'users.deactivate' => 'Activate and deactivate user accounts',
                'sessions.revoke' => 'View and end other users\' sessions',
                'roles.manage' => 'Create, edit and delete roles',
            ],
            'Customers' => [
                'customers.view' => 'View customers',
                'customers.create' => 'Create customers',
                'customers.update' => 'Edit customers',
                'customers.delete' => 'Delete or deactivate customers',
            ],
            'Suppliers' => [
                'suppliers.view' => 'View suppliers',
                'suppliers.create' => 'Create suppliers',
                'suppliers.update' => 'Edit suppliers',
                'suppliers.delete' => 'Delete or deactivate suppliers',
            ],
            'Quotations' => [
                'quotations.view' => 'View quotations',
                'quotations.create' => 'Create quotations',
                'quotations.update' => 'Edit draft quotations',
                'quotations.delete' => 'Delete draft quotations',
                'quotations.approve' => 'Approve quotations',
                'quotations.send' => 'Mark quotations as sent / accepted / rejected',
                'quotations.convert' => 'Convert accepted quotations to invoices',
                'quotations.cancel' => 'Cancel quotations',
            ],
            'Invoices' => [
                'invoices.view' => 'View invoices',
                'invoices.create' => 'Create invoices',
                'invoices.update' => 'Edit draft invoices',
                'invoices.delete' => 'Delete draft invoices',
                'invoices.issue' => 'Issue invoices',
                'invoices.void' => 'Void issued invoices',
            ],
            'Payments & Receipts' => [
                'payments.view' => 'View payments and receipts',
                'payments.record' => 'Record payments',
                'payments.reverse' => 'Reverse payments',
            ],
            'Expenses' => [
                'expenses.view' => 'View expenses',
                'expenses.create' => 'Record expenses',
                'expenses.void' => 'Void expenses',
            ],
            'Inventory' => [
                'items.view' => 'View items and categories',
                'items.manage' => 'Create and edit items and categories',
                'warehouses.manage' => 'Create and edit warehouses',
                'stock.view' => 'View stock levels and movements',
                'stock.receive' => 'Receive stock (goods received notes)',
                'stock.issue' => 'Issue stock',
                'stock.transfer' => 'Transfer stock between warehouses',
                'stock.adjust' => 'Create stock adjustments',
                'stock.adjust_approve' => 'Approve stock adjustments',
            ],
            'Employees' => [
                'employees.view' => 'View all employees',
                'employees.view_department' => 'View employees of departments headed',
                'employees.create' => 'Register employees',
                'employees.update' => 'Edit employees',
                'employees.view_salary' => 'View salary and bank details',
                'employees.view_documents' => 'View employee documents',
                'employees.manage_documents' => 'Upload and remove employee documents',
                'departments.manage' => 'Create and edit departments',
            ],
            'Payroll' => [
                'payroll.view' => 'View payroll runs',
                'payroll.prepare' => 'Prepare and calculate payroll',
                'payroll.approve' => 'Approve payroll',
                'payroll.mark_paid' => 'Mark payroll as paid',
                'payroll.configure' => 'Configure pay components and statutory rates',
                'payslips.view_own' => 'View own payslips',
            ],
            'Requisitions' => [
                'requisitions.create' => 'Create requisitions',
                'requisitions.view_own' => 'View own requisitions',
                'requisitions.view_department' => 'View requisitions of departments headed',
                'requisitions.view_all' => 'View all requisitions',
                'requisitions.approve' => 'Approve or reject requisitions',
                'requisitions.fulfil' => 'Fulfil approved requisitions',
                'approvals.configure' => 'Configure approval workflows',
            ],
            'Reports' => [
                'reports.financial' => 'Financial reports',
                'reports.sales' => 'Quotation and sales reports',
                'reports.inventory' => 'Inventory reports',
                'reports.payroll' => 'Payroll reports',
                'reports.requisitions' => 'Requisition reports',
                'reports.export' => 'Export reports to PDF and Excel',
            ],
            'Settings & Audit' => [
                'settings.organization' => 'Edit organization profile and branding',
                'settings.finance' => 'Edit currency, taxes and document numbering',
                'settings.system' => 'Edit system settings',
                'audit.view' => 'View the audit log',
            ],
        ];
    }

    /**
     * Every permission name.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(array_merge(...array_values(static::groups())));
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, static::all(), true);
    }
}
