<?php
namespace SimpleRESTfullApi;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class Api extends Controller {
    use ResponseTrait;

	protected $format = 'json';

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
            $this->fail("Method Not Allowed");
            die($response->send());
        }
    }
    
	private function get($object = '', $id = 0) {
		$builder = $this->db->table($object);

        $builder->where("`{$object}`.id", (int)$id, FALSE);

		if($query = $builder->get()) 
			return $query->getRow();
		else
			return [];
	}

	public function restGet($object = '', $id = 0, $relations = "") {
		$builder = $this->db->table($object);

        foreach ($this->request->getGet() as $key => $value) {
			if (in_array($key, $this->fields)) {
				$builder->where("`{$object}`.{$key}", $value, true);
			}
			
			if (($end = strpos($key, "_like")) && ($key = substr($key, 0, $end))) {
				$builder->like("`{$object}`.{$key}", $value,);
			}
		}
        
        if ($id && strtolower($id) != 'all')
			$builder->where("`{$object}`.id", (int)$id, FALSE);

		if($query = $builder->get()) 
			$rows = $query->getResult();
		else
			$rows = [];
		
		$return = (object)[];

		foreach ($rows as $row) {
			foreach (explode(',', $relations) as $relation) {
				if (in_array($relation, $this->tables) && !empty($row->$relation))
					$row->$relation = $this->get($relation, $row->$relation);
			}

			$return->{$row->id} = $row;
		}

		return $this->respond(empty($id) || strtolower($id) == 'all' ? $return : $return->$id);
	}
	
	public function restPost($object = '') {
		$builder = $this->db->table($object);

		$request = $this->request->getJSON();
        
        $data = [];
		foreach ($this->fields as $field) {
			if (isset($request->{$field}))
				$data[$field] = $request->{$field};
		}
		
		if ($builder->insert($data)) {
            $query = $builder->getWhere(['id' => $this->db->insertID()]);
			return $this->respondCreated($query->getRow());
        }
        
        return $this->fail("Bad Request");
	}
	
	public function restPut($object = '', $id = 0) {
		$builder = $this->db->table($object);

		if (empty($id)) {
			return $this->fail("Bad Request");
		}
		
		$request = (object)$this->request->getJSON();
		
		$data = [];
		foreach ($this->fields as $field) {
			if (isset($request->{$field}))
				$data[$field] = $request->{$field};
		}
		
		$builder->where("`{$object}`.id", (int)$id, FALSE);
		if ($builder->update($data) && $this->db->affectedRows()) {
            $query = $builder->getWhere(['id' => $id]);
			return $this->respond($query->getRow());	
        }
        
        return $this->fail("Not Found");
	}
	
	public function restDelete($object = '', $id = 0) {
		$builder = $this->db->table($object);

		if (empty($id)) {
			return $this->fail("Bad Request");
		}
		
		$builder->where("`{$object}`.id", (int)$id, FALSE);
		if ($builder->delete() && $this->db->affectedRows())
            return $this->respondDeleted(['id' => $id]);	
        
        return $this->failNotFound("Not Found");
	}

}
