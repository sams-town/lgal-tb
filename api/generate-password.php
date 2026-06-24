<?php
// Helper script to generate password hashes
echo 'Password hash for "123123": ' . password_hash('123123', PASSWORD_DEFAULT) . "\n";
echo 'Password hash for "123456": ' . password_hash('123456', PASSWORD_DEFAULT) . "\n";
