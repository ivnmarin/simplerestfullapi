<?php
$routes = service('routes');

$routes->add('/api/(:any)', 'SimpleRESTfullApi\Api::assets/$1');
