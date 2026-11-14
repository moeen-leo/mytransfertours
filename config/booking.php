<?php

return [
    /*
    | On dev/staging, every email meant for a SUPPLIER goes here instead, so test
    | bookings never reach real suppliers. Leave empty on the live site.
    */
    'test_recipient' => env('MAIL_TEST_RECIPIENT'),

    /* Hours to wait for a supplier to confirm before each reminder (max 2 reminders, then ops is alerted). */
    'supplier_reminder_hours' => (int) env('SUPPLIER_REMINDER_HOURS', 4),

    /* Customers can change flight/hotel/notes themselves until this many hours before the service. */
    'customer_edit_cutoff_hours' => (int) env('CUSTOMER_EDIT_CUTOFF_HOURS', 24),
];
