<?php

declare(strict_types=1);

return [
    'home' => ['publicNavigation', 'home'],
    'schedule' => ['publicNavigation', 'schedule'],
    'contact' => ['publicNavigation', 'contact'],
    'privacy' => ['publicNavigation'],
    'terms' => ['publicNavigation'],
    'login' => ['login'],
    'register' => ['register'],
    'password.request' => ['forgotPassword'],
    'password.reset' => ['resetPassword'],
    'verification.notice' => ['verifyEmailPage'],
    'password.confirm' => ['confirmPassword'],
    'account' => ['publicNavigation', 'account', 'accountNav'],
    'account.profile' => ['publicNavigation', 'accountProfile', 'accountNav'],
    'account.settings' => ['publicNavigation', 'accountSettings', 'accountNav'],
    'account.history' => ['publicNavigation', 'accountHistory', 'accountNav'],
    'account.history.checkIn' => ['publicNavigation', 'accountHistoryRecord', 'accountNav'],
    'account.history.appointment' => ['publicNavigation', 'accountHistoryRecord', 'accountNav', 'appointmentCancel'],
    'checkIn.selectLocation' => ['publicNavigation', 'checkInSelectLocation', 'locationRequiredModal'],
    'checkIn.form' => ['publicNavigation', 'checkInForm', 'purchaseOrders'],
    'checkIn.confirmed' => ['publicNavigation', 'checkInConfirmation', 'registerCta'],
    'appointment.selectLocation' => ['publicNavigation', 'appointmentSelectLocation'],
    'appointment.form' => ['publicNavigation', 'appointmentForm', 'purchaseOrders'],
    'appointment.confirmed' => ['publicNavigation', 'appointmentConfirmation', 'registerCta'],
];
