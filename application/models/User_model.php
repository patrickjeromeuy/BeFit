<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User_model extends CI_Model
{

    function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function getAllUsers()
    {
        $query = $this->db->get('users');
        return $query->result();
    }

    public function insert($user)
    {
        $this->db->insert('users', $user);
        return $this->db->insert_id();
    }

    public function trainee($traineedetails)
    {
        $this->db->insert('traineeprofile', $traineedetails);
    }

    public function coach($coachdetails)
    {
        $this->db->insert('coachprofile', $coachdetails);
    }

    public function insert_service($service)
    {
        $this->db->insert('services', $service);
        return $this->db->insert_id();
    }

    public function insert_cashout($cashout)
    {
        $this->db->insert('cashout', $cashout);
        return $this->db->insert_id();
    }
    public function insert_rating($rating)
    {
        $this->db->insert('ratings', $rating);
        return $this->db->insert_id();
    }

    public function getUser($id)
    {
        $query = $this->db->get_where('users', array('users_id' => $id));
        return $query->row_array();
    }

    public function fetch_user($userid)
    {
        $query = $this->db->get_where('users', array('users_id' => $userid));
        $result = $query->result();
        return $result;
    }

    public function fetch_data($username)
    {
        $query = $this->db->get_where('users', array('users_username' => $username));
        $result = $query->result();
        return $result;
    }

    public function get_services($userid)
    {
        $query = $this->db->get_where('services', array('users_id' => $userid));
        $result = $query->result();
        return $result;
    }

    public function get_cashout($userid)
    {
        $this->db->select('*');
        $this->db->from('cashout');
        $this->db->where('users_id', $userid);
        $query = $this->db->get()->result();
        return $query;
    }

    public function get_trainees($username) {
        $this->db->select('*');
        $this->db->from('services');
        $this->db->join('orders', 'orders.services_id = services.services_id');
        $this->db->where('orders_to', $username);
        $query = $this->db->get()->result();
        return $query;
    }

    public function get_traineedetails($userid)
    {
        $this->db->select('*');
        $this->db->from('traineeprofile');
        $this->db->join('users', 'users.users_id = traineeprofile.ID');
        $this->db->where('users_id', $userid);
        $query = $this->db->get()->result();
        return $query;
    }

    public function get_coachdetails($userid)
    {
        $this->db->select('*');
        $this->db->from('coachprofile');
        $this->db->join('users', 'users.users_id = coachprofile.ID');
        $this->db->where('users_id', $userid);
        $query = $this->db->get()->result();
        return $query;
    }

    public function get_service_by_id($serviceid)
    {
        $query = $this->db->get_where('services', array('services_id' => $serviceid));
        $result = $query->result();
        return $result;
    }

    public function get_coach_by_service($serviceid)
    {
        $this->db->select('*');
        $this->db->from('users');
        $this->db->join('services', 'services.users_id = users.users_id');
        $this->db->where('services_id', $serviceid);
        $query = $this->db->get()->result();
        return $query;
    }

    public function update_wallet($value)
    {
        $this->db->set('users_wallet', $value);
        $this->db->where('users_id', $this->session->userdata('userid'));
        $this->db->update('users');
    }

    public function get_rating_by_id($serviceid)
    {
        $query = $this->db->get_where('ratings', array('services_id' => $serviceid));
        $result = $query->result();
        return $result;
    }

    public function fetch_all_service()
    {
        $this->db->select('*');
        $this->db->from('services');
        $this->db->join('users', 'services.users_id = users.users_id');
        $query = $this->db->get()->result();
        return $query;
    }

    public function fetch_all_orders_by_id($id)
    {
        $query = $this->db->get_where('orders', array('orders_id' => $id));
        $result = $query->result();
        return $result;
    }

    public function fetch_all_orders()
    {
        $query = $this->db->get('orders');
        return $query->result();
    }

    public function fetch_service_by_userid($username)
    {
        $this->db->select('*');
        $this->db->from('orders');
        $this->db->join('services', 'services.services_id = orders.services_id');
        $this->db->where('orders_from', $username);
        $query = $this->db->get()->result();
        return $query;
    }

    public function fetch_service_by_userid_2($username)
    {
        $this->db->select('*');
        $this->db->from('orders');
        $this->db->join('services', 'services.services_id = orders.services_id');
        $this->db->where('orders_to', $username);
        $query = $this->db->get()->result();
        return $query;
    }

    public function fetch_all_services_of_coach($userid) {
        $this->db->select('*');
        $this->db->from('services');
        $this->db->where('users_id', $userid);
        $query = $this->db->get()->result();
        return $query;
    }

    public function get_health($userid) {
        $this->db->select('Health');
        $this->db->from('traineeprofile');
        $this->db->where('ID', $userid);
        $query = $this->db->get()->result();
        return $query;
    }

    public function update_data($data)
    {
        $this->db->update('users', $data, array('users_username' => $data['users_username']));
    }

    public function update_trainee_wallet($value, $username)
    {
        $this->db->set('users_wallet', $value);
        $this->db->where('users_username', $username);
        $this->db->update('users');
    }

    public function update_coach_wallet($value, $username)
    {
        $this->db->set('users_wallet', $value);
        $this->db->where('users_username', $username);
        $this->db->update('users');
    }

    public function log_in_correctly()
    {
        $this->db->where('users_username', $this->input->post('username'));
        $this->db->where('users_password', $this->input->post('password'));
        $query = $this->db->get('users');

        if ($query->num_rows() == 1) {
            return true;
        } else {
            return false;
        }
    }
    public function password_correct()
    {
        $this->db->where('users_username', $this->session->userdata('userusername'));
        $this->db->where('users_password', $this->input->post('c_pass'));
        $query = $this->db->get('users');
        if ($query->num_rows() == 1) {
            return true;
        } else {
            return false;
        }
    }

    public function activate($data, $id)
    {
        $this->db->where('users.users_id', $id);
        return $this->db->update('users', $data);
    }

    public function get_wallet($userid)
    {
        $this->db->select('users_wallet');
        $this->db->from('users');
        $this->db->where('users_id', $userid);
        return $this->db->get()->result();
    }

    public function get_wallet_by_username($username)
    {
        $this->db->select('users_id, users_wallet');
        $this->db->from('users');
        $this->db->where('users_username', $username);
        return $this->db->get()->result();
    }

    public function update_orders_duration($new_session, $id)
    {
        $this->db->set('orders_duration', $new_session);
        $this->db->where('orders_id', $id);
        $this->db->update('orders');
    }
    
    public function update_orders_remarks($new_session, $id)
    {
        $this->db->set('orders_remarks', $new_session);
        $this->db->where('orders_id', $id);
        $this->db->update('orders');
    }

    public function success_topup($value)
    {
        $this->db->set('users_wallet', $value);
        $this->db->where('users_id', $this->session->userdata('userid'));
        $this->db->update('users');
    }

    public function success_topup_mobile($value, $username)
    {
        $this->db->set('users_wallet', $value);
        $this->db->where('users_username', $username);
        $this->db->update('users');
    }

    public function insert_topup($id, $value)
    {
        $data = array(
            'users_id' => $id,
            'payments_amount' => $value
        );
        $this->db->insert('payments', $data);
    }

    public function insert_notif($id, $time, $msg) {
        $data = array(
            'users_id' => $id,
            'notifications_time' => $time,
            'notifications_message' => $msg
        );
        $this->db->insert('notifications', $data);
    }

    public function fetch_all_payments()
    {
        $query = $this->db->get('payments');
        return $query->result();
    }

    public function insert_order($from, $to, $amount, $serviceid, $duration, $datetime)
    {
        $data = array(
            'orders_from' => $from,
            'orders_to' => $to,
            'orders_status' => 0,
            'orders_amount' => $amount,
            'orders_duration' => $duration,
            'orders_datetime' => $datetime,
            'services_id' => $serviceid
        );
        $this->db->insert('orders', $data);
    }

    public function deactivate_services($id)
    {
        $this->db->set('services_availability', 0);
        $this->db->where('services_id', $id);
        $this->db->update('services');
    }

    public function activate_services($id)
    {
        $this->db->set('services_availability', 1);
        $this->db->where('services_id', $id);
        $this->db->update('services');
    }

    public function update_rate($orderid)
    {
        $this->db->set('orders_rated', 1);
        $this->db->where('orders_id', $orderid);
        $this->db->update('orders');
    }

    public function delete_orders_by_id($id)
    {
        $this->db->where('orders_id', $id);
        $this->db->delete('orders');
    }

    public function confirm_trainee($id)
    {
        $this->db->set('orders_status', 1);
        $this->db->where('orders_id', $id);
        $this->db->update('orders');
    }

    public function get_servicebyorder($id)
    {
        $this->db->select('services_id');
        $this->db->from('orders');
        $this->db->where('orders_id', $id);
        return $this->db->get()->result();
    }

    public function if_rated($id, $username)
    {
        $this->db->select('orders_rated');
        $this->db->from('orders');
        $this->db->where('orders_id', $id);
        $this->db->where('orders_from', $username);
        return $this->db->get()->result();
    }

    public function get_services_by_sales(){
        $this->db->select('*');
        $this->db->from('services');
        $this->db->order_by('services_sale', 'desc');
        $query = $this->db->get();
        return $query->result();
    }

    public function get_servicesale($id){
        $this->db->select('services_sale');
        $this->db->from('services');
        $this->db->where('services_id', $id);
        return $this->db->get()->result();
    }

    public function update_servicesale($id, $sale_id)
    {
        $this->db->set('services_sale', $sale_id);
        $this->db->where('services_id', $id);
        $this->db->update('services');
    }

    public function update_traineeprofile($newprofile)
    {
        $this->db->update('traineeprofile', $newprofile, array('ID' => $newprofile['ID']));
    }

    public function update_coachprofile($newcoachdetails){
        $this->db->update('coachprofile', $newcoachdetails, array('ID' => $newcoachdetails['ID']));
    }

    /*public function update_coach_description($new_coach_description,$userid){
        $this->db->set('profile_description', $new_coach_description);
        $this->db->where('ID', $userid);$username = $this->uri->segment(3)
        $this->db->update('coachprofile');
    }*/

    public function get_user_by_username($profile_username)
    {
        $this->db->select('*');
        $this->db->from('users');
        $this->db->where('users_username', $profile_username);
        $query = $this->db->get()->result();
        return $query;
    }

    public function get_coachdetails_by_username($profile_username)
    {
        $this->db->select('*');
        $this->db->from('users');
        $this->db->where('users_username', $profile_username);
        $query = $this->db->get()->result();
        return $query;
    }

    public function fetch_user_by_username($users_username)
    {
        $this->db->select('*');
        $this->db->from('users');
        $this->db->where('users_username', $users_username);
        $query = $this->db->get()->result();
        
       return $query;
    }

    public function fetch_service_by_name($users_name)
    {
        $this->db->select('*');
        $this->db->from('services');
        $this->db->where('users_name', $users_name);
        $query = $this->db->get()->result();
        return $query;
    }

    public function fetch_workout_times($id)
    {
        $this->db->select('services_time');
        $this->db->from('services');
        $this->db->join('users', 'services.users_id = users.users_id');
        $this->db->where('services.users_id', $id);
        $query = $this->db->get()->result();
        return $query;
    }

    /*public function fetch_workout_times($id, $day)
    {
        $this->db->select('services_time');
        $this->db->from('services');
        $this->db->join('users', 'services.users_id = users.users_id');
        $this->db->where('services.users_id', $id);
        $this->db->like('services.services_day', $day);
        $query = $this->db->get()->result();
        return $query;
    }*/
}
