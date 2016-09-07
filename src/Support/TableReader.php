<?php

namespace Nwidart\Modules\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

class TableReader
{
    /**
     * @var \Illuminate\Database\Connection
     */
    protected $database;
    
    /**
     * The name of the table.
     *
     * @var string
     */
    protected $table;
    
    /**
     * The table's fields.
     *
     * Consists of a field (its name), type, null (YES or NO), key (PRI, etc), default (its default value), and extra (auto_increment, on update, etc) key.
     *
     * @var \Illuminate\Support\Collection
     */
    protected $fields;
    
    /**
     * Fields that should be guarded by default.
     *
     * @var array
     */
    protected $defaultGuarded = [
        'id',
        'password',
        'password_hash',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    
    /**
     * Default casted field types.
     *
     * @var array
     */
    protected $defaultCasts = [
        'int(' => 'integer',
        'tinyint(1)' => 'boolean',
        'json' => 'json',
    ];
    
    /**
     * Default date field types.
     *
     * @var array
     */
    protected $defaultDates = [
        'timestamp',
        'datetime',
        'date',
        'time',
        'year',
    ];
    
    /**
     * TableReader constructor.
     *
     * @param \Illuminate\Database\Connection $database
     */
    public function __construct(Connection $database)
    {
        $this->database = $database;
    }
    
    /**
     * Get information about the table's fields.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFields() : Collection
    {
        $fields = collect($this->database->select($this->database->raw('describe ' . $this->table)));
        
        // Normalize the output
        $fields = $fields->map(function($field) {
            return array_change_key_case((array)$field, CASE_LOWER);
        });
        
        return $fields;
    }
    
    /**
     * Get the native cast type of a database field.
     *
     * @param string $type
     *
     * @return string
     */
    protected function getCastType(string $type) : string
    {
        foreach ($this->defaultCasts as $character => $cast) {
            if (starts_with($type, $character)) {
                return $cast;
            }
        }
        
        return '';
    }
    
    /**
     * Check if a field type is a date.
     *
     * @param string $type
     *
     * @return bool
     */
    protected function isDate(string $type) : bool
    {
        foreach ($this->defaultDates as $date) {
            return starts_with($type, $date);
        }
        
        return false;
    }
    
    /**
     * Get the name of the table.
     *
     * @return string
     */
    public function getTable() : string
    {
        return $this->table;
    }
    
    /**
     * Get all guarded attributes.
     *
     * @return array
     */
    public function getGuarded() : array
    {
        // Compare default guarded fields with the ones in the table
        return array_values(array_intersect($this->fields->pluck('field')->toArray(), $this->defaultGuarded));
    }
    
    /**
     * Get all cast attributes.
     *
     * @return array
     */
    public function getCasts() : array
    {
        // Simply match the database types against any natives types and filter out "non-castworthy" fields
        return $this->fields->pluck('type', 'field')->map(function($type, $field) {
            return $this->getCastType($type);
        })->filter(function($type, $field) {
            return ! empty($type);
        })->toArray();
    }
    
    /**
     * Get all date attributes.
     *
     * @return array
     */
    public function getDates() : array
    {
        return $this->fields->pluck('type', 'field')->filter(function($type, $field) {
            return $this->isDate($type);
        })->keys()->toArray();
    }
    
    /**
     * Read all information from the table.
     *
     * @param string $table
     *
     * @return \Nwidart\Modules\Support\TableReader
     */
    public function read(string $table) : TableReader
    {
        $this->table = $table;
        
        $this->fields = $this->getFields();
        
        return $this;
    }
}