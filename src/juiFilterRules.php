<?php
/**
 * jui_filter_rules, helper class for jquery.jui_filter_rules plugin, handles AJAX requests.
 *
 * Da Capo database wrapper is required https://github.com/pontikis/dacapo
 *
 * @version 1.0.7 (08 Apr 2015)
 * @author Christos Pontikis http://www.pontikis.net
 * @license http://opensource.org/licenses/MIT MIT License
 **/
class juiFilterRules {

	/** @var bool Use prepared statements or not */
	protected $usePreparedStatements;

	/** @var string Prepared statements placeholder type ('question_mark' or 'numbered') */
	private $pst_placeholder;

	/** @var string RDBMS in use (one of 'MYSQLi', 'POSTGRES') */
	protected $rdbms;

	/**
	 * @var array last_error
	 *
	 * array(
	 *    'element_rule_id' => 'the id of rule li element',
	 *    'error_message' => 'error message'
	 * )
	 *
	 */
	protected $lastError;

    /** @var Sparrow  */
    protected $sparrow = null;

    /** @var array  */
    protected $allowedFunctions = array();

    /** @var null */
    protected $sqlPlaceholder = null;

    /** @var bool */
    protected $prepareStatementPlaceholder = null;

    /**
     * @param Sparrow $sparrow
     * @param array $allowedFunctions
     * @param bool $usePreparedStatement
     * @param bool $sqlPlaceholder
     * @param bool $prepareStatementPlaceholder
     */
	public function __construct(Sparrow $sparrow, $allowedFunctions = array(), $usePreparedStatement = false, $sqlPlaceholder = false, $prepareStatementPlaceholder = false)
    {
        // Datenbank
		$this->sparrow = $sparrow;

		$this->allowedFunctions = $allowedFunctions;
		$this->usePreparedStatements = $usePreparedStatement;
		$this->sqlPlaceholder = $sqlPlaceholder;
		$this->prepareStatementPlaceholder = $prepareStatementPlaceholder;

		$this->lastError = array(
			'element_rule_id' => null,
			'error_message' => null
		);
	}

	/**
	 * @return array
	 */
	public function getLastError()
    {
		return $this->lastError;
	}


	/**
	 * @param array $a_functions
	 */
    /**
     * @param array $a_functions
     * @return juiFilterRules
     */
	public function setAllowedFunctions($a_functions = array())
    {
		if(is_array($a_functions)) {
			$this->allowedFunctions = $a_functions;
		}

        return $this;
	}


