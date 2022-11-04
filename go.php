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

$local_to_zenodo = array();

$links = array();

$mapping_filename = 'mapping.json';

if (!file_exists($mapping_filename))
{
	file_put_contents($mapping_filename, json_encode($local_to_zenodo));
}
$json = file_get_contents($mapping_filename);
$local_to_zenodo = json_decode($json);


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
	
		// create a deposit if we haven't already
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		print_r($row);
		
		$records[] = $row;
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
			$row->metadata->description = $row->sequence;
		}
				
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
	
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		$records[] = $row;
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
	
		// metadata
		$row->metadata = new stdclass;
		
		$row->metadata->upload_type = 'physicalobject';
		
		$row->metadata->title = $row->name;
		$row->metadata->description = $row->description;
				
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
	
	
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		$records[] = $row;
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
	
	
	
		if (!isset($local_to_zenodo->{$row->id}))
		{
			echo "Create deposit...\n";
			$deposit = create_deposit();
			$local_to_zenodo->{$row->id} = $deposit;
		}
	
		echo json_encode($local_to_zenodo->{$row->id});
		
		$records[] = $row;
	}
}


file_put_contents($mapping_filename, json_encode($local_to_zenodo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

print_r($records);

// add crosslinks

// push to Zenodo

foreach ($records as $record)
{
	print_r($record);
	
	
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
   
	
	if (isset($local_to_zenodo->{$record->id}) && isset($row->filename))
	{
		print_r($local_to_zenodo->{$record->id});
		
		upload_file(
			$local_to_zenodo->{$record->id}, 
			dirname(__FILE__) . '/' . $row->filename,
			$row->filename
			);
	}
	
	
	exit();	
}


// publish


?>
