<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Role capabilities — the single source of truth
    |--------------------------------------------------------------------------
    |
    | TenantUserPolicy reads this file, the Roles API serves it to the dashboard,
    | and the dashboard renders it instead of the hardcoded matrix it used to
    | carry. That is deliberate: the UI and the enforcement cannot drift if they
    | read the same declaration.
    |
    | Read it through App\Tenancy\RoleCapabilities rather than directly.
    |
    */

    'roles' => [
        'tenant_admin' => [
            'label' => 'Tenant admin',
            'detail' => 'Full control, including roles and billing',
        ],
        'co_admin' => [
            'label' => 'Co-admin',
            'detail' => 'Runs the household day to day, cannot change roles',
        ],
        'member' => [
            'label' => 'Household member',
            'detail' => 'Creates, claims and completes requests',
        ],
        'junior' => [
            'label' => 'Junior',
            'detail' => 'Raises requests and completes their own; cannot dispatch',
        ],
    ],

    'capabilities' => [
        'manage_billing' => [
            'label' => 'Manage billing & seats',
            'detail' => 'Invoices and seat licences',
            'roles' => ['tenant_admin', 'co_admin'],
        ],
        'manage_roles' => [
            'label' => 'Change roles / remove members',
            'detail' => 'Household administration',
            'roles' => ['tenant_admin'],
        ],
        'invite_members' => [
            'label' => 'Invite family members',
            'detail' => 'Issue household invitations for non-admin roles',
            'roles' => ['tenant_admin', 'co_admin'],
        ],
        'manage_places' => [
            'label' => 'Add saved places & geofences',
            'detail' => 'Grocery stores, school, home',
            'roles' => ['tenant_admin', 'co_admin', 'member'],
        ],
        'dispatch_requests' => [
            'label' => 'Create & assign requests',
            'detail' => 'Dispatch work to another member',
            'roles' => ['tenant_admin', 'co_admin', 'member'],
        ],
        'complete_own_requests' => [
            'label' => 'Complete own requests',
            'detail' => 'Accept, start and finish assigned items',
            'roles' => ['tenant_admin', 'co_admin', 'member', 'junior'],
        ],
        'voice_escalation_target' => [
            'label' => 'Voice escalation target',
            'detail' => 'Automated calls for urgent overdue items',
            'roles' => ['tenant_admin', 'co_admin'],
        ],
    ],

    /*
    | Roles that only a tenant admin may grant. Inviting someone as an admin is a
    | role-management action, not an invitation — conflating the two is how a
    | co-admin could once escalate themselves to full admin.
    */
    'privileged_roles' => ['tenant_admin', 'co_admin'],

];
