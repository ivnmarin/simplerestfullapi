<?php 

$routes->get('/api(:any)', '\SimpleRESTfullApi\Api::restGet$1');
$routes->post('/api(:any)', '\SimpleRESTfullApi\Api::restPost$1');
$routes->put('/api(:any)', '\SimpleRESTfullApi\Api::restPut$1');
$routes->delete('/api(:any)', '\SimpleRESTfullApi\Api::restDelete$1');
