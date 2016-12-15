<?php
namespace Boparaiamrit\QueryBuilderParser;


use Illuminate\Database\Query\Builder;

trait QBPFunctions
{
	/**
	 * @param array $rule
	 */
	abstract protected function checkRuleCorrect(array $rule);
	
	protected $operators = [
		'equal'            => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
		'not_equal'        => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
		'in'               => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
		'not_in'           => ['accept_values' => true, 'apply_to' => ['string', 'number', 'datetime']],
		'less'             => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
		'less_or_equal'    => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
		'greater'          => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
		'greater_or_equal' => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
		'between'          => ['accept_values' => true, 'apply_to' => ['number', 'datetime']],
		'begins_with'      => ['accept_values' => true, 'apply_to' => ['string']],
		'not_begins_with'  => ['accept_values' => true, 'apply_to' => ['string']],
		'contains'         => ['accept_values' => true, 'apply_to' => ['string']],
		'not_contains'     => ['accept_values' => true, 'apply_to' => ['string']],
		'ends_with'        => ['accept_values' => true, 'apply_to' => ['string']],
		'not_ends_with'    => ['accept_values' => true, 'apply_to' => ['string']],
		'is_empty'         => ['accept_values' => false, 'apply_to' => ['string']],
		'is_not_empty'     => ['accept_values' => false, 'apply_to' => ['string']],
		'is_null'          => ['accept_values' => false, 'apply_to' => ['string', 'number', 'datetime']],
		'is_not_null'      => ['accept_values' => false, 'apply_to' => ['string', 'number', 'datetime']]
	];
	
	protected $operator_sql = [
		'equal'            => ['operator' => '='],
		'not_equal'        => ['operator' => '!='],
		'in'               => ['operator' => 'IN'],
		'not_in'           => ['operator' => 'NOT IN'],
		'less'             => ['operator' => '<'],
		'less_or_equal'    => ['operator' => '<='],
		'greater'          => ['operator' => '>'],
		'greater_or_equal' => ['operator' => '>='],
		'between'          => ['operator' => 'BETWEEN'],
		'begins_with'      => ['operator' => 'LIKE', 'prepend' => '%'],
		'not_begins_with'  => ['operator' => 'NOT LIKE', 'prepend' => '%'],
		'contains'         => ['operator' => 'LIKE', 'append' => '%', 'prepend' => '%'],
		'not_contains'     => ['operator' => 'NOT LIKE', 'append' => '%', 'prepend' => '%'],
		'ends_with'        => ['operator' => 'LIKE', 'append' => '%'],
		'not_ends_with'    => ['operator' => 'NOT LIKE', 'append' => '%'],
		'is_empty'         => ['operator' => '='],
		'is_not_empty'     => ['operator' => '!='],
		'is_null'          => ['operator' => 'NULL'],
		'is_not_null'      => ['operator' => 'NOT NULL']
	];
	
	protected $needs_array = [
		'IN', 'NOT IN', 'BETWEEN',
	];
	
	/**
	 * Determine if an operator (LIKE/IN) requires an array.
	 *
	 * @param $operator
	 *
	 * @return bool
	 */
	protected function operatorRequiresArray($operator)
	{
		return in_array($operator, $this->needs_array);
	}
	
	/**
	 * Determine if an operator is NULL/NOT NULL
	 *
	 * @param $operator
	 *
	 * @return bool
	 */
	protected function operatorIsNull($operator)
	{
		return ($operator == 'NULL' || $operator == 'NOT NULL') ? true : false;
	}
	
	/**
	 * Make sure that a condition is either 'or' or 'and'.
	 *
	 * @param $condition
	 *
	 * @return string
	 * @throws QBParseException
	 */
	protected function validateCondition($condition)
	{
		$condition = trim(strtolower($condition));
		
		if ($condition !== 'and' && $condition !== 'or') {
			throw new QBParseException("Condition can only be one of: 'and', 'or'.");
		}
		
		return $condition;
	}
	
	/**
	 * Enforce whether the value for a given field is the correct type
	 *
	 * @param bool   $requireArray value must be an array
	 * @param mixed  $value        the value we are checking against
	 * @param string $field        the field that we are enforcing
	 *
	 * @return mixed value after enforcement
	 * @throws QBParseException if value is not a correct type
	 */
	protected function enforceArrayOrString($requireArray, $value, $field)
	{
		$this->checkFieldIsAnArray($requireArray, $value, $field);
		
		if (!$requireArray && is_array($value)) {
			return $this->convertArrayToFlatValue($field, $value);
		}
		
		return $value;
	}
	
	/**
	 * Ensure that a given field is an array if required.
	 *
	 * @see enforceArrayOrString
	 *
	 * @param boolean $requireArray
	 * @param         $value
	 * @param string  $field
	 *
	 * @throws QBParseException
	 */
	protected function checkFieldIsAnArray($requireArray, $value, $field)
	{
		if ($requireArray && !is_array($value)) {
			throw new QBParseException("Field ($field) should be an array, but it isn't.");
		}
	}
	
