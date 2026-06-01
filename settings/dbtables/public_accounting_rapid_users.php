<?php
global $runnerDbTableInfo;
$runnerDbTableInfo['public_accounting_rapid_users'] = array(
	'type' => 0,
	'foreignKeys' => array( 
		 
	),
	'fields' => array( 
		array(
			'name' => 'id',
			'typeName' => 'bigint',
			'size' => 64,
			'scale' => 0,
			'type' => 20,
			'autoinc' => false,
			'defaultValueSQL' => 'nextval(\'accounting_rapid_users_id_seq\'::regclass)',
			'defaultValue' => 'nextval(\'accounting_rapid_users_id_seq\'::regclass)',
			'nullable' => false 
		),
		array(
			'name' => 'username',
			'typeName' => 'character varying',
			'size' => 191,
			'scale' => null,
			'type' => 200,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => false 
		),
		array(
			'name' => 'password',
			'typeName' => 'character varying',
			'size' => 191,
			'scale' => null,
			'type' => 200,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => false 
		),
		array(
			'name' => 'email',
			'typeName' => 'character varying',
			'size' => 191,
			'scale' => null,
			'type' => 200,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => false 
		),
		array(
			'name' => 'fullname',
			'typeName' => 'character varying',
			'size' => 191,
			'scale' => null,
			'type' => 200,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => false 
		),
		array(
			'name' => 'groupid',
			'typeName' => 'character varying',
			'size' => 191,
			'scale' => null,
			'type' => 200,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => true 
		),
		array(
			'name' => 'active',
			'typeName' => 'integer',
			'size' => 32,
			'scale' => 0,
			'type' => 3,
			'autoinc' => false,
			'defaultValueSQL' => '1',
			'defaultValue' => '1',
			'nullable' => false 
		),
		array(
			'name' => 'ext_security_id',
			'typeName' => 'character varying',
			'size' => 191,
			'scale' => null,
			'type' => 200,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => true 
		),
		array(
			'name' => 'created_at',
			'typeName' => 'timestamp without time zone',
			'size' => null,
			'scale' => null,
			'type' => 135,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => true 
		),
		array(
			'name' => 'updated_at',
			'typeName' => 'timestamp without time zone',
			'size' => null,
			'scale' => null,
			'type' => 135,
			'autoinc' => false,
			'defaultValueSQL' => null,
			'defaultValue' => '',
			'nullable' => true 
		) 
	),
	'primaryKeys' => array( 
		'id' 
	),
	'uniqueFields' => array( 
		 
	),
	'name' => 'accounting_rapid_users',
	'schema' => 'public' 
);
?>