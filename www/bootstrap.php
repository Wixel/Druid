<?php

Druid::map(
	'GET /', function(){
		echo "home page should be rendered here";
	}
);

/*
Druid::map(
	'GET /', 'Controller::index'
);
*/

/*
Druid::map(
	'POST /login', function(){
		// do cool login stuff
	}
);
*/

/*
Druid::map(
	'GET %/dashboard/signup/(.+)%i', function(){
		// regex matching
	}
);
*/

/*
Druid::map(
	'GET %/dashboard/signup/(?P<test>.+)/%i', function(){
		// grouped regex matching
	}
);
*/

Druid::instance()->run();