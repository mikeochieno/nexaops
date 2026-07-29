<?php
header('Content-Type: text/plain');
echo "openssl_get_cert_locations: " . print_r(openssl_get_cert_locations(), true) . "\n";
echo "CA file exists: " . (file_exists('/etc/ssl/certs/ca-certificates.crt') ? 'YES' : 'NO') . "\n";
echo "CA alt path exists: " . (file_exists('/etc/ssl/certs/ca-bundle.crt') ? 'YES' : 'NO') . "\n";
echo "apt ca path exists: " . (file_exists('/usr/share/ca-certificates/mozilla/CA_Root.crt') ? 'YES' : 'NO') . "\n";
echo "ls /etc/ssl/certs/: " . shell_exec('ls /etc/ssl/certs/ 2>&1') . "\n";
echo "ls /etc/ssl/: " . shell_exec('ls /etc/ssl/ 2>&1') . "\n";
echo "dpkg -l ca-certificates: " . shell_exec('dpkg -l ca-certificates 2>&1') . "\n";
