<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Tebakkode_m extends CI_Model {

    function __construct(){
        parent::__construct();
        $this->load->database();
    }

    function log_events($signature, $body) {
        $this->db->set('signature', $signature)
        ->set('events', $body)
        ->insert('eventlog');

        return $this->db->insert_id();
    }

    function getUser($userId) {
        $data = $this->db->where('user_id', $userId)->get('users')->row_array();
        if(count($data) > 0) return $data;
        return false;
    }

    function saveUser($profile){
        $this->db->set('user_id', $profile['userId'])
        ->set('display_name', $profile['displayName'])
        ->insert('users');
        return $this->db->insert_id();
    }


    function setUserProgress($user_id, $newNumber) {
        $this->db->set('number', $newNumber)
        ->where('user_id', $user_id)
        ->update('users');
        return $this->db->affected_rows();
    }

    function setParamRequest($parameters) {
        return $this->db->insert('param_request', $parameters);
    }

    function updateParamRequest($user_id, $parameters) {
        $this->db->set('params', $parameters)
        ->where('user_id', $user_id)
        ->update('param_request');
        return $this->db->affected_rows();
    }

    function getOldParams($user_id) {
        $this->db->order_by('id', 'DESC');
        $query = $this->db->get_where('param_request', array('user_id'=>$user_id), 1);
        return $query->row_array();
    }
}