	/**
	 * Parse rules array from given JSON object and returns WHERE SQL clause and bind params array (used on prepared statements).
	 * Recursion is used.
	 *
	 * @param array $rollen The rules array
	 * @param bool $is_group If current rule belogns to group (except first group)
	 * @return array
	 */
	public function parseRules($rollen, $is_group = false)
    {
		static $sql;
		static $bind_params = array();
		static $bind_param_index = 1;

		// Beginn WHERE clause
		if(is_null($sql)) {
			$sql = ' ';
		}

        // Anzahl der Rollen
		$a_len = count($rollen);

		foreach($rollen as $i => $rolle) {


			if(!isset($rolle['condition'][0])) {
				$sql .= PHP_EOL;

				// Klammern
				$sql .= ($is_group && $i == 0 ? '(' : '');

				// Bedingung
				$sql .= $rolle['condition']['field'];

				// operator
				$sql .= $this->createOperatorSql($rolle['condition']['operator']);

				// Filter Inhalt auf dem Server
				$filter_value_conversion_server_side = array_key_exists('filter_value_conversion_server_side', $rolle) ? $rolle['filter_value_conversion_server_side'] : null;

                // Inhalt des Filter
				$filter_value = array_key_exists('filterValue', $rolle['condition']) ? $rolle['condition']['filterValue'] : null;

                // Filter Typ
				$filter_type = array_key_exists('filterType', $rolle['condition']) ? $rolle['condition']['filterType'] : null;

                // Nummer Typ
				$number_type = array_key_exists('numberType', $rolle['condition']) ? $rolle['condition']['numberType'] : null;

                // Inhalt der SQL Filter
				$filter_value_sql = $this->createFilterValueSql($rolle['condition']['filterType'],
					$rolle['condition']['operator'],
					$filter_value,
					$filter_value_conversion_server_side,
					array_key_exists('element_rule_id', $rolle) ? $rolle['element_rule_id'] : 'element_rule_id: not given');

                // Verwendung prepaired Statement
				if($this->usePreparedStatements) {

					if(!in_array($rolle['condition']['operator'], array('is_null', 'is_not_null'))) {

						if(in_array($rolle['condition']['operator'], array('in', 'not_in'))) {
							$sql .= '(';
							$filter_value_len = count($filter_value);
							for($v = 0; $v < $filter_value_len; $v++) {

								$sql .= $this->sql_placeholder;
								if($v < $filter_value_len - 1) {
									$sql .= ',';
								}

								// set param type
								$bind_param = $this->setBindParamType($filter_value[$v], $filter_type, $number_type);
								array_push($bind_params, $bind_param);
							}
							$sql .= ')';
						}
                        else {

							$sql .= $this->sql_placeholder;

							if(in_array($rolle['condition']['operator'], array('is_empty', 'is_not_empty'))) {
								array_push($bind_params, '');
							} else {
								// set param type
								$bind_param = $this->setBindParamType($filter_value_sql, $filter_type, $number_type);
								array_push($bind_params, $bind_param);
							}

						}

					}

				}
                // normale SQL
                else {

					if(!in_array($rolle['condition']['operator'], array('is_null', 'is_not_null'))) {

						if(in_array($rolle['condition']['operator'], array('is_empty', 'is_not_empty'))) {
							$sql .= "''";
						} else {
							$sql .= $filter_value_sql;
						}

					}
				}

			}
            else {
				$this->parseRules($rolle['condition'], true);
			}

			// logical operator (between rules)
			$sql .= ($i < $a_len - 1 ? ' ' . $rolle['logical_operator'] : '');

			// Klammern
			$sql .= ($is_group && $i == $a_len - 1 ? ')' : '');
		}

		return array('sql' => $sql, 'bind_params' => $bind_params);
	}


	/**
	 * Set bind_param type
	 *
	 * @param $bind_param
	 * @param $filter_type
	 * @param $number_type
	 * @return mixed
	 */
	protected function setBindParamType($bind_param, $filter_type, $number_type)
    {
		if($filter_type == 'number') {
			if($number_type == 'integer') {
				settype($bind_param, 'int');
			}
            else{
				settype($bind_param, 'float');
			}
		}
        else {
			settype($bind_param, 'string');
		}

		return $bind_param;
	}


