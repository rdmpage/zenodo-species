<?php

require_once(dirname(__FILE__) . '/api.php');
require_once(dirname(__FILE__) . '/utils.php');

// Links between records
$links = array();

// Store records 
$record_store = new stdclass;
$store_filename = 'store.json';

if (!file_exists($store_filename))
{
	file_put_contents($store_filename, json_encode($record_store));
}
$json = file_get_contents($store_filename);
$record_store = json_decode($json);

//print_r($record_store);

//----------------------------------------------------------------------------------------
function add_link($source_id, $relation, $target_id, $inverse_relation = '')
{
	global $links;
	
	if (!isset($links[$source_id]))
	{
		$links[$source_id] = array();
	}

	if (!isset($links[$source_id][$relation]))
	{
		$links[$source_id][$relation] = array();
	}
	
	if (!in_array($target_id, $links[$source_id][$relation]))
	{
		$links[$source_id][$relation][] = $target_id;
	}
	
	if ($inverse_relation != '')
	{
		if (!isset($links[$target_id]))
		{
			$links[$target_id] = array();
		}

		if (!isset($links[$target_id][$inverse_relation]))
		{
			$links[$target_id][$inverse_relation] = array();
		}
	
		if (!in_array($source_id, $links[$target_id][$inverse_relation]))
		{
			$links[$target_id][$inverse_relation][] = $source_id;
		}
	}
}


//----------------------------------------------------------------------------------------
function default_agent()
{
	$agent = new stdclass;
	$agent->name = "Roderic D. M. Page";
	
	return $agent;
}

//----------------------------------------------------------------------------------------
class Record
{
	var $deposit 		= null;
	var $metadata 		= null;
	var $filename 		= '';
	var $error_message 	= '';
	
	var $local_id		= '';
	var $zenodo_id		= 0;

	//------------------------------------------------------------------------------------
	function __construct($id)
	{
		$this->local_id = $id;
		 
		$this->metadata = new stdclass;
		
		// defaults
		$this->set_title("Untitled");
		$this->set_description("Description");
		$this->set_license('https://creativecommons.org/publicdomain/zero/1.0/');	
		$this->set_notes("A generic record in Zenodo");	
	}	
	
	//------------------------------------------------------------------------------------
	function add_creator($agent = null)
	{
		if (!isset($this->metadata->creators))
		{
			$this->metadata->creators = array();
		}
		
		if (!$agent)
		{
			$agent = default_agent();
		}
		
		$this->metadata->creators[] = $agent;
	}
	
	//------------------------------------------------------------------------------------
	function set_description($description)
	{
		$this->metadata->description = $description;
	}
	
	//------------------------------------------------------------------------------------
	function set_filename($filename)
	{
		$this->filename = $filename;
	}
	
	//------------------------------------------------------------------------------------
	// See https://sandbox.zenodo.org/api/licenses/?size=100 for licenses, not all are supported
	function set_license($license = 'https://creativecommons.org/publicdomain/zero/1.0/')
	{
		switch 	($license)
		{
			/*
			case 'http://creativecommons.org/licenses/by-nc-sa/4.0/':
				$this->metadata->access_right 	= 'open';
				$this->metadata->license 		= 'cc-by-nc-sa';								
				break;
			*/
				
			case 'http://creativecommons.org/licenses/by/4.0/':
				$this->metadata->access_right 	= 'open';
				$this->metadata->license 		= 'cc-by';				
				break;
				
			case 'https://creativecommons.org/publicdomain/zero/1.0/':
				$this->metadata->access_right 	= 'open';
				$this->metadata->license 		= 'cc-zero';	
				break;
						
			default:
				echo "$license is not a recognised license\n";
				exit();
				break;
		}
	}
	
	//------------------------------------------------------------------------------------
	function set_notes($text)
	{
		$this->metadata->notes = $text;
	}
		
	//------------------------------------------------------------------------------------
	function set_title($title)
	{
		$this->metadata->title = $title;
	}

	//------------------------------------------------------------------------------------
	function set_type($type, $image_type = '')
	{
		$this->metadata->upload_type  = $type;
		if ($image_type != '')
		{
			$this->metadata->image_type  = $image_type;
		}
	}

