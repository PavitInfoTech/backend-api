<?php

return [
    // If true, all user's tokens will be revoked when the password is changed or reset.
    'revoke_tokens_on_password_change' => env('REVOKE_TOKENS_ON_PASSWORD_CHANGE', true),
];