	/**
	 * Convert an array with just one item to a string.
	 *
	 * In some instances, and array may be given when we want a string.
	 *
	 * @see enforceArrayOrString
	 *
	 * @param string $field
	 * @param        $value
	 *
	 * @return mixed
	 * @throws QBParseException
	 */
	protected function convertArrayToFlatValue($field, $value)
	{
		if (count($value) !== 1) {
			throw new QBParseException("Field ($field) should not be an array, but it is.");
		}
		
		return $value[0];
	}
	
	/**
	 * Append or prepend a string to the query if required.
	 *
	 * @param bool  $requireArray value must be an array
	 * @param mixed $value        the value we are checking against
	 * @param mixed $sqlOperator
	 *
	 * @return mixed $value
	 */
	protected function appendOperatorIfRequired($requireArray, $value, $sqlOperator)
	{
		if (!$requireArray) {
			if (isset($sqlOperator['append'])) {
				$value = $sqlOperator['append'] . $value;
			}
			
			if (isset($sqlOperator['prepend'])) {
				$value = $value . $sqlOperator['prepend'];
			}
		}
		
		return $value;
	}
	
	/**
	 * get a value for a given rule.
	 *
	 * throws an exception if the rule is not correct.
	 *
	 * @param array $rule
	 *
	 * @return mixed
	 * @throws QBRuleException
	 */
	private function getRuleValue(array $rule)
	{
		if (!$this->checkRuleCorrect($rule)) {
			throw new QBRuleException();
		}
		
		return $rule['value'];
	}
	
	/**
	 * Check that a given field is in the allowed list if set.
	 *
	 * @param $fields
	 * @param $field
	 *
	 * @throws QBParseException
	 */
	private function ensureFieldIsAllowed($fields, $field)
	{
		if (is_array($fields) && !in_array($field, $fields)) {
			throw new QBParseException("Field ({$field}) does not exist in fields list");
		}
	}
	
	/**
	 * makeQuery, for arrays.
	 *
	 * Some types of SQL Operators (ie, those that deal with lists/arrays) have specific requirements.
	 * This function enforces those requirements.
	 *
	 * @param Builder $query
	 * @param array   $rule
	 * @param array   $sqlOperator
	 * @param array   $value
	 * @param string  $condition
	 *
	 * @throws QBParseException
	 *
	 * @return Builder
	 */
	protected function makeQueryWhenArray(Builder $query, array $rule, array $sqlOperator, array $value, $condition)
	{
		if ($sqlOperator['operator'] == 'IN' || $sqlOperator['operator'] == 'NOT IN') {
			return $this->makeArrayQueryIn($query, $rule, $sqlOperator['operator'], $value, $condition);
		} elseif ($sqlOperator['operator'] == 'BETWEEN') {
			return $this->makeArrayQueryBetween($query, $rule, $value, $condition);
		}
		
		throw new QBParseException('makeQueryWhenArray could not return a value');
	}
	
	/**
	 * Create a 'null' query when required.
	 *
	 * @param Builder $query
	 * @param array   $rule
	 * @param array   $sqlOperator
	 * @param string  $condition
	 *
	 * @return Builder
	 * @throws QBParseException
	 * @internal param array $value
	 */
	protected function makeQueryWhenNull(Builder $query, array $rule, array $sqlOperator, $condition)
	{
		if ($sqlOperator['operator'] == 'NULL') {
			return $query->whereNull($rule['field'], $condition);
		} elseif ($sqlOperator['operator'] == 'NOT NULL') {
			return $query->whereNotNull($rule['field'], $condition);
		}
		
		throw new QBParseException('makeQueryWhenNull was called on an SQL operator that is not null');
	}
	
	/**
	 * makeArrayQueryIn, when the query is an IN or NOT IN...
	 *
	 * @see makeQueryWhenArray
	 *
	 * @param Builder $query
	 * @param array   $rule
	 * @param string  $operator
	 * @param array   $value
	 * @param string  $condition
	 *
	 * @return Builder
	 */
	private function makeArrayQueryIn(Builder $query, array $rule, $operator, array $value, $condition)
	{
		if ($operator == 'NOT IN') {
			return $query->whereNotIn($rule['field'], $value, $condition);
		}
		
		return $query->whereIn($rule['field'], $value, $condition);
	}
	
	
	/**
	 * makeArrayQueryBetween, when the query is an IN or NOT IN...
	 *
	 * @see makeQueryWhenArray
	 *
	 * @param Builder $query
	 * @param array   $rule
	 * @param array   $value
	 * @param string  $condition
	 *
	 * @throws QBParseException when more then two items given for the between
	 * @return Builder
	 */
	private function makeArrayQueryBetween(Builder $query, array $rule, array $value, $condition)
	{
		if (count($value) !== 2) {
			throw new QBParseException("{$rule['field']} should be an array with only two items.");
		}
		
		return $query->whereBetween($rule['field'], $value, $condition);
	}
}
