<?php

return [
    'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 15),
    'otp_max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'password_reset_url' => env('PASSWORD_RESET_URL'),
];