	//------------------------------------------------------------------------------------
	// Do we have everything we need to be a valid upload
	function check()
	{
		$ok = true;
		
		if ($ok)
		{
			$ok = $this->metadata->title != '';
			if (!$ok)
			{
				$this->error_message = "Must have a title";
			}
		}

		if ($ok)
		{
			$ok = $this->metadata->description != '';
			if (!$ok)
			{
				$this->error_message = "Must have a description";
			}
		}
		
		if ($ok)
		{
			$ok = isset($this->metadata->upload_type);
			if (!$ok)
			{
				$this->error_message = "Must have a data type";
			}
		}		

		if ($ok)
		{
			$ok = isset($this->metadata->creators) && count($this->metadata->creators) > 0;
			if (!$ok)
			{
				$this->error_message = "Must have at least one creator";
			}
		}
		
		if ($ok)
		{
			$ok = ($this->filename != '');
			if (!$ok)
			{
				$this->error_message = "Must have a data file";
			}
			else
			{
				$ok = file_exists($this->filename);
				if (!$ok)
				{
					$this->error_message = "File \"" . $this->filename . "\" not found";
				}			
			}
		}
				
		return $ok;
	}
	
	//------------------------------------------------------------------------------------
	// add link
	function add_related($relation, $target_local_id)
	{
		global $config;
		global $record_store;
		
		if (!isset($this->metadata->related_identifiers))
		{
			$this->metadata->related_identifiers = array();
		}
		
		$related = new stdclass;
		$related->relation = $relation;
		
		$target_record = unserialize($record_store->{$target_local_id});
		
		if ($config['zenodo_server'] == 'https://sandbox.zenodo.org')
		{
			$related->identifier = 'https://sandbox.zenodo.org/record/' . $target_record->zenodo_id;			
		}
		else
		{
			$related->identifier = 'https://doi.org/' . $config['zenodo_doi_prefix'] . '/zenodo.' .  $target_record->zenodo_id;
		}
		
		$this->metadata->related_identifiers[] = $related;	
	}
	
	//------------------------------------------------------------------------------------
	function clear_related()
	{
		if (isset($this->metadata->related_identifiers))
		{
			unset($this->metadata->related_identifiers);
		}		
	}

	
	//------------------------------------------------------------------------------------
	// repository functions 	

	//------------------------------------------------------------------------------------
	function repository_create()
	{
		$this->error_message = '';
	
		if ($this->deposit)
		{
			$ok = false;
			$this->error_message = "Have a deposit already.";
		}
		else
		{
			$ok = $this->check();
			if ($ok)
			{		
				$this->deposit = create_deposit();
				$this->zenodo_id = $this->deposit->id;
			}
		}	
		
		return $ok;
	}
	
	//------------------------------------------------------------------------------------
	function repository_upload_metadata()
	{		
		$this->error_message = '';

		$ok = $this->deposit;
		
		if (!$ok)
		{
			$this->error_message = "A deposit for this record has not yet been created.";
		}
		else
		{
			$ok = $this->check();
			if ($ok)
			{
				$upload_item = new stdclass;
				$upload_item->metadata = $this->metadata;
				
				upload_metadata(
					$this->deposit, 
					$upload_item 
					);	
			}
		}
		
		return $ok;
	}
	
	//------------------------------------------------------------------------------------
	function repository_upload_data()
	{
		$this->error_message = '';
		
		$ok = $this->deposit;
				
		if (!$ok)
		{
			$this->error_message = "A deposit for this record has not yet been created.";
		}
		else
		{
			$ok = $this->check();
			if ($ok)
			{
				upload_file(
					$this->deposit, 
					$this->filename,				
					basename($this->filename)
					);	
			}
		}
		
		return $ok;
	}

	//------------------------------------------------------------------------------------
	function repository_publish()
	{
		$ok = true;
		
		publish($this->deposit);
		return $ok;
	}



}

