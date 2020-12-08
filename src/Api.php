<?php
namespace SimpleRESTfullApi;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class Api extends Controller {
    use ResponseTrait;

    private $db;
    private $builder;

    private $tables = [];
    private $fields = [];
    
    /**
	 * Constructor.
	 */
	public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
	{
		// Do Not Edit This Line
        parent::initController($request, $response, $logger);

        $this->db = \Config\Database::connect();
		$this->db->query("SET time_zone='".date('P')."'");

        
		$this->tables = $this->db->listTables();
        
        $router = service('router');

        if (empty($router->params()) || empty($table = $router->params()[0])) {
            $this->fail("Bad Request");
            die($response->send());
        }
        
		if (!in_array($table, $this->tables)) {
            $this->failNotFound("Not Found");
            die($response->send());
        }
			
		if (empty($this->fields = $this->db->getFieldNames($table))) {
            $this->failValidationError("Method Not Allowed");
            die($response->send());
        }

        $this->builder = $this->db->table($table);
    }
    
	public function get($object = '', $id = 0, $relations = []) {
        if (!empty($relations)) {
			
		}
        
        foreach ($this->request->getGet() as $key => $value) {
			if (in_array($key, $this->fields)) {
				$this->builder->where("`{$object}`.{$key}", $value, true);
			}
			
			if (($end = strpos($key, "_like")) && ($key = substr($key, 0, $end))) {
				$this->builder->like("`{$object}`.{$key}", $value,);
			}
		}
        
        if ($id)
			$this->builder->where("`{$object}`.id", (int)$id, FALSE);
		if($query = $this->builder->get()) 
			return $this->respond(empty($id) ? $query->getResult() : $query->getRow());
		else
			return $this->failNotFound("Not Found");
		
	}
	
	public function post($object = '') {
		$request = $this->request->getJSON();
        
        $data = [];
		foreach ($this->fields as $field) {
			if (isset($request->{$field}))
				$data[$field] = $request->{$field};
		}
		
		if ($this->builder->insert($data)) {
            $query = $this->builder->getWhere(['id' => $this->db->insertID()]);
			return $this->respondCreated($query->getRow());
        }
        
        return $this->fail("Bad Request");
	}
	
	public function put($object = '', $id = 0) {
		if (empty($id)) {
			return $this->fail("Bad Request");
		}
		
		$request = (object)$this->request->getJSON();
		
		$data = [];
		foreach ($this->fields as $field) {
			if (isset($request->{$field}))
				$data[$field] = $request->{$field};
		}
		
		$this->builder->where("`{$object}`.id", (int)$id, FALSE);
		if ($this->builder->update($data) && $this->db->affectedRows()) {
            $query = $this->builder->getWhere(['id' => $id]);
			return $this->respond($query->getRow());	
        }
        
        return $this->fail("Not Found");
	}
	
	public function delete($object = '', $id = 0) {
		if (empty($id)) {
			return $this->fail("Bad Request");
		}
		
		$this->builder->where("`{$object}`.id", (int)$id, FALSE);
		if ($this->builder->delete() && $this->db->affectedRows())
            return $this->respondDeleted(['id' => $id]);	
        
        return $this->failNotFound("Not Found");
	}

}
