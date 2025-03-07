<?php

namespace Leantime\Core;

use PDO;
use PDOStatement;
use ReflectionProperty;

/**
 * repository
 *
 * @package    leantime
 * @subpackage core
 */
abstract class Repository
{
    use Eventhelpers;

    /**
     * @var string
     */
    protected string $entity;

    /**
     * @var string
     */
    protected string $model;

    /**
     * dbcall - creates a new dbcall object
     *
     * @param array $args - usually the value of func_get_args(), gives events/filters values to work with
     * @return object
     */
    protected function dbcall(array $args): object
    {
        return new class ($args, $this) {
            /**
             * @var PDOStatement
             */
            private PDOStatement $stmn;

            /**
             * @var array
             */
            private array $args;

            /**
             * @var repository
             */
            private Repository $caller_class;

            /**
             * @var db
             */
            private Db $Db;

            /**
             * constructor
             *
             * @param array  $args         - usually the value of func_get_args(), gives events/filters values to work with
             * @param object $caller_class - the class object that was called
             */
            public function __construct(array $args, repository $caller_class)
            {
                $this->args = $args;
                $this->caller_class = $caller_class;
                $this->db = app()->make(Db::class);
            }

            /**
             * prepares sql for entry; wrapper for PDO\prepare()
             *
             * @param string $sql
             * @param array  $args - additional arguments to pass along to prepare function
             */
            public function prepare($sql, $args = []): void
            {
                $sql = $this->caller_class::dispatch_filter(
                    "sql",
                    $sql,
                    $this->getArgs(['prepareArgs' => $args]),
                    4
                );

                $this->stmn = $this->db->database->prepare($sql, $args);
            }

            /**
             * binds values for search/replace of sql; wrapper for PDO\bindValue()
             *
             * @param string $needle  - placeholder to replace
             * @param string $replace - value to replace with
             * @param mixed  $type    - type of value being replaced
             */
            public function bindValue($needle, $replace, $type = PDO::PARAM_STR): void
            {
                $replace = $this->caller_class::dispatch_filter(
                    'binding.' . str_replace(':', '', $needle),
                    $replace,
                    $this->getArgs(),
                    4
                );

                $this->stmn->bindValue($needle, $replace, $type);
            }

            /**
             * executes the sql call - uses \PDO
             * @return mixed
             */
            public function lastInsertId(): mixed
            {
                return $this->db->database->lastInsertId();
            }

            /**
             * executes the sql call - uses \PDO
             *
             * @param string $fetchtype - the type of fetch to do (optional)
             *
             * @return mixed
             */
            public function setFetchMode($mode, $class)
            {
                return $this->stmn->setFetchMode($mode, $class);
            }

            /**
             * Gets the arguments to pass along to events/filter
             *
             * @param array $additions - any other additional parameters to include
             *
             * @return array
             */
            private function getArgs($additions = []): array
            {
                $args = array_merge($this->args, ['self' => $this]);

                if (!empty($additions)) {
                    $args = array_merge($args, $additions);
                }

                $this->caller_class::dispatch_filter("args", $args, [], 5);

                return $args;
            }

            /**
             * executes the sql call - uses \PDO
             *
             * @param string $fetchtype - the type of fetch to do (optional)
             *
             * @return mixed
             */
            public function __call(string $method, $arguments): mixed
            {
                if (!isset($this->stmn)) {
                    throw new Error("You must run the 'prepare' method first!");
                }

                if (!in_array($method, ['execute', 'fetch', 'fetchAll'])) {
                    throw new Error("Method does not exist");
                }

                $this->caller_class::dispatch_event("beforeExecute", $this->getArgs(), 4);

                $this->stmn = $this->caller_class::dispatch_filter("stmn", $this->stmn, $this->getArgs(), 4);
                $method = $this->caller_class::dispatch_filter("method", $method, $this->getArgs(), 4);

                $values = $this->stmn->execute();

                if (in_array($method, ['fetch', 'fetchAll'])) {
                    $values = $this->stmn->$method();
                }

                $this->stmn->closeCursor();

                $this->caller_class::dispatch_event("afterExecute", $this->getArgs(), 4);

                return $this->caller_class::dispatch_filter('return', $values, $this->getArgs(), 4);
            }
        };
    }