//----------------------------------------------------------------------------------------
function do_sequences($filename)
{
	global $links;
	global $record_store;
	
	$data = read_data($filename);

	//print_r($data);

	foreach ($data as $row)
	{	
		//--------------------------------------------------------------------------------
		if (isset($record_store->{$row->id}))
		{
			$record = unserialize($record_store->{$row->id});
		}
		else
		{			
			// Create a FASTA file for the sequences
			$sequence_filename = $row->id . '.fas';
	
			$fasta = '>' . $row->id . "\n";
			$fasta .= chunk_split($row->sequence, 60, "\n");
	
			file_put_contents($sequence_filename, $fasta);	
			
			$record = new Record($row->id);
			$record->set_type('dataset');
			$record->set_title($row->id);
			
			$fasta_html = str_replace("\n", "<br/>", $fasta);
			$record->set_description($fasta_html);
			
			// Need a creator/contributor
			$record->add_creator();			
				
			$record->set_filename($sequence_filename);	
			
			// Add notes for debugging
			$record->set_notes("A DNA sequence in FASTA format.");
		}

		//--------------------------------------------------------------------------------
		// link sequence to specimen it was obtained from
		if (isset($row->specimen_id))
		{	
			add_link($row->id, 'documents', $row->specimen_id, 'isDocumentedBy');
		}
		$record_store->{$row->id} = serialize($record);		
	}
	
}

//----------------------------------------------------------------------------------------
function do_specimens($filename)
{
	global $links;
	global $record_store;
	
	$data = read_data($filename);

	//print_r($data);

	foreach ($data as $row)
	{	
		//--------------------------------------------------------------------------------
		if (isset($record_store->{$row->id}))
		{
			$record = unserialize($record_store->{$row->id});
		}
		else
		{	
			// Create a data file for the specimen
			$specimen_filename = $row->id . '.txt';
			file_put_contents($specimen_filename, $row->id);	
			
			// Create a record
			$record = new Record($row->id);			
			$record->set_type('physicalobject');
			
			$record->set_title($row->id);
			
			// Construct a description from input data
			// To do: do we add custom terms and spatial data?
			$terms = array();
		
			if (isset($row->locality))
			{
				$terms[] = $row->locality;
			}
		
			if (isset($row->museum))
			{
				$terms[] = $row->museum;
			}
			
			if (count($terms) > 0)
			{
				$record->set_description($row->id);		
			}
			else
			{						
				$record->set_description(join(', ', $terms));
			}
			
			// Need a creator/contributor
			$record->add_creator();
			
			// Link to file we have created for this specimen
			$record->set_filename($specimen_filename);
			
			// Add notes for debugging
			$record->set_notes("A specimen record.");						
		}

		//--------------------------------------------------------------------------------
		// link specimen to taxon it is (currently) assigned too
		if (isset($row->taxon_id))
		{	
			add_link($row->id, 'isPartOf', $row->taxon_id, 'hasPart');
		}
		$record_store->{$row->id} = serialize($record);		
	}
	
}

//----------------------------------------------------------------------------------------
function do_images($filename)
{
	global $links;
	global $record_store;
	
	$data = read_data($filename);

	// print_r($data);

	foreach ($data as $row)
	{	
		//--------------------------------------------------------------------------------
		if (isset($record_store->{$row->id}))
		{
			$record = unserialize($record_store->{$row->id});
		}
		else
		{			
			$image_filename = url_to_filename($row->url);
	
			if (!file_exists($image_filename))
			{
				$image = get($row->url);
				file_put_contents($image_filename, $image);
			}
			$image = file_get_contents($image_filename);
	
			// get details on the image	
			$finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type a la mimetype extension
			$mime_type = finfo_file($finfo, $image_filename);
			finfo_close($finfo);
	
			// check if actually an image
			if (!in_array($mime_type, array('image/jpeg', 'image/png', 'image/tif')))
			{
				echo "$image_filename, is not an image!\n";
				exit();
			}
			
			$record = new Record($row->id);
			$record->set_type('image', 'photo');
			$record->set_title($row->id);
			
			if (isset($row->description))
			{
				$record->set_description($row->description);
			}
			else
			{
				$record->set_description("Image of " . $row->name);
			}			

			// Need a creator/contributor
			$record->add_creator();
			
			$record->set_filename($image_filename);	
			
			if (isset($row->license))
			{
				$record->set_license($row->license);
			}
			
			// Add notes for debugging
			$record->set_notes("An image of a specimen.");
		}

		//--------------------------------------------------------------------------------
		// link image to specimen being imaged
		if (isset($row->specimen_id))
		{	
			add_link($row->id, 'documents', $row->specimen_id, 'isDocumentedBy');
		}
		$record_store->{$row->id} = serialize($record);		
	}
	
}

