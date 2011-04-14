<?php

error_reporting(-1);

setlocale(LC_ALL, 'en_US.utf-8');

if(function_exists('mb_internal_encoding'))
{
	mb_internal_encoding('UTF-8');
}

session_start();

require realpath('../').'/core/Druid.php';
require realpath('../').'/www/config/global.conf.php';
require realpath('../').'/www/bootstrap.php';