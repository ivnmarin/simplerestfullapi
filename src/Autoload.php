<?php
$routes = service('routes');

//$routes->add('/api/(:any)', 'SimpleRESTfullApi\Api::$1');

$routes->get('/api(:any)', 'SimpleRESTfullApi\Api::get$1');
$routes->post('/api(:any)', 'SimpleRESTfullApi\Api::post$1');
$routes->put('/api(:any)', 'SimpleRESTfullApi\Api::put$1');
$routes->delete('/api(:any)', 'SimpleRESTfullApi\Api::delete$1');
