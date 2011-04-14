<?php

// ** ------------------------- Framework Configuration ----------------------- ** //

Druid::set('base_url'	, 'http://app.mooblr.com/'			 );	
Druid::set('controllers', realpath('../').'/www/controllers/');	
Druid::set('models'     , realpath('../').'/www/models/'	 ); 
Druid::set('cache'      , realpath('../').'/www/cache/'	     ); 
Druid::set('logs'       , realpath('../').'/www/logs/'		 ); 
Druid::set('i18n'       , realpath('../').'/www/i18n/'	     ); 
Druid::set('temp'       , realpath('../').'/www/temp/'	     );	
Druid::set('helpers'    , realpath('../').'/www/helpers/'	 );
Druid::set('validators' , realpath('../').'/www/validators/' );

