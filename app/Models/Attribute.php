<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;
use stdClass;

/**
 * Attribute class
 * 
 * @property mixed attribute
 */
class Attribute extends Model
{
	const SHOW_IN_ITEMS = 1;
	const SHOW_IN_SALES = 2;
	const SHOW_IN_RECEIVINGS = 4;

	public function __construct()
	{
		parent::__construct();

		$this->appconfig = model('Appconfig');
	}
	
	public static function get_definition_flags(): array
	{
		$class = new ReflectionClass(__CLASS__);

		return array_flip($class->getConstants());
	}

	/*
	 Determines if a given definition_id is an attribute
	 */
	public function exists($definition_id, $deleted = FALSE): bool
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('definition_id', $definition_id);
		$builder->where('deleted', $deleted);

		return ($builder->get()->getNumRows() == 1);
	}

	/**
	 * Returns whether an attribute_link row exists given an item_id and optionally a definition_id
	 * @param	int		$item_id
	 * @param	boolean	$definition_id
	 * @return	boolean					TRUE if at least one attribute_link exists or FALSE if no attributes exist.
	 */
	public function link_exists($item_id, $definition_id = FALSE): bool
	{
		$builder = $this->db->table('attribute_links');
		$builder->where('item_id', $item_id);
		$builder->where('sale_id');
		$builder->where('receiving_id');

		if(empty($definition_id))
		{
			$builder->where('definition_id <>');	//TODO: WE NEED TO MAKE SURE THAT THESE ARE STILL GENERATING THE CODE WE THINK IT'S GENERATING
			$builder->where('attribute_id');
		}
		else
		{
			$builder->where('definition_id', $definition_id);
		}

		return ($builder->get()->getNumRows() > 0);
	}

	/*
	 * Determines if a given attribute_value exists in the attribute_values table and returns the attribute_id if it does
	 */
	public function value_exists($attribute_value, $definition_type = TEXT): bool
	{
		switch($definition_type)
		{
			case DATE:
				$data_type				= 'date';
				$attribute_date_value	= DateTime::createFromFormat($this->appconfig->get('dateformat'), $attribute_value);
				$attribute_value		= $attribute_date_value->format('Y-m-d');
				break;
			case DECIMAL:
				$data_type = 'decimal';
				break;
			default:
				$data_type = 'value';
				break;
		}

		$builder = $this->db->table('attribute_values');
		$builder->select('attribute_id');
		$builder->where("attribute_$data_type", $attribute_value);
		$query = $builder->get();

		if($query->getNumRows() > 0)
		{
			return $query->getRow()->attribute_id;
		}

		return FALSE;
	}

	/*
	 Gets information about a particular attribute definition
	 */
	public function get_info($definition_id)
	{
		$builder = $this->db->table('attribute_definitions AS definition');
		$builder->select('parent_definition.definition_name AS definition_group, definition.*');
		$builder->join('attribute_definitions AS parent_definition', 'parent_definition.definition_id = definition.definition_fk', 'left');
		$builder->where('definition.definition_id', $definition_id);

		$query = $builder->get();

		if($query->getNumRows() == 1)
		{
			return $query->getRow();
		}
		else
		{
			//Get empty base parent object, as $item_id is NOT an item
			$item_obj = new stdClass();

			//Get all the fields from attribute_definitions table
			foreach($this->db->getFieldNames('attribute_definitions') as $field)
			{
				$item_obj->$field = '';
			}

			return $item_obj;
		}
	}

	/*
	 Performs a search on attribute definitions
	 */
	public function search($search, $rows = 0, $limit_from = 0, $sort = 'definition.definition_name', $order = 'asc')
	{
		$builder = $this->db->table('attribute_definitions AS definition');
		$builder->select('parent_definition.definition_name AS definition_group, definition.*');
		$builder->join('attribute_definitions AS parent_definition', 'parent_definition.definition_id = definition.definition_fk', 'left');

		$builder->groupStart();
			$builder->like('definition.definition_name', $search);
			$builder->orLike('definition.definition_type', $search);
		$builder->groupEnd();

		$builder->where('definition.deleted', 0);
		$builder->orderBy($sort, $order);

		if($rows > 0)
		{
			$builder->limit($rows, $limit_from);
		}

		return $builder->get();
	}

	public function get_attributes_by_item($item_id): array
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->join('attribute_links', 'attribute_links.definition_id = attribute_definitions.definition_id');
		$builder->where('item_id', $item_id);
		$builder->where('sale_id');
		$builder->where('receiving_id');
		$builder->where('deleted', 0);
		$builder->orderBy('definition_name', 'ASC');

		$results = $builder->get()->getResultArray();

		return $this->to_array($results, 'definition_id');
	}

	public function get_values_by_definitions($definition_ids): array
	{
		if(count($definition_ids ? : []))
		{
			$builder = $this->db->table('attribute_definitions');
			$builder->groupStart();
				$builder->whereIn('definition_fk', array_keys($definition_ids));
				$builder->orWhereIn('definition_id', array_keys($definition_ids));
				$builder->where('definition_type !=', GROUP);
			$builder->groupEnd();

			$builder->where('deleted', 0);

			$results = $builder->get()->getResultArray();

			return $this->to_array($results, 'definition_id');
		}

		return [];
	}

	public function get_definitions_by_type($attribute_type, $definition_id = NO_DEFINITION_ID): array
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('definition_type', $attribute_type);
		$builder->where('deleted', 0);
		$builder->where('definition_fk');

		if($definition_id != CATEGORY_DEFINITION_ID)
		{
			$builder->where('definition_id <>', $definition_id);
		}

		$results = $builder->get()->getResultArray();

		return $this->to_array($results, 'definition_id', 'definition_name');
	}

	public function get_definitions_by_flags($definition_flags): array
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('definition_flags &', $definition_flags);
		$builder->where('deleted', 0);
		$builder->where('definition_type <>', GROUP);
		$builder->orderBy('definition_id');
		$results = $builder->get()->getResultArray();

		return $this->to_array($results, 'definition_id', 'definition_name');
	}

	/**
	 * Returns an array of attribute definition names and IDs
	 *
	 * @param 	boolean		$groups		If FALSE does not return GROUP type attributes in the array
	 * @return	array					Array containing definition IDs, attribute names and -1 index with the local language '[SELECT]' line.
	 */
	public function get_definition_names($groups = TRUE): array
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('deleted', 0);
		$builder->orderBy('definition_name','ASC');

		if($groups === FALSE)
		{
			$builder->whereNotIn('definition_type',GROUP);
		}

		$results = $builder->get()->getResultArray();
		$definition_name = array(-1 => lang('Common.none_selected_text'));

		return $definition_name + $this->to_array($results, 'definition_id', 'definition_name');
	}

	public function get_definition_values($definition_id): array
	{
		$attribute_values = [];

		if($definition_id > 0 || $definition_id == CATEGORY_DEFINITION_ID)
		{
			$builder = $this->db->table('attribute_links');
			$builder->join('attribute_values', 'attribute_values.attribute_id = attribute_links.attribute_id');
			$builder->where('item_id');
			$builder->where('definition_id', $definition_id);
			$builder->orderBy('attribute_value','ASC');

			$results = $builder->get()->getResultArray();

			return $this->to_array($results, 'attribute_id', 'attribute_value');
		}

		return $attribute_values;
	}

	private function to_array($results, $key, $value = ''): array
	{
		return array_column(array_map(function($result) use ($key, $value){
			return [$result[$key], empty($value) ? $result : $result[$value]];
		}, $results), 1, 0);
	}

	/*
	 Gets total of rows
	 */
	public function get_total_rows()
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('deleted', 0);

		return $builder->countAllResults();
	}

	/*
	 Get number of rows
	 */
	public function get_found_rows($search)
	{
		return $this->search($search)->getNumRows();
	}

	private function check_data_validity($definition_id, $from, $to): bool
	{
		$success = FALSE;

		if($from === TEXT)
		{
			$success = TRUE;

			$builder = $this->db->table('attribute_values');
			$builder->distinct()->select('attribute_value');
			$builder->join('attribute_links', 'attribute_values.attribute_id = attribute_links.attribute_id');
			$builder->where('definition_id', $definition_id);

			foreach($builder->get()->getResult() as $attribute)
			{
				switch($to)
				{
					case DATE:
							$success = valid_date($attribute->attribute_value);
						break;
					case DECIMAL:
							$success = valid_decimal($attribute->attribute_value);
						break;
				}

				if($success === FALSE)
				{
					$affected_items = $this->get_items_by_value($attribute->attribute_value, $definition_id);
					foreach($affected_items as $affected_item)
					{
						$affected_items[] = $affected_item['item_id'];
					}

					log_message('ERROR', "Attribute_value: '$attribute->attribute_value' cannot be converted to $to. Affected Items: ". implode(',', $affected_items));
					unset($affected_items);
				}
			}
		}
		return $success;
	}

	/**
	 * Returns all item_ids with a specific attribute_value and attribute_definition
	 * @param string $attribute_value
	 * @param int $definition_id
	 * @return array
	 */
	private function get_items_by_value($attribute_value, $definition_id): array
	{
		$builder = $this->db->table('attribute_links');
		$builder->select('item_id');
		$builder->join('attribute_values', 'attribute_values.attribute_id = attribute_links.attribute_id');
		$builder->where('definition_id', $definition_id);
		$builder->where('attribute_value', $attribute_value);
		return $builder->get()->getResultArray();
	}

	/**
	 * Converts data in attribute_values and attribute_links tables associated with the conversion of one attribute type to another.
	 * @param int $definition_id
	 * @param 	string	$from_type
	 * @param 	string	$to_type
	 * @return boolean
	 */
	private function convert_definition_data($definition_id, $from_type, $to_type): bool
	{
		$success = FALSE;

		if($from_type === TEXT)
		{
			if(in_array($to_type, [DATE, DECIMAL], TRUE))
			{
				if($this->check_data_validity($definition_id, $from_type, $to_type))
				{
					$attributes_to_convert = $this->get_attributes_by_definition($definition_id);
					$success = $this->attribute_cleanup($attributes_to_convert, $definition_id, $to_type);
				}
			}
			else if($to_type === DROPDOWN)
			{
				$success = TRUE;
			}
			else if($to_type === CHECKBOX)	//TODO: duplicated code.
			{
				$checkbox_attribute_values = $this->checkbox_attribute_values($definition_id);

				$this->db->transStart();
//TODO: We should see if we can convert these queries to use query builder from CI4.
				$query = 'UPDATE '. $this->db->prefixTable('attribute_links') .' links ';
				$query .= 'JOIN '. $this->db->prefixTable('attribute_values') .' vals ';
				$query .= 'ON vals.attribute_id = links.attribute_id ';
				$query .= "SET links.attribute_id = IF((attribute_value IN('FALSE','0','') OR (attribute_value IS NULL)), $checkbox_attribute_values[0], $checkbox_attribute_values[1]) ";
				$query .= 'WHERE definition_id = '. $this->db->escape($definition_id);
				$success = $this->db->query($query);

				$this->db->transComplete();
			}
		}
		else if($from_type === DROPDOWN)
		{
			if(in_array($to_type, [TEXT, CHECKBOX], TRUE))
			{
				if($to_type === CHECKBOX)	//TODO: Duplicated code.
				{
					$checkbox_attribute_values = $this->checkbox_attribute_values($definition_id);

					$this->db->transStart();
//TODO: We should see if we can convert these queries to use query builder from CI4.
//TODO: We should see if we can refactor a function out of this block and the identical block above it.
					$query = 'UPDATE '. $this->db->prefixTable('attribute_links') .' links ';
					$query .= 'JOIN '. $this->db->prefixTable('attribute_values') .' vals ';
					$query .= 'ON vals.attribute_id = links.attribute_id ';
					$query .= "SET links.attribute_id = IF((attribute_value IN('FALSE','0','') OR (attribute_value IS NULL)), $checkbox_attribute_values[0], $checkbox_attribute_values[1]) ";
					$query .= 'WHERE definition_id = '. $this->db->escape($definition_id);
					$success = $this->db->query($query);

					$this->db->transComplete();
				}
			}
		}
		else
		{
			$success = TRUE;
		}

		$this->delete_orphaned_links($definition_id);
		$this->delete_orphaned_values();
		return $success;
	}

	private function checkbox_attribute_values($definition_id): array
	{
		$zero_attribute_id = $this->value_exists('0');
		$one_attribute_id = $this->value_exists('1');

		if($zero_attribute_id === FALSE)
		{
			$zero_attribute_id = $this->save_value('0', $definition_id, FALSE, FALSE, CHECKBOX);
		}

		if($one_attribute_id === FALSE)
		{
			$one_attribute_id = $this->save_value('1', $definition_id, FALSE, FALSE, CHECKBOX);
		}

		return array($zero_attribute_id, $one_attribute_id);
	}

	/*
	 Inserts or updates a definition
	 */
	public function save_definition(&$definition_data, $definition_id = NO_DEFINITION_ID)
	{
		$this->db->transStart();

		$builder = $this->db->table('attribute_definitions');

		//Definition doesn't exist
		if($definition_id === NO_DEFINITION_ID || !$this->exists($definition_id))
		{
			if($this->exists($definition_id,TRUE))
			{
				$success = $this->undelete($definition_id);
			}
			else
			{
				$success = $builder->insert($definition_data);
				$definition_data['definition_id'] = $this->db->insertID();
			}
		}

		//Definition already exists
		else
		{
			//Get current definition type and name
			$builder->select('definition_type');
			$builder->where('definition_id', $definition_id);

			$row = $builder->get('attribute_definitions')->getRow();
			$from_definition_type = $row->definition_type;
			$to_definition_type = $definition_data['definition_type'];

			//Update the definition values
			$builder->where('definition_id', $definition_id);

			$success = $builder->update($definition_data);
			$definition_data['definition_id'] = $definition_id;

			if($from_definition_type !== $to_definition_type)
			{
				if($this->convert_definition_data($definition_id, $from_definition_type, $to_definition_type) === FALSE)
				{
					return FALSE;
				}
			}
		}

		$this->db->transComplete();

		$success &= $this->db->transStatus();

		return $success;
	}

	public function get_definition_by_name($definition_name, $definition_type = FALSE): array
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('definition_name', $definition_name);

		if($definition_type != FALSE)
		{
			$builder->where('definition_type', $definition_type);
		}

		return $builder->get()->getResultArray();
	}

	public function save_link($item_id, $definition_id, $attribute_id): bool
	{
		$this->db->transStart();

		$builder = $this->db->table('attribute_links');

		if($this->link_exists($item_id, $definition_id))
		{
			$builder->where('definition_id', $definition_id);
			$builder->where('item_id', $item_id);
			$builder->where('sale_id');
			$builder->where('receiving_id');
			$builder->update(['attribute_id' => $attribute_id]);
		}
		else
		{
			$builder->insert([
					'attribute_id' => $attribute_id,
					'item_id' => $item_id,
					'definition_id' => $definition_id
			]);
		}

		$this->db->transComplete();

		return $this->db->transStatus();
	}

	public function delete_link($item_id, $definition_id = FALSE)
	{
		$delete_data = array('item_id' => $item_id);

		//Exclude rows where sale_id or receiving_id has a value
		$builder = $this->db->table('attribute_links');
		$builder->where('sale_id');
		$builder->where('receiving_id');

		if(!empty($definition_id))
		{
			$delete_data += ['definition_id' => $definition_id];
		}

		$success = $builder->delete($delete_data);

		return $success;
	}

	public function get_link_value($item_id, $definition_id)
	{
		$builder = $this->db->table('attribute_links');
		$builder->where('item_id', $item_id);
		$builder->where('sale_id');
		$builder->where('receiving_id');
		$builder->where('definition_id', $definition_id);

		return $builder->get('attribute_links')->getRowObject();
	}

	public function get_link_values($item_id, $sale_receiving_fk, $id, $definition_flags)
	{
		$format = $this->db->escape(dateformat_mysql());

		$builder = $this->db->table('attribute_links');
		$builder->select("GROUP_CONCAT(attribute_value SEPARATOR ', ') AS attribute_values");
		$builder->select("GROUP_CONCAT(DATE_FORMAT(attribute_date, $format) SEPARATOR ', ') AS attribute_dtvalues");
		$builder->join('attribute_values', 'attribute_values.attribute_id = attribute_links.attribute_id');
		$builder->join('attribute_definitions', 'attribute_definitions.definition_id = attribute_links.definition_id');
		$builder->where('definition_type <>', GROUP);
		$builder->where('deleted', 0);
		$builder->where('item_id', intval($item_id));

		if(!empty($id))
		{
			$builder->where($sale_receiving_fk, $id);
		}
		else
		{
			$builder->where('sale_id');
			$builder->where('receiving_id');
		}

		$builder->where('definition_flags & ', $definition_flags);

		return $builder->get('attribute_links');
	}

	public function get_attribute_value($item_id, $definition_id)
	{
		$builder = $this->db->table('attribute_values');
		$builder->join('attribute_links', 'attribute_links.attribute_id = attribute_values.attribute_id');
		$builder->where('item_id', intval($item_id));
		$builder->where('sale_id');
		$builder->where('receiving_id');
		$builder->where('definition_id', $definition_id);

		return $builder->get()->getRowObject();
	}

	public function get_attribute_values($item_id): array
	{
		$builder = $this->db->table('attribute_links');
		$builder->select('attribute_values.attribute_value, attribute_values.attribute_decimal, attribute_values.attribute_date, attribute_links.definition_id');
		$builder->join('attribute_values', 'attribute_links.attribute_id = attribute_values.attribute_id');
		$builder->where('item_id', intval($item_id));

		$results = $builder->get()->getResultArray();

		return $this->to_array($results, 'definition_id');
	}


	public function copy_attribute_links($item_id, $sale_receiving_fk, $id)
	{
//TODO: this likely needs to be rewritten as two different queries rather than a subquery within a query.  Then use query_builder for both.
		$query = 'INSERT INTO ' . $this->db->prefixTable('attribute_links') . ' (item_id, definition_id, attribute_id, ' . $sale_receiving_fk . ')	';
		$query .= 'SELECT ' . $this->db->escape($item_id) . ', definition_id, attribute_id, ' . $this->db->escape($id);
		$query .= 'FROM ' . $this->db->prefixTable('attribute_links');
		$query .= 'WHERE item_id = ' . $this->db->escape($item_id);
		$query .=' AND sale_id IS NULL AND receiving_id IS NULL';
		$this->db->query($query);
	}

	public function get_suggestions($definition_id, $term): array
	{
		$suggestions = [];
		
		$builder = $this->db->table('attribute_definitions AS definition');
		$builder->distinct()->select('attribute_value, attribute_values.attribute_id');
		$builder->join('attribute_links', 'attribute_links.definition_id = definition.definition_id');
		$builder->join('attribute_values', 'attribute_values.attribute_id = attribute_links.attribute_id');
		$builder->like('attribute_value', $term);
		$builder->where('deleted', 0);
		$builder->where('definition.definition_id', $definition_id);
		$builder->orderBy('attribute_value','ASC');

		foreach($builder->get()->getResult() as $row)
		{
			$row_array = (array) $row;
			$suggestions[] = array('value' => $row_array['attribute_id'], 'label' => $row_array['attribute_value']);
		}

		return $suggestions;
	}

	public function save_value($attribute_value, $definition_id, $item_id = FALSE, $attribute_id = FALSE, $definition_type = DROPDOWN)
	{
		$this->db->transStart();

		$locale_date_format = $this->appconfig->get('dateformat');

		//New Attribute
		if(empty($attribute_id) || empty($item_id))
		{
		//Update attribute_value
			$attribute_id = $this->value_exists($attribute_value, $definition_type);

			if($attribute_id === FALSE)
			{
				switch($definition_type)	//TODO: Duplicated code
				{
					case DATE:
						$data_type				= 'date';
						$attribute_date_value	= DateTime::createFromFormat($locale_date_format, $attribute_value);
						$attribute_value		= $attribute_date_value->format('Y-m-d');
						break;
					case DECIMAL:
						$data_type	= 'decimal';
						break;
					default:
						$data_type	= 'value';
						break;
				}

				$builder = $this->db->table('attribute_values');
				$builder->insert(["attribute_$data_type" => $attribute_value]);
			}

			$attribute_id = $attribute_id ? $attribute_id : $this->db->insertID();

			$builder = $this->db->table('attribute_links');
			$builder->insert([
				'attribute_id' => empty($attribute_id) ? NULL : $attribute_id,
				'item_id' => empty($item_id) ? NULL : $item_id,
				'definition_id' => $definition_id
			]);
		}
		//Existing Attribute
		else
		{//TODO: TAKE A LOOK AT THIS DUPLICATE CODE FRAGMENT FROM ABOVE AND SEE ABOUT REFACTORING OUT A FUNCTION
			switch($definition_type)
			{
				case DATE:
					$data_type				= 'date';
					$attribute_date_value	= DateTime::createFromFormat($locale_date_format, $attribute_value);
					$attribute_value		= $attribute_date_value->format('Y-m-d');
					break;
				case DECIMAL:
					$data_type	= 'decimal';
					break;
				default:
					$data_type	= 'value';
					break;
			}

			$builder = $this->db->table('attribute_values');
			$builder->where('attribute_id', $attribute_id);
			$builder->update(["attribute_$data_type" => $attribute_value]);
		}

		$this->db->transComplete();

		return $attribute_id;
	}

	public function delete_value($attribute_value, $definition_id)
	{
		$query = 'DELETE atrv, atrl ';
		$query .= 'FROM ' . $this->db->prefixTable('attribute_values') . ' atrv, ' . $this->db->prefixTable('attribute_links') .  ' atrl ';
		$query .= 'WHERE atrl.attribute_id = atrv.attribute_id AND atrv.attribute_value = ' . $this->db->escape($attribute_value);
		$query .= ' AND atrl.definition_id = ' . $this->db->escape($definition_id);
		return $this->db->query($query);
	}

	/**
	 * Deletes an Attribute definition from the database and associated column in the items_import.csv
	 *
	 * @param	int		$definition_id	Attribute definition ID to remove.
	 * @return 	boolean					TRUE if successful and FALSE if there is a failure
	 */
	public function delete_definition($definition_id): bool
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('definition_id', $definition_id);

		return $builder->update(['deleted' => 1]);
	}

	public function delete_definition_list($definition_ids): bool
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->whereIn('definition_id', $definition_ids);

		return $builder->update(['deleted' => 1]);
	}

	/**
	 * Deletes any attribute_links for a specific definition that do not have an item_id associated with them and are not DROPDOWN types
	 *
	 * @param int $definition_id
	 * @return boolean TRUE is returned if the delete was successful or FALSE if there were any failures
	 */
	public function delete_orphaned_links($definition_id): bool
	{
		$builder = $this->db->table('attribute_links');
		$builder->select('definition_type');
		$builder->where('definition_id', $definition_id);

		$definition = $builder->get('attribute_definitions')->getRow();

		if($definition->definition_type != DROPDOWN)
		{
			$this->db->transStart();

			$builder->where('item_id');		//TODO: We need to make sure this is still the same as $builder->where('item_id', NULL) and produces 'WHERE item_id IS NULL' there are several other places where this syntax is used.
			$builder->where('definition_id', $definition_id);
			$builder->delete();

			$this->db->transComplete();

			return $this->db->transStatus();
		}

		return true;	//TODO: if the definition type is a dropdown then this will return true.  Is that what we want here?
	}

	/*
	 * Deletes any orphaned values that do not have associated links
	 * @param int $definition_id
	 * @return boolean TRUE is returned if the delete was successful or FALSE if there were any failures
	 */
	public function delete_orphaned_values(): bool
	{
		$builder = $this->db->table('attribute_values');
		$builder->distinct()->select('attribute_id');
		$attribute_ids = $builder->getCompiledSelect('attribute_links');

		$this->db->transStart();

		$builder->whereNotIn('attribute_id', $attribute_ids, FALSE);
		$builder->delete();

		$this->db->transComplete();

		return $this->db->transStatus();
	}

	/*
	Undeletes one attribute definition
	*/
	public function undelete($definition_id): bool
	{
		$builder = $this->db->table('attribute_definitions');
		$builder->where('definition_id', $definition_id);

		return $builder->update(['deleted' => 0]);
	}

	/**
	 *
	 * @param array 	attributes 			attributes that need to be fixed
	 * @param int 		$definition_id
	 * @param string	$definition_type	This dictates what column should be populated in any new attribute_values that are created
	 */
	public function attribute_cleanup($attributes, $definition_id, $definition_type): bool
	{
		$this->db->transBegin();

		foreach($attributes as $attribute)
		{
			$new_attribute_id = $this->save_value($attribute['attribute_value'], $definition_id, FALSE, $attribute['attribute_id'], $definition_type);

			if($this->save_link($attribute['item_id'], $definition_id, $new_attribute_id) == FALSE)
			{
				log_message('Error', 'Transaction failed');
				$this->db->transRollback();
				return FALSE;
			}
		}
		$success = $this->delete_orphaned_links($definition_id);

		$this->db->transCommit();
		return $success;
	}

	/**
	 * Returns all attribute_ids and item_ids assigned to that definition_id
	 *
	 * @param int $definition_id
	 * @return array All attribute_id and item_id pairs in the attribute_links table with that attribute definition_id
	 */
	public function get_attributes_by_definition($definition_id): array
	{
		$builder = $this->db->table('attribute_links');
		$builder->select('attribute_links.attribute_id, item_id, attribute_value, attribute_decimal, attribute_date');
		$builder->join('attribute_values', 'attribute_values.attribute_id = attribute_links.attribute_id');
		$builder->where('definition_id', $definition_id);

		return $builder->get()->getResultArray();
	}
}