<?php

require_once(dirname(__FILE__) . '/api.php');


//----------------------------------------------------------------------------------------
function get($url)
{
	$data = null;
	
	$opts = array(
	  CURLOPT_URL =>$url,
	  CURLOPT_FOLLOWLOCATION => TRUE,
	  CURLOPT_RETURNTRANSFER => TRUE 
	);
	
	$ch = curl_init();
	curl_setopt_array($ch, $opts);
	$data = curl_exec($ch);
	$info = curl_getinfo($ch); 
	curl_close($ch);
	
	return $data;
}

//----------------------------------------------------------------------------------------
function read_data($filename)
{
	$data = array();
	
	$headings = array();

	$row_count = 0;

	$file = @fopen($filename, "r") or die("couldn't open $filename");
		
	$file_handle = fopen($filename, "r");
	while (!feof($file_handle)) 
	{
		$row = fgetcsv(
			$file_handle, 
			0, 
			"\t" 
			);
		
		$go = is_array($row);
	
		if ($go)
		{
			if ($row_count == 0)
			{
				$headings = $row;		
			}
			else
			{
				$obj = new stdclass;
		
				foreach ($row as $k => $v)
				{
					if ($v != '')
					{
						$obj->{$headings[$k]} = $v;
					}
				}
		
				$data[] = $obj;	
			}
		}	
		$row_count++;
	}
	
	return $data;
}

//----------------------------------------------------------------------------------------
function url_to_filename($url)
{
	$parts = explode("/", $url);
	
	$filename = array_pop($parts);
	
	return $filename;
}


$records = array();

$local_to_zenodo = new stdclass;

$links = array();

$mapping_filename = 'mapping.json';

if (!file_exists($mapping_filename))
{
	file_put_contents($mapping_filename, json_encode($local_to_zenodo));
}
$json = file_get_contents($mapping_filename);
$local_to_zenodo = json_decode($json);

print_r($local_to_zenodo);



//----------------------------------------------------------------------------------------
// images
if (1)
{
	$filename = 'images.tsv';

	$data = read_data($filename);

	print_r($data);

	foreach ($data as $row)
	{	
		// get image
		echo "Fetch image...\n";
		$image_filename = url_to_filename($row->url);
		
		$row->filename = $image_filename;
	
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
		
		//--------------------------------------------------------------------------------
		// links
		if (isset($row->specimen_id))
		{		
			if (!isset($links[$row->id]))
			{
				$links[$row->id] = array();				
			}
			if (!isset($links[$row->id]['documents']))
			{
				$links[$row->id]['documents'] = array();				
			}
		
			$links[$row->id]['documents'][] = $row->specimen_id;
			
			// inverse
			if (!isset($links[$row->specimen_id]))
			{
				$links[$row->specimen_id] = array();				
			}
			if (!isset($links[$row->specimen_id]['isDocumentedBy']))
			{
				$links[$row->specimen_id]['isDocumentedBy'] = array();				
			}
		
			$links[$row->specimen_id]['isDocumentedBy'][] = $row->id;			
		}
				
		//--------------------------------------------------------------------------------
		// metadata
		$row->metadata = new stdclass;
		
		$row->metadata->upload_type = 'image';
		$row->metadata->image_type = 'photo';
		
		if (isset($row->name))
		{
			$row->metadata->title = $row->name;
		}
		else
		{
			echo "Image must have a name\n";
			exit();
		}
		
		if (isset($row->description))
		{
			$row->metadata->description = $row->description;
		}
		else
		{
			$row->metadata->description = "Image of " . $row->name;
		}
		
		// we need a creator, for now cheat
		$row->metadata->creators = array();
		$creator = new stdclass;
		$creator->name = "Roderic D. M. Page";
		$row->metadata->creators[] = $creator;
						
		if (isset($row->license))
		{
			switch 	($row->license)
			{
				case 'http://creativecommons.org/licenses/by/4.0/':
					$row->metadata->license 		= 'cc-by';
					$row->metadata->access_right 	= 'open';
					break;
			
				default:
					$row->metadata->access_right 	= 'open';
					$row->metadata->license 		= 'cc-zero';			
					break;
			}
		}
		else
		{		
			// Default license
			$row->metadata->access_right 	= 'open';	
			$row->metadata->license 		= 'cc-zero';		
		}
	
		//--------------------------------------------------------------------------------
		// create a deposit if we haven't already
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		print_r($row);
		
		$records[$row->id] = $row;
	}
}

