<?php
/********************************************************************************
 * File Name:		Test_Class.php
 * Date:			15th February 2020
 * Written By:		KotaroW
 * Description:
 * 		Test and just for fun :-)
********************************************************************************/


namespace Test_Class {
    define('SOMETHING', 'SOMETHING');
    include_once("./db_config.php");

    /*
        Database class
        - Do not want it to be instantiated -
    */
	abstract class Database {

        // database opener
        // argument is a const defined in db config file.
        public static function get_connection($credential_index = null) {
            if (!$credential_index) {
                $credential_index =  \DB_CONFIG\DB_CONFIG::SECONDARY_CREDENTIAL;
            }

            $credential = \DB_CONFIG\DB_CONFIG::get_credential($credential_index);

            $db_conn = new \mysqli(
                $credential[\DB_CONFIG\DB_CONFIG::INDEX_HOST],
                $credential[\DB_CONFIG\DB_CONFIG::INDEX_USER],
                $credential[\DB_CONFIG\DB_CONFIG::INDEX_PWD],
                $credential[\DB_CONFIG\DB_CONFIG::INDEX_DB]
            );

            // return null if connection error occurred
            if ($db_conn->connect_errno)
                return null;

            return $db_conn;
        }

        // select
        public static function select($query_string, $want_assoc = true, $credential_index = null) {
            $db_conn = self::get_connection($credential_index);
            $retval = null;

            $results = $db_conn->query($query_string);

            if ($results) {
                $fetch_mode = $want_assoc ? MYSQLI_ASSOC : MYSQLI_NUM;
                $retval = $results->fetch_all($fetch_mode);
                $results->free();
            }
            $db_conn->close();
            return $retval;
        }

        // anything but select
        public function do_transaction($callback, $credential_index = null) {
            $db_conn = self::get_connection ($credential_index);

                return $callback($db_conn);

            }

    }


    class Parent_Object {

        // argument must be key => value pairs
        protected function __construct(array $props = null) {
            if ($props) {
                $this->set_props($props);
            }
        }

        // modify access modifier as required
        private function set_props($props) {
            foreach ($props as $name => $value) {
                $this->$name = $value;
            }
        }

        public function __get($name) {
            /*****
             * add code here as required
             * e.g. I do not want to allow access to particular properties, etc
            *****/

            return $this->$name;
        }

        public function __set($name, $value) {
            /*****
             * add code here as required
             * e.g. I do not want to change particular property value(s)
            *****/

            $this->$name = $value;
        }

        /*
         * static functions
         */
        // can be used to get either a list or a scalar
        // security is the caller's responsibility
        protected static function get_objects($query_string, $want_assoc = true, $credential_index = null) {
            return Database::select($query_string, $want_assoc, $credential_index);
        }

        protected static function call_transaction($callback, $credential_index = null) {
            return Database::do_transaction($callback, $credential_index);
        }
        
        /*****
         * test space
         *****/
        // transaction types
        // values correspond with query format index
        protected const INSERT = 0;
        protected const UPDATE = 1;
        protected const DELETE = 2;
        
        protected const QUERY_FORMAT = array(
            'insert into %s %s values (%s);',
            'update %s set %s where %s = ?;',
            'delete from %s where %s = ?;'
        );
        
        
    }

    class Child_Object extends Parent_Object {

        /*****
         * You don't necessarily have to define class variables
         * because variable names are the keys of an associative array
         * which is the argument for constructor.
         * However, it's highly advisable to define class variables
         * (with same names as the keys) for
         * the maintainance purpose.
         * Please remember declare the variables as "protected" so the
         * parent class magic methods (__get, __set) can access those
         * variables.
        *****/

        private const SELECT_FORMAT = 'select * from testtable %s;';
        private const ID_COND_FORMAT = ' where id = %d';

        private const INSERT_FORMAT = 'insert into testtable values (NULL, ?, ?);';
        private const INSERT_PARAM_TYPES = 'ss';
        private const UPDATE_FORMAT = 'update testtable set val1 = ?, val2 = ? where id = ?;';
        private const UPDATE_PARAM_TYPES = 'ssd';
        private const DELETE_FORMAT = 'delete from testtable where id = ?;';
        private const DELETE_PARAM_TYPES = 'd';
        
        
        protected $prop1;
        protected $prop2;
        protected $prop3;
        protected $prop4;
        protected $prop5;

        public function __construct(array $props) {
            parent::__construct($props);
        }

        // for data array
        public static function get_child_object_list($condition, $want_assoc = true, $credential_index = null) {
            $query_string = sprintf(self::SELECT_FORMAT, $condition);

            return parent::get_objects($query_string, $want_assoc, $credential_index);
        }

