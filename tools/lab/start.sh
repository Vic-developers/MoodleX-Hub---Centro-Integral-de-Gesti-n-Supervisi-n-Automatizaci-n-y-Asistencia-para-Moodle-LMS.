#!/bin/sh
set -eu

install -o www-data -g www-data -m 2770 -d /var/moodledata/site /var/moodledata/phpunit /var/moodledata/behat

if php -r '
    $database = new mysqli("database", "moodlexhub", getenv("LAB_DB_PASSWORD"), "moodlexhub");
    $tables = $database->query("SHOW TABLES LIKE \"mdl_config\"");
    exit($tables->num_rows ? 0 : 3);
'; then
    su -s /bin/sh www-data -c 'php admin/cli/upgrade.php --non-interactive'
else
    result=$?
    if [ "$result" -ne 3 ]; then
        exit "$result"
    fi
    su -s /bin/sh www-data -c 'php admin/cli/install_database.php --agree-license --fullname="MoodleX Hub Lab" --shortname=MOODLEXLAB --adminpass="$LAB_ADMIN_PASSWORD" --adminemail=admin@example.invalid'
fi

exec docker-php-entrypoint "$@"