//----------------------------------------------------------------------------------------
// sequences
if (1)
{

	$filename = 'sequences.tsv';

	$data = read_data($filename);

	print_r($data);

	foreach ($data as $row)
	{	
		$sequence_filename = $row->id . '.fas';
		
		$row->filename = $sequence_filename;
	
		$fasta = '>' . $row->id . "\n";
		$fasta .= chunk_split($row->sequence, 60, "\n");
	
		file_put_contents($sequence_filename, $fasta);
		
		if (!isset($row->name))
		{
			$row->name = $row->id;		
		}
		
		//--------------------------------------------------------------------------------
		// links
		if (isset($row->specimen_id))
		{		
			if (!isset($links[$row->id]))
			{
				$links[$row->id] = array();				
			}
			if (!isset($links[$row->id]['documents']))
			{
				$links[$row->id]['documents'] = array();				
			}
			$links[$row->id]['documents'][] = $row->specimen_id;
		
			// inverse
			
			if (!isset($links[$row->specimen_id]))
			{
				$links[$row->specimen_id] = array();				
			}
			if (!isset($links[$row->specimen_id]['isDocumentedBy']))
			{
				$links[$row->specimen_id]['isDocumentedBy'] = array();				
			}
			
		
			$links[$row->specimen_id]['isDocumentedBy'][] = $row->id;
			
		}

		//--------------------------------------------------------------------------------
		// metadata
		$row->metadata = new stdclass;
		
		$row->metadata->upload_type = 'dataset';
		
		if (isset($row->name))
		{
			$row->metadata->title = $row->name;
		}
		else
		{
			echo "Sequence must have a name\n";
			exit();
		}
		
		if (isset($row->description))
		{
			$row->metadata->description = $row->description;
		}
		else
		{
			$row->metadata->description = chunk_split($row->sequence, 60, "\n");
		}
				
		// we need a creator, for now cheat
		$row->metadata->creators = array();
		$creator = new stdclass;
		$creator->name = "Roderic D. M. Page";
		$row->metadata->creators[] = $creator;

		if (isset($row->license))
		{
			switch 	($row->license)
			{
				case 'http://creativecommons.org/licenses/by/4.0/':
					$row->metadata->license 		= 'cc-by';
					$row->metadata->access_right 	= 'open';
					break;
			
				default:
					$row->metadata->access_right 	= 'open';
					$row->metadata->license 		= 'cc-zero';			
					break;
			}
		}
		else
		{		
			// Default license
			$row->metadata->access_right 	= 'open';	
			$row->metadata->license 		= 'cc-zero';		
		}		
	
		//--------------------------------------------------------------------------------
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		$records[$row->id] = $row;
	}
}

//----------------------------------------------------------------------------------------
// specimens
if (1)
{

	$filename = 'specimens.tsv';

	$data = read_data($filename);

	print_r($data);

	foreach ($data as $row)
	{	
		// must have a file
		$specimen_filename = $row->id . '.txt';
		
		$row->filename = $specimen_filename;
	
		file_put_contents($specimen_filename, $row->id);	
	
		if (!isset($row->name))
		{
			$row->name = $row->id;		
		}
	
		if (!isset($row->description))
		{
			$terms = array();
			
			if (isset($row->locality))
			{
				$terms[] = $row->locality;
			}
			
			if (isset($row->museum))
			{
				$terms[] = $row->museum;
			}
			
			$row->description = join(', ', $terms);
		}
		
		//--------------------------------------------------------------------------------
		// links
		if (isset($row->taxon_id))
		{		
			if (!isset($links[$row->id]))
			{
				$links[$row->id] = array();				
			}
			if (!isset($links[$row->id]['isPartOf']))
			{
				$links[$row->id]['isPartOf'] = array();				
			}
			$links[$row->id]['isPartOf'][] = $row->taxon_id;
		
			// inverse			
			if (!isset($links[$row->taxon_id]))
			{
				$links[$row->taxon_id] = array();				
			}
			if (!isset($links[$row->taxon_id]['hasPart']))
			{
				$links[$row->taxon_id]['hasPart'] = array();				
			}			
		
			$links[$row->taxon_id]['hasPart'][] = $row->id;
			
		}
		
	
		//--------------------------------------------------------------------------------
		// metadata
		$row->metadata = new stdclass;
		
		$row->metadata->upload_type = 'physicalobject';
		
		$row->metadata->title = $row->name;
		$row->metadata->description = $row->description;
				
		// we need a creator, for now cheat
		$row->metadata->creators = array();
		$creator = new stdclass;
		$creator->name = "Roderic D. M. Page";
		$row->metadata->creators[] = $creator;

		if (isset($row->license))
		{
			switch 	($row->license)
			{
				case 'http://creativecommons.org/licenses/by/4.0/':
					$row->metadata->license 		= 'cc-by';
					$row->metadata->access_right 	= 'open';
					break;
			
				default:
					$row->metadata->access_right 	= 'open';
					$row->metadata->license 		= 'cc-zero';			
					break;
			}
		}
		else
		{		
			// Default license
			$row->metadata->access_right 	= 'open';	
			$row->metadata->license 		= 'cc-zero';		
		}		
	
	
		//--------------------------------------------------------------------------------
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		$records[$row->id] = $row;
	}
}

