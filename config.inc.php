<?php

global $config;

// Date timezone
date_default_timezone_set('UTC');

if (file_exists(dirname(__FILE__) . '/env.php'))
{
	include 'env.php';
}

// Zenodo---------------------------------------------------------------------------------
if (0)
{
	// Live site
	$config['access_token'] 	 = getenv('ZENODO_TOKEN');
	$config['zenodo_server'] 	 = 'https://zenodo.org';
	$config['zenodo_doi_prefix'] = '10.5281';
}
else
{
	// Sandbox
	$config['access_token']  	 = getenv('SANDBOX_TOKEN');
	$config['zenodo_server'] 	 = 'https://sandbox.zenodo.org';
	$config['zenodo_doi_prefix'] = '10.5072';
}

?>