        // for scalar
        public static function get_child_object_by_id ($id, $want_assoc = true, $credential_index = null) {
            $condition = sprintf(self::ID_COND_FORMAT, intval($id));

            $query_string = sprintf(self::SELECT_FORMAT, $condition);
            $retval = parent::get_objects($query_string, $want_assoc, $credential_index);

            // scalar should be ONE
            if (count($retval) === 1) {
                $retval = $retval[0];
            }
            else {
                $retval = 0;
            }

            return $retval;
        }

        public static function add_child_object(array $values, $credential_index = null) {

            $stmt_format = self::INSERT_FORMAT;
            $stmt_param_types = self::INSERT_PARAM_TYPES;

            $bind_values = array(
                "val1" => $values[0],
                "val2" => $values[1]
            );

            $callback = function ($db_conn) use ($stmt_format, $bind_values, $stmt_param_types) {
                $retval = false;

                if (!$db_conn) {
                    return $retval;
                }

                extract($bind_values);

                $stmt = $db_conn->prepare($stmt_format);
                $stmt->bind_param($stmt_param_types, $val1, $val2);

                $db_conn->begin_transaction();

                $stmt->execute();

                if ($stmt->affected_rows === 1) {
                    $db_conn->commit();
                    $retval = true;
                }
                else {
                    $db_conn->rollback();
                }

                $stmt->close();
                $db_conn->close();

                return $retval;
            };

            return parent::call_transaction($callback, $credential_index);
        }

        // being possible to be updated means it's already an object, not a candidate
        // however, this could be a static method which I would personally prefer
        public function update($credential_index = null) {
            $query_format = self::UPDATE_FORMAT;
            $param_types = self::UPDATE_PARAM_TYPES;
            $obj_ptr = $this;

            $callback = function ($db_conn) use ($obj_ptr, $query_format, $param_types) {
                $retval = false;

                if (!$db_conn) {
                    return $retval;
                }

                $stmt = $db_conn->prepare($query_format);
                $stmt->bind_param(
                    $param_types,
                    $obj_ptr->val1,
                    $obj_ptr->val2,
                    $obj_ptr->id
                );

                $db_conn->begin_transaction();

                $stmt->execute();

                if ($stmt->affected_rows === 1) {
                    $retval = true;
                    $db_conn->commit();
                }
                else {
                    $db_conn->rollback();
                }

                $stmt->close();
                $db_conn->close();

                return $retval;
            };

            return parent::call_transaction($callback, $credential_index);
        }

        // same comments as update are applied
        public function delete ($credential_index = null) {

            $query_string = self::DELETE_FORMAT;
            $param_types = self::DELETE_PARAM_TYPES;
            $obj_ptr = $this;

            $callback = function ($db_conn) use ($obj_ptr, $query_string, $param_types) {
                $retval = false;
                
                if (!$db_conn) {
                    return $retval;
                }

                $stmt = $db_conn->prepare($query_string);
                $stmt->bind_param($param_types, $obj_ptr->id);
                
                $db_conn->begin_transaction();
                $stmt->execute();
                
                if ($stmt->affected_rows === 1) {
                    $db_conn->commit();
                    $retval = true;
                }
                else {
                    $db_conn->rollback();
                }
                
                $stmt->close();
                $db_conn->close();
                
                return $retval;
            };
            
            return parent::call_transaction($callback, $credential_index);
        }

        /*****
         * Let's see if we can merge those post methods into one ...
        *****/

        const INSERT = parent::INSERT;
        const UPDATE = parent::UPDATE;
        const DELETE = parent::DELETE;
        
        public function make_transaction($transaction_type, $table_name, $field_value, $condition, $param_types) {
            $query_format = parent::QUERY_FORMAT[$transaction_type];

            if ($field_value) {
                $fields = array_keys($field_value);
                $values = array_values($field_value);
            }
            
            // still need to implement $condition value check
            switch ($transaction_type) {
                case self::INSERT:
                    $fields = $fields ? '(' . implode(',', $fields) . ')' : '';

                    $placeholder = str_repeat('?,', count($values));
                    $placeholder = preg_replace('/,\s*$/', '', $placeholder);
                    
                    $query_string = sprintf($query_format, $table_name, $fields, $placeholder);
                    
                    break;

                case self::UPDATE:
                    $set_fields = implode (' = ?, ', $fields);
                    $set_fields .= ' = ? ';
                    
                    $condition_field = $condition['field'];
                    $condition_value = $condition['value'];
                    
                    $query_string = sprintf($query_format, $table_name, $set_fields, $condition_field);
                    
                    break;
                    
                case self::DELETE:
                    $condition_field = $condition['field'];
                    $condition_value = $condition['value'];
                    
                    $query_string =sprintf($query_format, $table_name,  $condition_field);
            }
            
            echo $query_string;
            
        }
        
    }
        
}
/* namespace ends here */

namespace {
    $tc = new \Test_Class\Child_Object(Test_Class\Child_Object::get_child_object_by_id(14));

    echo $tc->make_transaction(\Test_Class\Child_Object::DELETE, 'testtable', null, array('field' => 'id', 'value' => 14), 'd');
}
?>