//----------------------------------------------------------------------------------------
// taxa
if (1)
{

	$filename = 'taxa.tsv';

	$data = read_data($filename);

	print_r($data);

	foreach ($data as $row)
	{	
		// must have a file
		$taxon_filename = $row->id . '.txt';
		
		$row->filename = $taxon_filename;
	
		file_put_contents($taxon_filename, $row->id);	
	
	
		//--------------------------------------------------------------------------------
		// metadata
		$row->metadata = new stdclass;
		
		$row->metadata->upload_type = 'dataset';
		
		if (isset($row->name))
		{
			$row->metadata->title = $row->name;
		}
		else
		{
			echo "Taxon must have a name\n";
			exit();
		}

		if (isset($row->description))
		{
			$row->metadata->description = $row->description;
		}
		else
		{
			echo "Taxon must have a description\n";
			exit();
		}
				
		// we need a creator, for now cheat
		$row->metadata->creators = array();
		$creator = new stdclass;
		$creator->name = "Roderic D. M. Page";
		$row->metadata->creators[] = $creator;

		if (isset($row->license))
		{
			switch 	($row->license)
			{
				case 'http://creativecommons.org/licenses/by/4.0/':
					$row->metadata->license 		= 'cc-by';
					$row->metadata->access_right 	= 'open';
					break;
			
				default:
					$row->metadata->access_right 	= 'open';
					$row->metadata->license 		= 'cc-zero';			
					break;
			}
		}
		else
		{		
			// Default license
			$row->metadata->access_right 	= 'open';	
			$row->metadata->license 		= 'cc-zero';		
		}		
	
	
	
		//--------------------------------------------------------------------------------
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		$records[$row->id] = $row;
	}
}


file_put_contents($mapping_filename, json_encode($local_to_zenodo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));



// add crosslinks between records
print_r($links);

foreach ($links as $source => $typed_links)
{
	foreach ($typed_links as $relation => $targets)
	{
		foreach ($targets as $target)
		{
			echo $source . ' -> ' . $target . "\n";
			
			
			if (!isset($records[$source]->metadata->related_identifiers))
			{
				$records[$source]->metadata->related_identifiers = array();
			}
	
			$related = new stdclass;
			$related->relation = $relation;
			
			if ($config['zenodo_server'] == 'https://sandbox.zenodo.org')
			{
				$related->identifier = 'https://sandbox.zenodo.org/record/' . $local_to_zenodo->{$target}->id;			
			}
			else
			{
				$related->identifier = 'https://doi.org/' . $config['zenodo_doi_prefix'] . '/zenodo.' . $local_to_zenodo->{$target}->id;
			}
			
			$records[$source]->metadata->related_identifiers[] = $related;
		}
	}
}

// push to Zenodo
if (0)
{
	foreach ($records as $record)
	{
		//print_r($record);
	
		// metadata
		if (isset($local_to_zenodo->{$record->id}))
		{
			print_r($local_to_zenodo->{$record->id});
				
			$upload_item = new stdclass;
			$upload_item->metadata = $record->metadata;
		
			print_r($upload_item);
		
			upload_metadata(
				$local_to_zenodo->{$record->id}, 
				$upload_item 
				);
		}
   
		// do we have data (i.e., a file)?
		if (isset($local_to_zenodo->{$record->id}) && isset($record->filename))
		{
			print_r($local_to_zenodo->{$record->id});
		
			upload_file(
				$local_to_zenodo->{$record->id}, 
				dirname(__FILE__) . '/' . $record->filename,
				$record->filename
				);
		}
	
	
		//exit();	
	}
}

// publish
if (0)
{
	foreach ($records as $record)
	{
		print_r($record);
	
		if (isset($local_to_zenodo->{$record->id}))
		{
			publish($local_to_zenodo->{$record->id});
		}
		
		//exit();
	}
}


?>
