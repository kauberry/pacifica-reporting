<?php
 if (!defined('BASEPATH')) {
     exit('No direct script access allowed');
 }

function build_folder_structure(&$dirs, $path_array, $item_info)
{
    if (count($path_array) > 1) {
        if (!isset($dirs['folders'][$path_array[0]])) {
            $dirs['folders'][$path_array[0]] = array();
        }

        build_folder_structure($dirs['folders'][$path_array[0]], array_splice($path_array, 1), $item_info);
    } else {
        $size_string = format_bytes($item_info['size']);
        $item_id = $item_info['item_id'];
        $url = base_url()."myemsl/itemauth/{$item_id}";
        $item_info['url'] = $url;
        $item_info_json = json_encode($item_info);
        $dirs['files'][$item_id] = "<a class='item_link' id='item_{$item_id}' href='#'>{$path_array[0]}</a> <span class='fineprint'>[size: {$size_string}]</span><span class='item_data_json' id='item_id_{$item_id}' style='display:none;'>{$item_info_json}</span>";
    }
}

function format_folder_object_json($folder_obj, $folder_name)
{
    $output = array();

    if (array_key_exists('folders', $folder_obj)) {
        foreach ($folder_obj['folders'] as $folder_entry => $folder_tree) {
            $folder_output = array('title' => $folder_entry, 'folder' => true);
            $children = format_folder_object_json($folder_tree, $folder_entry);
            if (!empty($children)) {
                foreach ($children as $child) {
                    $folder_output['children'][] = $child;
                }
            }
            $output[] = $folder_output;
        }
    }
    if (array_key_exists('files', $folder_obj)) {
        foreach ($folder_obj['files'] as $item_id => $file_entry) {
            $output[] = array('title' => $file_entry, 'key' => "ft_item_{$item_id}");
        }
    }

    return $output;
}

function format_folder_object_html($folder_obj, &$output_structure)
{
    foreach (array_keys($folder_obj) as $folder_entry) {
        $output_structure .= "<li class='folder'>{$folder_entry}<ul>";
        if (array_key_exists('folders', $folder_obj[$folder_entry])) {
            $f_obj = $folder_obj[$folder_entry]['folders'];
            format_folder_object_html($f_obj, $output_structure);
        }
        if (array_key_exists('files', $folder_obj[$folder_entry])) {
            $file_obj = $folder_obj[$folder_entry]['files'];
            format_file_object_html($file_obj, $output_structure);
        }
        $output_structure .= '</ul></li>';
    }
}

function format_file_object_html($file_obj, &$output_structure)
{
    foreach ($file_obj as $file_entry) {
        $output_structure .= "<li>{$file_entry}</li>";
    }
}

function format_bytes($bytes)
{
    if ($bytes < 1024) {
        return $bytes.' B';
    } elseif ($bytes < 1048576) {
        return round($bytes / 1024, 0).' KB';
    } elseif ($bytes < 1073741824) {
        return round($bytes / 1048576, 1).' MB';
    } elseif ($bytes < 1099511627776) {
        return round($bytes / 1073741824, 2).' GB';
    } else {
        return round($bytes / 1099511627776, 2).' TB';
    }
}

function process_search_query($query_filter)
{
    $query_terms = explode(' ', $query_filter);

    return $query_terms;
}

function split_array_into_ranges($items)
{
	sort($items);
	$output_array = array();
	$end_value = 0;
	$start_value = intval(array_shift($items));
	$prev_value = $start_value;
	while($item = intval(array_shift($items))){
		if($item - $prev_value > 1 and $item != $prev_value){
			$end_value = $prev_value;
			if($start_value == $end_value){
				$output_array[] = array($start_value);
			}else{
				$output_array[] = array($start_value,$end_value);
			}
			$start_value = $item;
		}
		$prev_value = $item;
	}
    $output_array[] = array($start_value,$prev_value);
	return $output_array;
}
