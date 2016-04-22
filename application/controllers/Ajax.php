<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once('Baseline_controller.php');

class Ajax extends Baseline_controller {
  public $last_update_time;
  public $accepted_object_types;
  public $accepted_time_basis_types;
  public $local_resources_folder;

  function __construct() {
    parent::__construct();
    $this->load->model('Reporting_model','rep');
    $this->load->library('EUS','','eus');
    // $this->load->helper(array('network','file_info','inflector','time','item','search_term','cookie'));
    $this->load->helper(array('network'));
    $this->accepted_object_types = array('instrument','user','proposal');
    $this->accepted_time_basis_types = array('submit_time','create_time','modified_time');
    $this->local_resources_folder = $this->config->item('local_resources_folder');
  }




/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
/* API functionality for Ajax calls from UI                  */
/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
  public function make_new_group($object_type){
    if($this->input->post()){
      $group_name = $this->input->post('group_name');
    }elseif($this->input->is_ajax_request() || $this->input->raw_input_stream){
      $post_info = json_decode($this->input->raw_input_stream,true);
      // $post_info = $post_info[0];
      $group_name = array_key_exists('group_name',$post_info) ? $post_info['group_name'] : false;
    }
    $group_info = $this->rep->make_new_group($object_type,$this->user_id,$group_name);
    if($group_info && is_array($group_info)){
      send_json_array($group_info);
    }else{
      $this->output->set_status_header(500, "Could not make a new group called '{$group_name}'");
      return;
    }
  }

  public function change_group_name($group_id){
    $new_group_name = false;
    $group_info = $this->rep->get_group_info($group_id);
    if(!$group_info){
      $this->output->set_status_header(404, "Group ID {$group_id} was not found");
      return;
    }
    if($this->input->post()){
      $new_group_name = $this->input->post('group_name');
    }elseif($this->input->is_ajax_request() || file_get_contents('php://input')){
      $HTTP_RAW_POST_DATA = file_get_contents('php://input');
      $post_info = json_decode($HTTP_RAW_POST_DATA,true);
      if(array_key_exists('group_name',$post_info)){
        $new_group_name = $post_info['group_name'];
      }
    }else{
      $this->output->set_status_header(400, 'No update information was sent');
      return;
    }
    if($new_group_name){
      //check for authorization
      if($this->user_id != $group_info['person_id']){
        $this->output->set_status_header(401, 'You are not allowed to alter this group');
        return;
      }
      if($new_group_name == $group_info['group_name']){
        //no change to name
        $this->output->set_status_header(400, 'Group name is unchanged');
        return;
      }

      $new_group_info = $this->rep->change_group_name($group_id,$new_group_name);
      if($new_group_info && is_array($new_group_info)){
        send_json_array($new_group_info);
      }else{
        $this->output->set_status_header(500, 'A database error occurred during the update process');
        return;
      }
    }else{
      $this->output->set_status_header(400, 'Changed "group_name" attribute was not found');
      return;
    }
  }

  public function change_group_option($group_id = false){
    if(!$group_id){
      //send a nice error message about why you should include a group_id
    }
    $option_type = false;
    $option_value = false;
    $group_info = $this->rep->get_group_info($group_id);
    if(!$group_info){
      $this->output->set_status_header(404, "Group ID {$group_id} was not found");
      return;
    }
    if($this->input->post()){
      $option_type = $this->input->post('option_type');
      $option_value = $this->input->post('option_value');
    }elseif($this->input->is_ajax_request() || $this->input->raw_input_stream){
      $HTTP_RAW_POST_DATA = file_get_contents('php://input');
      $post_info = json_decode($HTTP_RAW_POST_DATA,true);
      // $post_info = $post_info[0];
      $option_type = array_key_exists('option_type',$post_info) ? $post_info['option_type'] : false;
      $option_value = array_key_exists('option_value', $post_info) ? $post_info['option_value'] : false;
    }
    if(!$option_type || !$option_value){
      $missing_types = array();
      $message = "Group option update information was incomplete (missing '";
      //$message .= !$option_type ? " 'option_type' "
      if(!$option_type){
        $missing_types[] = 'option_type';
      }
      if(!$option_value){
        $missing_types[] = 'option_value';
      }
      $message .= implode("' and '",$missing_types);
      $message .= "' entries)";
      $this->output->set_status_header(400, $message);
      return;
    }

    $success = $this->rep->change_group_option($group_id,$option_type,$option_value);
    if($success && is_array($success)){
      send_json_array($success);
    }else{
      $message = "Could not set options for group ID {$group_id}";
      $this->output->set_status_header('500',$message);
      return;
    }
    return;
  }

