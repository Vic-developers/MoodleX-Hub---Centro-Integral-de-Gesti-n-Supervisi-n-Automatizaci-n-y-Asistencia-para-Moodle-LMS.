<?php
// Configuration for the isolated development container.
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'mariadb';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'database';
$CFG->dbname = 'moodlexhub';
$CFG->dbuser = 'moodlexhub';
$CFG->dbpass = getenv('LAB_DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbcollation' => 'utf8mb4_unicode_ci'];
$CFG->wwwroot = getenv('LAB_WWWROOT');
$CFG->dataroot = '/var/moodledata/site';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;
$CFG->phpunit_prefix = 'phpu_';
$CFG->phpunit_dataroot = '/var/moodledata/phpunit';
$CFG->behat_prefix = 'bht_';
$CFG->behat_dataroot = '/var/moodledata/behat';
$CFG->behat_wwwroot = 'http://127.0.0.1';
$CFG->noemailever = true;

$moodlewebroot = is_dir(__DIR__ . '/public/lib') ? __DIR__ . '/public' : __DIR__;
require_once($moodlewebroot . '/lib/setup.php');
