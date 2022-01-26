#!/bin/sh

service apache2 start

(
  cd /var/www/html/c || exit
  /bin/composer install
)

TERM=dumb php -- <<'EOPHP'
<?php
$stderr = fopen('php://stderr', 'w');
$user = 'root';
$pass = 'example';
$dbName = 'dp_db';
$maxTries = 10;
do {
  $mysql = new mysqli("mysql", $user, $pass, '', 3306, null);
  if ($mysql->connect_error) {
    fwrite($stderr, "\n" . 'MySQL Connection Error: (' . $mysql->connect_errno . ') ' . $mysql->connect_error . "\n");
    --$maxTries;
    if ($maxTries <= 0) {
      exit(1);
    }
    sleep(3);
  }
} while ($mysql->connect_error);
if (!$mysql->query('CREATE DATABASE IF NOT EXISTS `' . $mysql->real_escape_string($dbName) . '`')) {
  fwrite($stderr, "\n" . 'MySQL "CREATE DATABASE" Error: ' . $mysql->error . "\n");
  $mysql->close();
  exit(1);
}
if (!$mysql->query('CREATE DATABASE IF NOT EXISTS `' . $mysql->real_escape_string('dp_phpbb3') . '`')) {
  fwrite($stderr, "\n" . 'MySQL "CREATE DATABASE" Error: ' . $mysql->error . "\n");
  $mysql->close();
  exit(1);
}
$mysql->close();
EOPHP

/var/www/html/c/SETUP/configure /var/www/html/c/SETUP/dockerConfiguration.sh /var/www/html/c

(
  cd /var/www/html/c/SETUP || exit
  php -f install_db.php
)

(
  cd /var/www/html/phpBB3/bin || exit
  php phpbbcli.php db:migrate
)

tail -f /var/log/apache2/error.log