	/**
	 * Return current rule filter value as a string suitable for SQL WHERE clause
	 *
	 * @param string $filter_type (one of 'text', 'number', 'date' - see documentation)
	 * @param string $operator_type (see documentation for available operators)
	 * @param array|null $a_values the values array
	 * @param array|null $filter_value_conversion_server_side
	 * @param string $element_rule_id
	 * @return string|null
     */
	protected function createFilterValueSql($filter_type, $operator_type, $a_values, $filter_value_conversion_server_side, $element_rule_id)
    {
		$sparrow = $this->sparrow;
		$res = '';

        // Rückgabe null
		if(in_array($operator_type, array('is_empty', 'is_not_empty', 'is_null', 'is_not_null'))) {
			return null;
		}

		// apply filter value conversion (if any)
		$vlen = count($a_values);
		if(is_array($filter_value_conversion_server_side)) {

            // Serverseitige Funktion
			$function_name = $filter_value_conversion_server_side['function_name'];

            // wenn serverseitige Funktion erlaubt !
			if(in_array($function_name, $this->allowedFunctions)) {

				$args = $filter_value_conversion_server_side['args'];
				$arg_len = count($args);

				for($i = 0; $i < $vlen; $i++) {

					// create arguments values for this filter value
					$conversion_args = array();

					for($a = 0; $a < $arg_len; $a++) {
						if(array_key_exists('filter_value', $args[$a])) {
							array_push($conversion_args, $a_values[$i]);
						}
						if(array_key_exists('value', $args[$a])) {
							array_push($conversion_args, $args[$a]['value']);
						}
					}

                    // ausführen der serverseitigen Funktion
					// execute user function and assign return value to filter value
					try {

                        // Aufruf der Funktion
						$a_values[$i] = call_user_func_array($function_name, $conversion_args);

                    }
                    // wenn Funktion nicht vorhanden
                    catch(Exception $e) {
						$this->lastError = array(
							'element_rule_id' => $element_rule_id,
							'error_message' => $e->getMessage()
						);

						break;
					}
				}
			}

		}

        // prepare Statements
		if($this->usePreparedStatements) {

            $test = 123;

			if(in_array($operator_type, array('equal', 'not_equal', 'less', 'not_equal', 'less_or_equal', 'greater', 'greater_or_equal'))) {
				$res = $a_values[0];
			}
            elseif(in_array($operator_type, array('begins_with', 'not_begins_with'))) {
				$res = $a_values[0] . '%';
			}
            elseif(in_array($operator_type, array('contains', 'not_contains'))) {
				$res = '%' . $a_values[0] . '%';
			}
            elseif(in_array($operator_type, array('ends_with', 'not_ends_with'))) {
				$res = '%' . $a_values[0];
			}
            elseif(in_array($operator_type, array('in', 'not_in'))) {
				for($i = 0; $i < $vlen; $i++) {
					$res .= ($i == 0 ? '(' : '');
					$res .= $a_values[$i];
					$res .= ($i < $vlen - 1 ? ',' : ')');
				}
			}
		}
        // normale Rückgabe
        else {

			if(in_array($operator_type, array('equal', 'not_equal', 'less', 'not_equal', 'less_or_equal', 'greater', 'greater_or_equal'))) {
				$res = ($filter_type == 'number' ? $a_values[0] : $sparrow->quote($a_values[0]));
			}
            elseif(in_array($operator_type, array('begins_with', 'not_begins_with'))) {
				$res = $sparrow->quote($a_values[0] . '%');
			}
            elseif(in_array($operator_type, array('contains', 'not_contains'))) {
				$res = $sparrow->quote('%' . $a_values[0] . '%');
			}
            elseif(in_array($operator_type, array('ends_with', 'not_ends_with'))) {
				$res = $sparrow->quote('%' . $a_values[0]);
			}
            elseif(in_array($operator_type, array('in', 'not_in'))) {
				for($i = 0; $i < $vlen; $i++) {
					$res .= ($i == 0 ? '(' : '');
					$res .= ($filter_type == 'number' ? $a_values[$i] : $sparrow->quote($a_values[$i]));
					$res .= ($i < $vlen - 1 ? ',' : ')');
				}
			}
		}

		return $res;
	}

	/**
	 * Create rule operator SQL substring
	 *
	 * @param string $operator_type
	 * @return string
	 */
	protected function createOperatorSql($operator_type)
    {
		$operator = '';
		switch($operator_type) {
			case 'equal':
				$operator = '=';
				break;
			case 'not_equal':
				$operator = '!=';
				break;
			case 'in':
				$operator = 'IN';
				break;
			case 'not_in':
				$operator = 'NOT IN';
				break;
			case 'less':
				$operator = '<';
				break;
			case 'less_or_equal':
				$operator = '<=';
				break;
			case 'greater':
				$operator = '>';
				break;
			case 'greater_or_equal':
				$operator = '>=';
				break;
			case 'begins_with':
				$operator = 'LIKE';
				break;
			case 'not_begins_with':
				$operator = 'NOT LIKE';
				break;
			case 'contains':
				$operator = 'LIKE';
				break;
			case 'not_contains':
				$operator = 'NOT LIKE';
				break;
			case 'ends_with':
				$operator = 'LIKE';
				break;
			case 'not_ends_with':
				$operator = 'NOT LIKE';
				break;
			case 'is_empty':
				$operator = '=';
				break;
			case 'is_not_empty':
				$operator = '!=';
				break;
			case 'is_null':
				$operator = 'IS NULL';
				break;
			case 'is_not_null':
				$operator = 'IS NOT NULL';
				break;
		}

		$operator = ' ' . $operator . ' ';

		return $operator;
	}
}