//----------------------------------------------------------------------------------------
function do_taxa($filename)
{
	global $links;
	global $record_store;
	
	$data = read_data($filename);

	// print_r($data);

	foreach ($data as $row)
	{	
		//--------------------------------------------------------------------------------
		if (isset($record_store->{$row->id}))
		{
			$record = unserialize($record_store->{$row->id});
		}
		else
		{
			// Create a data file for the taxon
			$taxon_filename = $row->id . '.txt';
			file_put_contents($taxon_filename, $row->id);	
			
			// Create a record
			$record = new Record($row->id);			
			$record->set_type('dataset');
			
			$record->set_title($row->name);
			$record->set_description($row->description);	
			
			// Need a creator/contributor
			$record->add_creator();
				
			// Link to file we have created for this taxon
			$record->set_filename($taxon_filename);
			
			// Add notes for debugging
			$record->set_notes("A taxon description.");						
		}

		//--------------------------------------------------------------------------------
		// No links required as we should already have links to specimens
		$record_store->{$row->id} = serialize($record);		
	}
	
}



// tests

if (1)
{

	$filename = 'sequences.tsv';
	do_sequences($filename);

	$filename = 'specimens.tsv';
	do_specimens($filename);

	$filename = 'images.tsv';
	do_images($filename);

	$filename = 'taxa.tsv';
	do_taxa($filename);

	
	print_r($links);
	
	// Create deposits
	foreach ($record_store as $record_id => $stored_record)
	{
		$record = unserialize($stored_record);
		
		print_r($record);
		
		$ok = $record->repository_create();
		if (!$ok)
		{
			echo $record->error_message . "\n";
		}		
			
		$record_store->{$record_id} = serialize($record);
	}
	
	// update store	
	file_put_contents($store_filename, json_encode($record_store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	
	// Now that we have Zenodo ids we can add links to records
	foreach ($links as $source_id => $typed_links)
	{
		$source_record = unserialize($record_store->{$source_id});	
		
		// start with a clean slate			
		$source_record->clear_related();
	
		foreach ($typed_links as $relation => $target_list)
		{
			foreach ($target_list as $target_id)
			{
				echo $source_id . ' -> ' . $target_id . "\n";
				$source_record->add_related($relation, $target_id);
				
			}
		}
		$record_store->{$source_id} = serialize($source_record);	
				
		print_r($source_record);
		
	}
	
	// update store	
	file_put_contents($store_filename, json_encode($record_store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	
		
	// Upload metadata
	echo "\n\nUploading metadata...\n";
	foreach ($record_store as $record_id => $stored_record)
	{
		$record = unserialize($stored_record);
		
		print_r($record);
		
		$ok = $record->repository_upload_metadata();
		if (!$ok)
		{
			echo $record->error_message . "\n";
			exit();
		}		
	}
	
	
	// Upload data
	foreach ($record_store as $record_id => $stored_record)
	{
		$record = unserialize($stored_record);
		
		print_r($record);
		
		$ok = $record->repository_upload_data();
		if (!$ok)
		{
			echo $record->error_message . "\n";
			exit();
		}
	}

	// update store	
	file_put_contents($store_filename, json_encode($record_store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	
	// Publish (here be dragons!)
	foreach ($record_store as $record_id => $stored_record)
	{
		$record = unserialize($stored_record);
		
		print_r($record);
		
		$ok = $record->repository_publish();
		if (!$ok)
		{
			echo $record->error_message . "\n";
			exit();
		}
	}
	

}




?>
