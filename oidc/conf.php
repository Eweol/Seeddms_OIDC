<?php
$EXT_CONF['oidc'] = array(
	'title' => 'OIDC Extension',
	'description' => 'This extension enables users to login via OIDC',
	'disable' => false,
	'version' => '1.2.0',
	'releasedate' => '2022-11-20',
	'author' => array('name'=>'Eweol', 'email'=>'eweol@outlook.com', 'company'=>'Unimain'),
	'config' => array(
		'oidcEnable' => array(
			'title'=>'Enable OIDC Login',
			'type'=>'checkbox',
		),
		'oidcEndpoint' => array(
			'title'=>'OIDC Endpoint (Required)',
			'type'=>'input',
			'required'=>'required'
		),
		'oidcClientId' => array(
			'title'=>'Client ID (Required)',
			'type'=>'input',
		),
		'oidcClientSecret' => array(
			'title'=>'Client Secret (Required)',
			'type'=>'password',
		),
		'oidcUsername' => array(
		    'title' => 'Username Claim',
		    'type' => 'input',
			'placeholder' => "preferred_username",
		),
		'oidcMail' => array(
			'title'=>'E-Mail Claim',
		    'type'=>'input',
			'placeholder' => "email",
		),
		'oidcFullName' => array(
		    'title' => 'Fullname Claim',
		    'type' => 'input',
			'placeholder' => "name"
		),
		'oidcGroup' => array(
		    'title' => 'Group Claim',
		    'type' => 'input',
			'placeholder' => "groups"
		),
		'adminGroup' => array(
			'title' => 'Admin Group',
		    'type' => 'input',
			'placeholder' => "admin"
		),
		'accessGroup' => array(
			'title' => 'Access Group',
		    'type' => 'input',
		),
	),
	'constraints' => array(
		'depends' => array('php' => '5.6.40-', 'seeddms' => '5.1.0-'),
	),
	'icon' => 'icon.svg',
	'changelog' => 'changelog.md',
	'class' => array(
		'file' => 'class.oidc.php',
		'name' => 'SeedDMS_OIDC'
	),
);
?>