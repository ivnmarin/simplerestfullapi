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

	public function __construct() {
		$this->db = \Config\Database::connect();
		$this->db->query("SET time_zone='".date('P')."'");

		$this->tables = $this->db->listTables();
	}
    
	public function get($request) {
		$request = explode("?", $request);
		
		$params = $request[1] ?? "";

		$request = explode("/", $request[0]);

		$object 	= $request[0];
		$id			= $request[1] ?? 0;
		$relations	= !empty($request[2]) ? explode(',', $request[2]) : [];
        $pk         = $this->primaryKey($object);

		parse_str($params, $params);

		$builder = $this->db->table($object);

		$fields = $this->db->getFieldNames($object);

		foreach ($params as $key => $value) {
			if (in_array($key, $fields)) {
				$builder->where("`{$object}`.{$key}", $value, true);
			}
			
			if (($end = strpos($key, "_like")) && ($key = substr($key, 0, $end))) {
				$builder->like("`{$object}`.{$key}", $value);
			}

            if (($end = strpos($key, "_orderby")) && ($key = substr($key, 0, $end)) && in_array($key, $this->fields)) {
                $builder->orderBy($key, $value);
            }
		}

        if ($id && strtolower($id) != 'all')
			$builder->where("`{$object}`.{$pk}", (int)$id, FALSE);

		if($query = $builder->get()) 
			$rows = $query->getResult();
		else
			$rows = [];

		$return = (object)[];

		foreach ($rows as $row) {
			foreach ($relations as $relation) {
				if (in_array($relation, $this->tables) && !empty($row->$relation))
					$row->$relation = $this->get("{$relation}/{$row->$relation}");
			}

			$return->{$row->{$pk}} = $row;
		}

		return empty($id) || strtolower($id) == 'all' ? $return : $return->$id;
	}

	public function restGet($object = '', $id = 0, $relations = "") {
        $pk = $this->primaryKey($object);
		$builder = $this->db->table($object);

        foreach ($this->request->getGet() as $key => $value) {
			if (in_array($key, $this->fields)) {
				$builder->where("`{$object}`.{$key}", $value, true);
			}
			
			if (($end = strpos($key, "_like")) && ($key = substr($key, 0, $end))) {
				$builder->like("`{$object}`.{$key}", $value);
			}

            if (($end = strpos($key, "_orderby")) && ($key = substr($key, 0, $end)) && in_array($key, $this->fields)) {
                $builder->orderBy($key, $value);
            }
		}
        
        if ($id && strtolower($id) != 'all')
            $builder->where("`{$object}`.{$pk}", (int)$id, FALSE);

		if($query = $builder->get()) 
			$rows = $query->getResult();
		else
			$rows = [];
		
		$return = (object)[];

		foreach ($rows as $row) {
			foreach (explode(',', $relations) as $relation) {
				if (in_array($relation, $this->tables) && !empty($row->$relation))
					$row->$relation = $this->get("{$relation}/{$row->$relation}");
			}

			$return->{$row->{$pk}} = $row;
		}

		return $this->respond(empty($id) || strtolower($id) == 'all' ? $return : $return->$id);
	}
	
	public function restPost($object = '') {
		$builder = $this->db->table($object);

		$request = $this->request->getJSON();
        
        $data = [];
		foreach ($this->fields as $field) {
			if (property_exists($request, $field))
				$data[$field] = $request->{$field};
		}
		
		if ($builder->insert($data)) {
            $pk = $this->primaryKey($object);
            $query = $builder->getWhere(["{$pk}" => $this->db->insertID()]);
			return $this->respondCreated($query->getRow());
        }
        
        return $this->fail("Bad Request");
	}
	
	public function restPut($object = '', $id = 0) {
		$builder = $this->db->table($object);

		if (empty($id)) {
			return $this->fail("Bad Request");
		}

        $pk = $this->primaryKey($object);

        if ($builder->getWhere(["{$pk}" => $id])->getRow() === null) {
            return $this->fail("Not Found");
        }

		$request = (object)$this->request->getJSON();
		
		$data = [];
		foreach ($this->fields as $field) {
			if (property_exists($request, $field))
				$data[$field] = $request->{$field};
		}
		
		$builder->where("`{$object}`.{$pk}", (int)$id, FALSE);

        try {
            if ($builder->update($data)) {
                $query = $builder->getWhere(["{$pk}" => $id]);
                return $this->respond($query->getRow());
            }
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }

        return $this->fail($this->dbError());
	}
	
	public function restDelete($object = '', $id = 0) {
		$builder = $this->db->table($object);

		if (empty($id)) {
			return $this->fail("Bad Request");
		}
		
        $pk = $this->primaryKey($object);

		$builder->where("`{$object}`.{$pk}", (int)$id, FALSE);
		if ($builder->delete() && $this->db->affectedRows())
            return $this->respondDeleted(["{$pk}" => $id]);
        
        return $this->failNotFound("Not Found");
	}

    public function primaryKey($object)
    {
        return array_reduce($this->db->getFieldData($object), function($carry, $item) {
            return !empty($item->primary_key) ? $item->name : $carry;
        }, 'id');
    }

    public function dbError()
    {
        $msg = [];
        $error = $this->db->error();
        foreach (['code', 'title', 'message'] as $error_field) {
            if (!empty($error[$error_field])) {
                $msg[$error_field] = $error[$error_field];
            }
        }

        if (count($msg)) {
            return implode(' - ', $msg);
        }

        return "Fail update, error unknown";
    }


}