    /**
     * patch - updates a record in the database
     *
     * @param integer $id     - the id of the record to update
     * @param array   $params - the parameters to update
     * @return boolean
     */
    public function patch(int $id, array $params): bool
    {
        if ($this->entity == '') {
            error_log("Patch not implemented for this entity");
            return false;
        }

        $sql = "UPDATE zp_" . $this->entity . " SET ";

        foreach ($params as $key => $value) {
            $sql .= "" . Db::sanitizeToColumnString($key) . "=:" . Db::sanitizeToColumnString($key) . ", ";
        }

        $sql .= "id=:id WHERE id=:id LIMIT 1";

        $call = $this->dbcall(func_get_args());

        $call->prepare($sql);

        $call->bindValue(':id', $id, PDO::PARAM_STR);

        foreach ($params as $key => $value) {
            $call->bindValue(':' . Db::sanitizeToColumnString($key), $value, PDO::PARAM_STR);
        }

        return $call->execute();
    }

    public function insert(object $objectToInsert): false|int
    {

        if ($this->entity == '') {
            error_log("Insert not implemented for this entity");
            return false;
        }

        $sql = "INSERT INTO zp_" . $this->entity . " (";

        $sqlArr = array();
        foreach ($objectToInsert as $key => $value) {
            if ($this->getFieldAttribute($objectToInsert, $key)) {
                $sqlArr[] = "`" . Db::sanitizeToColumnString($key) . "`";
            }
        }
        $sql .= implode(",", $sqlArr);

        $sql .= ") VALUES (";

        $sqlArr2 = array();
        foreach ($objectToInsert as $key => $value) {
            if ($this->getFieldAttribute($objectToInsert, $key)) {
                $sqlArr2[] = ":" . Db::sanitizeToColumnString($key) . "";
            }
        }
        $sql .= implode(",", $sqlArr2);

        $sql .= ")";

        $call = $this->dbcall(func_get_args());

        $call->prepare($sql);

        foreach ($objectToInsert as $key => $value) {
            if ($this->getFieldAttribute($objectToInsert, $key)) {
                $call->bindValue(':' . Db::sanitizeToColumnString($key), $value, PDO::PARAM_STR);
            }
        }

        $call->execute();

        return $call->lastInsertId();
    }

    /**
     * delete - deletes a record from the database
     *
     * @param integer $id - the id of the record to delete
     */
    public function delete($id)
    {
    }

    /**
     * get - gets a record from the database
     *
     * @param integer $id - the id of the record to get
     */
    public function get($id)
    {
        if ($this->entity == '' || $this->model == '') {
            error_log("Get not implemented for this entity");
            return false;
        }

        $sql = "SELECT ";

        $entityModel = app()->make($this->model);
        $dbFields = $this->getDbFields($this->model);

        $sql .= implode(",", $dbFields);

        $sql .= " FROM zp_" . $this->entity . " WHERE id = :id ";

        $call = $this->dbcall(func_get_args());

        $call->prepare($sql);

         $call->bindValue(':id', $id, PDO::PARAM_STR);

         $call->execute();

         $call->setFetchMode(PDO::FETCH_CLASS, $this->model);

         return $call->fetch();
    }

    /**
     * getAll - gets all records from the database
     *
     * @param integer $id - the id of the record to get
     * @todo - implement
     */
    public function getAll($id)
    {
    }

    /**
     * getFieldAttribute - gets the field attribute for a given property
     *
     * @param string  $class     - the class to get the attribute from
     * @param string  $property  - the property to get the attribute from
     * @param boolean $includeId - whether or not to include the id attribute
     * @return array|false
     */
    protected function getFieldAttribute($class, $property, $includeId = false): array|false
    {
        //Don't create or update id attributes
        if ($includeId === false && $property == "id") {
            return false;
        }

        $property = new ReflectionProperty($class, $property);

        $attributes = $property->getAttributes();
        foreach ($attributes as $attribute) {
            $name = $attribute->getName();
            if (str_contains($name, "DbColumn")) {
                return $attribute->getArguments();
            }
        }

        return false;
    }

    /**
     * getDbFields - gets the database fields for a given class
     *
     * @param object|string $class - the class to get the fields from
     * @return array
     */
    protected function getDbFields(object|string $class): array
    {
        $property = new \ReflectionClass($class);

        $properties = $property->getProperties();

        $propertyArray = array();
        foreach ($properties as $property) {
            if ($this->getFieldAttribute($class, $property->getName(), true)) {
                $propertyArray[] = $property->getName();
            }
        }

        return $propertyArray;
    }
}