  public function get_group_container($object_type, $group_id, $time_range = false, $start_date = false, $end_date = false, $time_basis = false){
    $group_info = $this->rep->get_group_info($group_id);
    $options_list = $group_info['options_list'];
    $item_list = $group_info['item_list'];
    $time_range = !empty($time_range) ? $time_range : $options_list['time_range'];
    $time_basis = $options_list['time_basis'];
    if((!empty($start_date) && !empty($end_date)) && (strtotime($start_date) && strtotime($end_date))){
      $time_range = 'custom';
    }
    $object_type = singular($object_type);
    $accepted_object_types = array('instrument','proposal','user');

    $valid_date_range = $this->rep->earliest_latest_data_for_list($object_type,$group_info['item_list'],$time_basis);
    $my_times = $this->fix_time_range($time_range, $start_date, $end_date, $valid_date_range);
    $latest_available_date = new DateTime($valid_date_range['latest']);
    $earliest_available_date = new DateTime($valid_date_range['earliest']);

    $valid_range = array(
      'earliest' => $earliest_available_date->format('Y-m-d H:i:s'),
      'latest' => $latest_available_date->format('Y-m-d H:i:s'),
      'earliest_available_object' => $earliest_available_date,
      'latest_available_object' => $latest_available_date
    );
    $my_times = array_merge($my_times, $valid_range);

    $this->page_data['placeholder_info'][$group_id] = array(
      'group_id' => $group_id,
      'object_type' => $object_type,
      'options_list' => $options_list,
      'group_name' => $group_info['group_name'],
      'item_list' => $group_info['item_list'],
      'time_basis' => $time_basis,
      'time_range' => $time_range,
      'times' => $my_times
    );
    if(!array_key_exists('my_groups',$this->page_data)){
      $this->page_data['my_groups'] = array($group_id => $group_info);
    }else{
      $this->page_data['my_groups'][$group_id] = $group_info;
    }
    $this->page_data['my_object_type'] = $object_type;
    if(empty($item_list)){
      $this->page_data['examples'] = add_objects_instructions($object_type);
    }else{
      $this->page_data['placeholder_info'][$group_id]['times'] = $this->fix_time_range($time_range,$start_date,$end_date);
    }
    $this->load->view('object_types/group.html',$this->page_data);
  }

  public function update_object_preferences($object_type, $group_id = false){
    if($this->input->post()){
      $object_list = $this->input->post();
    }elseif($this->input->is_ajax_request() || file_get_contents('php://input')){
      $HTTP_RAW_POST_DATA = file_get_contents('php://input');
      $object_list = json_decode($HTTP_RAW_POST_DATA,true);
    }else{
      //return a 404 error
    }
    $filter = $object_list[0]['current_search_string'];
    $new_set = array();
    if($this->rep->update_object_preferences($object_type,$object_list,$group_id)){
      $this->get_object_group_lookup($object_type, $group_id, $filter);
    }
    //send_json_array($new_set);
  }

  public function remove_group($group_id = false){
    if(!$group_id){
      $this->output->set_status_header(400, "No Group ID specified");
      return;
    }
    $group_info = $this->rep->get_group_info($group_id);
    if(!$group_info){
      $this->output->set_status_header(404, "Group ID {$group_id} was not found");
      return;
    }
    if($this->user_id != $group_info['person_id']){
      $this->output->set_status_header(401, "User {$this->eus_person_id} is not the owner of Group ID {$group_id}");
      return;
    }
    $results = $this->rep->remove_group_object($group_id, true);

    $this->output->set_status_header(200);
    return;
  }



}
?>
