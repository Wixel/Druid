<?php

Druid::map(
	'GET /', function(){
		echo "home";
	}
);

#Druid::map(
#	'GET /', 'Controller_Test::t'
#);

Druid::map(
	'GET /dashboard/login', function(){
		
	}
);

#Druid::map(
#	'GET %/dashboard/signup/(.+)%i', function(){
#		
#	}
#);

Druid::map(
	'GET %/dashboard/signup/(?P<test>.+)/%i', function(){
		echo "sdfsf";
	}
);

Druid::instance()->run();